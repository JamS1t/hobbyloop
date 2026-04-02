<?php
// ═══════════════════════════════════════════════════
// Category 10: Promotions and Discounts
// Coupon codes, discount percentages, validity dates,
// campaign performance
// ═══════════════════════════════════════════════════

if (!isset($pdo)) { require_once __DIR__ . '/bootstrap.php'; }

echo "  --- promo_codes Table ---\n";
assert_test(table_exists($pdo, 'promo_codes'), 'promo_codes table exists');
assert_test(column_exists($pdo, 'promo_codes', 'code'), 'promo_codes.code exists (coupon codes)');
assert_test(has_unique_key($pdo, 'promo_codes', 'code'), 'promo_codes.code is UNIQUE');
assert_test(column_exists($pdo, 'promo_codes', 'discount_type'), 'promo_codes.discount_type exists');
assert_test(
    enum_contains_all($pdo, 'promo_codes', 'discount_type', ['percent', 'fixed']),
    "discount_type ENUM has 'percent' and 'fixed'"
);
assert_test(column_exists($pdo, 'promo_codes', 'discount_value'), 'promo_codes.discount_value exists (discount amount/percentage)');
assert_test(column_exists($pdo, 'promo_codes', 'min_order'), 'promo_codes.min_order exists');
assert_test(column_exists($pdo, 'promo_codes', 'max_uses'), 'promo_codes.max_uses exists');
assert_test(column_exists($pdo, 'promo_codes', 'used_count'), 'promo_codes.used_count exists (campaign performance)');
assert_test(column_exists($pdo, 'promo_codes', 'valid_from'), 'promo_codes.valid_from exists (promo start date)');
assert_test(column_exists($pdo, 'promo_codes', 'expires_at'), 'promo_codes.expires_at exists (promo end date)');
assert_test(column_exists($pdo, 'promo_codes', 'is_active'), 'promo_codes.is_active exists');

// Verify seed promo codes exist
$promoCount = table_row_count($pdo, 'promo_codes');
assert_test($promoCount > 0, "Promo codes seeded (found: $promoCount)");

echo "\n  --- promo_usage Table (Campaign Tracking) ---\n";
assert_test(table_exists($pdo, 'promo_usage'), 'promo_usage table exists');
assert_test(column_exists($pdo, 'promo_usage', 'promo_id'), 'promo_usage.promo_id exists');
assert_test(column_exists($pdo, 'promo_usage', 'user_id'), 'promo_usage.user_id exists');
assert_test(column_exists($pdo, 'promo_usage', 'order_id'), 'promo_usage.order_id exists');
assert_test(column_exists($pdo, 'promo_usage', 'created_at'), 'promo_usage.created_at exists');

// Unique constraint: one promo use per user
assert_test(
    has_composite_unique_key($pdo, 'promo_usage', ['promo_id', 'user_id']),
    'promo_usage has UNIQUE constraint on (promo_id, user_id) — prevents double-use'
);

// Foreign keys
assert_test(has_foreign_key($pdo, 'promo_usage', 'promo_id', 'promo_codes'), 'promo_usage.promo_id FK -> promo_codes');
assert_test(has_foreign_key($pdo, 'promo_usage', 'user_id', 'users'), 'promo_usage.user_id FK -> users');
assert_test(has_foreign_key($pdo, 'promo_usage', 'order_id', 'orders'), 'promo_usage.order_id FK -> orders');
