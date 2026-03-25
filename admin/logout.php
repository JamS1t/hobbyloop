<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Log logout before destroying session
if (!empty($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../api/config.php';
    $pdo->prepare("
        INSERT INTO system_logs (admin_id, action, entity_type, ip_address)
        VALUES (?, 'logout', 'session', ?)
    ")->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR'] ?? null]);
}

session_destroy();
header('Location: /hobbyloop/admin/login.php');
exit;
