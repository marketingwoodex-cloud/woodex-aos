<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}
// Ensure the session's prices and quantities still match the staging catalog.
// This is a check, not an inventory reservation; concurrent sales need separate handling.
function wdx_validate_checkout_cart(mysqli $conn, array $cart): bool {
    $needed = [];
    foreach ($cart as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $variationId = (int)($item['variation_id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        $price = (float)($item['current_price'] ?? -1);
        if ($productId <= 0 || $quantity <= 0 || !is_finite($price) || $price < 0) return false;
        $key = $productId . ':' . $variationId;
        $needed[$key] = ($needed[$key] ?? 0) + $quantity;
        if ($variationId > 0) {
            $stmt = $conn->prepare('SELECT price AS price, stock AS stock FROM product_variations WHERE id = ? AND product_id = ?');
            if (!$stmt) return false;
            $stmt->bind_param('ii', $variationId, $productId);
        } else {
            $stmt = $conn->prepare('SELECT current_price AS price, quantity AS stock FROM products WHERE id = ?');
            if (!$stmt) return false;
            $stmt->bind_param('i', $productId);
        }
        if (!$stmt->execute()) { $stmt->close(); return false; }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || round((float)$row['price'], 2) !== round($price, 2) || $needed[$key] > (int)$row['stock']) return false;
    }
    return count($cart) > 0;
}
