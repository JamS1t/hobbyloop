<?php
// GET /api/reviews/list.php?product_id= — Approved reviews for a product
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
if ($productId <= 0) {
    json_error('Product ID required');
}

$stmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS reviewer_name,
           u.avatar_initials, u.avatar_color
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC
");
$stmt->execute([$productId]);
$reviews = $stmt->fetchAll();

foreach ($reviews as &$rev) {
    $rev['id'] = (int) $rev['id'];
    $rev['rating'] = (int) $rev['rating'];
}

json_success($reviews);
