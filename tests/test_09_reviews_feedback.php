<?php
// ═══════════════════════════════════════════════════
// Category 9: Reviews and Feedback
// Product ratings, customer reviews, feedback messages
// ═══════════════════════════════════════════════════

if (!isset($pdo)) { require_once __DIR__ . '/bootstrap.php'; }

echo "  --- Reviews Table ---\n";
assert_test(table_exists($pdo, 'reviews'), 'reviews table exists');
assert_test(column_exists($pdo, 'reviews', 'user_id'), 'reviews.user_id exists (customer)');
assert_test(column_exists($pdo, 'reviews', 'product_id'), 'reviews.product_id exists');
assert_test(column_exists($pdo, 'reviews', 'rating'), 'reviews.rating exists (product ratings)');
assert_test(column_exists($pdo, 'reviews', 'comment'), 'reviews.comment exists (customer reviews)');
assert_test(column_exists($pdo, 'reviews', 'is_approved'), 'reviews.is_approved exists (moderation)');
assert_test(column_exists($pdo, 'reviews', 'created_at'), 'reviews.created_at exists');

// Rating should be constrained (tinyint or int, used as 1-5)
$ratingType = column_type($pdo, 'reviews', 'rating');
assert_test(
    strpos($ratingType, 'tinyint') !== false || strpos($ratingType, 'int') !== false,
    "reviews.rating is integer type (got: $ratingType)"
);

// Unique constraint: one review per user per product
assert_test(
    has_composite_unique_key($pdo, 'reviews', ['user_id', 'product_id']),
    'reviews has UNIQUE constraint on (user_id, product_id)'
);

// Foreign keys
assert_test(has_foreign_key($pdo, 'reviews', 'user_id', 'users'), 'reviews.user_id FK -> users');
assert_test(has_foreign_key($pdo, 'reviews', 'product_id', 'products'), 'reviews.product_id FK -> products');

// Products track aggregate ratings
echo "\n  --- Product Rating Aggregates ---\n";
assert_test(column_exists($pdo, 'products', 'rating'), 'products.rating exists (aggregate rating)');
assert_test(column_exists($pdo, 'products', 'review_count'), 'products.review_count exists');

echo "\n  --- Feedback Messages ---\n";
assert_test(table_exists($pdo, 'feedback_messages'), 'feedback_messages table exists');
assert_test(column_exists($pdo, 'feedback_messages', 'user_id'), 'feedback_messages.user_id exists');
assert_test(column_exists($pdo, 'feedback_messages', 'subject'), 'feedback_messages.subject exists');
assert_test(column_exists($pdo, 'feedback_messages', 'message'), 'feedback_messages.message exists');
assert_test(column_exists($pdo, 'feedback_messages', 'status'), 'feedback_messages.status exists');
assert_test(column_exists($pdo, 'feedback_messages', 'admin_reply'), 'feedback_messages.admin_reply exists');
assert_test(column_exists($pdo, 'feedback_messages', 'created_at'), 'feedback_messages.created_at exists');

// Feedback status workflow
$fbStatuses = ['open', 'in_progress', 'resolved', 'closed'];
foreach ($fbStatuses as $s) {
    assert_test(
        enum_contains($pdo, 'feedback_messages', 'status', $s),
        "feedback_messages.status ENUM includes '$s'"
    );
}
