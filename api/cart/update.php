<?php
// ═══════════════════════════════════════════
// PUT /api/cart/update.php — Update cart item qty or selection
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

$product_id = isset($body['product_id']) ? (int)$body['product_id'] : 0;

// Support bulk select/deselect all
if (isset($body['select_all'])) {
    $selected = $body['select_all'] ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE cart_items SET is_selected = ? WHERE user_id = ?");
    $stmt->execute([$selected, $user['id']]);
    json_success(['message' => 'Updated all items']);
}

if ($product_id <= 0) {
    json_error('Invalid product ID');
}

// Build SET clause dynamically
$sets = [];
$params = [];

if (isset($body['qty'])) {
    $qty = max(1, (int)$body['qty']);
    $sets[] = 'qty = ?';
    $params[] = $qty;
}

if (isset($body['is_selected'])) {
    $sets[] = 'is_selected = ?';
    $params[] = $body['is_selected'] ? 1 : 0;
}

if (empty($sets)) {
    json_error('Nothing to update');
}

$params[] = $user['id'];
$params[] = $product_id;

$sql = "UPDATE cart_items SET " . implode(', ', $sets) . " WHERE user_id = ? AND product_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    json_error('Cart item not found');
}

json_success(['message' => 'Cart updated']);
