<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../../config/auth_guard.php';
}

require_once '../../config/connection.php';

if (!isset($_GET['id'])) {
    die('<div class="alert alert-danger">Transaction ID not provided</div>');
}

$transactionId = (int)$_GET['id'];

// Get transaction details
$stmt = $conn->prepare("SELECT * FROM pos_transactions WHERE id = ?");
$stmt->bind_param("i", $transactionId);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();

if (!$transaction) {
    die('<div class="alert alert-danger">Transaction not found</div>');
}

$items = json_decode($transaction['items_sold'], true);
?>

<div class="row">
    <div class="col-md-6">
        <h6>Transaction Information</h6>
        <table class="table table-sm">
            <tr>
                <th>Transaction ID</th>
                <td>#<?= $transaction['id'] ?></td>
            </tr>
            <tr>
                <th>Date/Time</th>
                <td><?= date('M j, Y h:i A', strtotime($transaction['created_at'])) ?></td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>
                    <?= htmlspecialchars($transaction['customer_name']) ?>
                    <?php if (!empty($transaction['customer_phone'])): ?>
                        <br><?= htmlspecialchars($transaction['customer_phone']) ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6>Payment Information</h6>
        <table class="table table-sm">
            <tr>
                <th>Payment Method</th>
                <td>
                    <span class="badge rounded-pill badge-<?= $transaction['payment_method'] ?>">
                        <?= ucfirst($transaction['payment_method']) ?>
                        <?php if ($transaction['payment_method'] === 'mobile' && !empty($transaction['payment_app'])): ?>
                            (<?= $transaction['payment_app'] ?>)
                        <?php endif; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Transaction ID</th>
                <td><?= !empty($transaction['transaction_id']) ? $transaction['transaction_id'] : 'N/A' ?></td>
            </tr>
            <tr>
                <th>Amount Tendered</th>
                <td>₨<?= number_format($transaction['amount_tendered'], 2) ?></td>
            </tr>
            <tr>
                <th>Change Given</th>
                <td>₨<?= number_format($transaction['change_given'], 2) ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="mt-4">
    <h6>Items Purchased</h6>
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>₨<?= number_format($item['unit_price'], 2) ?></td>
                    <td>₨<?= number_format($item['total_price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Subtotal</th>
                <th>₨<?= number_format($transaction['subtotal'], 2) ?></th>
            </tr>
            <tr>
                <th colspan="3">Tax</th>
                <th>₨<?= number_format($transaction['tax'], 2) ?></th>
            </tr>
            <tr>
                <th colspan="3">Total</th>
                <th>₨<?= number_format($transaction['total'], 2) ?></th>
            </tr>
        </tfoot>
    </table>
</div>