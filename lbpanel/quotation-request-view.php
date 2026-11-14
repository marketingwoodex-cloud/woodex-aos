<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
  header('Location: index.php');
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: quotation-requests.php');
  exit;
}

$stmt = $conn->prepare("SELECT * FROM quotation_requests WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
  header('Location: quotation-requests.php');
  exit;
}

$itStmt = $conn->prepare("SELECT * FROM quotation_request_items WHERE request_id = ? ORDER BY id ASC");
$itStmt->bind_param('i', $id);
$itStmt->execute();
$items = $itStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quotation Request #<?php echo (int)$id; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container" style="max-width:1200px;margin-top:24px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Quotation <?php echo htmlspecialchars($req['quote_no']); ?></h2>
    <a href="quotation-requests.php" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-6"><div class="bg-white border rounded-3 p-3"><strong>Name:</strong> <?php echo htmlspecialchars($req['customer_name']); ?><br><strong>Email:</strong> <?php echo htmlspecialchars($req['customer_email']); ?><br><strong>Phone:</strong> <?php echo htmlspecialchars($req['customer_phone']); ?><br><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($req['customer_address'] ?? '')); ?></div></div>
    <div class="col-md-6"><div class="bg-white border rounded-3 p-3"><strong>Subtotal:</strong> PKR <?php echo number_format((float)$req['subtotal'],2); ?><br><strong>Total:</strong> PKR <?php echo number_format((float)$req['total'],2); ?><br><strong>Status:</strong> <?php echo htmlspecialchars($req['status']); ?><br><strong>Date:</strong> <?php echo htmlspecialchars($req['created_at']); ?><br><a class="btn btn-sm btn-primary mt-2" href="<?php echo rtrim($domain,'/'); ?>/generate_quote_request_pdf.php?id=<?php echo (int)$id; ?>">Download PDF</a></div></div>
  </div>

  <div class="table-responsive bg-white border rounded-3">
    <table class="table table-striped mb-0 align-middle">
      <thead><tr><th>#</th><th>SKU</th><th>Product</th><th>Attributes</th><th>Qty</th><th>Unit</th><th>Line</th></tr></thead>
      <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="7" class="text-center">No items.</td></tr>
      <?php else: foreach ($items as $idx => $item): ?>
        <tr>
          <td><?php echo $idx+1; ?></td>
          <td><?php echo htmlspecialchars($item['sku'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
          <td><?php
            $attrs = json_decode($item['attributes_json'] ?? '', true);
            if (is_array($attrs) && !empty($attrs)) {
              $parts = [];
              foreach ($attrs as $k => $v) $parts[] = htmlspecialchars($k) . ': ' . htmlspecialchars($v['label'] ?? ($v['value'] ?? ''));
              echo implode(', ', $parts);
            } else {
              echo '-';
            }
          ?></td>
          <td><?php echo (int)$item['quantity']; ?></td>
          <td>PKR <?php echo number_format((float)$item['unit_price'],2); ?></td>
          <td>PKR <?php echo number_format((float)$item['line_total'],2); ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
