<?php
// ═══════════════════════════════════════════
// POST /api/notifications/mark-read.php
// Mark one notification (id) or all (all=true) as read
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

if (!empty($body['all'])) {
    // Mark all as read
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    json_success(['message' => 'All notifications marked as read', 'unread_count' => 0]);
}

$id = isset($body['id']) ? (int)$body['id'] : 0;
if ($id <= 0) {
    json_error('Invalid notification ID');
}

// Verify the notification belongs to this user before updating
$check = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
$check->execute([$id, $user['id']]);
if (!$check->fetch()) {
    json_error('Notification not found', 404);
}

$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);

// Return new unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unread_count = (int)$stmt->fetchColumn();

json_success(['message' => 'Marked as read', 'unread_count' => $unread_count]);
