<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  $cart_count = array_sum(array_map(function($i){ return (int)$i['quantity']; }, $_SESSION['cart']));
  echo json_encode(['status' => true, 'cart_count' => $cart_count]);
} else {
  echo json_encode(['status' => true, 'cart_count' => 0]);
}
