<?php
// GET /api/products/search.php?q= — Search products by name/category/description/seller/condition
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (strlen($q) < 1) {
    json_success([]);
}

$like = '%' . $q . '%';

$stmt = $pdo->prepare("
    SELECT p.id, p.category_id AS cat, p.name, p.bg_gradient AS bg, p.image_url AS img,
           p.price, p.original_price AS orig, p.condition_label AS cond,
           p.rating, p.review_count AS reviews, p.badge, p.description AS `desc`,
           CONCAT(u.first_name, ' ', u.last_name) AS seller,
           s.id AS sellerId,
           c.label AS catLabel
    FROM products p
    JOIN sellers s ON s.user_id = p.seller_id
    JOIN users u ON u.id = p.seller_id
    JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1
      AND (p.name LIKE ? OR p.category_id LIKE ? OR p.description LIKE ?
           OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR p.condition_label LIKE ?)
    ORDER BY p.id ASC
    LIMIT 8
");
$stmt->execute([$like, $like, $like, $like, $like]);
$results = $stmt->fetchAll();

foreach ($results as &$p) {
    $p['id'] = (int) $p['id'];
    $p['price'] = (float) $p['price'];
    $p['orig'] = $p['orig'] ? (float) $p['orig'] : null;
    $p['rating'] = (float) $p['rating'];
    $p['reviews'] = (int) $p['reviews'];
    $p['sellerId'] = (int) $p['sellerId'];
    $p['name'] = sanitize($p['name']);
    $p['seller'] = sanitize($p['seller']);
    $p['cond'] = sanitize($p['cond']);
}

json_success($results);
