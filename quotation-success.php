<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

$requestId = isset($_SESSION['last_quote_request_id']) ? (int)$_SESSION['last_quote_request_id'] : 0;
$quoteNo = isset($_SESSION['last_quote_no']) ? (string)$_SESSION['last_quote_no'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quotation Request Submitted</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">
</head>
<body>
<?php include_once('header/header.php'); ?>
<div class="container py-5" style="max-width:760px;">
  <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
    <h2 class="mb-2" style="font-weight:800;color:#1f2a36;">Quote Request Submitted</h2>
    <p class="text-muted mb-1">Thank you! Your quotation request has been received.</p>
    <?php if ($quoteNo !== ''): ?><p class="mb-3"><strong>Request No:</strong> <?php echo htmlspecialchars($quoteNo, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

    <div class="d-flex justify-content-center gap-2 flex-wrap">
      <?php if ($requestId > 0): ?>
        <a class="btn btn-primary" href="<?php echo $domain; ?>generate_quote_request_pdf.php?id=<?php echo $requestId; ?>">Download Quotation PDF</a>
      <?php endif; ?>
      <a class="btn btn-outline-dark" href="<?php echo $domain; ?>shop">Continue Shopping</a>
    </div>
  </div>
</div>
<?php include_once('footer/footer.php'); ?>
</body>
</html>
