<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Pagination variables
$resultsPerPage = isset($_GET['results_per_page']) ? (int)$_GET['results_per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $resultsPerPage;

// Handle date filter
$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Base query to fetch sales data
$query = "
    SELECT 
        o.order_number, 
        o.customer_name, 
        oi.product_name, 
        oi.price, 
        oi.quantity, 
        (oi.price * oi.quantity) AS subtotal, 
        o.status, 
        o.discount_amount,
        o.total_amount, 
        o.order_date 
    FROM 
        order_items AS oi
    INNER JOIN 
        orders AS o ON oi.order_id = o.order_number 
    WHERE 1=1
";

// Apply date filter if provided
if ($fromDate && $toDate) {
    $query .= " AND o.order_date BETWEEN '$fromDate' AND '$toDate'";
}

$totalQuery = $query; // Clone query for totals
$query .= " ORDER BY o.order_date DESC LIMIT $resultsPerPage OFFSET $offset";

$result = $conn->query($query);
$salesData = $result->fetch_all(MYSQLI_ASSOC);

// Calculate summary stats
$totalResult = $conn->query($totalQuery);
$totalData = $totalResult->fetch_all(MYSQLI_ASSOC);

$totalSales = count($totalData);
$totalRevenue = array_sum(array_column(array_filter($totalData, fn($row) => $row['status'] === 'Completed'), 'total_amount'));
$pendingSales = count(array_filter($totalData, fn($row) => $row['status'] === 'Pending'));
$processingSales = count(array_filter($totalData, fn($row) => $row['status'] === 'Processing'));
$pendingRevenue = array_sum(array_column(array_filter($totalData, fn($row) => $row['status'] === 'Pending' || $row['status'] === 'Processing'), 'total_amount'));
$canceledSales = count(array_filter($totalData, fn($row) => $row['status'] === 'Cancelled'));
$totalDiscounts = array_sum(array_column($totalData, 'discount_amount'));

// Total pages
$totalPages = ceil($totalSales / $resultsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- Sales Report Container -->
<div class="report-container">
    <div class="row mb-4">
        <!-- Filter Form -->
        <form method="GET" action="" class="col-md-8 d-flex gap-2">
            <input type="date" class="form-control" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" placeholder="From Date">
            <input type="date" class="form-control" name="to_date" value="<?= htmlspecialchars($toDate) ?>" placeholder="To Date">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <!-- Export Button -->
        <form method="POST" action="php/export_sales.php" class="col-md-4 mb-3 text-end">
            <input type="hidden" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
            <input type="hidden" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-file-download"></i> Export as CSV</button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4 text-center">
        <div class="col-md-4 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Total Sales</h5>
                    <h3><?= $totalSales ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Total Revenue</h5>
                    <h3>PKR <?= number_format($totalRevenue, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5>Pending Orders</h5>
                    <h3><?= $pendingSales ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5>Orders in Processing</h5>
                    <h3><?= $processingSales ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Pending Revenue</h5>
                    <h3>PKR <?= number_format($pendingRevenue, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Discounts Offered</h5>
                    <h3>PKR <?= number_format($totalDiscounts, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Data Table -->
    <table class="table table-striped text-center">
        <thead class="sticky-top bg-light">
            <tr>
                <th>Order ID</th>
                <th>Product Name</th>
                <th>Client Name</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
                <th>Discount</th>
                <th>Status</th>
                <th>Order Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salesData)): ?>
                <tr>
                    <td colspan="9">No sales data found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($salesData as $data): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($data['order_number']) ?></td>
                        <td><?= htmlspecialchars($data['product_name']) ?></td>
                        <td><?= htmlspecialchars($data['customer_name']) ?></td>
                        <td><?= htmlspecialchars($data['quantity']) ?></td>
                        <td>PKR <?= number_format($data['price'], 2) ?></td>
                        <td>PKR <?= number_format($data['subtotal'], 2) ?></td>
                        <td>PKR <?= number_format($data['discount_amount'], 2) ?></td>
                        <td>
                            <span class="badge bg-<?= 
    $data['status'] === 'Completed' ? 'success' : 
    ($data['status'] === 'Pending' ? 'warning' : 
    ($data['status'] === 'Processing' ? 'info' : 'danger')) 
?>"><?= htmlspecialchars($data['status']) ?>

                            </span>
                        </td>
                        <td><?= htmlspecialchars($data['order_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <div class="d-flex justify-content-between">
        <form method="GET" action="" class="d-flex align-items-center gap-2">
            <label for="results_per_page" class="form-label">Results per page:</label>
            <select name="results_per_page" class="form-select" onchange="this.form.submit()">
                <option value="10" <?= $resultsPerPage === 10 ? 'selected' : '' ?>>10</option>
                <option value="25" <?= $resultsPerPage === 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $resultsPerPage === 50 ? 'selected' : '' ?>>50</option>
            </select>
        </form>
        <div class="pagination-container">
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&results_per_page=<?= $resultsPerPage ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&results_per_page=<?= $resultsPerPage ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&results_per_page=<?= $resultsPerPage ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
