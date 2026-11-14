<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
require_once __DIR__ . '/config/order_totals.php';
require_once __DIR__ . '/config/coupon_validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = $_POST['coupon_code'] ?? '';

    if (empty($couponCode)) {
        echo json_encode(['status' => false, 'message' => 'Please enter a coupon code.']);
        exit;
    }

    $coupon = wdx_active_coupon($conn, (string)$couponCode);
    if (!$coupon) {
        echo json_encode(['status' => false, 'message' => 'Invalid or expired coupon code.']);
        exit;
    }
    $cart = $_SESSION['cart'] ?? [];
    $subtotal = 0.0;
    foreach ($cart as $item) $subtotal += (float)$item['current_price'] * (int)$item['quantity'];
    $discount = wdx_coupon_discount($cart, $coupon);
    $appliesTo = $coupon['applies_to'];
    $relatedId = $coupon['related_id'];

    $grandTotal = $subtotal - $discount;

    // Store the coupon details in the session
    $_SESSION['coupon'] = [
        'code' => $couponCode,
        'discount' => number_format($discount, 2, '.', ''),
        'grand_total' => number_format($grandTotal, 2, '.', ''),
        'applies_to' => $appliesTo,
        'related_id' => $relatedId,
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'cart_fingerprint' => wdx_cart_fingerprint($_SESSION['cart'] ?? [])
    ];

    // Return the discount and updated total
    echo json_encode([
        'status' => true,
        'message' => 'Coupon applied successfully.',
        'discount' => number_format($discount, 2),
        'grand_total' => number_format($grandTotal, 2)
    ]);
    exit;
}
?>
