<?php
// ═══════════════════════════════════════════
// HobbyLoop API — Database Configuration
// ═══════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_NAME', 'hobbyloop_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('TOKEN_EXPIRY_HOURS', 24);
define('SHIPPING_FEE', 280.00);
define('MULTI_ITEM_DISCOUNT_RATE', 0.03);
define('MULTI_ITEM_DISCOUNT_MIN', 2);

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}
