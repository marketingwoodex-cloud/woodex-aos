<?php
// Shared calculation for the checkout display and order submission.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}
function wdx_cart_fingerprint(array $cart): string {
    return hash('sha256', json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function wdx_checkout_totals(array $cart, ?array $coupon): array {
    $subtotal = 0.0;
    foreach ($cart as $item) {
        $price = (float)($item['current_price'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        if (!is_finite($price) || $price < 0 || $quantity < 1) {
            throw new InvalidArgumentException('Invalid cart item.');
        }
        $subtotal += $price * $quantity;
    }
    $subtotal = round($subtotal, 2);
    $validCoupon = $coupon !== null && isset($coupon['subtotal'], $coupon['discount'], $coupon['cart_fingerprint'])
        && is_string($coupon['cart_fingerprint'])
        && hash_equals($coupon['cart_fingerprint'], wdx_cart_fingerprint($cart))
        && abs((float)str_replace(',', '', (string)$coupon['subtotal']) - $subtotal) < 0.01;
    $discount = $validCoupon ? (float)str_replace(',', '', (string)$coupon['discount']) : 0.0;
    if (!is_finite($discount)) $discount = 0.0;
    $discount = round(max(0, min($subtotal, $discount)), 2);
    $shipping = $subtotal > 3000 ? 0.0 : 200.0;
    return ['subtotal' => $subtotal, 'discount' => $discount, 'shipping' => $shipping,
        'total' => round($subtotal - $discount + $shipping, 2), 'coupon_valid' => $validCoupon];
}
