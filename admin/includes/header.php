<?php
// ── Admin Panel Header ──
// Include at the TOP of every admin page.
// Expects $page_title (string) to be set before include.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
if (empty($_SESSION['admin_id'])) {
    header('Location: /hobbyloop/admin/login.php');
    exit;
}

// PDO connection (available as $pdo in all pages)
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/admin/helpers.php';

$_admin_name  = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$_admin_level = htmlspecialchars($_SESSION['admin_level'] ?? '', ENT_QUOTES, 'UTF-8');

// Flash message helper
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// Level check helpers
function is_super(): bool    { return ($_SESSION['admin_level'] ?? '') === 'super'; }
function is_editor(): bool   { return in_array($_SESSION['admin_level'] ?? '', ['super', 'editor']); }
function require_editor(): void {
    if (!is_editor()) {
        flash('error', 'You do not have permission to perform this action.');
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '/hobbyloop/admin/');
        exit;
    }
}
function require_super(): void {
    if (!is_super()) {
        flash('error', 'Only super admins can perform this action.');
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '/hobbyloop/admin/');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin', ENT_QUOTES) ?> — HobbyLoop Admin</title>
    <link rel="stylesheet" href="/hobbyloop/admin/admin.css">
</head>
<body>
<div class="admin-layout">
