<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop Requirements Audit — Master Test Runner
// Tests all 10 data categories from the requirements
// ═══════════════════════════════════════════════════════

echo "\n";
echo "+====================================================+\n";
echo "|   HobbyLoop Requirements Audit - Test Suite         |\n";
echo "|   Testing all 10 data categories + 5 API suites     |\n";
echo "+====================================================+\n\n";

require_once __DIR__ . '/bootstrap.php';

$test_files = [
    'test_01_customer_data.php'    => '1. Customer Data',
    'test_02_product_data.php'     => '2. Product Data',
    'test_03_order_data.php'       => '3. Order Data',
    'test_04_payment_data.php'     => '4. Payment Data',
    'test_05_inventory_data.php'   => '5. Inventory Data',
    'test_06_shipping_data.php'    => '6. Shipping & Delivery Data',
    'test_07_analytics_data.php'   => '7. User Activity (Analytics)',
    'test_08_admin_data.php'       => '8. Admin/Management Data',
    'test_09_reviews_feedback.php' => '9. Reviews & Feedback',
    'test_10_promotions.php'       => '10. Promotions & Discounts',
    // ── API Integration & Auth Security Suites ─────────
    'test_api_auth.php'            => 'API: Auth & Security',
    'test_api_products.php'        => 'API: Products & Catalog',
    'test_api_cart_checkout.php'   => 'API: Cart & Checkout',
    'test_api_community.php'       => 'API: Community',
    'test_api_misc.php'            => 'API: Wishlist / Notifications / Feedback / Analytics',
];

$category_results = [];
$grand_pass = 0;
$grand_fail = 0;

foreach ($test_files as $file => $label) {
    $filePath = __DIR__ . '/' . $file;
    if (!file_exists($filePath)) {
        echo "\n  [SKIP] $label — file not found: $file\n";
        continue;
    }

    echo "\n" . str_repeat('-', 50) . "\n";
    echo "  $label\n";
    echo str_repeat('-', 50) . "\n";

    // Reset counters before each file
    $before_pass = $test_pass;
    $before_fail = $test_fail;

    include $filePath;

    $file_pass = $test_pass - $before_pass;
    $file_fail = $test_fail - $before_fail;
    $file_total = $file_pass + $file_fail;

    $status = $file_fail === 0 ? 'ALL PASS' : "$file_fail FAILED";
    echo "\n  >> $label: $file_pass/$file_total ($status)\n";

    $category_results[$label] = [
        'pass' => $file_pass,
        'fail' => $file_fail,
        'total' => $file_total,
    ];
}

$grand_pass = $test_pass;
$grand_fail = $test_fail;
$grand_total = $grand_pass + $grand_fail;

// Summary
echo "\n\n";
echo "+====================================================+\n";
echo "|                  AUDIT SUMMARY                      |\n";
echo "+====================================================+\n\n";

foreach ($category_results as $label => $r) {
    $icon = $r['fail'] === 0 ? '[OK]' : '[!!]';
    printf("  %s %-38s %d/%d\n", $icon, $label, $r['pass'], $r['total']);
}

echo "\n" . str_repeat('=', 52) . "\n";
printf("  GRAND TOTAL: %d/%d passed", $grand_pass, $grand_total);
if ($grand_fail > 0) {
    echo " — $grand_fail FAILED";
}
echo "\n" . str_repeat('=', 52) . "\n";

if ($grand_fail === 0) {
    echo "\n  All requirements verified! System is aligned.\n\n";
} else {
    echo "\n  Some requirements are NOT met. Review FAIL items above.\n\n";
}
