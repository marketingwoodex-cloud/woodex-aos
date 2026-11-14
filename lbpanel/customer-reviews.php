<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access customer reviews.';
    header('Location: index.php');
    exit;
}

function ensure_customer_reviews_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS customer_reviews (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      customer_name VARCHAR(150) NOT NULL,
      review_text TEXT NOT NULL,
      rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
      status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      review_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      profile_image VARCHAR(2048) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_customer_reviews_active_date (is_active, status, review_date, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $statusCol = $conn->query("SHOW COLUMNS FROM customer_reviews LIKE 'status'");
    if ($statusCol && $statusCol->num_rows === 0) {
        $conn->query("ALTER TABLE customer_reviews ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER rating");
    }
}

function delete_review_image_file($relativePath) {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '' || strpos($relativePath, 'assets/images/customer-reviews/') !== 0) {
        return;
    }
    $full = '../' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

ensure_customer_reviews_table($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['error'] = 'Invalid request token.';
        header('Location: customer-reviews.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($id > 0 && $action === 'toggle') {
        $stmt = $conn->prepare("UPDATE customer_reviews SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Review status updated.' : 'Failed to update review status.';
    }

    if ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE customer_reviews SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? ('Review ' . $newStatus . '.') : 'Failed to update review approval.';
    }

    if ($id > 0 && $action === 'delete') {
        $imagePath = '';
        $imgStmt = $conn->prepare("SELECT profile_image FROM customer_reviews WHERE id = ? LIMIT 1");
        $imgStmt->bind_param('i', $id);
        $imgStmt->execute();
        $imgRes = $imgStmt->get_result();
        if ($imgRes) {
            $imgRow = $imgRes->fetch_assoc();
            $imagePath = (string)($imgRow['profile_image'] ?? '');
        }
        $imgStmt->close();

        $stmt = $conn->prepare("DELETE FROM customer_reviews WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            delete_review_image_file($imagePath);
        }

        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Review deleted successfully.' : 'Failed to delete review.';
    }

    header('Location: customer-reviews.php');
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'pending');
$visibility = (string)($_GET['visibility'] ?? 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$args = [];

if ($q !== '') {
    $where[] = '(customer_name LIKE ? OR review_text LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ss';
    $args[] = $like;
    $args[] = $like;
}

if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'status = ?';
    $types .= 's';
    $args[] = $status;
}

if ($visibility === 'active' || $visibility === 'inactive') {
    $where[] = 'is_active = ?';
    $types .= 'i';
    $args[] = $visibility === 'active' ? 1 : 0;
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) AS total FROM customer_reviews" . $whereSql;
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$args);
}
$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$sql = "SELECT id, customer_name, rating, review_text, profile_image, review_date, is_active, status FROM customer_reviews" . $whereSql . " ORDER BY review_date DESC, id DESC LIMIT ?, ?";
$types2 = $types . 'ii';
$args2 = $args;
$args2[] = $offset;
$args2[] = $perPage;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$args2);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="table-container">
    <div class="table-header">Customer Reviews</div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search name or review" style="min-width:280px;">
            <select class="form-select" name="status" style="width:170px;">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Approval</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select class="form-select" name="visibility" style="width:170px;">
                <option value="all" <?= $visibility === 'all' ? 'selected' : '' ?>>All Visibility</option>
                <option value="active" <?= $visibility === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $visibility === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button class="btn btn-primary" type="submit">Filter</button>
            <?php if ($q !== '' || $status !== 'all' || $visibility !== 'all'): ?>
                <a href="customer-reviews.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>

        <a href="add-customer-review.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Review</a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-borderless align-middle text-center">
            <thead>
                <tr>
                    <th style="width:90px;">Avatar</th>
                    <th class="text-start">Name</th>
                    <th style="width:110px;">Rating</th>
                    <th style="width:130px;">Approval</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:200px;">Date</th>
                    <th style="width:290px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-muted">No customer reviews found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $name = trim((string)($row['customer_name'] ?? 'Customer'));
                            if ($name === '') $name = 'Customer';
                            $initial = strtoupper(substr($name, 0, 1));
                            $rating = max(0, min(5, (int)($row['rating'] ?? 0)));
                            $img = trim((string)($row['profile_image'] ?? ''));
                            $imgSrc = $img !== '' ? ('../' . ltrim($img, '/')) : '';
                            $active = (int)($row['is_active'] ?? 0) === 1;
                            $approval = (string)($row['status'] ?? 'pending');
                        ?>
                        <tr>
                            <td>
                                <?php if ($imgSrc !== ''): ?>
                                    <img src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <div style="width:44px;height:44px;border-radius:50%;background:#dcebd0;color:#5f874f;display:inline-flex;align-items:center;justify-content:center;font-weight:800;"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-start">
                                <div class="fw-semibold"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="small text-muted text-truncate" style="max-width:420px;"><?= htmlspecialchars((string)($row['review_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span style="color:#f4c531;"><?= str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) ?></span>
                            </td>
                            <td>
                                <?php if ($approval === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($approval === 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $active ? 'bg-success' : 'bg-secondary' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
                            </td>
                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string)$row['review_date'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="edit-customer-review.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>

                                <?php if ($approval !== 'approved'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Approve this review?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <?php endif; ?>

                                <?php if ($approval !== 'rejected'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Reject this review?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" class="d-inline" onsubmit="return confirm('Toggle this review status?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button type="submit" class="btn btn-sm btn-warning"><?= $active ? 'Disable' : 'Enable' ?></button>
                                </form>

                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this review?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'status' => $status, 'visibility' => $visibility, 'page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'status' => $status, 'visibility' => $visibility, 'page' => $i]), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'status' => $status, 'visibility' => $visibility, 'page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
