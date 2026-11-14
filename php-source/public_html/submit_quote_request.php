<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

function ensure_quote_tables($conn) {
  $conn->query("CREATE TABLE IF NOT EXISTS quotation_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_no VARCHAR(40) NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(160) NOT NULL,
    customer_phone VARCHAR(60) NOT NULL,
    customer_address TEXT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quote_no (quote_no)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $conn->query("CREATE TABLE IF NOT EXISTS quotation_request_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    product_id INT NOT NULL,
    variation_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(120) NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    attributes_json TEXT NULL,
    image VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_request_id (request_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $domain . 'requested-quote');
  exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));

if ($name === '' || $email === '' || $phone === '') {
  $_SESSION['error'] = 'Please fill required fields.';
  header('Location: ' . $domain . 'requested-quote');
  exit;
}

$items = isset($_SESSION['quote_basket']) && is_array($_SESSION['quote_basket']) ? $_SESSION['quote_basket'] : [];
if (empty($items)) {
  $_SESSION['error'] = 'No products in quotation basket.';
  header('Location: ' . $domain . 'requested-quote');
  exit;
}

ensure_quote_tables($conn);

$subtotal = 0;
foreach ($items as $item) {
  $subtotal += ((float)($item['current_price'] ?? 0) * (int)($item['quantity'] ?? 0));
}
$total = $subtotal;

$quoteNo = 'QR-' . date('ymd') . '-' . strtoupper(substr(md5((string)microtime(true)), 0, 6));

$stmt = $conn->prepare("INSERT INTO quotation_requests (quote_no, customer_name, customer_email, customer_phone, customer_address, subtotal, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
$stmt->bind_param('sssssdd', $quoteNo, $name, $email, $phone, $address, $subtotal, $total);
$stmt->execute();
$requestId = (int)$stmt->insert_id;
$stmt->close();

$itemStmt = $conn->prepare("INSERT INTO quotation_request_items (request_id, product_id, variation_id, product_name, sku, unit_price, quantity, line_total, attributes_json, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($items as $item) {
  $pid = (int)($item['product_id'] ?? 0);
  $vid = isset($item['variation_id']) ? (int)$item['variation_id'] : null;
  $pname = (string)($item['name'] ?? 'Product');
  $sku = (string)($item['sku'] ?? '');
  $unitPrice = (float)($item['current_price'] ?? 0);
  $qty = (int)($item['quantity'] ?? 1);
  $lineTotal = $unitPrice * $qty;
  $attrs = json_encode($item['attributes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $img = (string)($item['image'] ?? '');
  $itemStmt->bind_param('iiissdidss', $requestId, $pid, $vid, $pname, $sku, $unitPrice, $qty, $lineTotal, $attrs, $img);
  $itemStmt->execute();
}
$itemStmt->close();

$_SESSION['last_quote_request_id'] = $requestId;
$_SESSION['last_quote_no'] = $quoteNo;
unset($_SESSION['quote_basket']);

header('Location: ' . $domain . 'quotation-success');
exit;
