<?php
// GET /api/auth/session.php
// Validates the current Bearer token and returns user data.
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$user = require_auth($pdo);
$user['name'] = $user['first_name'] . ' ' . $user['last_name'];

json_success(['user' => $user]);
