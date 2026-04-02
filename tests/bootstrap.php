<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop Test Suite — Bootstrap
// Connects to hobbyloop_db and defines all test helpers.
// ═══════════════════════════════════════════════════════

$host = 'localhost';
$db   = 'hobbyloop_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage() . "\n");
}

$test_pass = 0;
$test_fail = 0;
$test_results = [];

function assert_test($condition, $description) {
    global $test_pass, $test_fail, $test_results;
    if ($condition) {
        $test_pass++;
        $test_results[] = "[PASS] $description";
        echo "  [PASS] $description\n";
    } else {
        $test_fail++;
        $test_results[] = "[FAIL] $description";
        echo "  [FAIL] $description\n";
    }
}

function column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'hobbyloop_db' AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() > 0;
}

function table_exists($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'hobbyloop_db' AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return $stmt->fetchColumn() > 0;
}

function column_type($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'hobbyloop_db' AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn();
}

function column_is_nullable($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'hobbyloop_db' AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() === 'YES';
}

function has_unique_key($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'hobbyloop_db' AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0");
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() > 0;
}

function has_foreign_key($pdo, $table, $column, $ref_table = null) {
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'hobbyloop_db'
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $params = [$table, $column];
    if ($ref_table !== null) {
        $sql .= " AND REFERENCED_TABLE_NAME = ?";
        $params[] = $ref_table;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function enum_contains($pdo, $table, $column, $value) {
    $type = column_type($pdo, $table, $column);
    // COLUMN_TYPE looks like: enum('val1','val2',...)
    return strpos($type, "'$value'") !== false;
}

function enum_contains_all($pdo, $table, $column, array $values) {
    $type = column_type($pdo, $table, $column);
    foreach ($values as $v) {
        if (strpos($type, "'$v'") === false) {
            return false;
        }
    }
    return true;
}

function table_row_count($pdo, $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
    return (int) $stmt->fetchColumn();
}

function has_composite_unique_key($pdo, $table, array $columns) {
    // Find an index that is UNIQUE and covers exactly these columns.
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $pdo->prepare(
        "SELECT INDEX_NAME, COUNT(*) as col_count
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = 'hobbyloop_db'
           AND TABLE_NAME = ?
           AND NON_UNIQUE = 0
           AND COLUMN_NAME IN ($placeholders)
         GROUP BY INDEX_NAME
         HAVING col_count = " . count($columns)
    );
    $stmt->execute(array_merge([$table], $columns));
    return $stmt->fetchColumn() !== false;
}

function print_summary() {
    global $test_pass, $test_fail;
    $total = $test_pass + $test_fail;
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Results: $test_pass/$total passed";
    if ($test_fail > 0) echo " ($test_fail FAILED)";
    echo "\n" . str_repeat('=', 50) . "\n";
}
