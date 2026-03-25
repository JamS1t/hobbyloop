<?php
// ═══════════════════════════════════════════
// POST /api/community/follow.php — Toggle follow on a user
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

$target_id = isset($body['user_id']) ? (int)$body['user_id'] : 0;
if ($target_id <= 0) {
    json_error('Invalid user ID');
}
if ($target_id === $user['id']) {
    json_error('Cannot follow yourself');
}

// Check target user exists
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
$stmt->execute([$target_id]);
$target = $stmt->fetch();
if (!$target) {
    json_error('User not found', 404);
}

// Check if already following
$stmt = $pdo->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
$stmt->execute([$user['id'], $target_id]);
$already_following = (bool)$stmt->fetch();

if ($already_following) {
    $stmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$user['id'], $target_id]);
    json_success(['following' => false, 'name' => $target['first_name'] . ' ' . $target['last_name']]);
} else {
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_follows (follower_id, following_id) VALUES (?, ?)");
    $stmt->execute([$user['id'], $target_id]);
    json_success(['following' => true, 'name' => $target['first_name'] . ' ' . $target['last_name']]);
}
