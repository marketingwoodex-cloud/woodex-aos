<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['quote_key'], $_POST['action'])) {
  echo json_encode(['status' => false, 'message' => 'Invalid request.']);
  exit;
}

$key = (string)$_POST['quote_key'];
$action = (string)$_POST['action'];

if (!isset($_SESSION['quote_basket'][$key])) {
  echo json_encode(['status' => false, 'message' => 'Item not found.']);
  exit;
}

$qty = (int)($_SESSION['quote_basket'][$key]['quantity'] ?? 1);
if ($action === 'increase') {
  $_SESSION['quote_basket'][$key]['quantity'] = $qty + 1;
  echo json_encode(['status' => true, 'message' => 'Quantity increased.']);
  exit;
}
if ($action === 'decrease') {
  if ($qty > 1) {
    $_SESSION['quote_basket'][$key]['quantity'] = $qty - 1;
    echo json_encode(['status' => true, 'message' => 'Quantity decreased.']);
  } else {
    echo json_encode(['status' => false, 'message' => 'Minimum quantity is 1.']);
  }
  exit;
}

echo json_encode(['status' => false, 'message' => 'Invalid action.']);
