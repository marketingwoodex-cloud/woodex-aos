<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../../config/auth_guard.php';
}

require_once '../../config/connection.php';

if (!isset($_GET['id'])) {
    die('Transaction ID not provided');
}

$transactionId = (int)$_GET['id'];

// Get transaction details
$stmt = $conn->prepare("SELECT * FROM pos_transactions WHERE id = ?");
$stmt->bind_param("i", $transactionId);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();

if (!$transaction) {
    die('Transaction not found');
}

$items = json_decode($transaction['items_sold'], true);
$now = new DateTime($transaction['created_at']);
$dateStr = $now->format('M j, Y');
$timeStr = $now->format('h:i A');
$transactionRef = $transaction['transaction_ref']; // Use the stored reference
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= $transactionRef ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            width: 300px;
            margin: 0 auto;
            padding: 20px;
            color: #000;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        .receipt-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .receipt-subtitle {
            font-size: 12px;
            margin-bottom: 5px;
        }
        .receipt-address {
            font-size: 10px;
            line-height: 1.3;
            margin-bottom: 10px;
        }
        .receipt-details {
            margin-bottom: 15px;
            font-size: 12px;
        }
        .receipt-items {
            width: 100%;
            margin-bottom: 15px;
            font-size: 12px;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .receipt-item-name {
            flex: 2;
        }
        .receipt-item-qty {
            flex: 0 0 30px;
            text-align: center;
        }
        .receipt-item-price {
            flex: 0 0 70px;
            text-align: right;
        }
        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .receipt-totals {
            font-size: 12px;
            margin-bottom: 15px;
        }
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .receipt-total-label {
            font-weight: bold;
        }
        .receipt-total-value {
            font-weight: bold;
        }
        .receipt-payment {
            font-size: 12px;
            margin-bottom: 15px;
        }
        .receipt-footer {
            text-align: center;
            font-size: 10px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }
        .receipt-barcode {
            text-align: center;
            margin-top: 10px;
            font-family: 'Libre Barcode 128', cursive;
            font-size: 24px;
        }
        @page {
            size: auto;
            margin: 0;
        }
        @media print {
            body {
                width: 100%;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
</head>
<body onload="window.print()">
    <div class="receipt">
        <div class="receipt-header">
            <div class="receipt-title">WOODEX FURNITURE</div>
            <div class="receipt-subtitle">POS RECEIPT</div>
            <div class="receipt-address">
                www.woodexfurniture.pk<br>
                Shop # G-66, Zainab Tower<br>
                Model Town Link Road, Lahore<br>
                Phone: (123) 456-7890
            </div>
        </div>
        
        <div class="receipt-details">
            <div><strong>Date:</strong> <?= $dateStr ?></div>
            <div><strong>Time:</strong> <?= $timeStr ?></div>
            <div><strong>Transaction #:</strong> <?= $transactionRef ?></div>
            <div><strong>Cashier:</strong> <?= htmlspecialchars($transaction['cashier_name'] ?? 'System') ?></div>
            <div><strong>Customer:</strong> <?= htmlspecialchars($transaction['customer_name']) ?></div>
            <?php if (!empty($transaction['customer_phone'])): ?>
                <div><strong>Phone:</strong> <?= htmlspecialchars($transaction['customer_phone']) ?></div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-items">
            <?php foreach ($items as $item): ?>
                <div class="receipt-item">
                    <div class="receipt-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="receipt-item-qty"><?= $item['quantity'] ?></div>
                    <div class="receipt-item-price">₨<?= number_format($item['total_price'], 2) ?></div>
                </div>
                <div class="receipt-item">
                    <div class="receipt-item-name" style="padding-left: 10px;">@ ₨<?= number_format($item['unit_price'], 2) ?> each</div>
                    <div class="receipt-item-qty"></div>
                    <div class="receipt-item-price"></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="receipt-divider"></div>
        
        <div class="receipt-totals">
            <div class="receipt-total-row">
                <span class="receipt-total-label">Subtotal:</span>
                <span class="receipt-total-value">₨<?= number_format($transaction['subtotal'], 2) ?></span>
            </div>
            <div class="receipt-total-row">
                <span class="receipt-total-label">Tax:</span>
                <span class="receipt-total-value">₨<?= number_format($transaction['tax'], 2) ?></span>
            </div>
            <div class="receipt-total-row">
                <span class="receipt-total-label">Total:</span>
                <span class="receipt-total-value">₨<?= number_format($transaction['total'], 2) ?></span>
            </div>
        </div>
        
        <div class="receipt-divider"></div>
        
        <div class="receipt-payment">
            <div><strong>Payment Method:</strong> <?= ucfirst($transaction['payment_method']) ?></div>
            <?php if ($transaction['payment_method'] === 'mobile' && !empty($transaction['payment_app'])): ?>
                <div><strong>Payment App:</strong> <?= $transaction['payment_app'] ?></div>
                <div><strong>Transaction ID:</strong> <?= $transaction['transaction_id'] ?></div>
            <?php endif; ?>
            <div><strong>Amount Tendered:</strong> ₨<?= number_format($transaction['amount_tendered'], 2) ?></div>
            <div><strong>Change:</strong> ₨<?= number_format($transaction['change_given'], 2) ?></div>
        </div>
        
        <div class="receipt-barcode"><?= $transactionRef ?></div>
        
        <div class="receipt-footer">
            Thank you for shopping with us!<br>
            Please retain this receipt for your records<br>
            Returns accepted within 14 days with receipt <br>
            <strong>POS Developed By BAKSORBIT</strong>
        </div>
    </div>
</body>
</html>
