<?php
// ═══════════════════════════════════════════════════
// Category 7: User Activity Data (Analytics)
// Browsing behavior, search queries, clicks/views,
// cart additions & abandoned carts, login/logout
// ═══════════════════════════════════════════════════

if (!isset($pdo)) { require_once __DIR__ . '/bootstrap.php'; }

echo "  --- user_activity Table ---\n";
assert_test(table_exists($pdo, 'user_activity'), 'user_activity table exists');
assert_test(column_exists($pdo, 'user_activity', 'user_id'), 'user_activity.user_id exists');
assert_test(column_exists($pdo, 'user_activity', 'session_token'), 'user_activity.session_token exists');
assert_test(column_exists($pdo, 'user_activity', 'action'), 'user_activity.action exists');
assert_test(column_exists($pdo, 'user_activity', 'target_id'), 'user_activity.target_id exists');
assert_test(column_exists($pdo, 'user_activity', 'search_query'), 'user_activity.search_query exists (search queries)');
assert_test(column_exists($pdo, 'user_activity', 'metadata'), 'user_activity.metadata exists (JSON details)');
assert_test(column_exists($pdo, 'user_activity', 'ip_address'), 'user_activity.ip_address exists');
assert_test(column_exists($pdo, 'user_activity', 'user_agent'), 'user_activity.user_agent exists');
assert_test(column_exists($pdo, 'user_activity', 'created_at'), 'user_activity.created_at exists');

echo "\n  --- Action ENUM Coverage ---\n";
$required_actions = [
    'page_view'         => 'browsing behavior',
    'product_view'      => 'clicks and views',
    'search'            => 'search queries',
    'add_to_cart'       => 'cart additions',
    'remove_from_cart'  => 'cart modifications',
    'checkout_start'    => 'checkout tracking',
    'checkout_complete' => 'purchase completion',
    'login'             => 'login activity',
    'logout'            => 'logout activity',
    'cart_abandon'      => 'abandoned carts',
];

foreach ($required_actions as $action => $purpose) {
    assert_test(
        enum_contains($pdo, 'user_activity', 'action', $action),
        "action ENUM includes '$action' ($purpose)"
    );
}
