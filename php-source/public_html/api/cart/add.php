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

// Build root-relative URLs (works on live + local and avoids mixed-content issues)
function absolute_url_for($path) {
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

  $bpTrim = trim($basePath, '/');
  $p = str_replace('\\', '/', (string)$path);

  if ($bpTrim !== '') {
    if (strpos($p, '/' . $bpTrim . '/') === 0) return $p;
    if (strpos($p, $bpTrim . '/') === 0) return '/' . $p;
  }

  if (strpos($p, '/assets/') === 0) {
    return rtrim($basePath, '/') . $p;
  }
  if (strpos($p, '/') === 0) {
    return rtrim($basePath, '/') . $p;
  }
  return rtrim($basePath, '/') . '/' . ltrim($p, '/');
}

$response = ['status' => false, 'message' => 'Invalid input.'];

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity   = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$cart_key   = $_POST['cart_item_id'] ?? '';
$attributes = isset($_POST['attributes']) ? $_POST['attributes'] : [];

// Be tolerant: if client fails to send cart_item_id (e.g. JS hash lib missing), compute it server-side.
if (!is_array($attributes)) {
  // jQuery typically posts arrays; if it comes as JSON string, try decode.
  if (is_string($attributes)) {
    $decoded = json_decode($attributes, true);
    $attributes = is_array($decoded) ? $decoded : [];
  } else {
    $attributes = [];
  }
}

if ($product_id <= 0 || $quantity <= 0) {
  echo json_encode($response);
  exit;
}

if (empty($cart_key)) {
  $cart_key = md5($product_id . '|' . json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// fetch base product
$stmt = $conn->prepare("SELECT id, product_name, old_price, current_price, quantity, images FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  echo json_encode(['status' => false, 'message' => 'Product not found.']);
  exit;
}
$product = $result->fetch_assoc();
$stmt->close();

$matchedVariation = null;
$attributesWithLabels = [];

// match variation if attributes posted
if (is_array($attributes) && count($attributes) > 0) {
  $varStmt = $conn->prepare("SELECT id, sku, price, stock, image, variation_data, variation_key FROM product_variations WHERE product_id = ?");
  $varStmt->bind_param("i", $product_id);
  $varStmt->execute();
  $varResult = $varStmt->get_result();
  while ($row = $varResult->fetch_assoc()) {
    $variationData = json_decode($row['variation_data'], true) ?: [];
    $isMatch = true;
    foreach ($attributes as $attrName => $attrValue) {
      if (!isset($variationData[$attrName]['value']) ||
          strtolower(trim($variationData[$attrName]['value'])) !== strtolower(trim($attrValue))) {
        $isMatch = false; break;
      }
    }
    if ($isMatch) { $matchedVariation = $row; break; }
  }
  $varStmt->close();

  if ($matchedVariation) {
    $product['current_price'] = (float)$matchedVariation['price'];
    $product['quantity']      = (int)$matchedVariation['stock'];
    if (!empty($matchedVariation['image'])) {
      $product['images'] = json_encode([$matchedVariation['image']]);
    }
    $variationData = json_decode($matchedVariation['variation_data'], true) ?: [];
    foreach ($attributes as $attrName => $attrValue) {
      $label = $attrValue;
      if (isset($variationData[$attrName]['label']) && !empty($variationData[$attrName]['label'])) {
        $label = $variationData[$attrName]['label'];
      }
      $attributesWithLabels[$attrName] = ['value' => $attrValue, 'label' => $label];
    }
  }
}

// fallback labels if not matched
if (empty($attributesWithLabels) && is_array($attributes)) {
  foreach ($attributes as $attrName => $attrValue) {
    $attributesWithLabels[$attrName] = ['value' => $attrValue, 'label' => $attrValue];
  }
}

// stock guard
$availableStock = (int)$product['quantity'];
if ($quantity > $availableStock) {
  echo json_encode(['status' => false, 'message' => 'Not enough stock available.']);
  exit;
}

// init cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// image
$images = json_decode($product['images'] ?: "[]", true);
$productImage = is_array($images) && count($images) ? absolute_url_for($images[0]) : absolute_url_for('assets/images/placeholder.svg');

// store/update item
if (isset($_SESSION['cart'][$cart_key])) {
  $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
} else {
  $_SESSION['cart'][$cart_key] = [
    'cart_item_id' => $cart_key,
    'product_id'   => (int)$product['id'],
    'variation_id' => $matchedVariation ? (int)$matchedVariation['id'] : null,
    'name'         => $product['product_name'],
    'old_price'    => (float)$product['old_price'],
    'current_price'=> (float)$product['current_price'],
    'quantity'     => (int)$quantity,
    'image'        => $productImage,
    'attributes'   => $attributesWithLabels
  ];
}

$cart_count = array_sum(array_map(function($i){ return (int)$i['quantity']; }, $_SESSION['cart']));

// toast html (image + text + qty)
$toast_html ='<span>'.htmlspecialchars($product['product_name']).' (x'.(int)$quantity.') added to cart.</span>';

echo json_encode([
  'status'      => true,
  'message'     => htmlspecialchars($product['product_name']).' added to cart successfully.',
  'toast_html'  => $toast_html,
  'image'       => $productImage,
  'cart_count'  => $cart_count
]);
