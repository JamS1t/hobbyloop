<?php
// POST /api/auth/login.php
// Validates credentials and returns a session token.
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = get_json_body();
$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) {
    json_error('Email and password are required');
}

// Allow login by email address or username
$field = strpos($email, '@') !== false ? 'email' : 'username';

// Find user by email or username
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, username, password_hash, phone,
           avatar_initials, avatar_color, role, admin_level,
           trust_badge, is_verified
    FROM users WHERE $field = ?
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid email or password', 401);
}

// Clean up expired sessions for this user
$pdo->prepare("DELETE FROM sessions WHERE user_id = ? AND expires_at <= NOW()")
    ->execute([$user['id']]);

// Create new session token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_HOURS . ' hours'));

$stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$user['id'], $token, $expiresAt]);

unset($user['password_hash']);

json_success([
    'token' => $token,
    'user'  => [
        'id'              => (int) $user['id'],
        'first_name'      => $user['first_name'],
        'last_name'       => $user['last_name'],
        'name'            => $user['first_name'] . ' ' . $user['last_name'],
        'email'           => $user['email'],
        'phone'           => $user['phone'],
        'avatar_initials' => $user['avatar_initials'],
        'avatar_color'    => $user['avatar_color'],
        'role'            => $user['role'],
        'admin_level'     => $user['admin_level'],
        'trust_badge'     => $user['trust_badge'],
        'is_verified'     => (int) $user['is_verified'],
        'username'        => $user['username'],
    ]
]);
