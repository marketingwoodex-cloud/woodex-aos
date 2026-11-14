<?php
require_once __DIR__ . '/config/auth_guard.php';
include "config/connection.php";
// Determine pagination parameters
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$offset = ($page - 1) * $perPage;

// Count total
$totalOrders = 0;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?");
    if ($countStmt) {
        $countStmt->bind_param('ssss', $like, $like, $like, $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalOrders = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }
} else {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM orders");
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $totalOrders = (int)($countRow['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch orders with limit and offset
$stmt = $q !== ''
    ? $conn->prepare("SELECT `id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `shipping_address`, `order_date`, `status` FROM orders WHERE order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? ORDER BY order_date DESC LIMIT ? OFFSET ?")
    : $conn->prepare("SELECT `id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `shipping_address`, `order_date`, `status` FROM orders ORDER BY order_date DESC LIMIT ? OFFSET ?");
if ($q !== '') {
    $stmt->bind_param('ssssii', $like, $like, $like, $like, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Customers</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
<?php include 'includes/navbar.php'; ?>
<div class="table-container">
    <div class="table-header">
        View Customers
    </div>
    <div class="search-bar">
        <form method="get" action="view-customers.php" class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control" id="searchInput" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by order no, name, email, phone...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-customers.php">Clear</a><?php endif; ?>
        </form>
    </div>
    <table class="table table-borderless text-center">
        <thead>
            <tr>
                <th>Client ID</th>
                <th>Client Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Product Ordered</th>
                <th>Ordered Date</th>
                <th>Order Status</th>
            </tr>
        </thead>
        <tbody id="clientTable">
            <?php if (empty($customers)): ?>
                <tr><td colspan="6">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($customers as $doc): ?>
                    <tr>
                        <td><?= htmlspecialchars($doc['order_number']) ?></td>
                        <td><?= htmlspecialchars($doc['customer_name']) ?></td>
                        <td><?= htmlspecialchars($doc['customer_email']) ?></td>
                        <td><?= htmlspecialchars($doc['customer_phone']) ?></td>
                        <td>
                            <!-- Trigger button for modal -->
                            <button class="btn btn-primary btn-sm view-order-details" 
                                    data-order-id="<?= $doc['order_number'] ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#orderDetailsModal">
                                View
                            </button>
                        </td>
                        <td><?= htmlspecialchars($doc['order_date']) ?></td>
                        <td><?= htmlspecialchars($doc['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <nav>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="view-customers.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page-1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                </li>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="view-customers.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $p]), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="view-customers.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page+1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <!-- Bootstrap Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsBody">
                        <!-- Order details will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('.view-order-details').on('click', function() {
        const orderId = $(this).data('order-id');

        $.ajax({
            url: 'php/fetch_order_items.php',
            method: 'POST',
            data: { order_id: orderId },
            success: function(response) {
                $('#orderDetailsBody').html(response);
            }
        });
    });
});
</script>
</body>
</html>
