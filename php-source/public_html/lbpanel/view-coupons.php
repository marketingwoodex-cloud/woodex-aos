<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Handle deletion if delete_id is present
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Coupon deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting coupon!';
    }
    $stmt->close();
    header('Location: view-coupons.php');
    exit;
}

// Pagination parameters
$perPage = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$offset = ($page - 1) * $perPage;

// Count total coupons
$totalCoupons = 0;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM coupons WHERE coupon_code LIKE ? OR discount_type LIKE ? OR status LIKE ?");
    if ($countStmt) {
        $countStmt->bind_param('sss', $like, $like, $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalCoupons = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }
} else {
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM coupons");
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $totalCoupons = (int)($countRow['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalCoupons / $perPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch coupons with limit and offset
$stmt = $q !== ''
    ? $conn->prepare("SELECT id, coupon_code, discount_type, discount_amount, validity_period, status, used_count FROM coupons WHERE coupon_code LIKE ? OR discount_type LIKE ? OR status LIKE ? ORDER BY coupon_code ASC LIMIT ? OFFSET ?")
    : $conn->prepare("SELECT id, coupon_code, discount_type, discount_amount, validity_period, status, used_count FROM coupons ORDER BY coupon_code ASC LIMIT ? OFFSET ?");
if ($q !== '') {
    $stmt->bind_param('sssii', $like, $like, $like, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$coupons = [];
while ($row = $result->fetch_assoc()) {
    $coupons[] = $row;
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
    <title>View Coupons</title>
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
        View Coupons
    </div>

    <!-- Display Success or Error Message -->
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="search-bar">
        <form method="get" action="view-coupons.php" class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control" id="searchInput" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search coupons by code, type, status...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-coupons.php">Clear</a><?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered text-center">
            <thead>
                <tr>
                    <th>Coupon Code</th>
                    <th>Discount Type</th>
                    <th>Discount Amount</th>
                    <th>Validity</th>
                    <th>Status</th>
                    <th>Used</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="7">No coupons found.</td></tr>
                <?php else: ?>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><?= htmlspecialchars($coupon['coupon_code']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($coupon['discount_type'])) ?></td>
                            <td>
                                <?php 
                                if ($coupon['discount_type'] == 'percentage') {
                                    echo htmlspecialchars($coupon['discount_amount']) . '%';
                                } else {
                                    echo '$' . htmlspecialchars($coupon['discount_amount']);
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($coupon['validity_period']) ?></td>
                            <td>
                                <?php if ($coupon['status'] == 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$coupon['used_count'] ?></td>
                            <td>
                                <a href="edit_coupon.php?id=<?= $coupon['id'] ?>" class="btn btn-action btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <a href="view-coupons.php?delete_id=<?= $coupon['id'] ?>" class="btn btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this coupon?');"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <nav>
            <ul class="pagination">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="view-coupons.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page-1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="view-coupons.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $p]), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="view-coupons.php?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page+1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
