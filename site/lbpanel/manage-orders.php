<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access settings.';
    header('Location: index.php');
    exit;
}

// Handle deletion if delete_id is present
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Order deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting doctor!';
    }
    $stmt->close();
    header('Location: manage-orders.php');
    exit;
}

// Pagination
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

// Fetch orders
$stmt = $q !== '' ? $conn->prepare("
    SELECT id, order_number, customer_name, customer_email, customer_phone, 
           shipping_address, order_date, total_amount, status, payment_status, 
           payment_intent_id, payment_method
    FROM orders 
    WHERE order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
") : $conn->prepare("
    SELECT id, order_number, customer_name, customer_email, customer_phone, 
           shipping_address, order_date, total_amount, status, payment_status, 
           payment_intent_id, payment_method
    FROM orders 
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
");
if ($q !== '') {
    $stmt->bind_param('ssssii', $like, $like, $like, $like, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Check for session messages
$successMessage = '';
$errorMessage   = '';
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
  <title>Manage Orders</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <!-- Bootstrap CSS -->
  <link 
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" 
    rel="stylesheet">
  <!-- Font Awesome -->
  <link 
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" 
    rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="table-container">
  <div class="table-header">Manage Orders</div>

  <!-- Search Bar -->
  <div class="search-bar">
    <form method="get" action="manage-orders.php" class="d-flex gap-2 align-items-center">
      <input 
        type="text" 
        id="searchInput" 
        name="q"
        value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
        class="form-control" 
        placeholder="Search by order no, name, email, phone...">
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="manage-orders.php">Clear</a><?php endif; ?>
    </form>
  </div>

  <!-- Orders Table -->
  <table class="table table-borderless text-center">
    <thead>
      <tr>
        <th>Order ID</th>
        <th>Client Name</th>
        <th>Price</th>
        <th>Payment Method</th>
        <th>Order Details</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody id="orderTable">
      <?php if (empty($orders)): ?>
        <tr><td colspan="6">No orders found.</td></tr>
      <?php else: ?>
        <?php foreach ($orders as $doc): ?>
          <tr>
            <td><?= htmlspecialchars($doc['order_number']) ?></td>
            <td><?= htmlspecialchars($doc['customer_name']) ?></td>
            <td>PKR <?= htmlspecialchars(number_format($doc['total_amount'], 2)) ?></td>
            <td><?= htmlspecialchars($doc['payment_method']) ?></td>
            <td>
              <!-- Trigger Order Details Modal -->
              <button 
                class="btn btn-primary btn-sm view-order-details" 
                data-order-id="<?= $doc['order_number'] ?>" 
                data-bs-toggle="modal" 
                data-bs-target="#orderDetailsModal"
              >
                View Details
              </button>
            </td>
            <td>
              <select 
                class="form-select order-status-dropdown" 
                data-order-id="<?= $doc['order_number'] ?>"
              >
                <option value="Pending"    <?= $doc['status'] === 'Pending'    ? 'selected' : '' ?>>Pending</option>
                <option value="Processing" <?= $doc['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                <option value="Completed"  <?= $doc['status'] === 'Completed'  ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled"  <?= $doc['status'] === 'Cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                <option value="Delivered"  <?= $doc['status'] === 'Delivered'  ? 'selected' : '' ?>>Delivered</option>
              </select>
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
            <a class="page-link" href="manage-orders.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
          </li>
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
              <a class="page-link" href="manage-orders.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $p]), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="manage-orders.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div 
  class="modal fade" 
  id="orderDetailsModal" 
  tabindex="-1" 
  aria-labelledby="orderDetailsModalLabel" 
  aria-hidden="true"
>
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
        <button 
          type="button" 
          class="btn-close" 
          data-bs-dismiss="modal" 
          aria-label="Close"
        ></button>
      </div>
      <div class="modal-body" id="printableArea">
        <!-- Customer Details Table -->
        <div id="customerDetails">
          <h5>Customer Information:</h5>
          <div class="row g-2 border rounded p-3">
            <!-- Name -->
            <div class="col-lg-6 d-flex border-bottom py-2">
              <div class="col-4 fw-bold">Name</div>
              <div class="col-8" id="custName"></div>
            </div>
            <!-- Email -->
            <div class="col-lg-6 d-flex border-bottom py-2">
              <div class="col-4 fw-bold">Email</div>
              <div class="col-8" id="custEmail"></div>
            </div>
            <!-- Phone -->
            <div class="col-lg-6 d-flex border-bottom py-2">
              <div class="col-4 fw-bold">Phone</div>
              <div class="col-8" id="custPhone"></div>
            </div>
            <!-- Coupon Code -->
            <div class="col-lg-6 d-flex border-bottom py-2">
              <div class="col-4 fw-bold">Coupon Code</div>
              <div class="col-8" id="custCoupon"></div>
            </div>
            <!-- Discount -->
            <div class="col-lg-6 d-flex border-bottom py-2">
              <div class="col-4 fw-bold">Discount</div>
              <div class="col-8" id="custDiscount"></div>
            </div>
            <!-- Total Amount -->
            <div class="col-lg-6 d-flex border-bottom py-2">
              <div class="col-4 fw-bold">Total Amount</div>
              <div class="col-8" id="custTotal"></div>
            </div>
            <!-- Address -->
            <div class="col-lg-12 d-flex py-2">
              <div class="col-2 fw-bold">Address</div>
              <div class="col-10" id="custAddress"></div>
            </div>
          </div>
        </div>

        <!-- Order Items Table -->
        <h5>Order Items</h5>
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
            <!-- Order details will be loaded here -->
          </tbody>
        </table>
      </div><!-- /.modal-body -->
      <div class="modal-footer">
        <button 
          type="button" 
          class="btn btn-secondary" 
          data-bs-dismiss="modal"
        >
          Close
        </button>
        <button 
          type="button" 
          class="btn btn-primary" 
          id="printButton"
        >
          Print
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delivery Info Modal -->
<div 
  class="modal fade" 
  id="deliveryInfoModal" 
  tabindex="-1" 
  aria-hidden="true"
>
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter Delivery Details</h5>
        <button 
          type="button" 
          class="btn-close" 
          data-bs-dismiss="modal" 
          aria-label="Close"
        ></button>
      </div>
      <div class="modal-body">
        <!-- Hidden field to store the order number -->
        <input type="hidden" id="delOrderId">

        <div class="mb-3">
          <label for="deliveryCompany" class="form-label">Delivery Company</label>
          <input 
            type="text" 
            class="form-control" 
            id="deliveryCompany" 
            placeholder="e.g., FedEx, DHL"
          >
        </div>

        <div class="mb-3">
          <label for="trackingURL" class="form-label">Tracking URL</label>
          <input 
            type="text" 
            class="form-control" 
            id="trackingURL" 
            placeholder="https://..."
          >
        </div>

        <div class="mb-3">
          <label for="trackingCode" class="form-label">Tracking Code</label>
          <input 
            type="text" 
            class="form-control" 
            id="trackingCode" 
            placeholder="ABC123..."
          >
        </div>
      </div>
      <div class="modal-footer">
        <button 
          type="button" 
          class="btn btn-secondary" 
          data-bs-dismiss="modal"
        >
          Cancel
        </button>
        <button 
          type="button" 
          class="btn btn-primary" 
          id="saveDeliveryBtn"
        >
          Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Loader (hidden by default) -->
<div 
  class="modal fade" 
  id="loadingIndicator" 
  tabindex="-1" 
  aria-hidden="true" 
  data-bs-backdrop="static" 
  data-bs-keyboard="false"
>
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3">Updating status...</p>
      </div>
    </div>
  </div>
</div>

<!-- Status Response Modal -->
<div 
  class="modal fade" 
  id="statusResponseModal" 
  tabindex="-1" 
  aria-hidden="true"
>
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Status Update</h5>
        <button 
          type="button" 
          class="btn-close" 
          data-bs-dismiss="modal" 
          aria-label="Close"
        ></button>
      </div>
      <div class="modal-body" id="statusResponseBody">
        <!-- Response message will go here -->
      </div>
      <div class="modal-footer">
        <button 
          type="button" 
          class="btn btn-primary" 
          data-bs-dismiss="modal"
        >
          OK
        </button>
      </div>
    </div>
  </div>
</div>

<script 
  src="https://code.jquery.com/jquery-3.5.1.min.js">
</script>
<script 
  src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js">
</script>
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
</script>

<script>
$(document).ready(function () {
  // 1. View Order Details
  $('.view-order-details').on('click', function () {
    const orderId = $(this).data('order-id');
    $('#orderDetailsModalLabel').text(`Order #${orderId} Details`);

    // Clear previous content
    $('#customerDetails').find('td').empty();
    $('#orderDetailsTable').empty();

    $.ajax({
      url: 'php/fetch_order_details.php',
      type: 'GET',
      data: { order_id: orderId },
      success: function (response) {
        const data = JSON.parse(response);

        if (data.error) {
          $('#customerDetails').prepend(
            `<p class="text-danger">${data.error}</p>`
          );
          return;
        }

        // Populate customer details table
        const customer = data.customer_details;
        $('#custName').text(customer.customer_name);
        $('#custEmail').text(customer.customer_email);
        $('#custPhone').text(customer.customer_phone);
        $('#custCoupon').text(customer.coupon_code || 'None');
        $('#custDiscount').text(
          `PKR${parseFloat(customer.discount_amount || 0).toFixed(2)}`
        );
        $('#custTotal').text(
          `PKR${parseFloat(customer.total_amount).toFixed(2)}`
        );
        $('#custAddress').text(customer.shipping_address);

        // Populate order items table
        data.order_items.forEach(item => {
          $('#orderDetailsTable').append(`
            <tr>
              <td>${item.product_name}</td>
              <td>PKR${parseFloat(item.price).toFixed(2)}</td>
              <td>${item.quantity}</td>
              <td>PKR${parseFloat(item.subtotal).toFixed(2)}</td>
            </tr>
          `);
        });
      },
      error: function () {
        $('#customerDetails').prepend(
          '<p class="text-danger">Failed to fetch order details.</p>'
        );
      }
    });
  });

  // 3. Handle status dropdown changes
  let selectedOrderId = null; // store order ID if "Delivered" is chosen

  $('.order-status-dropdown').on('change', function () {
    const orderId = $(this).data('order-id');
    const newStatus = $(this).val();

    if (newStatus === 'Delivered') {
      // Open the "deliveryInfoModal"
      selectedOrderId = orderId;
      $('#delOrderId').val(orderId); // store in hidden field
      // Reset fields
      $('#deliveryCompany').val('');
      $('#trackingURL').val('');
      $('#trackingCode').val('');
      // Show modal
      const deliveryModal = new bootstrap.Modal('#deliveryInfoModal');
      deliveryModal.show();
    } else {
      // Show loader
      const loaderModal = new bootstrap.Modal('#loadingIndicator', {
        backdrop: 'static',
        keyboard: false
      });
      loaderModal.show();

      // Normal flow for other statuses
      $.ajax({
        url: 'php/update_order_status.php',
        type: 'POST',
        data: { order_id: orderId, status: newStatus },
        success: function (resp) {
          // Hide loader
          loaderModal.hide();

          // Show response
          $('#statusResponseBody').text(resp);
          const responseModal = new bootstrap.Modal('#statusResponseModal');
          responseModal.show();

          // Close the status dropdown modal after a short delay
          setTimeout(() => {
            responseModal.hide();
          }, 2000); // Close after 2 seconds
        },
        error: function () {
          loaderModal.hide();
          $('#statusResponseBody').text('Failed to update order status.');
          const responseModal = new bootstrap.Modal('#statusResponseModal');
          responseModal.show();

          // Close the status dropdown modal after a short delay
          setTimeout(() => {
            responseModal.hide();
          }, 2000); // Close after 2 seconds
        }
      });
    }
  });

  // 4. Save button in "Delivery Info" modal
  $('#saveDeliveryBtn').on('click', function () {
    const orderId = $('#delOrderId').val();
    const deliveryCompany = $('#deliveryCompany').val().trim();
    const trackingURL = $('#trackingURL').val().trim();
    const trackingCode = $('#trackingCode').val().trim();

    if (!deliveryCompany || !trackingURL || !trackingCode) {
      alert('All fields are required.');
      return;
    }

    // Hide the Delivery Info modal
    const deliveryModal = bootstrap.Modal.getInstance(
      document.getElementById('deliveryInfoModal')
    );
    deliveryModal.hide();

    // Show loader
    const loaderModal = new bootstrap.Modal('#loadingIndicator', {
      backdrop: 'static',
      keyboard: false
    });
    loaderModal.show();

    // AJAX call
    $.ajax({
      url: 'php/update_order_status.php',
      type: 'POST',
      data: {
        order_id: orderId,
        status: 'Delivered',
        delivery_company: deliveryCompany,
        tracking_url: trackingURL,
        tracking_code: trackingCode
      },
      success: function (resp) {
        loaderModal.hide();
        $('#statusResponseBody').text(resp);
        const responseModal = new bootstrap.Modal('#statusResponseModal');
        responseModal.show();

        // Close the response modal after a short delay
        setTimeout(() => {
          responseModal.hide();
        }, 2000); // Close after 2 seconds
      },
      error: function () {
        loaderModal.hide();
        $('#statusResponseBody').text('Failed to update order status.');
        const responseModal = new bootstrap.Modal('#statusResponseModal');
        responseModal.show();

        // Close the response modal after a short delay
        setTimeout(() => {
          responseModal.hide();
        }, 2000); // Close after 2 seconds
      }
    });
  });

  // 5. Print button
  $('#printButton').on('click', function () {
    // Gather data from the modal
    const customerName = $('#custName').text().trim();
    const customerEmail = $('#custEmail').text().trim();
    const customerPhone = $('#custPhone').text().trim();
    const customerAddress = $('#custAddress').text().trim();
    const couponCode = $('#custCoupon').text().trim();
    const discount = $('#custDiscount').text().trim();
    const totalAmount = $('#custTotal').text().trim();
    const orderIdText = $('#orderDetailsModalLabel').text();
    // e.g. "Order #ORDER-ABC123 Details"

    // Extract just the order number from the modal label
    const orderNumber = orderIdText.replace('Order #', '').replace(' Details', '');

    // Build a stylish shipping label design
    const printContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Shipping Label - ${orderNumber}</title>
      <style>
        body {
          margin: 0;
          padding: 0;
          background-color: #f0f0f0;
          font-family: Arial, sans-serif;
        }
        .label-container {
          width: 500px;
          margin: 20px auto;
          background: #fff;
          border: 2px dashed #333;
          padding: 20px;
          box-sizing: border-box;
          position: relative;
        }
        .label-header {
          text-align: center;
          margin-bottom: 20px;
        }
        .label-header img {
          max-width: 150px;
          display: block;
          margin: 0 auto 10px auto;
        }
        .label-header h2 {
          margin: 0;
          font-size: 1.5rem;
          color: #333;
        }
        .label-section {
          margin-bottom: 15px;
        }
        .label-section h3 {
          margin: 0 0 10px 0;
          font-size: 1.1rem;
          border-bottom: 1px solid #ddd;
          padding-bottom: 5px;
          color: #555;
        }
        .label-row {
          margin: 0 0 6px 0;
        }
        .label-row strong {
          display: inline-block;
          width: 100px;
          color: #333;
        }
        .label-footer {
          text-align: center;
          margin-top: 20px;
          font-size: 0.9rem;
          color: #666;
        }
      </style>
    </head>
    <body>
      <div class="label-container">
        <div class="label-header">
          <img src="<?php echo htmlspecialchars($domain . 'assets/images/header-logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Woodex Logo">
          <h2>Shipping Label</h2>
        </div>

        <div class="label-section">
          <h3>Order Details</h3>
          <div class="label-row"><strong>Order ID:</strong> ${orderNumber}</div>
          <div class="label-row"><strong>Coupon:</strong> ${couponCode}</div>
          <div class="label-row"><strong>Discount:</strong> ${discount}</div>
          <div class="label-row"><strong>Total:</strong> ${totalAmount}</div>
        </div>

        <div class="label-section">
          <h3>Customer Info</h3>
          <div class="label-row"><strong>Name:</strong> ${customerName}</div>
          <div class="label-row"><strong>Email:</strong> ${customerEmail}</div>
          <div class="label-row"><strong>Phone:</strong> ${customerPhone}</div>
        </div>

        <div class="label-section">
          <h3>Shipping Address</h3>
          <div class="label-row" style="white-space: pre-wrap;">${customerAddress}</div>
        </div>

        <div class="label-footer">
          &copy; 2025 Jasmine Luxe — All rights reserved.
        </div>
      </div>
    </body>
    </html>
    `;

    // Open a new window for printing
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();

    // Once the window has loaded its content, trigger print
    printWindow.onload = () => {
      printWindow.focus();
      printWindow.print();
      printWindow.close();
    };
  });
});
</script>
</body>
</html>
