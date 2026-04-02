<?php
// ═══════════════════════════════════════════════════════
// Test 05 — Category 5: Inventory Data
// Verifies stock levels, inventory_log, suppliers,
// and product_suppliers tables
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- 5. Inventory Data ---\n";

// ── products.stock_qty (stock levels) ────────────────
assert_test(column_exists($pdo, 'products', 'stock_qty'), 'products.stock_qty exists (stock level tracking)');

$stock_type = column_type($pdo, 'products', 'stock_qty');
assert_test(stripos($stock_type, 'int') !== false, 'products.stock_qty is INT type');

// Verify no negative stock in seeded data
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty < 0");
$neg_stock = (int) $stmt->fetchColumn();
assert_test($neg_stock === 0, 'No seeded products have negative stock_qty');

// ── inventory_log table ───────────────────────────────
assert_test(table_exists($pdo, 'inventory_log'), 'Table `inventory_log` exists');
assert_test(column_exists($pdo, 'inventory_log', 'product_id'),   'inventory_log.product_id column exists');
assert_test(column_exists($pdo, 'inventory_log', 'change_qty'),   'inventory_log.change_qty column exists');
assert_test(column_exists($pdo, 'inventory_log', 'reason'),       'inventory_log.reason column exists');
assert_test(column_exists($pdo, 'inventory_log', 'reference_id'), 'inventory_log.reference_id column exists');
assert_test(column_exists($pdo, 'inventory_log', 'notes'),        'inventory_log.notes column exists');

// ── inventory_log.reason ENUM ─────────────────────────
$reason_type = column_type($pdo, 'inventory_log', 'reason');
assert_test(stripos($reason_type, 'enum') !== false, 'inventory_log.reason is ENUM type');
foreach (['sale', 'restock', 'adjustment', 'return'] as $val) {
    assert_test(
        strpos($reason_type, "'$val'") !== false,
        "inventory_log.reason ENUM contains '$val'"
    );
}

$reason_nullable = column_is_nullable($pdo, 'inventory_log', 'reason');
assert_test(!$reason_nullable, 'inventory_log.reason is NOT NULL');

assert_test(has_foreign_key($pdo, 'inventory_log', 'product_id', 'products'), 'inventory_log.product_id FK references products');

// ── suppliers table ───────────────────────────────────
assert_test(table_exists($pdo, 'suppliers'), 'Table `suppliers` exists');
assert_test(column_exists($pdo, 'suppliers', 'name'),          'suppliers.name column exists');
assert_test(column_exists($pdo, 'suppliers', 'contact_email'), 'suppliers.contact_email column exists');
assert_test(column_exists($pdo, 'suppliers', 'contact_phone'), 'suppliers.contact_phone column exists');
assert_test(column_exists($pdo, 'suppliers', 'address'),       'suppliers.address column exists');

$sup_name_nullable = column_is_nullable($pdo, 'suppliers', 'name');
assert_test(!$sup_name_nullable, 'suppliers.name is NOT NULL');

$sup_count = table_row_count($pdo, 'suppliers');
assert_test($sup_count >= 3, "suppliers table has seeded rows ($sup_count found)");

// ── product_suppliers table ───────────────────────────
assert_test(table_exists($pdo, 'product_suppliers'), 'Table `product_suppliers` exists');
assert_test(column_exists($pdo, 'product_suppliers', 'product_id'),     'product_suppliers.product_id column exists');
assert_test(column_exists($pdo, 'product_suppliers', 'supplier_id'),    'product_suppliers.supplier_id column exists');
assert_test(column_exists($pdo, 'product_suppliers', 'reorder_level'),  'product_suppliers.reorder_level column exists');
assert_test(column_exists($pdo, 'product_suppliers', 'lead_time_days'), 'product_suppliers.lead_time_days column exists');

assert_test(has_foreign_key($pdo, 'product_suppliers', 'product_id',  'products'),  'product_suppliers.product_id FK references products');
assert_test(has_foreign_key($pdo, 'product_suppliers', 'supplier_id', 'suppliers'), 'product_suppliers.supplier_id FK references suppliers');

$ps_count = table_row_count($pdo, 'product_suppliers');
assert_test($ps_count >= 12, "product_suppliers table has seeded rows ($ps_count found)");

if (!isset($run_all)) {
    print_summary();
}
