<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cart_key'], $_POST['action'])) {
  echo json_encode(['status' => false, 'message' => 'Invalid request.']);
  exit;
}

include "../../lbpanel/config/connection.php";

$cart_key = $_POST['cart_key'];
$action   = $_POST['action'];

if (!isset($_SESSION['cart'][$cart_key])) {
  echo json_encode(['status' => false, 'message' => 'Item not found in cart.']);
  exit;
}

$cartItem = $_SESSION['cart'][$cart_key];

$product_id   = (int)($cartItem['product_id'] ?? 0);
$variation_id = isset($cartItem['variation_id']) ? (int)$cartItem['variation_id'] : null;

if ($product_id <= 0) {
  echo json_encode(['status' => false, 'message' => 'Invalid cart item.']);
  exit;
}

// fetch stock
if (!empty($variation_id)) {
  $stmt = $conn->prepare("SELECT stock AS qty FROM product_variations WHERE id = ?");
  $stmt->bind_param("i", $variation_id);
} else {
  $stmt = $conn->prepare("SELECT quantity AS qty FROM products WHERE id = ?");
  $stmt->bind_param("i", $product_id);
}
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$currentStock = (int)($res['qty'] ?? 0);
$currentQty   = (int)$cartItem['quantity'];

if ($action === 'increase') {
  if ($currentQty < $currentStock) {
    $_SESSION['cart'][$cart_key]['quantity'] = $currentQty + 1;
    echo json_encode(['status' => true, 'message' => 'Quantity increased successfully.']);
  } else {
    echo json_encode(['status' => false, 'message' => 'Cannot increase quantity. Stock limit reached.']);
  }
  exit;
}

if ($action === 'decrease') {
  if ($currentQty > 1) {
    $_SESSION['cart'][$cart_key]['quantity'] = $currentQty - 1;
    echo json_encode(['status' => true, 'message' => 'Quantity decreased successfully.']);
  } else {
    echo json_encode(['status' => false, 'message' => 'Minimum quantity is 1.']);
  }
  exit;
}

echo json_encode(['status' => false, 'message' => 'Invalid action.']);
