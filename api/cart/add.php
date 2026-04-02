<?php
// ═══════════════════════════════════════════
// POST /api/cart/add.php — Add item to cart
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

$product_id = isset($body['product_id']) ? (int)$body['product_id'] : 0;
if ($product_id <= 0) {
    json_error('Invalid product ID');
}

// Check product exists and is active
$stmt = $pdo->prepare("SELECT id, name, stock_qty FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) {
    json_error('Product not found');
}
if ($product['stock_qty'] <= 0) {
    json_error('Product is out of stock');
}

// Check current cart qty — don't exceed stock
$stmt = $pdo->prepare("SELECT qty FROM cart_items WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user['id'], $product_id]);
$current = $stmt->fetch();
$currentQty = $current ? (int)$current['qty'] : 0;

if ($currentQty + 1 > $product['stock_qty']) {
    json_error('Only ' . $product['stock_qty'] . ' available in stock');
}

// Upsert — if already in cart, increment qty
$stmt = $pdo->prepare("
    INSERT INTO cart_items (user_id, product_id, qty, is_selected)
    VALUES (?, ?, 1, 1)
    ON DUPLICATE KEY UPDATE qty = qty + 1
");
$stmt->execute([$user['id'], $product_id]);

json_success(['message' => 'Added to cart']);
