<?php
// GET /api/products/categories.php — List all categories
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$stmt = $pdo->query("SELECT id, label, sort_order FROM categories WHERE id != 'all' ORDER BY sort_order ASC");
$categories = $stmt->fetchAll();

json_success($categories);
