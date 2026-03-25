<?php
// GET /api/products/detail.php?id= — Single product with seller + reviews
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    json_error('Product ID required');
}

// ── Fetch product with seller info ──
$stmt = $pdo->prepare("
    SELECT p.id, p.category_id AS cat, p.name, p.bg_gradient AS bg, p.image_url AS img,
           p.price, p.original_price AS orig, p.condition_label AS cond,
           p.rating, p.review_count AS reviews, p.badge, p.description AS `desc`,
           p.stock_qty, p.sku, p.brand, p.is_active,
           CONCAT(u.first_name, ' ', u.last_name) AS seller,
           s.id AS sellerId,
           u.avatar_initials AS sellerInitials,
           u.avatar_color AS sellerColor,
           s.city AS sellerCity,
           s.badge AS sellerBadge,
           s.total_sales AS sellerSales,
           s.seller_rating AS sellerRating,
           c.label AS catLabel
    FROM products p
    JOIN sellers s ON s.user_id = p.seller_id
    JOIN users u ON u.id = p.seller_id
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    json_error('Product not found', 404);
}

// Cast types
$product['id'] = (int) $product['id'];
$product['price'] = (float) $product['price'];
$product['orig'] = $product['orig'] ? (float) $product['orig'] : null;
$product['stock_qty'] = (int) $product['stock_qty'];
$product['sellerId'] = (int) $product['sellerId'];
$product['sellerSales'] = (int) $product['sellerSales'];
$product['sellerRating'] = (float) $product['sellerRating'];

// Sanitize free-text fields
$product['name'] = sanitize($product['name']);
$product['desc'] = sanitize($product['desc'] ?? '');
$product['seller'] = sanitize($product['seller']);
$product['sellerBadge'] = sanitize($product['sellerBadge'] ?? '');
$product['sellerCity'] = sanitize($product['sellerCity'] ?? '');

// ── Calculate live average rating from approved reviews ──
$stmt = $pdo->prepare("
    SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
    FROM reviews
    WHERE product_id = ? AND is_approved = 1
");
$stmt->execute([$id]);
$ratingData = $stmt->fetch();

$product['rating'] = $ratingData['avg_rating'] ? round((float) $ratingData['avg_rating'], 1) : 0;
$product['reviews'] = (int) $ratingData['total_reviews'];

// ── Fetch approved reviews with reviewer info ──
$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name,
           u.avatar_initials, u.avatar_color
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
");
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

foreach ($reviews as &$rev) {
    $rev['id'] = (int) $rev['id'];
    $rev['rating'] = (int) $rev['rating'];
    $rev['comment'] = $rev['comment'] ? sanitize($rev['comment']) : null;
    $rev['reviewer_name'] = sanitize($rev['reviewer_name']);
}

$product['reviewsList'] = $reviews;

json_success($product);
