<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Handle deletion if delete_id is present
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'O deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting doctor!';
    }
    $stmt->close();
    header('Location: cancelled-orders.php');
    exit;
}

// Determine pagination parameters
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$offset = ($page - 1) * $perPage;

// Count total
$totalOrders = 0;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders WHERE status='Cancelled' AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)");
    if ($countStmt) {
        $countStmt->bind_param('ssss', $like, $like, $like, $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalOrders = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }
} else {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE status='Cancelled'");
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $totalOrders = (int)($countRow['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch doctors with limit and offset
$stmt = $q !== ''
    ? $conn->prepare("SELECT id, order_number, customer_name, customer_email, customer_phone, shipping_address, order_date, total_amount, status, payment_status, payment_intent_id, payment_method FROM orders WHERE status='Cancelled' AND (order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?) ORDER BY id DESC LIMIT ? OFFSET ?")
    : $conn->prepare("SELECT id, order_number, customer_name, customer_email, customer_phone, shipping_address, order_date, total_amount, status, payment_status, payment_intent_id, payment_method FROM orders WHERE status='Cancelled' ORDER BY id DESC LIMIT ? OFFSET ?");
if ($q !== '') {
    $stmt->bind_param('ssssii', $like, $like, $like, $like, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$doctors = [];
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}
$stmt->close();

// Check for session messages
$successMessage = '';
$errorMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
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
        Cancelled Orders
    </div>
    <div class="search-bar">
        <form method="get" action="cancelled-orders.php" class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control" id="searchInput" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by order no, name, email, phone...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="cancelled-orders.php">Clear</a><?php endif; ?>
        </form>
    </div>
    <table class="table table-borderless text-center">
        <thead>
            <tr>
                <th>Order No.</th>
                <th>Client Name</th>
                <th>Price</th>
                <th>Status</th>
                <th>Order Items</th>
            </tr>
        </thead>
        <tbody id="orderTable">
            <?php if (empty($doctors)): ?>
                <tr><td colspan="6">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($doctors as $doc): ?>
            <tr>
                <td><?= htmlspecialchars($doc['order_number']) ?></td>
                <td><?= htmlspecialchars($doc['customer_name']) ?></td>
                <td><span class="price">PKR <?= htmlspecialchars($doc['total_amount']) ?></span></td>
                <td><span class="status-indicator status-cancelled"></span><?= htmlspecialchars($doc['status']) ?></td>
                <td>
                     <!-- Trigger button for modal -->
                            <button class="btn btn-primary btn-sm view-order-details" 
                                    data-order-id="<?= $doc['order_number'] ?>" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#orderDetailsModal">
                                View Details
                            </button>
                </td>
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
                    <a class="page-link" href="cancelled-orders.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page-1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                </li>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="cancelled-orders.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $p]), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="cancelled-orders.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page+1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="orderDetailsTable">
                        <!-- Details will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function () {
    $('.view-order-details').on('click', function () {
        const orderId = $(this).data('order-id');
        const modalTitle = `${orderId} Details`; 
        $('#orderDetailsModalLabel').text(modalTitle);

        // **Remove this line to avoid clearing the entire modal body**
        // $('.modal-body').empty();
        
        // Clear only the order details table body
        $('#orderDetailsTable').empty();

        $.ajax({
            url: 'php/fetch_order_details.php',
            type: 'GET',
            data: { order_id: orderId },
            success: function (response) {
                const data = JSON.parse(response);

                if (data.error) {
                    // If there's an error, you can display it somewhere in the modal
                    $('.modal-body').prepend(`<p class="text-danger">${data.error}</p>`);
                    return;
                }

                const { customer_details, order_items } = data;

                // Display customer details before the table
                const customerDetailsHtml = `
                    <div class="customer-details">
                        <p><strong>Name:</strong> ${customer_details.customer_name}</p>
                        <p><strong>Email:</strong> ${customer_details.customer_email}</p>
                        <p><strong>Phone:</strong> ${customer_details.customer_phone}</p>
                        <p><strong>Shipping Address:</strong> ${customer_details.shipping_address}</p>
                        <p><strong>Coupon Code:</strong> ${customer_details.coupon_code || 'None'}</p>
                        <p><strong>Discount:</strong> PKR ${parseFloat(customer_details.discount_amount || 0).toFixed(2)}</p>
                        <p><strong>Total Amount:</strong> PKR ${parseFloat(customer_details.total_amount).toFixed(2)}</p>
                    </div>
                `;
                
                // Insert customer details at the top of the modal body
                $('.customer-details').remove(); // Remove old details if any
                $('.modal-body').prepend(customerDetailsHtml);

                // Display order items in the table
                const tableBody = $('#orderDetailsTable');
                order_items.forEach(item => {
                    const row = `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>PKR ${parseFloat(item.price).toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td>PKR ${parseFloat(item.subtotal).toFixed(2)}</td>
                        </tr>
                    `;
                    tableBody.append(row);
                });
            },
            error: function () {
                $('.modal-body').prepend('<p class="text-danger">Failed to fetch order details.</p>');
            }
        });
    });

    $('.order-status-dropdown').on('change', function () {
        const orderId = $(this).data('order-id');
        const newStatus = $(this).val();
        $.ajax({
            url: 'php/update_order_status.php',
            type: 'POST',
            data: { order_id: orderId, status: newStatus },
            success: function () {
                alert('Order status updated successfully.');
            },
            error: function () {
                alert('Failed to update order status.');
            }
        });
    });
});

</script>
</body>
</html>
