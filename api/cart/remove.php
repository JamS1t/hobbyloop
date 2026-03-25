<?php
// ═══════════════════════════════════════════
// DELETE /api/cart/remove.php — Remove item from cart
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

$product_id = isset($body['product_id']) ? (int)$body['product_id'] : 0;
if ($product_id <= 0) {
    json_error('Invalid product ID');
}

$stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user['id'], $product_id]);

if ($stmt->rowCount() === 0) {
    json_error('Cart item not found');
}

json_success(['message' => 'Removed from cart']);
