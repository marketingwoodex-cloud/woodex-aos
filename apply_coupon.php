<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
require_once __DIR__ . '/config/order_totals.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = $_POST['coupon_code'] ?? '';

    if (empty($couponCode)) {
        echo json_encode(['status' => false, 'message' => 'Please enter a coupon code.']);
        exit;
    }

    // Fetch the coupon details from the database
    $stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE coupon_code = ? AND status = 'active' AND validity_period >= CURDATE()
    ");
    $stmt->bind_param('s', $couponCode);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$coupon) {
        echo json_encode(['status' => false, 'message' => 'Invalid or expired coupon code.']);
        exit;
    }

    // Calculate the discount
    $discount = 0;
    $appliesTo = $coupon['applies_to'];
    $relatedId = $coupon['related_id'];
    $discountType = $coupon['discount_type'];
    $discountAmount = $coupon['discount_amount'];

    $subtotal = 0;
    $discountableTotal = 0;

    foreach ($_SESSION['cart'] as $product_id => $item) {
        $itemSubtotal = $item['current_price'] * $item['quantity'];
        $subtotal += $itemSubtotal;

        if (
            $appliesTo === 'all' || 
            ($appliesTo === 'product' && $product_id == $relatedId) || 
            ($appliesTo === 'treatment' && $item['treatment'] == $relatedId)
        ) {
            $discountableTotal += $itemSubtotal;
        }
    }

    if ($discountableTotal > 0) {
        if ($discountType === 'percentage') {
            $discount = ($discountAmount / 100) * $discountableTotal;
        } elseif ($discountType === 'fixed') {
            $discount = min($discountAmount, $discountableTotal);
        }
    }

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
