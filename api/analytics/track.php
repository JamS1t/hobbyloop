<?php
// ═══════════════════════════════════════════
// POST /api/analytics/track.php — Log user activity
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = get_json_body();

// Auth optional — guests can be tracked without a user_id
$user    = get_auth_user($pdo);
$user_id = $user ? $user['id'] : null;

$valid_actions = [
    'page_view', 'product_view', 'search',
    'add_to_cart', 'checkout_complete', 'login', 'logout'
];

$action = isset($body['action']) ? $body['action'] : '';
if (!in_array($action, $valid_actions)) {
    json_error('Invalid action');
}

$target_id    = !empty($body['target_id'])    ? (int)$body['target_id']           : null;
$search_query = !empty($body['search_query']) ? sanitize($body['search_query'])   : null;
$metadata     = !empty($body['metadata'])     ? json_encode($body['metadata'])    : null;
$ip_address   = $_SERVER['REMOTE_ADDR'] ?? null;

// Capture session token for correlation
$session_token = null;
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
    $session_token = $matches[1];
}

$stmt = $pdo->prepare("
    INSERT INTO user_activity
        (user_id, session_token, action, target_id, search_query, metadata, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $user_id, $session_token, $action,
    $target_id, $search_query, $metadata, $ip_address
]);

json_success(['tracked' => true]);
