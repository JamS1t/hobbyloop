<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop API Test — Products & Catalog Endpoints
// Tests: list, filter, detail, search, categories, variants
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- API: Products & Catalog ---\n";

// ── HTTP helper ────────────────────────────────────────
if (!function_exists('api_request')) {
    function api_request($method, $endpoint, $data = null, $token = null) {
        $url = 'http://localhost/hobbyloop/api' . $endpoint;
        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => "Content-Type: application/json\r\n",
                'ignore_errors' => true,
                'timeout'       => 10,
            ]
        ];
        if ($token) {
            $opts['http']['header'] .= "Authorization: Bearer $token\r\n";
        }
        if ($data !== null) {
            $opts['http']['content'] = json_encode($data);
        }
        $context  = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        $status   = 200;
        if (isset($http_response_header[0])) {
            preg_match('/\d{3}/', $http_response_header[0], $m);
            $status = (int)($m[0] ?? 200);
        }
        return ['status' => $status, 'body' => json_decode($response, true), 'raw' => $response];
    }
}

// ════════════════════════════════════════════════════
// PRODUCT LIST
// ════════════════════════════════════════════════════

// 1. GET /products/list.php — returns array of products
$r = api_request('GET', '/products/list.php');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Products list: returns 200 success');
assert_test(is_array($r['body']['data'] ?? null) && count($r['body']['data']) > 0,
    'Products list: data is a non-empty array');

// 2. GET /products/list.php?cat=creative-arts — filtered results
$r = api_request('GET', '/products/list.php?cat=creative-arts');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Products list filtered: returns 200 success for cat=creative-arts');
$filtered = $r['body']['data'] ?? [];
assert_test(is_array($filtered),
    'Products list filtered: data is an array (may be empty if category has no items)');
// Every returned product must belong to the requested category
if (!empty($filtered)) {
    $all_match = true;
    foreach ($filtered as $p) {
        if (($p['cat'] ?? '') !== 'creative-arts') {
            $all_match = false;
            break;
        }
    }
    assert_test($all_match, 'Products list filtered: all returned products have cat=creative-arts');
}

// ════════════════════════════════════════════════════
// PRODUCT DETAIL
// ════════════════════════════════════════════════════

// 3. GET /products/detail.php?id=1 — returns product with required fields
$r = api_request('GET', '/products/detail.php?id=1');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Product detail id=1: returns 200 success');
$product = $r['body']['data'] ?? [];
assert_test(!empty($product['name']),   'Product detail id=1: has name field');
assert_test(isset($product['price']),   'Product detail id=1: has price field');
assert_test(!empty($product['sku']),    'Product detail id=1: has sku field');
assert_test(isset($product['brand']),   'Product detail id=1: has brand field');
assert_test(!empty($product['seller']), 'Product detail id=1: has seller field');
assert_test(isset($product['reviewsList']) && is_array($product['reviewsList']),
    'Product detail id=1: has reviewsList array');

// 4. GET /products/detail.php?id=99999 — non-existent product returns error
$r = api_request('GET', '/products/detail.php?id=99999');
assert_test(in_array($r['status'], [404, 400]) && $r['body']['success'] === false,
    'Product detail id=99999: returns 404/400 with error');

// ════════════════════════════════════════════════════
// PRODUCT SEARCH
// ════════════════════════════════════════════════════

// 5. GET /products/search.php?q=camera — returns results
$r = api_request('GET', '/products/search.php?q=camera');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Product search q=camera: returns 200 success');
assert_test(is_array($r['body']['data'] ?? null),
    'Product search q=camera: data is an array');

// 6. GET /products/search.php?q= — empty query returns empty array (not error)
$r = api_request('GET', '/products/search.php?q=');
assert_test($r['status'] === 200,
    'Product search q=(empty): returns 200 (not a server error)');
$emptyData = $r['body']['data'] ?? null;
assert_test(is_array($emptyData) && count($emptyData) === 0,
    'Product search q=(empty): returns empty array');

// ════════════════════════════════════════════════════
// CATEGORIES
// ════════════════════════════════════════════════════

// 7. GET /products/categories.php — returns category list
$r = api_request('GET', '/products/categories.php');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Categories: returns 200 success');
$cats = $r['body']['data'] ?? [];
assert_test(is_array($cats) && count($cats) > 0,
    'Categories: returns non-empty array');
// Each category should have an id and label
$first_cat = $cats[0] ?? [];
assert_test(!empty($first_cat['id']) && !empty($first_cat['label']),
    'Categories: each entry has id and label fields');

// ════════════════════════════════════════════════════
// VARIANTS
// ════════════════════════════════════════════════════

// 8. GET /products/variants.php?product_id=1 — returns variants array (may be empty)
$r = api_request('GET', '/products/variants.php?product_id=1');
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Variants product_id=1: returns 200 success');
assert_test(isset($r['body']['variants']) && is_array($r['body']['variants']),
    'Variants product_id=1: response has variants array (may be empty)');

// 9. GET /products/variants.php without product_id — returns error
$r = api_request('GET', '/products/variants.php?product_id=0');
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Variants product_id=0: missing/invalid product_id returns 400');

if (!isset($run_all)) {
    print_summary();
}
