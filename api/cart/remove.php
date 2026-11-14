<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cart_key'])) {
  echo json_encode(['status' => false, 'message' => 'Invalid request.']);
  exit;
}

$cart_key = $_POST['cart_key'];

if (isset($_SESSION['cart'][$cart_key])) {
  unset($_SESSION['cart'][$cart_key]);
  echo json_encode(['status' => true, 'message' => 'Item removed from cart successfully.']);
  exit;
}

echo json_encode(['status' => false, 'message' => 'Item not found in cart.']);
