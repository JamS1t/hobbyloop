<?php
// ═══════════════════════════════════════════════════
// Category 8: Admin/Management Data
// Admin accounts, roles & permissions, system logs,
// reports (sales, revenue, inventory)
// ═══════════════════════════════════════════════════

if (!isset($pdo)) { require_once __DIR__ . '/bootstrap.php'; }

echo "  --- Admin Accounts & Roles ---\n";
assert_test(column_exists($pdo, 'users', 'role'), 'users.role exists');
assert_test(enum_contains($pdo, 'users', 'role', 'admin'), "users.role ENUM includes 'admin'");
assert_test(column_exists($pdo, 'users', 'admin_level'), 'users.admin_level exists (permissions)');
assert_test(
    enum_contains_all($pdo, 'users', 'admin_level', ['super', 'editor', 'viewer']),
    "admin_level ENUM has super/editor/viewer roles"
);

// Verify at least one admin user exists in seed data
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$adminCount = (int) $stmt->fetchColumn();
assert_test($adminCount > 0, "At least one admin user exists in seed data (found: $adminCount)");

// Verify admin has admin_level set
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND admin_level IS NOT NULL");
$adminWithLevel = (int) $stmt->fetchColumn();
assert_test($adminWithLevel > 0, 'Admin users have admin_level set');

echo "\n  --- System Logs ---\n";
assert_test(table_exists($pdo, 'system_logs'), 'system_logs table exists');
assert_test(column_exists($pdo, 'system_logs', 'admin_id'), 'system_logs.admin_id exists');
assert_test(column_exists($pdo, 'system_logs', 'action'), 'system_logs.action exists');
assert_test(column_exists($pdo, 'system_logs', 'entity_type'), 'system_logs.entity_type exists');
assert_test(column_exists($pdo, 'system_logs', 'entity_id'), 'system_logs.entity_id exists');
assert_test(column_exists($pdo, 'system_logs', 'details'), 'system_logs.details exists');
assert_test(column_exists($pdo, 'system_logs', 'ip_address'), 'system_logs.ip_address exists');
assert_test(column_exists($pdo, 'system_logs', 'created_at'), 'system_logs.created_at exists');
assert_test(has_foreign_key($pdo, 'system_logs', 'admin_id', 'users'), 'system_logs.admin_id FK -> users');

echo "\n  --- Reports Data Availability ---\n";
// Sales reports require: orders with totals
assert_test(column_exists($pdo, 'orders', 'total'), 'orders.total exists (for sales reports)');
assert_test(column_exists($pdo, 'orders', 'created_at'), 'orders.created_at exists (for date-range reports)');

// Revenue reports require: order_items with prices
assert_test(column_exists($pdo, 'order_items', 'price'), 'order_items.price exists (for revenue reports)');
assert_test(column_exists($pdo, 'order_items', 'qty'), 'order_items.qty exists (for revenue reports)');

// Inventory reports require: stock + reorder levels
assert_test(column_exists($pdo, 'products', 'stock_qty'), 'products.stock_qty exists (for inventory reports)');
assert_test(column_exists($pdo, 'product_suppliers', 'reorder_level'), 'product_suppliers.reorder_level exists (for restock alerts)');

// Admin reports page exists
$reportsFile = __DIR__ . '/../admin/reports.php';
assert_test(file_exists($reportsFile), 'admin/reports.php file exists');
