<?php
// GET /api/products/variants.php?product_id=X — List all variants for a product
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    json_error('A valid product_id query parameter is required');
}

// Verify the product exists
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
$stmt->execute([$product_id]);
if (!$stmt->fetch()) {
    json_error('Product not found', 404);
}

$stmt = $pdo->prepare("
    SELECT id, product_id, sku, size, color, material, weight_grams,
           price_modifier, stock_qty, image_url
    FROM product_variants
    WHERE product_id = ?
    ORDER BY id ASC
");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();

json_response(['success' => true, 'variants' => $variants]);
