<?php
// ═══════════════════════════════════════════
// POST /api/checkout/place-order.php — Place order (transactional)
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

// ── Validate required fields ──
$required = ['shipping_name','shipping_email','shipping_phone','shipping_address','shipping_city','shipping_zip','payment_method'];
foreach ($required as $field) {
    if (empty($body[$field])) {
        json_error('Missing required field: ' . $field);
    }
}

$payment_method = $body['payment_method'];
if (!in_array($payment_method, ['card','gcash','bank','cod'])) {
    json_error('Invalid payment method');
}

$promo_code = isset($body['promo_code']) ? strtoupper(trim($body['promo_code'])) : null;

// ── Get selected cart items (server-side, not trusting client) ──
$stmt = $pdo->prepare("
    SELECT ci.product_id, ci.qty, p.name, p.price, p.stock_qty
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id
    WHERE ci.user_id = ? AND ci.is_selected = 1
");
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();

if (empty($items)) {
    json_error('No selected items in cart');
}

// ── Check stock ──
foreach ($items as $item) {
    if ($item['qty'] > $item['stock_qty']) {
        json_error($item['name'] . ' only has ' . $item['stock_qty'] . ' in stock');
    }
}

// ── Server-side total calculation ──
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['qty'];
}

$total_qty = array_sum(array_column($items, 'qty'));

// Multi-item 3% discount
$multi_discount = 0;
if (count($items) >= MULTI_ITEM_DISCOUNT_MIN) {
    $multi_discount = round($subtotal * MULTI_ITEM_DISCOUNT_RATE, 2);
}

// Promo discount
$promo_discount = 0;
$promo_row = null;
if ($promo_code) {
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
    $stmt->execute([$promo_code]);
    $promo_row = $stmt->fetch();

    if (!$promo_row) {
        json_error('Invalid promo code');
    }
    if ($promo_row['expires_at'] && $promo_row['expires_at'] < date('Y-m-d')) {
        json_error('Promo code has expired');
    }
    if ($promo_row['max_uses'] !== null && $promo_row['used_count'] >= $promo_row['max_uses']) {
        json_error('Promo code usage limit reached');
    }
    if ($subtotal < (float)$promo_row['min_order']) {
        json_error('Order does not meet minimum for promo');
    }

    // Check user hasn't used this promo before
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM promo_usage WHERE promo_id = ? AND user_id = ?");
    $stmt->execute([$promo_row['id'], $user['id']]);
    if ($stmt->fetchColumn() > 0) {
        json_error('You have already used this promo code');
    }

    if ($promo_row['discount_type'] === 'percent') {
        $promo_discount = round($subtotal * ($promo_row['discount_value'] / 100), 2);
    } else {
        $promo_discount = (float)$promo_row['discount_value'];
    }
    $promo_discount = min($promo_discount, $subtotal);
}

$total_discount = $multi_discount + $promo_discount;
$shipping_fee = SHIPPING_FEE;
$total = $subtotal - $total_discount + $shipping_fee;

// ── Transaction ──
$pdo->beginTransaction();
try {
    // 1. Create order (temporary order_number, will update after getting ID)
    $stmt = $pdo->prepare("
        INSERT INTO orders (order_number, user_id, subtotal, discount, shipping_fee, total, status,
            shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_zip, promo_code)
        VALUES ('TEMP', ?, ?, ?, ?, ?, 'Processing', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'], $subtotal, $total_discount, $shipping_fee, $total,
        sanitize($body['shipping_name']), sanitize($body['shipping_email']),
        sanitize($body['shipping_phone']), sanitize($body['shipping_address']),
        sanitize($body['shipping_city']), sanitize($body['shipping_zip']),
        $promo_code
    ]);
    $order_id = $pdo->lastInsertId();

    // Generate deterministic order number from auto-increment ID
    $order_number = '#HL-' . date('Y') . '-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
    $stmt = $pdo->prepare("UPDATE orders SET order_number = ? WHERE id = ?");
    $stmt->execute([$order_number, $order_id]);

    // 2. Create order items + decrement stock + inventory log
    $stmt_item = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, price, qty)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_stock = $pdo->prepare("
        UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?
    ");
    $stmt_log = $pdo->prepare("
        INSERT INTO inventory_log (product_id, change_qty, reason, reference_id, notes)
        VALUES (?, ?, 'sale', ?, ?)
    ");

    foreach ($items as $item) {
        $stmt_item->execute([$order_id, $item['product_id'], $item['name'], $item['price'], $item['qty']]);

        $stmt_stock->execute([$item['qty'], $item['product_id'], $item['qty']]);
        if ($stmt_stock->rowCount() === 0) {
            throw new Exception($item['name'] . ' is out of stock');
        }

        $stmt_log->execute([
            $item['product_id'],
            -$item['qty'],
            $order_id,
            'Sale - Order ' . $order_number
        ]);
    }

    // 3. Create payment record
    $txn_id = strtoupper(substr(md5(uniqid()), 0, 12));
    $stmt = $pdo->prepare("
        INSERT INTO payments (order_id, method, transaction_id, status, amount, billing_name)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $pay_status = ($payment_method === 'cod') ? 'pending' : 'completed';
    $stmt->execute([$order_id, $payment_method, $txn_id, $pay_status, $total, sanitize($body['shipping_name'])]);

    // 4. Create shipment
    $est_delivery = date('Y-m-d', strtotime('+5 days'));
    $stmt = $pdo->prepare("
        INSERT INTO shipments (order_id, courier, shipping_fee, status, estimated_delivery)
        VALUES (?, 'J&T Express', ?, 'pending', ?)
    ");
    $stmt->execute([$order_id, $shipping_fee, $est_delivery]);

    // 5. Promo usage tracking
    if ($promo_row) {
        $stmt = $pdo->prepare("
            INSERT INTO promo_usage (promo_id, order_id, user_id, discount_applied)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$promo_row['id'], $order_id, $user['id'], $promo_discount]);

        $stmt = $pdo->prepare("UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?");
        $stmt->execute([$promo_row['id']]);
    }

    // 6. Create notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, icon, text, is_read)
        VALUES (?, '🎉', ?, 0)
    ");
    $notif_text = '<strong>Order ' . $order_number . ' confirmed!</strong> Your items are being prepared for shipment.';
    $stmt->execute([$user['id'], $notif_text]);

    // 7. Remove selected items from cart (keep unselected)
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND is_selected = 1");
    $stmt->execute([$user['id']]);

    $pdo->commit();

    json_success([
        'order_number' => $order_number,
        'order_id'     => (int)$order_id,
        'total'        => $total,
        'subtotal'     => $subtotal,
        'discount'     => $total_discount,
        'shipping_fee' => $shipping_fee,
        'items_count'  => count($items),
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    json_error($e->getMessage(), 500);
}
