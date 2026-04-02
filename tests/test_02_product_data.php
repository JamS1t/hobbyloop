<?php
// ═══════════════════════════════════════════════════════
// Test 02 — Category 2: Product Data
// Verifies products, categories, images, variants tables
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- 2. Product Data ---\n";

// ── products table existence ──────────────────────────
assert_test(table_exists($pdo, 'products'), 'Table `products` exists');

// ── products core columns ─────────────────────────────
assert_test(column_exists($pdo, 'products', 'name'),        'products.name column exists');
assert_test(column_exists($pdo, 'products', 'description'), 'products.description column exists');
assert_test(column_exists($pdo, 'products', 'category_id'), 'products.category_id column exists');
assert_test(column_exists($pdo, 'products', 'price'),       'products.price column exists');
assert_test(column_exists($pdo, 'products', 'stock_qty'),   'products.stock_qty column exists');
assert_test(column_exists($pdo, 'products', 'sku'),         'products.sku column exists');
assert_test(column_exists($pdo, 'products', 'brand'),       'products.brand column exists');
assert_test(column_exists($pdo, 'products', 'image_url'),   'products.image_url column exists');

// ── products column types ─────────────────────────────
$price_type = column_type($pdo, 'products', 'price');
assert_test(stripos($price_type, 'decimal') !== false, 'products.price is DECIMAL type');

$stock_type = column_type($pdo, 'products', 'stock_qty');
assert_test(stripos($stock_type, 'int') !== false, 'products.stock_qty is INT type');

$name_nullable = column_is_nullable($pdo, 'products', 'name');
assert_test(!$name_nullable, 'products.name is NOT NULL');

$price_nullable = column_is_nullable($pdo, 'products', 'price');
assert_test(!$price_nullable, 'products.price is NOT NULL');

// ── products FK to categories and users ───────────────
assert_test(has_foreign_key($pdo, 'products', 'category_id', 'categories'), 'products.category_id FK references categories');
assert_test(has_foreign_key($pdo, 'products', 'seller_id',   'users'),      'products.seller_id FK references users');

// ── categories table ──────────────────────────────────
assert_test(table_exists($pdo, 'categories'), 'Table `categories` exists');
assert_test(column_exists($pdo, 'categories', 'id'),    'categories.id column exists');
assert_test(column_exists($pdo, 'categories', 'label'), 'categories.label column exists');

$cat_count = table_row_count($pdo, 'categories');
assert_test($cat_count > 0, "categories table has seeded rows ($cat_count found)");

// Verify expected hobby categories exist
$expected_cats = ['creative', 'outdoor', 'stem', 'sports', 'culinary', 'gaming', 'collecting'];
$stmt = $pdo->query("SELECT id FROM categories");
$seeded_cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($expected_cats as $cat) {
    assert_test(in_array($cat, $seeded_cats), "Category '$cat' exists in categories seed data");
}

// ── product_images table ──────────────────────────────
assert_test(table_exists($pdo, 'product_images'), 'Table `product_images` exists');
assert_test(column_exists($pdo, 'product_images', 'product_id'),  'product_images.product_id column exists');
assert_test(column_exists($pdo, 'product_images', 'image_url'),   'product_images.image_url column exists');
assert_test(column_exists($pdo, 'product_images', 'sort_order'),  'product_images.sort_order column exists');
assert_test(has_foreign_key($pdo, 'product_images', 'product_id', 'products'), 'product_images.product_id FK references products');

// ── product_variants table ────────────────────────────
assert_test(table_exists($pdo, 'product_variants'), 'Table `product_variants` exists');
assert_test(column_exists($pdo, 'product_variants', 'product_id'),     'product_variants.product_id column exists');
assert_test(column_exists($pdo, 'product_variants', 'variant_name'),   'product_variants.variant_name column exists');
assert_test(column_exists($pdo, 'product_variants', 'variant_value'),  'product_variants.variant_value column exists');
assert_test(column_exists($pdo, 'product_variants', 'price_modifier'), 'product_variants.price_modifier column exists');
assert_test(column_exists($pdo, 'product_variants', 'stock_qty'),      'product_variants.stock_qty column exists');
assert_test(has_foreign_key($pdo, 'product_variants', 'product_id', 'products'), 'product_variants.product_id FK references products');

// ── Seed data ─────────────────────────────────────────
$prod_count = table_row_count($pdo, 'products');
assert_test($prod_count >= 30, "products table has at least 30 seeded rows ($prod_count found)");

// Verify all prices are positive
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE price <= 0");
$bad_prices = (int) $stmt->fetchColumn();
assert_test($bad_prices === 0, 'All seeded products have positive price');

// Verify all seeded products have a stock_qty
$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_qty IS NULL");
$null_stock = (int) $stmt->fetchColumn();
assert_test($null_stock === 0, 'All seeded products have a non-NULL stock_qty');

if (!isset($run_all)) {
    print_summary();
}
