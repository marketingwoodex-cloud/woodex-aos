<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Total Customers (Unique by email)
$totalCustomersQuery = "SELECT COUNT(DISTINCT customer_email) AS total_customers FROM orders";
$totalCustomersResult = $conn->query($totalCustomersQuery);
$totalCustomersRow = $totalCustomersResult->fetch_assoc();
$totalCustomers = $totalCustomersRow['total_customers'];

// Total Revenue (Only Completed Orders)
$totalRevenueQuery = "SELECT SUM(total_amount) AS total_revenue FROM orders WHERE status = 'completed'";
$totalRevenueResult = $conn->query($totalRevenueQuery);
$totalRevenueRow = $totalRevenueResult->fetch_assoc();
$totalRevenue = $totalRevenueRow['total_revenue'];

// Pending Orders
$pendingOrdersQuery = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status = 'pending'";
$pendingOrdersResult = $conn->query($pendingOrdersQuery);
$pendingOrdersRow = $pendingOrdersResult->fetch_assoc();
$pendingOrders = $pendingOrdersRow['pending_orders'];

// Delivered Orders
$deliveredOrdersQuery = "SELECT COUNT(*) AS delivered_orders FROM orders WHERE status = 'completed'";
$deliveredOrdersResult = $conn->query($deliveredOrdersQuery);
$deliveredOrdersRow = $deliveredOrdersResult->fetch_assoc();
$deliveredOrders = $deliveredOrdersRow['delivered_orders'];

// Products Ordered Table
$productsOrderedQuery = "
    SELECT oi.product_name, o.order_number, o.customer_name, oi.price, o.status 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_number
    ORDER BY o.order_date DESC
    LIMIT 10";
$productsOrderedResult = $conn->query($productsOrderedQuery);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Woodex Admin Dashboard</title>
   <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Main Dashboard Content -->
<div class="dashboard-content">
    <div class="menu">
        <?php include 'includes/navbar.php';?>
    </div>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>Woodex Admin Dashboard</h1>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="stats-card bg-gradient-primary">
                <div>
                    <h4>Total Customers</h4>
                    <div class="stats-value"><?= $totalCustomers ?></div>
                </div>
                <div class="stats-icon"><i class="fas fa-user"></i></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="stats-card bg-gradient-success">
                <div>
                    <h4>Total Revenue</h4>
                    <div class="stats-value">PKR <?= $totalRevenue ?></div>
                </div>
                <div class="stats-icon"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="stats-card bg-gradient-warning">
                <div>
                    <h4>Pending Orders</h4>
                    <div class="stats-value"><?= $pendingOrders ?></div>
                </div>
                <div class="stats-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6 mb-4">
            <div class="stats-card bg-gradient-danger">
                <div>
                    <h4>Delivered Orders</h4>
                    <div class="stats-value"><?= $deliveredOrders ?></div>
                </div>
                <div class="stats-icon"><i class="fas fa-truck"></i></div>
            </div>
        </div>
    </div>

    <!-- Box for Product Details Table -->
    <div class="box-container">
        <div class="box-header">Products Ordered</div>
        <div class="box-content">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order No.</th>
                            <th>Product Name</th>
                            <th>Client Name</th>
                            <th>Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $productsOrderedResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['order_number']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td>PKR <?= number_format($row['price'], 2) ?></td>
                        <td>
                            <?php if ($row['status'] == 'Completed'): ?>
                                <span class="badge bg-success">Delivered</span>
                            <?php elseif ($row['status'] == 'Pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($row['status'] == 'Processing'): ?>
                                <span class="badge bg-primary">Processing</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><?= htmlspecialchars($row['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
    
</style>
<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
