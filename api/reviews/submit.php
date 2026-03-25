<?php
// ═══════════════════════════════════════════
// POST /api/reviews/submit.php — Submit a product review
// Auth required. Purchase-verified. Prevents duplicates.
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$user = require_auth($pdo);
$body = get_json_body();

$product_id = isset($body['product_id']) ? (int)$body['product_id'] : 0;
$rating     = isset($body['rating'])     ? (int)$body['rating']     : 0;
$comment    = isset($body['comment'])    ? sanitize($body['comment']): '';

if (!$product_id)             json_error('product_id is required');
if ($rating < 1 || $rating > 5) json_error('Rating must be between 1 and 5');

// Verify product exists
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$product_id]);
if (!$stmt->fetch()) json_error('Product not found', 404);

// Verify user has purchased this product
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.user_id = ? AND oi.product_id = ? AND o.status != 'Cancelled'
");
$stmt->execute([$user['id'], $product_id]);
if ((int)$stmt->fetchColumn() === 0) {
    json_error('You can only review products you have purchased');
}

// Prevent duplicate review
$stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$stmt->execute([$user['id'], $product_id]);
if ($stmt->fetch()) {
    json_error('You have already reviewed this product');
}

// Insert review (auto-approved)
$stmt = $pdo->prepare("
    INSERT INTO reviews (product_id, user_id, rating, comment, is_approved)
    VALUES (?, ?, ?, ?, 1)
");
$stmt->execute([$product_id, $user['id'], $rating, $comment]);

// Recalculate product avg rating and review_count
$stmt = $pdo->prepare("
    UPDATE products
    SET review_count = (
            SELECT COUNT(*) FROM reviews WHERE product_id = ? AND is_approved = 1
        ),
        rating = (
            SELECT ROUND(AVG(rating), 1) FROM reviews WHERE product_id = ? AND is_approved = 1
        )
    WHERE id = ?
");
$stmt->execute([$product_id, $product_id, $product_id]);

json_success([
    'review' => [
        'rating'          => $rating,
        'comment'         => $comment,
        'reviewer_name'   => $user['first_name'] . ' ' . $user['last_name'],
        'avatar_initials' => $user['avatar_initials'],
        'avatar_color'    => $user['avatar_color'],
        'created_at'      => date('Y-m-d H:i:s'),
    ]
]);
