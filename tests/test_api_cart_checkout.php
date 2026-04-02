<?php
// ═══════════════════════════════════════════════════════
// HobbyLoop API Test — Cart & Checkout Endpoints
// Registers a test user, exercises cart operations,
// validates promo codes, places an order, then cleans up.
// ═══════════════════════════════════════════════════════

if (!isset($pdo)) {
    require_once __DIR__ . '/bootstrap.php';
}

echo "\n--- API: Cart & Checkout ---\n";

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

// ── Register a dedicated test user ─────────────────────
$ts         = time();
$test_email = "test_cart_{$ts}@test.com";

$reg = api_request('POST', '/auth/register.php', [
    'first_name' => 'Cart',
    'last_name'  => 'Tester',
    'email'      => $test_email,
    'password'   => 'password123',
]);
$token   = $reg['body']['data']['token'] ?? null;
$user_id = $reg['body']['data']['user']['id'] ?? null;

assert_test($token !== null && $user_id !== null,
    'Cart setup: test user registered and token obtained');

if (!$token) {
    echo "  [SKIP] Cannot run cart/checkout tests — test user registration failed.\n";
    if (!isset($run_all)) print_summary();
    return;
}

// ════════════════════════════════════════════════════
// CART TESTS
// ════════════════════════════════════════════════════

// 1. Add to cart without auth should return 401
$r = api_request('POST', '/cart/add.php', ['product_id' => 1]);
assert_test($r['status'] === 401,
    'Cart add: no auth token returns 401');

// 2. Add product (id=1) to cart with auth
$r = api_request('POST', '/cart/add.php', ['product_id' => 1], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Cart add: authenticated user can add product_id=1 to cart');

// 3. GET cart — should now contain the added item
$r = api_request('GET', '/cart/get.php', null, $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Cart get: returns 200 success');
$items = $r['body']['data'] ?? [];
assert_test(is_array($items) && count($items) > 0,
    'Cart get: cart contains at least one item after add');

// Confirm product_id=1 is in the cart
$found_product = false;
foreach ($items as $item) {
    if (($item['product']['id'] ?? 0) === 1) {
        $found_product = true;
        break;
    }
}
assert_test($found_product, 'Cart get: product_id=1 is present in cart items');

// 4. PUT cart update — change qty to 2
$r = api_request('PUT', '/cart/update.php', ['product_id' => 1, 'qty' => 2], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Cart update: change qty to 2 returns 200 success');

// Verify the qty was actually updated
$r2 = api_request('GET', '/cart/get.php', null, $token);
$updated_qty = 0;
foreach ($r2['body']['data'] ?? [] as $item) {
    if (($item['product']['id'] ?? 0) === 1) {
        $updated_qty = $item['qty'];
        break;
    }
}
assert_test($updated_qty === 2, 'Cart update: qty is now 2 after update');

// 5. DELETE cart item
$r = api_request('DELETE', '/cart/remove.php', ['product_id' => 1], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Cart remove: delete product_id=1 returns 200 success');

// 6. GET cart after removal — should be empty
$r = api_request('GET', '/cart/get.php', null, $token);
$items_after = $r['body']['data'] ?? [];
assert_test(is_array($items_after) && count($items_after) === 0,
    'Cart get: cart is empty after removing the only item');

// ════════════════════════════════════════════════════
// CHECKOUT TESTS
// ════════════════════════════════════════════════════

// Re-add items to cart for checkout
api_request('POST', '/cart/add.php', ['product_id' => 1], $token);

// 7. Validate promo code HOBBY10 — should succeed
$r = api_request('POST', '/checkout/validate-promo.php',
    ['code' => 'HOBBY10', 'subtotal' => 1000], $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Promo validate: HOBBY10 is a valid promo code');
assert_test(!empty($r['body']['data']['code']),
    'Promo validate: response includes code field');

// 8. Validate invalid promo code — should fail
$r = api_request('POST', '/checkout/validate-promo.php',
    ['code' => 'FAKECODE', 'subtotal' => 1000], $token);
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Promo validate: FAKECODE returns 400 error');

// 9. Place order with valid data
$order_payload = [
    'shipping_name'    => 'Cart Tester',
    'shipping_email'   => $test_email,
    'shipping_phone'   => '09171234567',
    'shipping_address' => '123 Rizal Street',
    'shipping_city'    => 'Manila',
    'shipping_zip'     => '1000',
    'payment_method'   => 'cod',
    'billing_address'  => '123 Rizal Street',
    'billing_city'     => 'Manila',
    'billing_zip'      => '1000',
];
$r = api_request('POST', '/checkout/place-order.php', $order_payload, $token);
assert_test($r['status'] === 200 && $r['body']['success'] === true,
    'Checkout place-order: valid payload returns 200 success');
$order_number = $r['body']['data']['order_number'] ?? null;
$order_id     = $r['body']['data']['order_id'] ?? null;
assert_test(!empty($order_number),
    'Checkout place-order: response includes order_number');

// 10. Verify order exists in DB
if ($order_id) {
    $stmt = $pdo->prepare("SELECT id, order_number FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $db_order = $stmt->fetch();
    assert_test(!empty($db_order) && $db_order['order_number'] === $order_number,
        'Checkout DB verify: order exists in orders table with correct order_number');

    // 11. Verify payments record was created with billing_name
    $stmt = $pdo->prepare("SELECT id, billing_name FROM payments WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch();
    assert_test(!empty($payment),
        'Checkout DB verify: payment record exists in payments table');
    assert_test(!empty($payment['billing_name']),
        'Checkout DB verify: payments.billing_name is populated');
} else {
    assert_test(false, 'Checkout DB verify: order_id not returned, cannot verify DB record');
    assert_test(false, 'Checkout DB verify: skipped (no order_id)');
}

// 12. Cart should be empty after order placement (selected items cleared)
$r = api_request('GET', '/cart/get.php', null, $token);
$items_post_order = $r['body']['data'] ?? [];
assert_test(is_array($items_post_order) && count($items_post_order) === 0,
    'Checkout: cart is empty after order placement');

// 13. Place order with empty cart — should return error
$r = api_request('POST', '/checkout/place-order.php', $order_payload, $token);
assert_test($r['status'] === 400 && $r['body']['success'] === false,
    'Checkout place-order: empty cart returns 400 error');

// ════════════════════════════════════════════════════
// CLEANUP
// ════════════════════════════════════════════════════

if ($user_id) {
    // Remove in FK-safe order
    $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$user_id]);

    if ($order_id) {
        $pdo->prepare("DELETE FROM shipments WHERE order_id = ?")->execute([$order_id]);
        $pdo->prepare("DELETE FROM payments WHERE order_id = ?")->execute([$order_id]);
        $pdo->prepare("DELETE FROM inventory_log WHERE reference_id = ?")->execute([$order_id]);
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$order_id]);
    }

    $pdo->prepare("DELETE FROM sessions WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
}

if (!isset($run_all)) {
    print_summary();
}
