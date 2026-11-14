<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}

require_once __DIR__ . '/../config/session.php';
wdx_session_start();
require_once '../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit();
}
// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get filter parameters
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_payment = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$filter_customer = isset($_GET['customer']) ? $_GET['customer'] : '';

// Build base query
// Update your base query to include refund data
$query = "SELECT t.*, 
            COALESCE(r.total_refunded, 0) as total_refunded
          FROM pos_transactions t
          LEFT JOIN (
              SELECT original_transaction_id, 
                     SUM(refund_amount) as total_refunded
              FROM pos_refunds
              GROUP BY original_transaction_id
          ) r ON r.original_transaction_id = t.id
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM pos_transactions WHERE 1=1";
$params = [];
$types = '';

// Add filters
if (!empty($filter_date)) {
    $query .= " AND DATE(created_at) = ?";
    $count_query .= " AND DATE(created_at) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

if (!empty($filter_payment)) {
    $query .= " AND payment_method = ?";
    $count_query .= " AND payment_method = ?";
    $params[] = $filter_payment;
    $types .= 's';
}

if (!empty($filter_customer)) {
    $query .= " AND (customer_name LIKE ? OR customer_phone LIKE ?)";
    $count_query .= " AND (customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$filter_customer%";
    $params[] = "%$filter_customer%";
    $types .= 'ss';
}

// Get transactions (without pagination for count)
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Now get paginated results
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);

// Get payment methods for filter dropdown
$payment_methods = ['cash', 'mobile'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Orders Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --gray-color: #adb5bd;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
        }

        .page-header {
            background-color: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 15px 20px;
        }

        .badge-cash {
            background-color: var(--success-color);
            color: white;
        }

        .badge-mobile {
            background-color: var(--accent-color);
            color: white;
        }

        .transaction-details {
            background-color: var(--light-color);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .filter-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .pagination .page-link {
            color: var(--primary-color);
        }

        .table-responsive {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .table th {
            background-color: var(--light-color);
            border-top: none;
        }

        .action-btns .btn {
            padding: 5px 10px;
            font-size: 14px;
        }

        .transaction-summary {
            background-color: var(--light-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .summary-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .summary-card h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .summary-card p {
            color: var(--gray-color);
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .summary-card {
                margin-bottom: 15px;
            }
        }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
        }
        /* Add to your existing styles */
.badge-refunded {
    background-color: var(--danger-color);
    color: white;
}

.badge-partial-refund {
    background-color: var(--warning-color);
    color: var(--dark-color);
}

.badge-completed {
    background-color: var(--success-color);
    color: white;
}

.text-danger {
    color: var(--danger-color) !important;
}

.text-success {
    color: var(--success-color) !important;
}

.fw-bold {
    font-weight: 600;
}

/* POS manage page consistency */
.container-fluid.py-4 {
    max-width: 1380px;
}

.page-header {
    border: 1px solid #dbe3ea;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
    margin-bottom: 20px;
}

.filter-section {
    border: 1px solid #dbe3ea;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
    margin-bottom: 20px;
}

.filter-section .form-control,
.filter-section .form-select {
    min-height: 40px;
    border-radius: 8px;
    border-color: #cfd8e3;
}

.filter-section .form-control:focus,
.filter-section .form-select:focus {
    border-color: #78be20;
    box-shadow: 0 0 0 3px rgba(120, 190, 32, 0.14);
}

.table-responsive {
    border: 1px solid #dbe3ea;
    border-radius: 12px;
    padding: 0;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}

.table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #d9e1ea;
    padding: 12px 10px;
}

.table td {
    padding: 12px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #edf2f7;
}

.action-btns {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btns .btn {
    min-width: 34px;
    min-height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.card {
    border: 1px solid #dbe3ea;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}

.pagination {
    gap: 6px;
    flex-wrap: wrap;
}

.pagination .page-link {
    min-width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #d1dae5;
    color: #334155;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    background: #fff;
}

.pagination .page-link:hover {
    border-color: #78be20;
    background: #f5faef;
    color: #111827;
}

.pagination .page-item.active .page-link {
    background: #78be20;
    border-color: #78be20;
    color: #0b0f14;
}

@media (max-width: 767.98px) {
    .filter-section .col-md-3.d-flex.align-items-end {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .filter-section .col-md-3.d-flex.align-items-end .btn {
        width: 100%;
    }

    .action-btns {
        justify-content: center;
    }
}
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2><i class="fas fa-shopping-cart me-2"></i> POS Orders</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">POS Orders</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-md-6 text-md-end">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Updated Transaction Summary Cards -->
<div class="row transaction-summary">
    <div class="col-md-3">
        <div class="summary-card">
            <h3><?= number_format($total_rows) ?></h3>
            <p>Total Transactions</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <h3>₨<?php
                // Calculate gross sales minus refunds
                $total_sales = $conn->query("
                    SELECT 
                        COALESCE(SUM(t.total), 0) - 
                        COALESCE(SUM(r.refund_amount), 0) as net_sales
                    FROM pos_transactions t
                    LEFT JOIN pos_refunds r ON r.original_transaction_id = t.id
                ")->fetch_assoc()['net_sales'];
                echo number_format($total_sales, 2);
            ?></h3>
            <p>Net Sales</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <h3>₨<?php
                $total_refunds = $conn->query("
                    SELECT COALESCE(SUM(refund_amount), 0) as total 
                    FROM pos_refunds
                ")->fetch_assoc()['total'];
                echo number_format($total_refunds, 2);
            ?></h3>
            <p>Total Refunds</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="summary-card">
            <h3><?php
                $cash_sales = $conn->query("
                    SELECT COUNT(*) as total 
                    FROM pos_transactions 
                    WHERE payment_method = 'cash'
                ")->fetch_assoc()['total'];
                echo number_format($cash_sales);
            ?></h3>
            <p>Cash Payments</p>
        </div>
    </div>
</div>

        <!-- Filters Section -->
        <div class="filter-section">
            <form method="get" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="">All Methods</option>
                            <?php foreach ($payment_methods as $method): ?>
                                <option value="<?= $method ?>" <?= $filter_payment === $method ? 'selected' : '' ?>>
                                    <?= ucfirst($method) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="customer" class="form-label">Customer</label>
                        <input type="text" class="form-control" id="customer" name="customer" placeholder="Name or phone" value="<?= htmlspecialchars($filter_customer) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="table-responsive">
            <!-- Updated Transactions Table -->
<table class="table table-hover">
    <thead>
        <tr>
            <th>ID</th>
            <th>Date/Time</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Refunded</th>
            <th>Net</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Replacement Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $transaction): 
            $items = json_decode($transaction['items_sold'], true);
            // Get refund data for this transaction
            $refund_query = $conn->prepare("
                SELECT COALESCE(SUM(refund_amount), 0) as total_refunded
                FROM pos_refunds
                WHERE original_transaction_id = ?
            ");
            $refund_query->bind_param("i", $transaction['id']);
            $refund_query->execute();
            $refund_result = $refund_query->get_result();
            $refund_data = $refund_result->fetch_assoc();
            $refund_query->close();
            
            $total_refunded = $refund_data['total_refunded'] ?? 0;
            $net_amount = $transaction['total'] - $total_refunded;
            $status = $total_refunded > 0 ? 
                     ($total_refunded >= $transaction['total'] ? 'Fully Refunded' : 'Partially Refunded') : 
                     'Completed';
        ?>
            <tr>
                <td>#<?= $transaction['id'] ?></td>
                <td><?= date('M j, Y h:i A', strtotime($transaction['created_at'])) ?></td>
                <td>
                    <?= htmlspecialchars($transaction['customer_name']) ?>
                    <?php if (!empty($transaction['customer_phone'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($transaction['customer_phone']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= count($items) ?></td>
                <td>₨<?= number_format($transaction['total'], 2) ?></td>
                <td class="<?= $total_refunded > 0 ? 'text-danger' : '' ?>">
                    <?= $total_refunded > 0 ? '-₨' . number_format($total_refunded, 2) : '₨0.00' ?>
                </td>
                <td class="fw-bold">₨<?= number_format($net_amount, 2) ?></td>
                <td>
                    <span class="badge rounded-pill badge-<?= $transaction['payment_method'] ?>">
                        <?= ucfirst($transaction['payment_method']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge rounded-pill bg-<?= 
                        $status == 'Completed' ? 'success' : 
                        ($status == 'Partially Refunded' ? 'warning' : 'danger') 
                    ?>">
                        <?= $status ?>
                    </span>
                </td>
                <td>
    <?php
    $replacement_status = $conn->query("
        SELECT COUNT(*) as replacements
        FROM pos_replacements
        WHERE original_transaction_id = {$transaction['id']}
    ")->fetch_assoc()['replacements'];
    
    if ($replacement_status > 0) {
        echo '<span class="badge rounded-pill bg-info">Replaced</span>';
    } else {
        echo '<span class="badge rounded-pill bg-secondary">None</span>';
    }
    ?>
</td>
                <td class="action-btns">
                    <button class="btn btn-sm btn-outline-primary view-details" data-id="<?= $transaction['id'] ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger refund-btn" data-id="<?= $transaction['id'] ?>">
                        <i class="fas fa-undo"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning replace-btn" data-id="<?= $transaction['id'] ?>">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <a href="api/receipt.php?id=<?= $transaction['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-receipt"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>
        <!-- Add this after the transactions table -->
<div class="card mt-4">
    <div class="card-header">
        <h5><i class="fas fa-history me-2"></i> Recent Refunds</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Refund ID</th>
                    <th>Original Order</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $refunds = $conn->query("
                    SELECT r.*, t.id as transaction_id
                    FROM pos_refunds r
                    JOIN pos_transactions t ON r.original_transaction_id = t.id
                    ORDER BY r.refund_date DESC
                    LIMIT 5
                ")->fetch_all(MYSQLI_ASSOC);
                
                foreach ($refunds as $refund): ?>
                    <tr>
                        <td>#<?= $refund['id'] ?></td>
                        <td>#<?= $refund['transaction_id'] ?></td>
                        <td><?= date('M j, Y h:i A', strtotime($refund['refund_date'])) ?></td>
                        <td class="text-danger">-₨<?= number_format($refund['refund_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($refund['processed_by']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($filter_date) ? '&date='.$filter_date : '' ?><?= !empty($filter_payment) ? '&payment_method='.$filter_payment : '' ?><?= !empty($filter_customer) ? '&customer='.$filter_customer : '' ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($filter_date) ? '&date='.$filter_date : '' ?><?= !empty($filter_payment) ? '&payment_method='.$filter_payment : '' ?><?= !empty($filter_customer) ? '&customer='.$filter_customer : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($filter_date) ? '&date='.$filter_date : '' ?><?= !empty($filter_payment) ? '&payment_method='.$filter_payment : '' ?><?= !empty($filter_customer) ? '&customer='.$filter_customer : '' ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Refund Modal -->
    <div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Refund for Transaction #<span id="refundTransactionId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="refundForm" method="POST">
                        <input type="hidden" id="refundTransactionIdInput" name="transaction_id">
                        <div class="mb-3">
                            <label for="refundReason" class="form-label">Reason for Refund</label>
                            <select class="form-select" id="refundReason" name="reason" required>
                                <option value="">Select reason</option>
                                <option value="Customer Changed Mind">Customer Changed Mind</option>
                                <option value="Defective Product">Defective Product</option>
                                <option value="Wrong Item Received">Wrong Item Received</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Items to Refund</label>
                            <div class="table-responsive">
                                <table class="table" id="refundItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Original Price</th>
                                            <th>Qty to Refund</th>
                                            <th>Refund Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Items will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="fw-bold">Total Refund: <span id="totalRefundAmount">₨0.00</span></div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-money-bill-wave me-1"></i> Process Refund
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Replacement Modal -->
    <div class="modal fade" id="replacementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Process Replacement for Transaction #<span id="replacementTransactionId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="replacementForm" method="POST">
                        <input type="hidden" id="replacementTransactionIdInput" name="original_transaction_id">
                        <div class="mb-3">
                            <label for="replacementReason" class="form-label">Reason for Replacement</label>
                            <select class="form-select" id="replacementReason" name="reason" required>
                                <option value="">Select reason</option>
                                <option value="Defective Product">Defective Product</option>
                                <option value="Wrong Item Received">Wrong Item Received</option>
                                <option value="Size/Color Exchange">Size/Color Exchange</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Items to Replace</label>
                            <div class="table-responsive">
                                <table class="table" id="replacementItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Original Item</th>
                                            <th>Qty</th>
                                            <th>Replace With</th>
                                            <th>Qty</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Items will be populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-exchange-alt me-1"></i> Process Replacement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details #<span id="modalTransactionId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="transactionDetails">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="printReceiptBtn" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print me-1"></i> Print Receipt
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div class="toast-container">
        <div id="notificationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Notification function
            function showNotification(message, type = 'success') {
                const toast = new bootstrap.Toast(document.getElementById('notificationToast'));
                $('#notificationToast .toast-body').text(message);
                $('#notificationToast').removeClass('bg-success bg-danger').addClass(`bg-${type}`);
                toast.show();
            }

            // Refresh transactions (simple page reload for now)
            function loadTransactions() {
    $.ajax({
        url: 'api/get_transactions.php', // Create this endpoint
        method: 'GET',
        data: {
            page: <?= $page ?>,
            date: '<?= $filter_date ?>',
            payment_method: '<?= $filter_payment ?>',
            customer: '<?= $filter_customer ?>'
        },
        success: function(response) {
            $('.table-responsive').html(response);
        },
        error: function() {
            showNotification('Failed to refresh transactions', 'danger');
        }
    });
}

            // Transaction details modal
            $('.view-details').click(function() {
                const transactionId = $(this).data('id');
                $('#modalTransactionId').text(transactionId);
                
                $.ajax({
                    url: 'api/get_transaction_details.php',
                    method: 'GET',
                    data: { id: transactionId },
                    success: function(response) {
                        $('#transactionDetails').html(response);
                        $('#printReceiptBtn').attr('href', 'api/receipt.php?id=' + transactionId);
                        $('#transactionModal').modal('show');
                    },
                    error: function() {
                        $('#transactionDetails').html('<div class="alert alert-danger">Error loading transaction details</div>');
                        $('#transactionModal').modal('show');
                    }
                });
            });

            // Refund functionality
            // Refund button click handler - updated version
$(document).on('click', '.refund-btn', function() {
    const transactionId = $(this).data('id');
    $('#refundTransactionId').text(transactionId);
    $('#refundTransactionIdInput').val(transactionId);
    
    const refundModal = $('#refundModal');
    refundModal.find('.modal-body').html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Loading items...</p>
        </div>
    `);
    refundModal.modal('show');
    
    // Get transaction items with refund data
    $.ajax({
        url: 'api/get_transaction_items.php',
        method: 'GET',
        data: { 
            transaction_id: transactionId,
            with_refunds: true // Request refund data
        },
        dataType: 'json',
        success: function(response) {
            if (!response || !response.success) {
                refundModal.find('.modal-body').html(`
                    <div class="alert alert-danger">
                        Error: ${response.message || 'Failed to load items'}
                    </div>
                `);
                return;
            }

            if (!Array.isArray(response.items) || response.items.length === 0) {
                refundModal.find('.modal-body').html(`
                    <div class="alert alert-warning">
                        No items found for this transaction
                    </div>
                `);
                return;
            }

            // Build refund form
            let formHTML = `
                <form id="refundForm" method="POST">
                    <input type="hidden" name="transaction_id" value="${transactionId}">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label for="refundReason" class="form-label">Reason for Refund</label>
                        <select class="form-select" id="refundReason" name="reason" required>
                            <option value="">Select reason</option>
                            <option value="Customer Changed Mind">Customer Changed Mind</option>
                            <option value="Defective Product">Defective Product</option>
                            <option value="Wrong Item Received">Wrong Item Received</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Items to Refund</label>
                        <div class="table-responsive">
                            <table class="table" id="refundItemsTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Price</th>
                                        <th>Original Qty</th>
                                        <th>Already Refunded</th>
                                        <th>Qty to Refund</th>
                                        <th>Refund Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>`;

            response.items.forEach(item => {
                const alreadyRefunded = item.refunded_quantity || 0;
                const maxRefundable = item.quantity - alreadyRefunded;
                
                if (maxRefundable <= 0) return; // Skip fully refunded items

                formHTML += `
                    <tr data-item-id="${item.id}">
                        <td>${item.product_name}</td>
                        <td>₨${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>${item.quantity}</td>
                        <td>${alreadyRefunded}</td>
                        <td>
                            <input type="number" class="form-control refund-qty" 
                                   min="1" max="${maxRefundable}" 
                                   value="1" data-price="${item.unit_price}">
                        </td>
                        <td class="item-refund-amount">₨${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-refund-item">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                        <input type="hidden" name="items[${item.id}][transaction_item_id]" value="${item.id}">
                        <input type="hidden" name="items[${item.id}][product_id]" value="${item.product_id}">
                        <input type="hidden" name="items[${item.id}][quantity]" value="1">
                        <input type="hidden" name="items[${item.id}][refund_amount]" value="${item.unit_price}">
                        <input type="hidden" name="items[${item.id}][max_quantity]" value="${maxRefundable}">
                    </tr>`;
            });

            formHTML += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-bold">Total Refund: <span id="totalRefundAmount">₨0.00</span></div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-money-bill-wave me-1"></i> Process Refund
                        </button>
                    </div>
                </form>`;

            refundModal.find('.modal-body').html(formHTML);
            
            // Set up event handlers
            $('.refund-qty').on('change', function() {
                const max = parseInt($(this).attr('max'));
                let qty = parseInt($(this).val());
                
                // Validate quantity
                if (qty > max) {
                    qty = max;
                    $(this).val(max);
                } else if (qty < 1) {
                    qty = 1;
                    $(this).val(1);
                }
                
                const price = $(this).data('price');
                const amount = qty * price;
                
                $(this).closest('tr').find('.item-refund-amount').text('₨' + amount.toFixed(2));
                $(this).closest('tr').find('input[name*="[quantity]"]').val(qty);
                $(this).closest('tr').find('input[name*="[refund_amount]"]').val(amount);
                updateTotalRefund();
            });
            
            $('.remove-refund-item').on('click', function() {
                $(this).closest('tr').remove();
                updateTotalRefund();
            });
            
            updateTotalRefund();
        },
        error: function(xhr, status, error) {
            refundModal.find('.modal-body').html(`
                <div class="alert alert-danger">
                    Failed to load items: ${error}
                </div>
            `);
        }
    });
});



            // Replacement functionality
            // Updated replacement button click handler
$(document).on('click', '.replace-btn', function() {
    const transactionId = $(this).data('id');
    $('#replacementTransactionId').text(transactionId);
    $('#replacementTransactionIdInput').val(transactionId);
    
    const replacementModal = $('#replacementModal');
    replacementModal.find('.modal-body').html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p>Loading items...</p>
        </div>
    `);
    replacementModal.modal('show');
    
    // Get transaction items with refund data
    $.ajax({
        url: 'api/get_transaction_items.php',
        method: 'GET',
        data: { 
            transaction_id: transactionId,
            with_refunds: true
        },
        dataType: 'json',
        success: function(response) {
            if (!response || !response.success) {
                replacementModal.find('.modal-body').html(`
                    <div class="alert alert-danger">
                        Error: ${response.message || 'Failed to load items'}
                    </div>
                `);
                return;
            }

            if (!Array.isArray(response.items) || response.items.length === 0) {
                replacementModal.find('.modal-body').html(`
                    <div class="alert alert-warning">
                        No items found for this transaction
                    </div>
                `);
                return;
            }

            // Build replacement form
            let formHTML = `
                <form id="replacementForm" method="POST">
                    <input type="hidden" name="original_transaction_id" value="${transactionId}">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label for="replacementReason" class="form-label">Reason for Replacement</label>
                        <select class="form-select" id="replacementReason" name="reason" required>
                            <option value="">Select reason</option>
                            <option value="Defective Product">Defective Product</option>
                            <option value="Wrong Item Received">Wrong Item Received</option>
                            <option value="Size/Color Exchange">Size/Color Exchange</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Items Available for Replacement</label>
                        <div class="table-responsive">
                            <table class="table" id="replacementItemsTable">
                                <thead>
                                    <tr>
                                        <th>Original Item</th>
                                        <th>Original Qty</th>
                                        <th>Refunded Qty</th>
                                        <th>Available Qty</th>
                                        <th>Replace With</th>
                                        <th>New Qty</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>`;

            response.items.forEach(item => {
                const availableQty = item.quantity - (item.refunded_quantity || 0);
                
                // Skip fully refunded items
                if (availableQty <= 0) return;

                formHTML += `
                    <tr data-item-id="${item.id}">
                        <td>${item.product_name} (${item.product_sku || 'N/A'})</td>
                        <td>${item.quantity}</td>
                        <td>${item.refunded_quantity || 0}</td>
                        <td>${availableQty}</td>
                        <td>
                            <select class="form-select replacement-product" required>
                                <option value="">Loading products...</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control new-qty" 
                                   min="1" max="${availableQty}" value="1">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-replacement-item">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                        <input type="hidden" name="replacements[${item.id}][original_item_id]" value="${item.id}">
                        <input type="hidden" name="replacements[${item.id}][original_product_id]" value="${item.product_id}">
                        <input type="hidden" name="replacements[${item.id}][original_quantity]" value="1">
                        <input type="hidden" name="replacements[${item.id}][new_product_id]" value="">
                        <input type="hidden" name="replacements[${item.id}][quantity]" value="1">
                        <input type="hidden" name="replacements[${item.id}][max_quantity]" value="${availableQty}">
                    </tr>`;
            });

            formHTML += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-exchange-alt me-1"></i> Process Replacement
                        </button>
                    </div>
                </form>`;

            replacementModal.find('.modal-body').html(formHTML);
            
            // Load replacement products for each item
            response.items.forEach(item => {
                const availableQty = item.quantity - (item.refunded_quantity || 0);
                if (availableQty > 0) {
                    loadReplacementProducts(item.product_id, item.id);
                }
            });
            
            // Set up event handlers
            $('.new-qty').on('change', function() {
                const max = parseInt($(this).attr('max'));
                let qty = parseInt($(this).val());
                
                // Validate quantity
                if (qty > max) {
                    qty = max;
                    $(this).val(max);
                } else if (qty < 1) {
                    qty = 1;
                    $(this).val(1);
                }
                
                $(this).closest('tr').find('input[name*="[quantity]"]').val(qty);
            });
            
            $('.remove-replacement-item').on('click', function() {
                $(this).closest('tr').remove();
            });
        },
        error: function(xhr, status, error) {
            replacementModal.find('.modal-body').html(`
                <div class="alert alert-danger">
                    Failed to load items: ${error}
                </div>
            `);
        }
    });
});

// Updated loadReplacementProducts function
function loadReplacementProducts(excludeId, itemId) {
    $.ajax({
        url: 'api/get_products.php',
        method: 'GET',
        data: { exclude: excludeId },
        dataType: 'json',
        success: function(response) {
            if (!response || !response.success) {
                console.error('Failed to load products:', response.message);
                return;
            }

            const select = $(`tr[data-item-id="${itemId}"] .replacement-product`);
            select.empty();
            select.append('<option value="">Select replacement</option>');
            
            if (Array.isArray(response.products)) {
                response.products.forEach(product => {
                    select.append(
                        `<option value="${product.id}" data-price="${product.current_price}">
                            ${product.product_name} (${product.product_sku || 'N/A'}) - 
                            ₨${parseFloat(product.current_price).toFixed(2)} - 
                            Stock: ${product.quantity}
                        </option>`
                    );
                });
                
                select.on('change', function() {
                    const selectedOption = $(this).find('option:selected');
                    const productId = selectedOption.val();
                    const price = selectedOption.data('price');
                    
                    $(this).closest('tr').find('input[name*="[new_product_id]"]').val(productId);
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading products:', error);
            $(`tr[data-item-id="${itemId}"] .replacement-product`).html(
                '<option value="">Error loading products</option>'
            );
        }
    });
}

            // Helper function to update total refund amount
            // Helper function to update total refund amount
function updateTotalRefund() {
    let total = 0;
    $('input[name*="[refund_amount]"]').each(function() {
        total += parseFloat($(this).val()) || 0;
    });
    $('#totalRefundAmount').text('₨' + total.toFixed(2));
}

            // Refund form submission - fixed version
$(document).on('submit', '#refundForm', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();
    
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    
    // Collect form data
    const formData = form.serializeArray();
    const items = [];
    
    $('#refundItemsTable tbody tr').each(function() {
        items.push({
            transaction_item_id: $(this).find('input[name*="[transaction_item_id]"]').val(),
            product_id: $(this).find('input[name*="[product_id]"]').val(),
            quantity: $(this).find('input[name*="[quantity]"]').val(),
            refund_amount: $(this).find('input[name*="[refund_amount]"]').val()
        });
    });
    
    // Add items to form data
    formData.push({ name: 'items', value: JSON.stringify(items) });
    
    $.ajax({
        url: 'api/process_refund.php',
        method: 'POST',
        data: $.param(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success message
                alert('Refund processed successfully! Refund ID: ' + response.refund_id);
                $('#refundModal').modal('hide');
                
                // Optional: Update the page without full reload
                // loadTransactions();
                
                // Full reload to reflect changes
                location.reload();
            } else {
                alert('Error: ' + response.message);
                submitBtn.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to process refund. Please check console for details.');
            console.error('Refund error:', xhr.responseText);
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});

// Replacement form submission - fixed version
$(document).on('submit', '#replacementForm', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalText = submitBtn.html();
    
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    
    // Collect form data
    const formData = form.serializeArray();
    const replacements = [];
    
    $('#replacementItemsTable tbody tr').each(function() {
        replacements.push({
            original_item_id: $(this).find('input[name*="[original_item_id]"]').val(),
            original_product_id: $(this).find('input[name*="[original_product_id]"]').val(),
            original_quantity: $(this).find('input[name*="[original_quantity]"]').val(),
            new_product_id: $(this).find('input[name*="[new_product_id]"]').val(),
            quantity: $(this).find('input[name*="[quantity]"]').val()
        });
    });
    
    // Add replacements to form data
    formData.push({ name: 'replacements', value: JSON.stringify(replacements) });
    
    $.ajax({
        url: 'api/process_replacement.php',
        method: 'POST',
        data: $.param(formData),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Replacement processed successfully! Replacement ID: ' + response.replacement_id);
                $('#replacementModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
                submitBtn.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            alert('Failed to process replacement. Please check console for details.');
            console.error('Replacement error:', xhr.responseText);
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
        });
    </script>
</body>
</html>
