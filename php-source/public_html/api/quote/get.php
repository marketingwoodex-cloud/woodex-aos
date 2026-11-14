<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

$items = isset($_SESSION['quote_basket']) && is_array($_SESSION['quote_basket']) ? $_SESSION['quote_basket'] : [];

$quote_items = [];
$quote_count = 0;
foreach ($items as $key => $item) {
  $q = (int)($item['quantity'] ?? 0);
  $quote_count += $q;
  $quote_items[] = [
    'quote_item_id' => $key,
    'product_id' => $item['product_id'] ?? null,
    'variation_id' => $item['variation_id'] ?? null,
    'name' => $item['name'] ?? 'Product',
    'sku' => $item['sku'] ?? '',
    'current_price' => (float)($item['current_price'] ?? 0),
    'quantity' => $q,
    'image' => $item['image'] ?? 'assets/images/placeholder.png',
    'attributes' => $item['attributes'] ?? [],
  ];
}

echo json_encode([
  'status' => true,
  'quote_count' => $quote_count,
  'quote_items' => $quote_items,
]);
