<?php
// ═══════════════════════════════════════════
// GET /api/orders/list.php — Order history
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);

$stmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.subtotal, o.discount, o.shipping_fee, o.total,
           o.status, o.promo_code, o.created_at,
           s.courier, s.tracking_number, s.status AS shipping_status, s.estimated_delivery
    FROM orders o
    LEFT JOIN shipments s ON s.order_id = o.id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

// Fetch items for each order
$stmt_items = $pdo->prepare("
    SELECT oi.product_name AS name, oi.price, oi.qty, oi.product_id,
           p.image_url AS img, p.bg_gradient AS bg
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
");

$result = [];
foreach ($orders as $o) {
    $stmt_items->execute([$o['id']]);
    $items = $stmt_items->fetchAll();

    $result[] = [
        'id'            => $o['order_number'],
        'order_id'      => (int)$o['id'],
        'total'         => (float)$o['total'],
        'subtotal'      => (float)$o['subtotal'],
        'discount'      => (float)$o['discount'],
        'shipping_fee'  => (float)$o['shipping_fee'],
        'status'        => $o['status'],
        'promo_code'    => $o['promo_code'],
        'date'          => date('F j, Y', strtotime($o['created_at'])),
        'courier'       => $o['courier'],
        'tracking'      => $o['tracking_number'],
        'shipping_status' => $o['shipping_status'],
        'estimated_delivery' => $o['estimated_delivery'],
        'items'         => array_map(function ($i) {
            return [
                'name'       => $i['name'],
                'price'      => (float)$i['price'],
                'qty'        => (int)$i['qty'],
                'product_id' => (int)$i['product_id'],
                'img'        => $i['img'],
                'bg'         => $i['bg'],
            ];
        }, $items),
    ];
}

json_success($result);
