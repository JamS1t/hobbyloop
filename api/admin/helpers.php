<?php
// ═══════════════════════════════════════════
// HobbyLoop Admin API — Helpers
// ═══════════════════════════════════════════

/**
 * Require admin role from Bearer token (for API endpoints).
 * Returns user array or sends 401/403 and exits.
 */
function require_admin(PDO $pdo): array {
    $user = get_auth_user($pdo);
    if (!$user) {
        json_error('Authentication required', 401);
    }
    if ($user['role'] !== 'admin') {
        json_error('Admin access required', 403);
    }
    return $user;
}

/**
 * Log an admin action to system_logs.
 */
function log_action(PDO $pdo, int $admin_id, string $action, string $entity_type, $entity_id = null, array $details = []): void {
    $stmt = $pdo->prepare("
        INSERT INTO system_logs (admin_id, action, entity_type, entity_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $admin_id,
        $action,
        $entity_type,
        $entity_id,
        json_encode($details, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

/**
 * Log an admin panel session action (uses $_SESSION admin_id).
 */
function log_admin_action(PDO $pdo, string $action, string $entity_type, $entity_id = null, array $details = []): void {
    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($admin_id > 0) {
        log_action($pdo, $admin_id, $action, $entity_type, $entity_id, $details);
    }
}
