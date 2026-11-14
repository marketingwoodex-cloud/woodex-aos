<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
  exit;
}

include "../../lbpanel/config/connection.php";

function quote_abs_url($path) {
  if (!$path) return '';
  if (preg_match('~^https?://~i', $path)) return $path;
  $basePath = '/';
  if (!empty($GLOBALS['domain'])) {
    $p = parse_url($GLOBALS['domain'], PHP_URL_PATH);
    if (is_string($p) && $p !== '') {
      $p = '/' . trim($p, '/');
      $basePath = ($p === '/') ? '/' : ($p . '/');
    }
  }
  $p = str_replace('\\', '/', (string)$path);
  if (strpos($p, '/') === 0) return rtrim($basePath, '/') . $p;
  return rtrim($basePath, '/') . '/' . ltrim($p, '/');
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$attributes = isset($_POST['attributes']) ? $_POST['attributes'] : [];

if (!is_array($attributes)) {
  if (is_string($attributes)) {
    $decoded = json_decode($attributes, true);
    $attributes = is_array($decoded) ? $decoded : [];
  } else {
    $attributes = [];
  }
}

if ($product_id <= 0) {
  echo json_encode(['status' => false, 'message' => 'Invalid product.']);
  exit;
}
if ($quantity < 1) $quantity = 1;

$stmt = $conn->prepare("SELECT id, product_name, current_price, old_price, images, product_sku FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
  echo json_encode(['status' => false, 'message' => 'Product not found.']);
  exit;
}

$matchedVariation = null;
$attributesWithLabels = [];
if (!empty($attributes)) {
  $varStmt = $conn->prepare("SELECT id, sku, price, image, variation_data FROM product_variations WHERE product_id = ?");
  $varStmt->bind_param('i', $product_id);
  $varStmt->execute();
  $varRes = $varStmt->get_result();
  while ($row = $varRes->fetch_assoc()) {
    $variationData = json_decode($row['variation_data'], true) ?: [];
    $isMatch = true;
    foreach ($attributes as $attrName => $attrValue) {
      if (!isset($variationData[$attrName]['value']) || strtolower(trim((string)$variationData[$attrName]['value'])) !== strtolower(trim((string)$attrValue))) {
        $isMatch = false;
        break;
      }
    }
    if ($isMatch) {
      $matchedVariation = $row;
      foreach ($attributes as $attrName => $attrValue) {
        $label = $variationData[$attrName]['label'] ?? $attrValue;
        $attributesWithLabels[$attrName] = ['value' => $attrValue, 'label' => $label];
      }
      break;
    }
  }
  $varStmt->close();
}

if (empty($attributesWithLabels)) {
  foreach ($attributes as $k => $v) {
    $attributesWithLabels[$k] = ['value' => $v, 'label' => $v];
  }
}

$price = $matchedVariation ? (float)$matchedVariation['price'] : (float)$product['current_price'];
$sku = $matchedVariation && !empty($matchedVariation['sku']) ? $matchedVariation['sku'] : ($product['product_sku'] ?? '');

$images = json_decode($product['images'] ?: '[]', true);
$img = is_array($images) && count($images) ? quote_abs_url($images[0]) : quote_abs_url('assets/images/placeholder.svg');
if ($matchedVariation && !empty($matchedVariation['image'])) {
  $img = quote_abs_url($matchedVariation['image']);
}

$quote_key = md5($product_id . '|' . json_encode($attributesWithLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

if (!isset($_SESSION['quote_basket']) || !is_array($_SESSION['quote_basket'])) {
  $_SESSION['quote_basket'] = [];
}

if (isset($_SESSION['quote_basket'][$quote_key])) {
  $_SESSION['quote_basket'][$quote_key]['quantity'] += $quantity;
} else {
  $_SESSION['quote_basket'][$quote_key] = [
    'quote_item_id' => $quote_key,
    'product_id' => (int)$product['id'],
    'variation_id' => $matchedVariation ? (int)$matchedVariation['id'] : null,
    'name' => (string)$product['product_name'],
    'sku' => (string)$sku,
    'current_price' => (float)$price,
    'quantity' => (int)$quantity,
    'image' => $img,
    'attributes' => $attributesWithLabels,
  ];
}

$quote_count = 0;
foreach ($_SESSION['quote_basket'] as $it) {
  $quote_count += (int)($it['quantity'] ?? 0);
}

echo json_encode([
  'status' => true,
  'message' => 'Added to quotation basket.',
  'quote_count' => $quote_count,
]);
