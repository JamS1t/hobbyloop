<?php
// ═══════════════════════════════════════════
// GET /api/orders/detail.php?id= — Single order detail
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    json_error('Invalid order ID');
}

$stmt = $pdo->prepare("
    SELECT o.*, s.courier, s.tracking_number, s.status AS shipping_status,
           s.estimated_delivery, s.actual_delivery,
           pay.method AS payment_method, pay.transaction_id, pay.status AS payment_status
    FROM orders o
    LEFT JOIN shipments s ON s.order_id = o.id
    LEFT JOIN payments pay ON pay.order_id = o.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    json_error('Order not found', 404);
}

// Items
$stmt = $pdo->prepare("
    SELECT oi.product_name AS name, oi.price, oi.qty, oi.product_id,
           p.image_url AS img, p.bg_gradient AS bg, p.condition_label AS cond
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

json_success([
    'id'              => $order['order_number'],
    'order_id'        => (int)$order['id'],
    'status'          => $order['status'],
    'subtotal'        => (float)$order['subtotal'],
    'discount'        => (float)$order['discount'],
    'shipping_fee'    => (float)$order['shipping_fee'],
    'total'           => (float)$order['total'],
    'promo_code'      => $order['promo_code'],
    'date'            => date('F j, Y', strtotime($order['created_at'])),
    'shipping'        => [
        'name'    => $order['shipping_name'],
        'email'   => $order['shipping_email'],
        'phone'   => $order['shipping_phone'],
        'address' => $order['shipping_address'],
        'city'    => $order['shipping_city'],
        'zip'     => $order['shipping_zip'],
    ],
    'courier'            => $order['courier'],
    'tracking'           => $order['tracking_number'],
    'shipping_status'    => $order['shipping_status'],
    'estimated_delivery' => $order['estimated_delivery'],
    'actual_delivery'    => $order['actual_delivery'],
    'payment_method'     => $order['payment_method'],
    'transaction_id'     => $order['transaction_id'],
    'payment_status'     => $order['payment_status'],
    'items'              => array_map(function ($i) {
        return [
            'name'       => $i['name'],
            'price'      => (float)$i['price'],
            'qty'        => (int)$i['qty'],
            'product_id' => (int)$i['product_id'],
            'img'        => $i['img'],
            'bg'         => $i['bg'],
            'cond'       => $i['cond'],
        ];
    }, $items),
]);
