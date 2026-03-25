<?php
// GET /api/sellers/detail.php?id= — Seller profile
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    json_error('Seller ID required');
}

$stmt = $pdo->prepare("
    SELECT s.id, s.user_id AS userId,
           CONCAT(u.first_name, ' ', u.last_name) AS name,
           u.avatar_initials AS initials,
           u.avatar_color AS color,
           s.city, s.badge, s.total_sales AS sales,
           s.seller_rating AS rating,
           s.specialty_categories AS cats,
           u.is_verified
    FROM sellers s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$seller = $stmt->fetch();

if (!$seller) {
    json_error('Seller not found', 404);
}

$seller['id'] = (int) $seller['id'];
$seller['userId'] = (int) $seller['userId'];
$seller['sales'] = (int) $seller['sales'];
$seller['rating'] = (float) $seller['rating'];
$seller['is_verified'] = (bool) $seller['is_verified'];

json_success($seller);
