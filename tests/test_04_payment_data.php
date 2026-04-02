<?php
// ═══════════════════════════════════════════════════════
// Test 04 — Category 4: Payment Data
// Verifies payments table structure and ENUM values
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- 4. Payment Data ---\n";

// ── payments table existence ──────────────────────────
assert_test(table_exists($pdo, 'payments'), 'Table `payments` exists');

// ── payments core columns ─────────────────────────────
assert_test(column_exists($pdo, 'payments', 'method'),          'payments.method column exists');
assert_test(column_exists($pdo, 'payments', 'transaction_id'),  'payments.transaction_id column exists');
assert_test(column_exists($pdo, 'payments', 'status'),          'payments.status column exists');
assert_test(column_exists($pdo, 'payments', 'billing_name'),    'payments.billing_name column exists');
assert_test(column_exists($pdo, 'payments', 'billing_address'), 'payments.billing_address column exists');
assert_test(column_exists($pdo, 'payments', 'billing_city'),    'payments.billing_city column exists');
assert_test(column_exists($pdo, 'payments', 'billing_zip'),     'payments.billing_zip column exists');
assert_test(column_exists($pdo, 'payments', 'amount'),          'payments.amount column exists');

// ── payments.method ENUM ──────────────────────────────
$method_type = column_type($pdo, 'payments', 'method');
assert_test(stripos($method_type, 'enum') !== false, 'payments.method is ENUM type');
foreach (['card', 'gcash', 'bank', 'cod'] as $val) {
    assert_test(
        strpos($method_type, "'$val'") !== false,
        "payments.method ENUM contains '$val'"
    );
}

// ── payments.status ENUM — all 4 required values ──────
$status_type = column_type($pdo, 'payments', 'status');
assert_test(stripos($status_type, 'enum') !== false, 'payments.status is ENUM type');
$required_statuses = ['pending', 'completed', 'failed', 'refunded'];
foreach ($required_statuses as $val) {
    assert_test(
        strpos($status_type, "'$val'") !== false,
        "payments.status ENUM contains '$val'"
    );
}

// ── payments column types ─────────────────────────────
$amount_type = column_type($pdo, 'payments', 'amount');
assert_test(stripos($amount_type, 'decimal') !== false, 'payments.amount is DECIMAL type');

$amount_nullable = column_is_nullable($pdo, 'payments', 'amount');
assert_test(!$amount_nullable, 'payments.amount is NOT NULL');

$method_nullable = column_is_nullable($pdo, 'payments', 'method');
assert_test(!$method_nullable, 'payments.method is NOT NULL');

// ── payments FK ───────────────────────────────────────
assert_test(has_foreign_key($pdo, 'payments', 'order_id', 'orders'), 'payments.order_id FK references orders');

if (!isset($run_all)) {
    print_summary();
}
