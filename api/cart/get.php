<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

$items = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

$cart_items = [];
$cart_count = 0;

foreach ($items as $cart_item_id => $item) {
  $cart_items[] = [
    'cart_item_id'  => $cart_item_id,
    'product_id'    => $item['product_id'] ?? null,
    'variation_id'  => $item['variation_id'] ?? null,
    'name'          => $item['name'] ?? 'Product',
    'current_price' => (float)($item['current_price'] ?? 0),
    'quantity'      => (int)($item['quantity'] ?? 0),
    'image'         => $item['image'] ?? 'assets/images/placeholder.png',
    'attributes'    => $item['attributes'] ?? []
  ];
  $cart_count += (int)($item['quantity'] ?? 0);
}

echo json_encode([
  'status' => true,
  'cart_count' => $cart_count,
  'cart_items' => $cart_items
]);
