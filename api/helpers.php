<?php
// ═══════════════════════════════════════════
// HobbyLoop API — Shared Helper Functions
// ═══════════════════════════════════════════

// ── CORS headers must be first — before any require or output ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight immediately — no DB connection needed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';

/**
 * Send a JSON response and exit.
 */
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a success response.
 */
function json_success($data = null, int $status = 200): void {
    json_response(['success' => true, 'data' => $data], $status);
}

/**
 * Send an error response.
 */
function json_error(string $message, int $status = 400): void {
    json_response(['success' => false, 'error' => $message], $status);
}

/**
 * Get the authenticated user from the Bearer token.
 * Returns user row (assoc array) or null if not authenticated.
 */
function get_auth_user(PDO $pdo): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return null;
    }
    $token = $matches[1];

    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
               u.avatar_initials, u.avatar_color, u.role, u.admin_level,
               u.trust_badge, u.is_verified
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.token = ? AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Require authentication — returns user or sends 401 and exits.
 */
function require_auth(PDO $pdo): array {
    $user = get_auth_user($pdo);
    if (!$user) {
        json_error('Authentication required', 401);
    }
    return $user;
}

/**
 * Sanitize a string for safe output.
 */
function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Get JSON body from POST/PUT request.
 */
function get_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
