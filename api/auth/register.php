<?php
// POST /api/auth/register.php
// Creates a new user account and returns a session token.
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = get_json_body();
$firstName = trim($body['first_name'] ?? '');
$lastName  = trim($body['last_name'] ?? '');
$email     = trim($body['email'] ?? '');
$password  = $body['password'] ?? '';

// Validation
if (!$firstName || !$lastName) {
    json_error('First name and last name are required');
}
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('A valid email address is required');
}
if (strlen($password) < 8) {
    json_error('Password must be at least 8 characters');
}

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_error('An account with this email already exists');
}

// Create user
$hash = password_hash($password, PASSWORD_BCRYPT);
$initials = strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));

$stmt = $pdo->prepare("
    INSERT INTO users (first_name, last_name, email, password_hash, avatar_initials, avatar_color, role)
    VALUES (?, ?, ?, ?, ?, '#0D7C6E', 'buyer')
");
$stmt->execute([
    $firstName,
    $lastName,
    $email,
    $hash,
    $initials
]);
$userId = (int) $pdo->lastInsertId();

// Create session token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_HOURS . ' hours'));

$stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$userId, $token, $expiresAt]);

json_success([
    'token' => $token,
    'user'  => [
        'id'              => $userId,
        'first_name'      => $firstName,
        'last_name'       => $lastName,
        'name'            => sanitize($firstName) . ' ' . sanitize($lastName),
        'email'           => $email,
        'avatar_initials' => $initials,
        'avatar_color'    => '#0D7C6E',
        'role'            => 'buyer',
        'trust_badge'     => 'New Member',
        'is_verified'     => 0,
    ]
], 201);
