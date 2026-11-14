<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; }
function wdx_coupon_discount(array $cart, array $coupon): float {
    $eligible = 0.0;
    foreach ($cart as $item) {
        $applies = $coupon['applies_to'] === 'all'
            || ($coupon['applies_to'] === 'product' && (string)($item['product_id'] ?? '') === (string)$coupon['related_id'])
            || ($coupon['applies_to'] === 'treatment' && (string)($item['treatment'] ?? '') === (string)$coupon['related_id']);
        if ($applies) $eligible += (float)$item['current_price'] * (int)$item['quantity'];
    }
    $amount = max(0.0, (float)$coupon['discount_amount']);
    if ($coupon['discount_type'] === 'percentage') $amount = $eligible * $amount / 100;
    elseif ($coupon['discount_type'] !== 'fixed') return 0.0;
    return round(max(0.0, min($eligible, $amount)), 2);
}
function wdx_active_coupon(mysqli $conn, string $code): ?array {
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE coupon_code = ? AND status = 'active' AND validity_period >= CURDATE() LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $code);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $coupon = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $coupon ?: null;
}
