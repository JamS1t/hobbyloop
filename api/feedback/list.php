<?php
// ═══════════════════════════════════════════
// GET /api/feedback/list.php — User's own feedback messages
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$user = require_auth($pdo);

$stmt = $pdo->prepare("
    SELECT id, subject, message, status, admin_reply, created_at, updated_at
    FROM feedback_messages
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$result = array_map(function ($r) {
    return [
        'id'          => (int)$r['id'],
        'subject'     => $r['subject'],
        'message'     => $r['message'],
        'status'      => $r['status'],
        'admin_reply' => $r['admin_reply'],
        'date'        => date('F j, Y', strtotime($r['created_at'])),
    ];
}, $rows);

json_success($result);
