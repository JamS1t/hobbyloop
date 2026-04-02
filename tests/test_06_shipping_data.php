<?php
// ═══════════════════════════════════════════════════
// Category 6: Shipping & Delivery Data
// Courier/provider, shipping fees, delivery status,
// estimated delivery time
// ═══════════════════════════════════════════════════

if (!isset($pdo)) { require_once __DIR__ . '/bootstrap.php'; }

echo "  --- Shipments Table ---\n";
assert_test(table_exists($pdo, 'shipments'), 'shipments table exists');
assert_test(column_exists($pdo, 'shipments', 'order_id'), 'shipments.order_id exists');
assert_test(column_exists($pdo, 'shipments', 'courier'), 'shipments.courier exists (shipping provider)');
assert_test(column_exists($pdo, 'shipments', 'tracking_number'), 'shipments.tracking_number exists');
assert_test(column_exists($pdo, 'shipments', 'status'), 'shipments.status exists (delivery status)');
assert_test(column_exists($pdo, 'shipments', 'estimated_delivery'), 'shipments.estimated_delivery exists');
assert_test(column_exists($pdo, 'shipments', 'shipped_at'), 'shipments.shipped_at exists');
assert_test(column_exists($pdo, 'shipments', 'delivered_at'), 'shipments.delivered_at exists');

// Verify delivery status ENUM covers full pipeline
echo "\n  --- Delivery Status Pipeline ---\n";
$statuses = ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'returned'];
foreach ($statuses as $s) {
    assert_test(enum_contains($pdo, 'shipments', 'status', $s), "shipments.status ENUM includes '$s'");
}

// Foreign key to orders
assert_test(has_foreign_key($pdo, 'shipments', 'order_id', 'orders'), 'shipments.order_id FK -> orders');

echo "\n  --- Shipping Fee in Orders ---\n";
assert_test(column_exists($pdo, 'orders', 'shipping_fee'), 'orders.shipping_fee exists (shipping cost)');

// Verify shipping_fee is a decimal/numeric type
$feeType = column_type($pdo, 'orders', 'shipping_fee');
assert_test(strpos($feeType, 'decimal') !== false || strpos($feeType, 'float') !== false, "orders.shipping_fee is numeric type (got: $feeType)");
