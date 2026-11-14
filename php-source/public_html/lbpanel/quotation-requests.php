<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
  header('Location: index.php');
  exit;
}

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

ensure_quote_tables($conn);

$rows = [];
$res = $conn->query("SELECT id, quote_no, customer_name, customer_email, customer_phone, total, status, created_at FROM quotation_requests ORDER BY id DESC");
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quotation Requests</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container" style="max-width:1200px;margin-top:24px;">
  <h2 class="mb-3">Quotation Requests</h2>
  <div class="table-responsive bg-white border rounded-3">
    <table class="table table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th>ID</th><th>Quote No</th><th>Customer</th><th>Email</th><th>Phone</th><th>Total</th><th>Status</th><th>Date</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="9" class="text-center">No quotation requests yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo htmlspecialchars($r['quote_no']); ?></td>
          <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
          <td><?php echo htmlspecialchars($r['customer_email']); ?></td>
          <td><?php echo htmlspecialchars($r['customer_phone']); ?></td>
          <td>PKR <?php echo number_format((float)$r['total'], 2); ?></td>
          <td><span class="badge bg-secondary"><?php echo htmlspecialchars($r['status']); ?></span></td>
          <td><?php echo htmlspecialchars($r['created_at']); ?></td>
          <td><a class="btn btn-sm btn-primary" href="quotation-request-view.php?id=<?php echo (int)$r['id']; ?>">View</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
