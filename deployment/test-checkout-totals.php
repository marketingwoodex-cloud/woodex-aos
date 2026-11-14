<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../config/order_totals.php';
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
$cart = [['product_id' => 1, 'current_price' => 1000, 'quantity' => 2]];
$t = wdx_checkout_totals($cart, null);
check($t['total'] === 2200.0 && $t['shipping'] === 200.0, 'shipping under threshold');
$cart[0]['quantity'] = 4;
$t = wdx_checkout_totals($cart, null);
check($t['total'] === 4000.0 && $t['shipping'] === 0.0, 'free shipping');
$coupon = ['subtotal' => '4,000.00', 'discount' => '1,000.00', 'cart_fingerprint' => wdx_cart_fingerprint($cart)];
$t = wdx_checkout_totals($cart, $coupon);
check($t['coupon_valid'] && $t['total'] === 3000.0, 'coupon and formatted amounts');
$cart[0]['product_id'] = 2;
$t = wdx_checkout_totals($cart, $coupon);
check(!$t['coupon_valid'] && $t['total'] === 4000.0, 'coupon invalidated on cart change');
try { wdx_checkout_totals([['current_price' => -1, 'quantity' => 1]], null); check(false, 'negative price rejected'); }
catch (InvalidArgumentException $e) {}
echo "Checkout totals checks passed.\n";
