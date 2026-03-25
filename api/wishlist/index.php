<?php
// ═══════════════════════════════════════════
// /api/wishlist/index.php — Wishlist (GET / POST / DELETE)
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user   = require_auth($pdo);
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list user's wishlisted products ──
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.original_price AS orig,
               p.image_url AS img, p.bg_gradient AS bg,
               p.condition_label AS cond, p.badge, p.rating,
               p.review_count AS reviews, p.stock_qty,
               c.label AS catLabel,
               CONCAT(u.first_name, ' ', u.last_name) AS seller
        FROM wishlist w
        JOIN products p ON p.id = w.product_id
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = p.seller_id
        WHERE w.user_id = ? AND p.is_active = 1
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();

    $result = array_map(function ($p) {
        return [
            'id'       => (int)$p['id'],
            'name'     => $p['name'],
            'price'    => (float)$p['price'],
            'orig'     => $p['orig'] ? (float)$p['orig'] : null,
            'img'      => $p['img'],
            'bg'       => $p['bg'],
            'cond'     => $p['cond'],
            'badge'    => $p['badge'],
            'rating'   => round((float)$p['rating'], 1),
            'reviews'  => (int)$p['reviews'],
            'stock_qty' => (int)$p['stock_qty'],
            'catLabel' => $p['catLabel'],
            'seller'   => $p['seller'],
        ];
    }, $rows);

    json_success($result);
}

// ── POST: add product to wishlist ──
if ($method === 'POST') {
    $body       = get_json_body();
    $product_id = isset($body['product_id']) ? (int)$body['product_id'] : 0;
    if (!$product_id) json_error('product_id is required');

    // Verify product exists and is active
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) json_error('Product not found', 404);

    // INSERT IGNORE — silently handles duplicate
    $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $product_id]);

    json_success(['wishlisted' => true]);
}

// ── DELETE: remove product from wishlist ──
if ($method === 'DELETE') {
    $body       = get_json_body();
    $product_id = isset($body['product_id']) ? (int)$body['product_id'] : 0;
    if (!$product_id) json_error('product_id is required');

    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user['id'], $product_id]);

    json_success(['wishlisted' => false]);
}

json_error('Method not allowed', 405);
