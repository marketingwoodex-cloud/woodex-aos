<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

$dompdfAutoload = __DIR__ . '/vendor/autoload.php';
$canUseDompdf = false;
if (is_file($dompdfAutoload)) {
  require_once $dompdfAutoload;
  $canUseDompdf = class_exists('Dompdf\\Dompdf') && class_exists('Dompdf\\Options');
}

function pdf_ascii_line($text) {
  $text = (string)$text;
  $text = preg_replace('/[^\x20-\x7E]/', ' ', $text);
  return trim(preg_replace('/\s+/', ' ', $text));
}

function stream_simple_pdf_lines($fileName, array $lines) {
  $safeLines = [];
  foreach ($lines as $line) {
    $line = pdf_ascii_line($line);
    $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
    $safeLines[] = $line;
  }

  $content = "BT\n/F1 11 Tf\n40 800 Td\n";
  $lineCount = 0;
  foreach ($safeLines as $line) {
    $content .= '(' . $line . ") Tj\nT*\n";
    $lineCount++;
    if ($lineCount > 54) {
      break;
    }
  }
  $content .= "ET";

  $objects = [];
  $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
  $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj";
  $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj";
  $objects[] = "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";
  $objects[] = "5 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj";

  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  foreach ($objects as $obj) {
    $offsets[] = strlen($pdf);
    $pdf .= $obj . "\n";
  }
  $xrefPos = strlen($pdf);
  $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
  }
  $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName) . '"');
  header('Content-Length: ' . strlen($pdf));
  echo $pdf;
  exit;
}

function pkr($n) {
  return 'PKR ' . number_format((float)$n, 2);
}

function e($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($requestId <= 0) {
  die('Invalid quotation request.');
}

$stmt = $conn->prepare("SELECT * FROM quotation_requests WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
  die('Quotation request not found.');
}

$itStmt = $conn->prepare("SELECT * FROM quotation_request_items WHERE request_id = ? ORDER BY id ASC");
$itStmt->bind_param('i', $requestId);
$itStmt->execute();
$items = $itStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itStmt->close();

$quoteNo = (string)($request['quote_no'] ?? ('QR-' . $requestId));
$createdAt = strtotime((string)($request['created_at'] ?? 'now')) ?: time();
$dateStr = date('d/m/Y', $createdAt);
$validUntil = date('d/m/Y', strtotime('+30 days', $createdAt));
$poNo = str_pad((string)$requestId, 6, '0', STR_PAD_LEFT);

$customerName = (string)($request['customer_name'] ?? '');
$customerPhone = (string)($request['customer_phone'] ?? '');
$customerAddress = (string)($request['customer_address'] ?? '');
$customerCity = trim(explode(',', $customerAddress)[0] ?? $customerAddress);

$subtotal = (float)($request['subtotal'] ?? 0);
$discount = 0.0;
$total = (float)($request['total'] ?? $subtotal);
$advanceRate = 0.75;
$advance = $total * $advanceRate;
$balance = $total - $advance;

$rows = [];
$sr = 1;
foreach ($items as $item) {
  $qty = (int)($item['quantity'] ?? 0);
  $unit = (float)($item['unit_price'] ?? 0);
  $line = (float)($item['line_total'] ?? ($qty * $unit));
  $rows[] = [
    'sr' => $sr,
    'item' => (string)($item['product_name'] ?? 'Product'),
    'desc' => (string)($item['sku'] ?? 'office-furniture'),
    'qty' => $qty,
    'unit' => $unit,
    'line' => $line,
  ];
  $sr++;
}

$minRows = 12;
while (count($rows) < $minRows) {
  $rows[] = ['sr' => '', 'item' => '', 'desc' => '', 'qty' => '', 'unit' => '', 'line' => ''];
}

ob_start();
?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <title><?php echo e($quoteNo); ?> - Quotation</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; margin: 18px; color: #222; font-size: 12px; }
    .top-note { text-align: center; font-size: 11px; color: #4f4f4f; margin-bottom: 8px; }
    .head { display: table; width: 100%; margin-bottom: 8px; }
    .head-left, .head-right { display: table-cell; vertical-align: top; }
    .head-right { text-align: right; }
    .logo-title { font-size: 44px; font-weight: 800; letter-spacing: 1px; line-height: 1; }
    .eq-title { font-size: 38px; font-weight: 800; letter-spacing: 1px; line-height: 1; }
    .eq-sub { font-size: 11px; color: #666; letter-spacing: .7px; font-weight: 700; }
    .green-line { border-top: 4px solid #2f7f1f; margin: 10px 0 12px; }

    .info-wrap { display: table; width: 100%; margin-bottom: 12px; }
    .customer-box, .meta-box { display: table-cell; vertical-align: top; }
    .customer-box {
      width: 73%;
      border: 1px solid #d8d8d8;
      border-radius: 8px;
      padding: 12px;
      background: #f8f8f8;
    }
    .meta-box { width: 27%; padding-left: 10px; }
    .label-small { color: #7aa56a; font-weight: 800; font-size: 11px; letter-spacing: .4px; text-transform: uppercase; }
    .customer-name { font-size: 24px; font-weight: 700; margin: 4px 0; }
    .customer-line { color: #555; margin: 2px 0; font-size: 12px; }

    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 3px 0; font-size: 12px; }
    .meta-table td:first-child { color: #666; font-weight: 700; text-transform: uppercase; }
    .meta-table td:last-child { text-align: right; font-weight: 800; }

    .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .items-table th { background: #2f7f1f; color: #fff; padding: 7px 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; text-align: left; }
    .items-table td { border-bottom: 1px solid #ededed; padding: 7px 6px; font-size: 12px; height: 26px; }
    .items-table .num { text-align: center; width: 42px; }
    .items-table .qty { text-align: center; width: 65px; }
    .items-table .money { text-align: right; width: 130px; }
    .items-table .bold { font-weight: 700; }

    .bottom-wrap { margin-top: 14px; border-top: 3px solid #2f7f1f; padding-top: 10px; }
    .bottom-left, .bottom-right { display: table-cell; vertical-align: top; }
    .bottom-left { width: 62%; }
    .bottom-right { width: 38%; }
    .terms { font-size: 12px; color: #333; }
    .terms div { margin: 2px 0; }
    .terms strong { display: inline-block; width: 120px; }

    .sum-table { width: 100%; border-collapse: collapse; }
    .sum-table td { padding: 4px 6px; font-size: 12px; }
    .sum-table td:first-child { font-weight: 700; text-transform: uppercase; color: #666; }
    .sum-table td:last-child { text-align: right; font-weight: 800; }
    .sum-total td { background: #2f7f1f; color: #fff !important; font-size: 14px; }
    .sum-adv td:first-child { color: #666; }

    .thank { margin-top: 12px; text-align: center; border-top: 3px solid #2f7f1f; padding-top: 8px; color: #2f7f1f; font-size: 20px; font-weight: 900; letter-spacing: .8px; }
    .foot { margin-top: 4px; text-align: center; font-size: 10px; color: #555; }

    .print-actions { text-align: center; margin: 14px 0 4px; }
    .print-btn { display: inline-block; background: #2f7f1f; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 4px; font-size: 13px; font-weight: 700; }

    @page { margin: 10mm; }
    @media print {
      .print-actions { display: none; }
      body { margin: 8px; }
    }
  </style>
</head>
<body>
  <div class="top-note">Get E-Quotation — WOODEX Pakistan | Free Quote Request!</div>

  <div class="head">
    <div class="head-left">
      <div class="logo-title">WOODEX<span style="color:#6b6b6b;">.</span></div>
    </div>
    <div class="head-right">
      <div class="eq-title">E-QUOTATION</div>
      <div class="eq-sub">PROFESSIONAL FURNITURE SOLUTIONS</div>
    </div>
  </div>

  <div class="green-line"></div>

  <div class="info-wrap">
    <div class="customer-box">
      <div class="label-small">Quotation For</div>
      <div class="customer-name"><?php echo e($customerName); ?></div>
      <div class="customer-line"><?php echo e($customerCity); ?> - PKR <?php echo number_format($total, 0); ?></div>
      <div class="customer-line"><?php echo e($customerPhone); ?></div>
      <div class="customer-line">WhatsApp: <?php echo e($customerPhone); ?></div>
    </div>
    <div class="meta-box">
      <table class="meta-table">
        <tr><td>Quote No.</td><td><?php echo e($quoteNo); ?></td></tr>
        <tr><td>Date</td><td><?php echo e($dateStr); ?></td></tr>
        <tr><td>Valid Until</td><td><?php echo e($validUntil); ?></td></tr>
        <tr><td>PO#</td><td><?php echo e($poNo); ?></td></tr>
      </table>
    </div>
  </div>

  <table class="items-table">
    <thead>
      <tr>
        <th class="num">SR</th>
        <th>ITEM</th>
        <th>DESCRIPTION</th>
        <th class="qty">QTY</th>
        <th class="money">UNIT PRICE</th>
        <th class="money">TOTAL</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="num"><?php echo e($row['sr']); ?></td>
          <td class="bold"><?php echo e($row['item']); ?></td>
          <td><?php echo e($row['desc']); ?></td>
          <td class="qty"><?php echo e($row['qty']); ?></td>
          <td class="money"><?php echo $row['unit'] === '' ? '' : e(pkr($row['unit'])); ?></td>
          <td class="money bold"><?php echo $row['line'] === '' ? '' : e(pkr($row['line'])); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="bottom-wrap">
    <table style="width:100%; border-collapse:collapse;">
      <tr>
        <td class="bottom-left">
          <div class="terms">
            <div><strong>Advance</strong> 30% after delivery 70</div>
            <div><strong>Quotation Validity</strong> 15 Days</div>
            <div><strong>Delivery Time</strong> 15 to 20 Days</div>
            <div><strong>Transportation</strong> Include</div>
            <div><strong>Taxes</strong> Include</div>
          </div>
        </td>
        <td class="bottom-right">
          <table class="sum-table">
            <tr><td>Subtotal</td><td><?php echo e(pkr($subtotal)); ?></td></tr>
            <tr><td>Discount</td><td><?php echo e(pkr($discount)); ?></td></tr>
            <tr class="sum-total"><td>Total</td><td><?php echo e(pkr($total)); ?></td></tr>
            <tr class="sum-adv"><td>Advance (75%)</td><td><?php echo e(pkr($advance)); ?></td></tr>
            <tr><td>Balance Due</td><td><?php echo e(pkr($balance)); ?></td></tr>
          </table>
        </td>
      </tr>
    </table>
  </div>

  <div class="thank">THANK YOU FOR CHOOSING WOODEX!</div>
  <div class="foot">Payment Terms: 75% advance with order confirmation, 25% balance before delivery. Warranty as per product category.</div>

  <div class="print-actions">
    <a href="#" class="print-btn" onclick="window.print(); return false;">Print / Save as PDF</a>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

if ($canUseDompdf) {
  $options = new Dompdf\Options();
  $options->set('isRemoteEnabled', true);
  $dompdf = new Dompdf\Dompdf($options);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream('quotation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $quoteNo) . '.pdf', ['Attachment' => true]);
  exit;
}

echo $html;
