<?php
require_once __DIR__ . '/config/auth_guard.php';
/********************************
 * queries.php
 ********************************/
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include "config/connection.php";

// Require login
if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access contact forms.';
    header('Location: index.php');
    exit;
}

// --------------------------------------------------------
// 1. Handle Delete if ?delete_id= is in the URL
// --------------------------------------------------------
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    $stmt_del = $conn->prepare("DELETE FROM contact_forms WHERE id = ?");
    $stmt_del->bind_param("i", $delete_id);
    $stmt_del->execute();
    $stmt_del->close();

    // Redirect to avoid re-deletion on page refresh
    header("Location: queries.php");
    exit;
}

// --------------------------------------------------------
// 2. Handle Mark as "read" (AJAX) if posted
// --------------------------------------------------------
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1 && !empty($_POST['id'])) {
    $id = (int) $_POST['id'];
    $stmt_upd = $conn->prepare("UPDATE contact_forms SET read_status = 'read' WHERE id = ?");
    $stmt_upd->bind_param("i", $id);
    $stmt_upd->execute();

    // Debug check for DB errors
    if ($stmt_upd->error) {
        echo 'DB-ERROR:' . $stmt_upd->error; 
    } else {
        echo 'success';
    }
    $stmt_upd->close();
    exit; // Stop after handling AJAX
}

// --------------------------------------------------------
// 3. Pagination Setup
// --------------------------------------------------------
$results_per_page = 10;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$offset = ($page - 1) * $results_per_page;

$hasPhoneCol = false;
$phoneRes = $conn->query("SHOW COLUMNS FROM contact_forms LIKE 'phone'");
if ($phoneRes && $phoneRes->num_rows > 0) {
    $hasPhoneCol = true;
}

// --------------------------------------------------------
// 4. Count total entries for pagination
// --------------------------------------------------------
$total_entries = 0;
if ($q !== '') {
    if ($hasPhoneCol) {
        $countStmt = $conn->prepare("SELECT COUNT(id) AS total FROM contact_forms WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR topic LIKE ? OR order_number LIKE ? OR message LIKE ?");
        if ($countStmt) {
            $countStmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $total_entries = (int)($countRes->fetch_assoc()['total'] ?? 0);
            $countStmt->close();
        }
    } else {
        $countStmt = $conn->prepare("SELECT COUNT(id) AS total FROM contact_forms WHERE name LIKE ? OR email LIKE ? OR message LIKE ?");
        if ($countStmt) {
            $countStmt->bind_param('sss', $like, $like, $like);
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $total_entries = (int)($countRes->fetch_assoc()['total'] ?? 0);
            $countStmt->close();
        }
    }
} else {
    $countSql = "SELECT COUNT(id) AS total FROM contact_forms";
    $countResult = $conn->query($countSql);
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $total_entries = (int)($countRow['total'] ?? 0);
}

$total_pages = max(1, (int)ceil($total_entries / $results_per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $results_per_page;

// --------------------------------------------------------
// 5. Fetch contact forms for the current page
// --------------------------------------------------------
$result = false;
if ($hasPhoneCol) {
    if ($q !== '') {
        $stmtList = $conn->prepare("SELECT id, name, email, phone, topic, order_number, message, created_at, read_status FROM contact_forms WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR topic LIKE ? OR order_number LIKE ? OR message LIKE ? ORDER BY id DESC LIMIT ?, ?");
        if ($stmtList) {
            $stmtList->bind_param('ssssssii', $like, $like, $like, $like, $like, $like, $offset, $results_per_page);
            $stmtList->execute();
            $result = $stmtList->get_result();
            $stmtList->close();
        }
    } else {
        $stmtList = $conn->prepare("SELECT id, name, email, phone, topic, order_number, message, created_at, read_status FROM contact_forms ORDER BY id DESC LIMIT ?, ?");
        if ($stmtList) {
            $stmtList->bind_param('ii', $offset, $results_per_page);
            $stmtList->execute();
            $result = $stmtList->get_result();
            $stmtList->close();
        }
    }
}

if (!$result) {
    if ($q !== '') {
        $stmtList = $conn->prepare("SELECT id, name, email, message, created_at, read_status FROM contact_forms WHERE name LIKE ? OR email LIKE ? OR message LIKE ? ORDER BY id DESC LIMIT ?, ?");
        if ($stmtList) {
            $stmtList->bind_param('sssii', $like, $like, $like, $offset, $results_per_page);
            $stmtList->execute();
            $result = $stmtList->get_result();
            $stmtList->close();
        }
    } else {
        $stmtList = $conn->prepare("SELECT id, name, email, message, created_at, read_status FROM contact_forms ORDER BY id DESC LIMIT ?, ?");
        if ($stmtList) {
            $stmtList->bind_param('ii', $offset, $results_per_page);
            $stmtList->execute();
            $result = $stmtList->get_result();
            $stmtList->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>View Contact Forms</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- FontAwesome (optional) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <!-- Your custom CSS -->
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

  <!-- Include your navbar -->
  <?php include 'includes/navbar.php'; ?>

  <div class="table-container">
    <div class="table-header">Contact Forms</div>

    <!-- Search Bar -->
    <div class="search-bar">
      <form method="get" action="queries.php" class="d-flex gap-2 align-items-center">
        <input 
          type="text" 
          class="form-control" 
          id="searchInput"
          name="q"
          value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
          placeholder="Search forms by name, email, phone, topic...">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="queries.php">Clear</a><?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <table class="table table-borderless text-center">
      <thead>
        <tr>
          <th>Sr</th>
          <th>Name</th>
           <th>Email</th>
           <th>Phone</th>
           <th>Topic</th>
           <th>Order #</th>
           <th>Message</th>
           <th>Date</th>
           <th>Status</th>
           <th>Action</th>
        </tr>
      </thead>
      <tbody id="contactTable">
        <?php
        if ($result && $result->num_rows > 0) {
            $sr = $offset + 1; 
            while ($row = $result->fetch_assoc()) {
                // If read_status == 'read', display "Read", else "Unread"
                $readText = ($row['read_status'] === 'read') ? 'Read' : 'Unread';
                ?>
                <tr id="row-<?php echo $row['id']; ?>">
                  <td><?php echo $sr++; ?></td>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                   <td><?php echo htmlspecialchars($row['email']); ?></td>
                   <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                   <td><?php echo htmlspecialchars($row['topic'] ?? ''); ?></td>
                   <td><?php echo htmlspecialchars($row['order_number'] ?? ''); ?></td>
                   <td><?php echo htmlspecialchars($row['message']); ?></td>
                   <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                   <td class="statusCell"><?php echo $readText; ?></td>
                   <td>
                    <!-- View Button triggers the modal -->
                    <button 
                      class="btn btn-info btn-sm view-btn"
                      data-id="<?php echo $row['id']; ?>"
                      data-name="<?php echo htmlspecialchars($row['name']); ?>"
                      data-email="<?php echo htmlspecialchars($row['email']); ?>"
                      data-phone="<?php echo htmlspecialchars($row['phone'] ?? ''); ?>"
                      data-topic="<?php echo htmlspecialchars($row['topic'] ?? ''); ?>"
                      data-order="<?php echo htmlspecialchars($row['order_number'] ?? ''); ?>"
                      data-message="<?php echo htmlspecialchars($row['message']); ?>"
                      data-date="<?php echo htmlspecialchars($row['created_at']); ?>"
                      data-bs-toggle="modal" 
                      data-bs-target="#viewModal"
                    >
                      View
                    </button>

                    <!-- Delete Button -->
                    <a 
                      href="?delete_id=<?php echo $row['id']; ?>" 
                      class="btn btn-danger btn-sm"
                      onclick="return confirm('Are you sure you want to delete this entry?');"
                    >
                      Delete
                    </a>
                  </td>
                </tr>
                <?php
            }
        } else {
             echo "<tr><td colspan='10'>No contact forms found.</td></tr>";
         }
        ?>
      </tbody>
    </table>

    <!-- Pagination Links -->
    <div class="pagination-container">
      <nav>
        <ul class="pagination">
          <!-- Previous -->
          <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>">
                Previous
              </a>
            </li>
          <?php endif; ?>

          <!-- Numbered Pages -->
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?php echo ($i == $page ? 'active' : ''); ?>">
              <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'page' => $i]), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $i; ?>
              </a>
            </li>
          <?php endfor; ?>

          <!-- Next -->
          <?php if ($page < $total_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>">
                Next
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    </div>
  </div>

  <!-- Bootstrap Modal for Viewing -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        
        <div class="modal-header">
          <h5 class="modal-title" id="viewModalLabel">Contact Form Details</h5>
          <button 
            type="button" 
            class="btn-close" 
            data-bs-dismiss="modal" 
            aria-label="Close"
          ></button>
        </div>

        <div class="modal-body">
          <p><strong>ID:</strong> <span id="view-id"></span></p>
          <p><strong>Name:</strong> <span id="view-name"></span></p>
          <p><strong>Email:</strong> <span id="view-email"></span></p>
          <p><strong>Phone:</strong> <span id="view-phone"></span></p>
          <p><strong>Topic:</strong> <span id="view-topic"></span></p>
          <p><strong>Order #:</strong> <span id="view-order"></span></p>
          <p><strong>Date:</strong> <span id="view-date"></span></p>
          <hr>
          <p><strong>Message:</strong></p>
          <p id="view-message"></p>
        </div>

        <div class="modal-footer">
          <button 
            type="button" 
            class="btn btn-secondary" 
            data-bs-dismiss="modal"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS / jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js">
  </script>

  <script>
  $(document).ready(function () {

    // 2. On "View" button click => show modal, mark as read
    $('.view-btn').on('click', function() {
      // Extract data-* attributes
      const id      = $(this).data('id');
      const name    = $(this).data('name');
      const email   = $(this).data('email');
      const phone   = $(this).data('phone');
      const topic   = $(this).data('topic');
      const orderNo = $(this).data('order');
      const message = $(this).data('message');
      const date    = $(this).data('date');

      // Populate modal
      $('#view-id').text(id);
      $('#view-name').text(name);
      $('#view-email').text(email);
      $('#view-phone').text(phone || '');
      $('#view-topic').text(topic || '');
      $('#view-order').text(orderNo || '');
      $('#view-date').text(date);
      $('#view-message').text(message);

      // Send AJAX to mark read_status = 'read'
      $.ajax({
        url: 'queries.php', // same file
        type: 'POST',
        data: {
          mark_read: 1,
          id: id
        },
        success: function(response) {
          if (response === 'success') {
            // Immediately update the status cell
            $('#row-' + id).find('.statusCell').text('Read');
          } else {
            console.log('Response:', response); 
          }
        },
        error: function(xhr, status, error) {
          console.error('AJAX Error:', error);
        }
      });
    });

  });
  </script>
</body>
</html>
