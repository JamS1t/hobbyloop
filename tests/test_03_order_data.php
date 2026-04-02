<?php
// ═══════════════════════════════════════════════════════
// Test 03 — Category 3: Order Data
// Verifies orders, order_items, shipments tables
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- 3. Order Data ---\n";

// ── orders table existence ────────────────────────────
assert_test(table_exists($pdo, 'orders'), 'Table `orders` exists');

// ── orders core columns ───────────────────────────────
assert_test(column_exists($pdo, 'orders', 'id'),               'orders.id (order ID PK) column exists');
assert_test(column_exists($pdo, 'orders', 'order_number'),     'orders.order_number column exists');
assert_test(column_exists($pdo, 'orders', 'user_id'),          'orders.user_id (customer ID) column exists');
assert_test(column_exists($pdo, 'orders', 'created_at'),       'orders.created_at (order date) column exists');
assert_test(column_exists($pdo, 'orders', 'total'),            'orders.total (total amount) column exists');
assert_test(column_exists($pdo, 'orders', 'status'),           'orders.status column exists');
assert_test(column_exists($pdo, 'orders', 'shipping_name'),    'orders.shipping_name column exists');
assert_test(column_exists($pdo, 'orders', 'shipping_address'), 'orders.shipping_address column exists');
assert_test(column_exists($pdo, 'orders', 'shipping_city'),    'orders.shipping_city column exists');
assert_test(column_exists($pdo, 'orders', 'shipping_zip'),     'orders.shipping_zip column exists');

// ── orders constraints ────────────────────────────────
assert_test(has_unique_key($pdo, 'orders', 'order_number'), 'orders.order_number has UNIQUE constraint');
assert_test(has_foreign_key($pdo, 'orders', 'user_id', 'users'), 'orders.user_id FK references users');

$total_nullable = column_is_nullable($pdo, 'orders', 'total');
assert_test(!$total_nullable, 'orders.total is NOT NULL');

// ── orders.status ENUM values ─────────────────────────
$status_type = column_type($pdo, 'orders', 'status');
assert_test(stripos($status_type, 'enum') !== false, 'orders.status is ENUM type');
// Schema uses capitalised values matching the frontend
foreach (['Processing', 'Shipped', 'Delivered', 'Cancelled'] as $val) {
    assert_test(
        strpos($status_type, "'$val'") !== false,
        "orders.status ENUM contains '$val'"
    );
}

// ── order_items table ─────────────────────────────────
assert_test(table_exists($pdo, 'order_items'), 'Table `order_items` exists');
assert_test(column_exists($pdo, 'order_items', 'order_id'),      'order_items.order_id column exists');
assert_test(column_exists($pdo, 'order_items', 'product_id'),    'order_items.product_id column exists');
assert_test(column_exists($pdo, 'order_items', 'product_name'),  'order_items.product_name column exists');
assert_test(column_exists($pdo, 'order_items', 'price'),         'order_items.price column exists');
assert_test(column_exists($pdo, 'order_items', 'qty'),           'order_items.qty column exists');

assert_test(has_foreign_key($pdo, 'order_items', 'order_id',   'orders'),   'order_items.order_id FK references orders');
assert_test(has_foreign_key($pdo, 'order_items', 'product_id', 'products'), 'order_items.product_id FK references products');

$item_qty_nullable = column_is_nullable($pdo, 'order_items', 'qty');
assert_test(!$item_qty_nullable, 'order_items.qty is NOT NULL');

$item_price_nullable = column_is_nullable($pdo, 'order_items', 'price');
assert_test(!$item_price_nullable, 'order_items.price is NOT NULL');

// ── shipments table ───────────────────────────────────
assert_test(table_exists($pdo, 'shipments'), 'Table `shipments` exists');
assert_test(column_exists($pdo, 'shipments', 'tracking_number'), 'shipments.tracking_number column exists');
assert_test(has_foreign_key($pdo, 'shipments', 'order_id', 'orders'), 'shipments.order_id FK references orders');

if (!isset($run_all)) {
    print_summary();
}
