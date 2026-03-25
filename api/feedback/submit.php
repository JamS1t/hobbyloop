<?php
// ═══════════════════════════════════════════
// POST /api/feedback/submit.php — Submit a feedback message
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$user = require_auth($pdo);
$body = get_json_body();

$subject = isset($body['subject']) ? sanitize($body['subject']) : '';
$message = isset($body['message']) ? sanitize($body['message']) : '';

if (!$subject)                  json_error('Subject is required');
if (!$message)                  json_error('Message is required');
if (strlen($message) < 10)      json_error('Message must be at least 10 characters');

$stmt = $pdo->prepare("
    INSERT INTO feedback_messages (user_id, subject, message, status)
    VALUES (?, ?, ?, 'open')
");
$stmt->execute([$user['id'], $subject, $message]);

json_success(['id' => (int)$pdo->lastInsertId()]);
