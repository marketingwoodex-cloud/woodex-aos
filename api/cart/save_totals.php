<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $subtotal   = (float)($_POST['subtotal'] ?? 0);
  $discount   = (float)($_POST['discount'] ?? 0);
  $grandTotal = (float)($_POST['grand_total'] ?? 0);

  $_SESSION['cart_totals'] = [
    'subtotal'   => $subtotal,
    'discount'   => $discount,
    'grand_total'=> $grandTotal
  ];
  echo json_encode(['status' => true]);
  exit;
}

echo json_encode(['status' => false, 'message' => 'Invalid request.']);
