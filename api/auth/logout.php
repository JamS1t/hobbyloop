<?php
// POST /api/auth/logout.php
// Invalidates the current session token.
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
    $token = $matches[1];
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
    $stmt->execute([$token]);
}

json_success(['message' => 'Logged out']);
