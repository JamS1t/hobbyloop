<?php
// ═══════════════════════════════════════════
// GET /api/notifications/list.php — User notifications
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);

$stmt = $pdo->prepare("
    SELECT id, icon, text, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

// Unread count
$unread_count = 0;
$notifications = array_map(function ($n) use (&$unread_count) {
    $unread = !(bool)$n['is_read'];
    if ($unread) $unread_count++;

    $diff = time() - strtotime($n['created_at']);
    if ($diff < 60)       $time = 'Just now';
    elseif ($diff < 3600) $time = floor($diff / 60) . 'm ago';
    elseif ($diff < 86400) $time = floor($diff / 3600) . 'h ago';
    else                   $time = floor($diff / 86400) . 'd ago';

    return [
        'id'     => (int)$n['id'],
        'icon'   => $n['icon'],
        'text'   => $n['text'],
        'unread' => $unread,
        'time'   => $time,
    ];
}, $rows);

json_success(['notifications' => $notifications, 'unread_count' => $unread_count]);
