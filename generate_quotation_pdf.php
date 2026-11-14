<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";

if (!isset($domain)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $projectRoot = realpath(__DIR__);
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
    $basePath = '';
    if ($projectRoot && $docRoot && strpos($projectRoot, $docRoot) === 0) {
        $rel = substr($projectRoot, strlen($docRoot));
        $rel = str_replace('\\', '/', $rel);
        $rel = '/' . trim($rel, '/');
        $basePath = ($rel === '/') ? '' : $rel;
    }
    $domain = $scheme . '://' . $host . $basePath . '/';
}
if (!isset($siteName)) {
    $siteName = 'Woodex Furniture';
}

use Dompdf\Dompdf;
use Dompdf\Options;

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

/**
 * Format amount as PKR string for PDF
 */
function formatPKR($amount) {
    return 'PKR ' . number_format((float)$amount, 2);
}

// Only accept POST with payload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

$payloadJson = $_POST['pdf_payload'] ?? '';
$payload     = json_decode($payloadJson, true);

if (!$payload || empty($payload['items']) || empty($payload['customer'])) {
    die('Invalid quotation data. Please go back and try again.');
}

// Extract and sanitize customer info
$custRaw       = $payload['customer'];
$custName      = htmlspecialchars(trim($custRaw['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$custEmail     = htmlspecialchars(trim($custRaw['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$custPhone     = htmlspecialchars(trim($custRaw['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
$custAddress   = htmlspecialchars(trim($custRaw['address'] ?? ''), ENT_QUOTES, 'UTF-8');
$custAddressBr = nl2br($custAddress);

if ($custName === '' || $custEmail === '' || $custPhone === '' || $custAddress === '') {
    die('Customer details are incomplete. Please go back and fill all required fields.');
}

// Items and GST mode
$items   = $payload['items'];
$gstMode = ($payload['gst_mode'] ?? 'with') === 'without' ? 'without' : 'with';

// Recalculate totals on server side
$subTotal = 0;
foreach ($items as $idx => $item) {
    $price = isset($item['price']) ? (float)$item['price'] : 0;
    $qty   = isset($item['qty']) ? (int)$item['qty'] : 0;
    if ($qty <= 0) {
        $qty = 1;
    }

    $items[$idx]['price']      = $price;
    $items[$idx]['qty']        = $qty;
    $items[$idx]['line_total'] = $price * $qty;
    $subTotal                 += $items[$idx]['line_total'];
}

$gstRate   = 0.17;
$gstAmount = ($gstMode === 'with') ? ($subTotal * $gstRate) : 0;
$grandTotal = $subTotal + $gstAmount;

// Branding & meta
$companyName    = 'Woodex Furniture';
$companyLogo    = $domain . 'assets/images/header-logo.png';
$companyPhone   = '+92 321 4000 768';
$companyAddress = 'LG 89, Model, Zainab Tower, Link Road, Phase 3 Town, Lahore, 54700';
$companyWebsite = 'https://woodexfurniture.pk/';

$dateStr = date('d/m/Y');
$qNum    = 'Q' . date('ymd') . '-' . rand(1000, 9999);

// Build PDF HTML (reference-style design)
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($qNum, ENT_QUOTES, 'UTF-8'); ?> - Quotation</title>
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
          width: 73%; border: 1px solid #d8d8d8; border-radius: 8px; padding: 12px; background: #f8f8f8;
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

        .thank { margin-top: 12px; text-align: center; border-top: 3px solid #2f7f1f; padding-top: 8px; color: #2f7f1f; font-size: 20px; font-weight: 900; letter-spacing: .8px; }
        .foot { margin-top: 4px; text-align: center; font-size: 10px; color: #555; }
        .print-actions { text-align: center; margin: 14px 0 4px; }
        .print-btn { display: inline-block; background: #2f7f1f; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 4px; font-size: 13px; font-weight: 700; }

        @media print {
          .print-actions { display: none; }
          body { margin: 8px; }
        }
    </style>
</head>
<body>
  <?php
    $validUntil = date('d/m/Y', strtotime('+30 days'));
    $poNo = str_pad((string)rand(1000, 999999), 6, '0', STR_PAD_LEFT);
    $cityLine = trim(explode(',', strip_tags($custAddress))[0] ?? strip_tags($custAddress));
    $discount = 0;
    $advance = $grandTotal * 0.75;
    $balance = $grandTotal - $advance;
    $rows = [];
    foreach ($items as $idx => $item) {
      $rows[] = [
        'sr' => $idx + 1,
        'item' => (string)($item['name'] ?? ''),
        'desc' => (string)($item['sku'] ?? 'office-furniture'),
        'qty' => (int)($item['qty'] ?? 1),
        'unit' => (float)($item['price'] ?? 0),
        'line' => (float)($item['line_total'] ?? 0),
      ];
    }
    while (count($rows) < 12) {
      $rows[] = ['sr' => '', 'item' => '', 'desc' => '', 'qty' => '', 'unit' => '', 'line' => ''];
    }
  ?>

  <div class="top-note">Get E-Quotation — WOODEX Pakistan | Free Quote Request!</div>

  <div class="head">
    <div class="head-left"><div class="logo-title">WOODEX<span style="color:#6b6b6b;">.</span></div></div>
    <div class="head-right">
      <div class="eq-title">E-QUOTATION</div>
      <div class="eq-sub">PROFESSIONAL FURNITURE SOLUTIONS</div>
    </div>
  </div>
  <div class="green-line"></div>

  <div class="info-wrap">
    <div class="customer-box">
      <div class="label-small">Quotation For</div>
      <div class="customer-name"><?php echo $custName; ?></div>
      <div class="customer-line"><?php echo htmlspecialchars($cityLine, ENT_QUOTES, 'UTF-8'); ?> - <?php echo formatPKR($grandTotal); ?></div>
      <div class="customer-line"><?php echo $custPhone; ?></div>
      <div class="customer-line">WhatsApp: <?php echo $custPhone; ?></div>
    </div>
    <div class="meta-box">
      <table class="meta-table">
        <tr><td>Quote No.</td><td><?php echo htmlspecialchars($qNum, ENT_QUOTES, 'UTF-8'); ?></td></tr>
        <tr><td>Date</td><td><?php echo $dateStr; ?></td></tr>
        <tr><td>Valid Until</td><td><?php echo $validUntil; ?></td></tr>
        <tr><td>PO#</td><td><?php echo $poNo; ?></td></tr>
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
        <td class="num"><?php echo htmlspecialchars((string)$row['sr'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="bold"><?php echo htmlspecialchars((string)$row['item'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$row['desc'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="qty"><?php echo htmlspecialchars((string)$row['qty'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="money"><?php echo $row['unit'] === '' ? '' : htmlspecialchars(formatPKR($row['unit']), ENT_QUOTES, 'UTF-8'); ?></td>
        <td class="money bold"><?php echo $row['line'] === '' ? '' : htmlspecialchars(formatPKR($row['line']), ENT_QUOTES, 'UTF-8'); ?></td>
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
            <div><strong>Taxes</strong> <?php echo $gstMode === 'with' ? 'Include' : 'Not Included'; ?></div>
          </div>
        </td>
        <td class="bottom-right">
          <table class="sum-table">
            <tr><td>Subtotal</td><td><?php echo formatPKR($subTotal); ?></td></tr>
            <tr><td>Discount</td><td><?php echo formatPKR($discount); ?></td></tr>
            <?php if ($gstMode === 'with'): ?><tr><td>GST (17%)</td><td><?php echo formatPKR($gstAmount); ?></td></tr><?php endif; ?>
            <tr class="sum-total"><td>Total</td><td><?php echo formatPKR($grandTotal); ?></td></tr>
            <tr><td>Advance (75%)</td><td><?php echo formatPKR($advance); ?></td></tr>
            <tr><td>Balance Due</td><td><?php echo formatPKR($balance); ?></td></tr>
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

// DOMPDF options
if ($canUseDompdf) {
    $options = new Options();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $fileName = 'quotation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $qNum) . '.pdf';
    $dompdf->stream($fileName, ['Attachment' => true]);
    exit;
}

echo $html;
