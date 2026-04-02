<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop API Test — Community Endpoints
// Tests: posts list, create post, like/unlike, follow/unfollow
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- API: Community ---\n";

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
$test_email = "test_community_{$ts}@test.com";

$reg = api_request('POST', '/auth/register.php', [
    'first_name' => 'Community',
    'last_name'  => 'Tester',
    'email'      => $test_email,
    'password'   => 'password123',
]);
$token   = $reg['body']['data']['token'] ?? null;
$user_id = $reg['body']['data']['user']['id'] ?? null;

assert_test($token !== null && $user_id !== null,
    'Community setup: test user registered and token obtained');

if (!$token) {
    echo "  [SKIP] Cannot run community tests — test user registration failed.\n";
    if (!isset($run_all)) print_summary();
    return;
}

// ── Find a seller to follow (must not be our test user) ─
$stmt = $pdo->prepare("
    SELECT u.id FROM sellers s
    JOIN users u ON u.id = s.user_id
    WHERE u.id != ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$seller_row  = $stmt->fetch();
$seller_user_id = $seller_row ? (int)$seller_row['id'] : null;

// ════════════════════════════════════════════════════
// POSTS LIST
// ════════════════════════════════════════════════════

// 1. GET /community/posts.php — should return posts and suggested sellers
$r = api_request('GET', '/community/posts.php', null, $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Community posts GET: returns 200 success');
$posts_data = $r['body']['data'] ?? [];
assert_test(isset($posts_data['posts']) && is_array($posts_data['posts']),
    'Community posts GET: response has posts array');
assert_test(isset($posts_data['suggested']) && is_array($posts_data['suggested']),
    'Community posts GET: response has suggested sellers array');

// ════════════════════════════════════════════════════
// CREATE POST
// ════════════════════════════════════════════════════

// 2. POST /community/posts.php — create a new post
$post_text = "Test post from automated test suite [{$ts}]";
$r = api_request('POST', '/community/posts.php', ['text' => $post_text], $token);
assert_test($r['status'] === 201 && $r['body']['success'] === true,
    'Community posts POST: create post returns 201 success');
$new_post_id = $r['body']['data']['id'] ?? null;
assert_test($new_post_id !== null,
    'Community posts POST: new post has an id in response');
assert_test(($r['body']['data']['text'] ?? '') === $post_text,
    'Community posts POST: returned post text matches submitted text');

// ════════════════════════════════════════════════════
// LIKES (TOGGLE)
// ════════════════════════════════════════════════════

if ($new_post_id) {
    // 3. POST /community/like.php — like the new post
    $r = api_request('POST', '/community/like.php', ['post_id' => $new_post_id], $token);
    assert_test($r['status'] === 200 && $r['body']['success'] === true,
        'Community like: first POST toggles like on (success)');
    assert_test(($r['body']['data']['liked'] ?? null) === true,
        'Community like: liked=true after first toggle');
    $likes_after_like = $r['body']['data']['likes_count'] ?? 0;
    assert_test($likes_after_like >= 1,
        'Community like: likes_count is at least 1 after liking');

    // 4. POST /community/like.php again — should unlike (toggle off)
    $r = api_request('POST', '/community/like.php', ['post_id' => $new_post_id], $token);
    assert_test($r['status'] === 200 && $r['body']['success'] === true,
        'Community unlike: second POST toggles like off (success)');
    assert_test(($r['body']['data']['liked'] ?? null) === false,
        'Community unlike: liked=false after second toggle');
    assert_test(($r['body']['data']['likes_count'] ?? -1) < $likes_after_like,
        'Community unlike: likes_count decreased after unliking');
} else {
    assert_test(false, 'Community like: skipped — no post_id from create step');
    assert_test(false, 'Community like: skipped');
    assert_test(false, 'Community unlike: skipped');
    assert_test(false, 'Community unlike: skipped');
}

// ════════════════════════════════════════════════════
// FOLLOWS (TOGGLE)
// ════════════════════════════════════════════════════

if ($seller_user_id) {
    // 5. POST /community/follow.php — follow a seller
    $r = api_request('POST', '/community/follow.php', ['user_id' => $seller_user_id], $token);
    assert_test($r['status'] === 200 && $r['body']['success'] === true,
        'Community follow: POST follow seller returns 200 success');
    assert_test(($r['body']['data']['following'] ?? null) === true,
        'Community follow: following=true after first toggle');

    // 6. POST /community/follow.php again — should unfollow (toggle)
    $r = api_request('POST', '/community/follow.php', ['user_id' => $seller_user_id], $token);
    assert_test($r['status'] === 200 && $r['body']['success'] === true,
        'Community unfollow: second POST unfollow seller returns 200 success');
    assert_test(($r['body']['data']['following'] ?? null) === false,
        'Community unfollow: following=false after second toggle');
} else {
    echo "  [SKIP] No seller found in DB to test follow/unfollow.\n";
    assert_test(true, 'Community follow: skipped — no seller available');
    assert_test(true, 'Community unfollow: skipped — no seller available');
}

// ════════════════════════════════════════════════════
// CLEANUP
// ════════════════════════════════════════════════════

if ($user_id) {
    if ($new_post_id) {
        $pdo->prepare("DELETE FROM post_likes WHERE post_id = ?")->execute([$new_post_id]);
        $pdo->prepare("DELETE FROM community_posts WHERE id = ? AND user_id = ?")->execute([$new_post_id, $user_id]);
    }
    if ($seller_user_id) {
        $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?")->execute([$user_id, $seller_user_id]);
    }
    $pdo->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
}

if (!isset($run_all)) {
    print_summary();
}
