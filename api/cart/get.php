<?php
// ═══════════════════════════════════════════
// GET /api/cart/get.php — Fetch user's cart
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);

$stmt = $pdo->prepare("
    SELECT ci.id AS cart_item_id, ci.qty, ci.is_selected,
           p.id, p.name, p.price, p.original_price AS orig,
           p.condition_label AS cond, p.image_url AS img,
           p.bg_gradient AS bg, p.badge, p.stock_qty,
           p.category_id AS cat,
           c.label AS cat_label,
           CONCAT(u.first_name, ' ', u.last_name) AS seller
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN sellers s ON s.user_id = p.seller_id
    LEFT JOIN users u ON u.id = p.seller_id
    WHERE ci.user_id = ?
    ORDER BY ci.added_at DESC
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$items = array_map(function ($r) {
    return [
        'cart_item_id' => (int)$r['cart_item_id'],
        'qty'          => (int)$r['qty'],
        'selected'     => (bool)$r['is_selected'],
        'product'      => [
            'id'    => (int)$r['id'],
            'name'  => $r['name'],
            'price' => (float)$r['price'],
            'orig'  => $r['orig'] ? (float)$r['orig'] : null,
            'cond'  => $r['cond'],
            'img'   => $r['img'],
            'bg'    => $r['bg'],
            'badge' => $r['badge'],
            'cat'   => $r['cat'],
            'cat_label' => $r['cat_label'],
            'seller'    => $r['seller'],
            'stock_qty' => (int)$r['stock_qty'],
        ],
    ];
}, $rows);

json_success($items);
