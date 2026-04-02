<?php
// ═══════════════════════════════════════════════════════
// Test 01 — Category 1: Customer Data
// Verifies users, addresses, sessions, wishlist, orders
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- 1. Customer Data ---\n";

// ── users table existence ──────────────────────────────
assert_test(table_exists($pdo, 'users'), 'Table `users` exists');

// ── users core columns ────────────────────────────────
assert_test(column_exists($pdo, 'users', 'first_name'),    'users.first_name column exists');
assert_test(column_exists($pdo, 'users', 'last_name'),     'users.last_name column exists');
assert_test(column_exists($pdo, 'users', 'email'),         'users.email column exists');
assert_test(column_exists($pdo, 'users', 'password_hash'), 'users.password_hash column exists');
assert_test(column_exists($pdo, 'users', 'phone'),         'users.phone column exists');
assert_test(column_exists($pdo, 'users', 'username'),      'users.username column exists');

// ── users column types / constraints ─────────────────
$pw_type = column_type($pdo, 'users', 'password_hash');
assert_test(stripos($pw_type, 'varchar(255)') !== false, 'users.password_hash is VARCHAR(255) (bcrypt-ready)');

assert_test(has_unique_key($pdo, 'users', 'email'),    'users.email has UNIQUE constraint');
assert_test(has_unique_key($pdo, 'users', 'username'), 'users.username has UNIQUE constraint');
assert_test(column_is_nullable($pdo, 'users', 'username'), 'users.username is nullable (optional handle)');

$email_nullable = column_is_nullable($pdo, 'users', 'email');
assert_test(!$email_nullable, 'users.email is NOT NULL');

$pw_nullable = column_is_nullable($pdo, 'users', 'password_hash');
assert_test(!$pw_nullable, 'users.password_hash is NOT NULL');

// ── user_addresses table ──────────────────────────────
assert_test(table_exists($pdo, 'user_addresses'), 'Table `user_addresses` exists');
assert_test(column_exists($pdo, 'user_addresses', 'street'),       'user_addresses.street column exists');
assert_test(column_exists($pdo, 'user_addresses', 'city'),         'user_addresses.city column exists');
assert_test(column_exists($pdo, 'user_addresses', 'province'),     'user_addresses.province column exists');
assert_test(column_exists($pdo, 'user_addresses', 'zip'),          'user_addresses.zip column exists');
assert_test(column_exists($pdo, 'user_addresses', 'is_default'),   'user_addresses.is_default column exists');
assert_test(column_exists($pdo, 'user_addresses', 'address_type'), 'user_addresses.address_type column exists');

$addr_type = column_type($pdo, 'user_addresses', 'address_type');
assert_test(
    strpos($addr_type, "'shipping'") !== false && strpos($addr_type, "'billing'") !== false,
    "user_addresses.address_type ENUM contains 'shipping' and 'billing'"
);

assert_test(has_foreign_key($pdo, 'user_addresses', 'user_id', 'users'), 'user_addresses.user_id FK references users');

// ── sessions table ────────────────────────────────────
assert_test(table_exists($pdo, 'sessions'), 'Table `sessions` exists');
assert_test(column_exists($pdo, 'sessions', 'token'),      'sessions.token column exists');
assert_test(column_exists($pdo, 'sessions', 'expires_at'), 'sessions.expires_at column exists');
assert_test(has_unique_key($pdo, 'sessions', 'token'),     'sessions.token has UNIQUE constraint');
assert_test(has_foreign_key($pdo, 'sessions', 'user_id', 'users'), 'sessions.user_id FK references users');

// ── wishlist table ────────────────────────────────────
assert_test(table_exists($pdo, 'wishlist'), 'Table `wishlist` exists (preferences/saved items)');
assert_test(column_exists($pdo, 'wishlist', 'user_id'),    'wishlist.user_id column exists');
assert_test(column_exists($pdo, 'wishlist', 'product_id'), 'wishlist.product_id column exists');

// ── orders table (purchase history hook) ─────────────
assert_test(table_exists($pdo, 'orders'), 'Table `orders` exists (purchase history)');

// ── Seed data: verify bcrypt hash prefix ─────────────
$stmt = $pdo->query("SELECT password_hash FROM users LIMIT 7");
$hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);
$all_bcrypt = true;
foreach ($hashes as $hash) {
    if (substr($hash, 0, 4) !== '$2y$') {
        $all_bcrypt = false;
        break;
    }
}
assert_test(count($hashes) > 0, 'users table has seeded rows');
assert_test($all_bcrypt, 'All seeded password_hash values start with $2y$ (bcrypt)');

if (!isset($run_all)) {
    print_summary();
}
