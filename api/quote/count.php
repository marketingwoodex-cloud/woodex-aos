<?php
require_once __DIR__ . '/../../lbpanel/config/session.php';
wdx_session_start();
header('Content-Type: application/json');

$count = 0;
if (!empty($_SESSION['quote_basket']) && is_array($_SESSION['quote_basket'])) {
  foreach ($_SESSION['quote_basket'] as $item) {
    $count += (int)($item['quantity'] ?? 0);
  }
}

echo json_encode(['status' => true, 'quote_count' => $count]);
