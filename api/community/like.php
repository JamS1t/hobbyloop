<?php
// ═══════════════════════════════════════════
// POST /api/community/like.php — Toggle like on a post
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

$post_id = isset($body['post_id']) ? (int)$body['post_id'] : 0;
if ($post_id <= 0) {
    json_error('Invalid post ID');
}

// Verify post exists
$stmt = $pdo->prepare("SELECT id FROM community_posts WHERE id = ?");
$stmt->execute([$post_id]);
if (!$stmt->fetch()) {
    json_error('Post not found', 404);
}

// Attempt INSERT IGNORE — affected rows tells us whether the like is new
$stmt = $pdo->prepare("INSERT IGNORE INTO post_likes (user_id, post_id) VALUES (?, ?)");
$stmt->execute([$user['id'], $post_id]);
$inserted = $stmt->rowCount() > 0;

if ($inserted) {
    // Liked — increment counter
    $pdo->prepare("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$post_id]);
    $liked = true;
} else {
    // Already liked — unlike it
    $pdo->prepare("DELETE FROM post_likes WHERE user_id = ? AND post_id = ?")->execute([$user['id'], $post_id]);
    $pdo->prepare("UPDATE community_posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = ?")->execute([$post_id]);
    $liked = false;
}

// Re-read authoritative count from DB (avoids stale snapshot)
$stmt = $pdo->prepare("SELECT likes_count FROM community_posts WHERE id = ?");
$stmt->execute([$post_id]);
$likes_count = (int)$stmt->fetchColumn();

json_success(['liked' => $liked, 'likes_count' => $likes_count]);
