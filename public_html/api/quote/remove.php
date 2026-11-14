<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['quote_key'])) {
  echo json_encode(['status' => false, 'message' => 'Invalid request.']);
  exit;
}

$key = (string)$_POST['quote_key'];
if (isset($_SESSION['quote_basket'][$key])) {
  unset($_SESSION['quote_basket'][$key]);
  echo json_encode(['status' => true, 'message' => 'Item removed.']);
  exit;
}

echo json_encode(['status' => false, 'message' => 'Item not found.']);
