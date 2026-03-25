<?php
// GET /api/products/list.php — List products with optional category filter
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$cat = isset($_GET['cat']) ? trim($_GET['cat']) : 'all';

$sql = "
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
    WHERE p.is_active = 1
";

$params = [];

if ($cat !== 'all') {
    $sql .= " AND p.category_id = ?";
    $params[] = $cat;
}

$sql .= " ORDER BY p.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Cast numeric fields + sanitize text
foreach ($products as &$p) {
    $p['id'] = (int) $p['id'];
    $p['price'] = (float) $p['price'];
    $p['orig'] = $p['orig'] ? (float) $p['orig'] : null;
    $p['rating'] = (float) $p['rating'];
    $p['reviews'] = (int) $p['reviews'];
    $p['stock_qty'] = (int) $p['stock_qty'];
    $p['sellerId'] = (int) $p['sellerId'];
    $p['sellerSales'] = (int) $p['sellerSales'];
    $p['sellerRating'] = (float) $p['sellerRating'];
    $p['name'] = sanitize($p['name']);
    $p['desc'] = sanitize($p['desc'] ?? '');
    $p['seller'] = sanitize($p['seller']);
    $p['cond'] = sanitize($p['cond']);
}

json_success($products);
