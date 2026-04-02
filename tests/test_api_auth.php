<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop API Test — Auth & Security
// Tests: registration, login, session, logout, SQL injection, XSS
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- API: Auth & Security ---\n";

// ── HTTP helper ────────────────────────────────────────
if (!function_exists('api_request')) {
function api_request($method, $endpoint, $data = null, $token = null) {
    $url = 'http://localhost/hobbyloop/api' . $endpoint;
    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => "Content-Type: application/json\r\n",
            'ignore_errors' => true,
            'timeout'       => 10,
        ]
    ];
    if ($token) {
        $opts['http']['header'] .= "Authorization: Bearer $token\r\n";
    }
    if ($data !== null) {
        $opts['http']['content'] = json_encode($data);
    }
    $context  = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $status   = 200;
    if (isset($http_response_header[0])) {
        preg_match('/\d{3}/', $http_response_header[0], $m);
        $status = (int)($m[0] ?? 200);
    }
    return ['status' => $status, 'body' => json_decode($response, true), 'raw' => $response];
}
} // end if !function_exists

// ── Track created user IDs for cleanup ────────────────
$cleanup_user_ids = [];

$ts = time();

// ════════════════════════════════════════════════════
// REGISTRATION TESTS
// ════════════════════════════════════════════════════

// 1. Register new user with valid data
$email1 = "test_auth_{$ts}@test.com";
$r = api_request('POST', '/auth/register.php', [
    'first_name' => 'Test',
    'last_name'  => 'Auth',
    'email'      => $email1,
    'password'   => 'password123',
]);
assert_test($r['status'] === 201 && !empty($r['body']['data']['token']),
    'Register: new user returns 201 + token');
$token1 = $r['body']['data']['token'] ?? null;
$user1_id = $r['body']['data']['user']['id'] ?? null;
if ($user1_id) $cleanup_user_ids[] = $user1_id;

// 2. Register with duplicate email
$r = api_request('POST', '/auth/register.php', [
    'first_name' => 'Test',
    'last_name'  => 'Dupe',
    'email'      => $email1,
    'password'   => 'password123',
]);
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Register: duplicate email returns 400 error');

// 3. Register with short password (< 8 chars)
$r = api_request('POST', '/auth/register.php', [
    'first_name' => 'Test',
    'last_name'  => 'Short',
    'email'      => "test_short_{$ts}@test.com",
    'password'   => '1234567',
]);
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Register: short password returns 400 error');

// 4. Register with missing required field (no email)
$r = api_request('POST', '/auth/register.php', [
    'first_name' => 'No',
    'last_name'  => 'Email',
    'password'   => 'password123',
]);
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Register: missing email returns 400 error');

// 5. Register with optional username
$email2 = "test_user_{$ts}@test.com";
$uname  = "hobbytest_{$ts}";
$r = api_request('POST', '/auth/register.php', [
    'first_name' => 'User',
    'last_name'  => 'Named',
    'email'      => $email2,
    'password'   => 'password123',
    'username'   => $uname,
]);
assert_test($r['status'] === 201 && $r['body']['data']['user']['username'] === $uname,
    'Register: optional username accepted and returned in response');
$user2_id = $r['body']['data']['user']['id'] ?? null;
if ($user2_id) $cleanup_user_ids[] = $user2_id;

// 6. Register with duplicate username
$r = api_request('POST', '/auth/register.php', [
    'first_name' => 'Other',
    'last_name'  => 'User',
    'email'      => "test_udup_{$ts}@test.com",
    'password'   => 'password123',
    'username'   => $uname,
]);
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Register: duplicate username returns 400 error');

// ════════════════════════════════════════════════════
// LOGIN TESTS
// ════════════════════════════════════════════════════

// 7. Login with correct credentials
$r = api_request('POST', '/auth/login.php', [
    'email'    => $email1,
    'password' => 'password123',
]);
assert_test($r['status'] === 200 && !empty($r['body']['data']['token']),
    'Login: correct credentials return 200 + token');
$login_token = $r['body']['data']['token'] ?? null;

// 8. Login with wrong password
$r = api_request('POST', '/auth/login.php', [
    'email'    => $email1,
    'password' => 'wrongpassword',
]);
assert_test($r['status'] === 401 && $r['body']['success'] === false,
    'Login: wrong password returns 401');

// 9. Login with non-existent email
$r = api_request('POST', '/auth/login.php', [
    'email'    => 'nobody_exists_here@fake.com',
    'password' => 'password123',
]);
assert_test($r['status'] === 401 && $r['body']['success'] === false,
    'Login: non-existent email returns 401');

// 10. Login with username instead of email
$r = api_request('POST', '/auth/login.php', [
    'email'    => $uname,  // username passed in email field
    'password' => 'password123',
]);
assert_test($r['status'] === 200 && !empty($r['body']['data']['token']),
    'Login: username accepted in email field returns 200 + token');

// ════════════════════════════════════════════════════
// SESSION TESTS
// ════════════════════════════════════════════════════

// 11. GET session with valid token
$r = api_request('GET', '/auth/session.php', null, $login_token);
assert_test($r['status'] === 200 && !empty($r['body']['data']['user']),
    'Session: valid token returns 200 + user data');
assert_test(
    isset($r['body']['data']['user']['username']) || array_key_exists('username', $r['body']['data']['user'] ?? []),
    'Session: user data includes username field');

// 12. GET session with invalid token
$r = api_request('GET', '/auth/session.php', null, 'this_is_not_a_real_token_xyz');
assert_test($r['status'] === 401 && $r['body']['success'] === false,
    'Session: invalid token returns 401');

// 13. GET session with no token
$r = api_request('GET', '/auth/session.php');
assert_test($r['status'] === 401 && $r['body']['success'] === false,
    'Session: no token returns 401');

// ════════════════════════════════════════════════════
// LOGOUT TESTS
// ════════════════════════════════════════════════════

// 14. POST logout with valid token
$r = api_request('POST', '/auth/logout.php', null, $login_token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Logout: valid token returns 200 success');

// 15. After logout, session check should fail
$r = api_request('GET', '/auth/session.php', null, $login_token);
assert_test($r['status'] === 401,
    'Logout: session check with invalidated token returns 401');

// ════════════════════════════════════════════════════
// SECURITY TESTS
// ════════════════════════════════════════════════════

// 16. SQL injection in email field should NOT succeed
$r = api_request('POST', '/auth/login.php', [
    'email'    => "' OR 1=1 --",
    'password' => 'anything',
]);
assert_test($r['status'] === 401 && $r['body']['success'] === false,
    'Security: SQL injection in email does not authenticate');

// 17. XSS in name field — should be stored sanitized (not cause a server error)
$xss_email = "test_xss_{$ts}@test.com";
$r = api_request('POST', '/auth/register.php', [
    'first_name' => '<script>alert(1)</script>',
    'last_name'  => 'XSSTest',
    'email'      => $xss_email,
    'password'   => 'password123',
]);
// Server should either accept it (storing safely) or reject with 400, not crash with 500
assert_test(in_array($r['status'], [201, 400]) && $r['body']['success'] !== null,
    'Security: XSS in first_name does not cause 500 server error');
if ($r['status'] === 201) {
    $xss_user_id = $r['body']['data']['user']['id'] ?? null;
    if ($xss_user_id) $cleanup_user_ids[] = $xss_user_id;
}

// ════════════════════════════════════════════════════
// CLEANUP — delete test users and their sessions
// ════════════════════════════════════════════════════

if (!empty($cleanup_user_ids)) {
    // Remove sessions first (FK), then users
    $placeholders = implode(',', array_fill(0, count($cleanup_user_ids), '?'));
    $pdo->prepare("DELETE FROM sessions WHERE user_id IN ($placeholders)")->execute($cleanup_user_ids);
    $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)")->execute($cleanup_user_ids);
}

if (!isset($run_all)) {
    print_summary();
}
