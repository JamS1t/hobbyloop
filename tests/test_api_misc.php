<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop API Test — Wishlist, Notifications, Feedback, Analytics
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- API: Wishlist / Notifications / Feedback / Analytics ---\n";

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
}

// ── Register a test user ───────────────────────────────
$ts         = time();
$test_email = "test_misc_{$ts}@test.com";

$reg = api_request('POST', '/auth/register.php', [
    'first_name' => 'Misc',
    'last_name'  => 'Tester',
    'email'      => $test_email,
    'password'   => 'password123',
]);
$token   = $reg['body']['data']['token'] ?? null;
$user_id = $reg['body']['data']['user']['id'] ?? null;

assert_test($token !== null && $user_id !== null,
    'Misc setup: test user registered and token obtained');

if (!$token) {
    echo "  [SKIP] Cannot run misc tests — test user registration failed.\n";
    if (!isset($run_all)) print_summary();
    return;
}

// ════════════════════════════════════════════════════
// WISHLIST
// ════════════════════════════════════════════════════

// 1. POST /wishlist/index.php — add product_id=1 to wishlist
$r = api_request('POST', '/wishlist/index.php', ['product_id' => 1], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Wishlist POST: add product_id=1 returns 200 success');
assert_test(($r['body']['data']['wishlisted'] ?? null) === true,
    'Wishlist POST: wishlisted=true in response');

// 2. GET /wishlist/index.php — should contain the added product
$r = api_request('GET', '/wishlist/index.php', null, $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Wishlist GET: returns 200 success');
$wishlist = $r['body']['data'] ?? [];
assert_test(is_array($wishlist) && count($wishlist) > 0,
    'Wishlist GET: wishlist is non-empty after adding a product');
$found_in_wishlist = false;
foreach ($wishlist as $item) {
    if (($item['id'] ?? 0) === 1) {
        $found_in_wishlist = true;
        break;
    }
}
assert_test($found_in_wishlist, 'Wishlist GET: product_id=1 is present in wishlist');

// 3. DELETE /wishlist/index.php — remove product_id=1
$r = api_request('DELETE', '/wishlist/index.php', ['product_id' => 1], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Wishlist DELETE: remove product_id=1 returns 200 success');
assert_test(($r['body']['data']['wishlisted'] ?? null) === false,
    'Wishlist DELETE: wishlisted=false in response');

// 4. GET /wishlist/index.php — should be empty now
$r = api_request('GET', '/wishlist/index.php', null, $token);
$wishlist_after = $r['body']['data'] ?? [];
assert_test(is_array($wishlist_after) && count($wishlist_after) === 0,
    'Wishlist GET: wishlist is empty after removing the only item');

// ════════════════════════════════════════════════════
// NOTIFICATIONS
// ════════════════════════════════════════════════════

// 5. GET /notifications/list.php — returns array (may be empty for new user)
$r = api_request('GET', '/notifications/list.php', null, $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Notifications GET: returns 200 success');
$notif_data = $r['body']['data'] ?? [];
assert_test(isset($notif_data['notifications']) && is_array($notif_data['notifications']),
    'Notifications GET: response has notifications array');
assert_test(isset($notif_data['unread_count']),
    'Notifications GET: response has unread_count field');

// ════════════════════════════════════════════════════
// FEEDBACK
// ════════════════════════════════════════════════════

// 6. POST /feedback/submit.php — submit feedback
$r = api_request('POST', '/feedback/submit.php', [
    'subject' => 'Test Feedback Subject',
    'message' => 'This is a test feedback message from the automated test suite.',
], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Feedback POST: valid subject+message returns 200 success');
$feedback_id = $r['body']['data']['id'] ?? null;
assert_test($feedback_id !== null,
    'Feedback POST: response includes the new feedback id');

// ════════════════════════════════════════════════════
// ANALYTICS — TRACK
// ════════════════════════════════════════════════════

$track = function ($action, $extra = []) use ($token) {
    return api_request('POST', '/analytics/track.php',
        array_merge(['action' => $action], $extra), $token);
};

// 7. page_view
$r = $track('page_view');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=page_view returns 200 success');

// 8. search with query
$r = $track('search', ['search_query' => 'test']);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=search returns 200 success');

// 9. add_to_cart
$r = $track('add_to_cart', ['target_id' => 1]);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=add_to_cart returns 200 success');

// 10. remove_from_cart
$r = $track('remove_from_cart', ['target_id' => 1]);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=remove_from_cart returns 200 success');

// 11. checkout_start
$r = $track('checkout_start');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=checkout_start returns 200 success');

// 12. cart_abandon
$r = $track('cart_abandon');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=cart_abandon returns 200 success');

// 13. product_view
$r = $track('product_view', ['target_id' => 1]);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Analytics track: action=product_view returns 200 success');

// 14. invalid action — should return error
$r = $track('invalid_action');
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Analytics track: action=invalid_action returns 400 error');

// ════════════════════════════════════════════════════
// CLEANUP
// ════════════════════════════════════════════════════

if ($user_id) {
    // Remove analytics rows for this user
    $pdo->prepare("DELETE FROM user_activity WHERE user_id = ?")->execute([$user_id]);

    if ($feedback_id) {
        $pdo->prepare("DELETE FROM feedback_messages WHERE id = ?")->execute([$feedback_id]);
    }

    // Wishlist should already be empty, but be safe
    $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?")->execute([$user_id]);

    $pdo->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
}

if (!isset($run_all)) {
    print_summary();
}
