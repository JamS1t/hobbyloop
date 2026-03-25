<?php
// ═══════════════════════════════════════════
// POST /api/checkout/validate-promo.php — Validate promo code
// ═══════════════════════════════════════════
require_once __DIR__ . '/../helpers.php';

$user = require_auth($pdo);
$body = get_json_body();

$code = isset($body['code']) ? strtoupper(trim($body['code'])) : '';
$subtotal = isset($body['subtotal']) ? (float)$body['subtotal'] : 0;

if ($code === '') {
    json_error('Please enter a promo code');
}

// Look up promo
$stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ?");
$stmt->execute([$code]);
$promo = $stmt->fetch();

if (!$promo) {
    json_error('Invalid promo code');
}

if (!$promo['is_active']) {
    json_error('This promo code is no longer active');
}

if ($promo['expires_at'] && $promo['expires_at'] < date('Y-m-d')) {
    json_error('This promo code has expired');
}

if ($promo['max_uses'] !== null && $promo['used_count'] >= $promo['max_uses']) {
    json_error('This promo code has reached its usage limit');
}

if ($subtotal < (float)$promo['min_order']) {
    json_error('Minimum order of ₱' . number_format($promo['min_order']) . ' required for this promo');
}

// Check if user already used this promo
$stmt = $pdo->prepare("SELECT COUNT(*) FROM promo_usage WHERE promo_id = ? AND user_id = ?");
$stmt->execute([$promo['id'], $user['id']]);
if ($stmt->fetchColumn() > 0) {
    json_error('You have already used this promo code');
}

// Calculate discount
if ($promo['discount_type'] === 'percent') {
    $discount = round($subtotal * ($promo['discount_value'] / 100), 2);
} else {
    $discount = (float)$promo['discount_value'];
}
// Don't let discount exceed subtotal
$discount = min($discount, $subtotal);

json_success([
    'code'           => $promo['code'],
    'discount_type'  => $promo['discount_type'],
    'discount_value' => (float)$promo['discount_value'],
    'discount'       => $discount,
    'message'        => 'Promo code applied! You save ₱' . number_format($discount),
]);
