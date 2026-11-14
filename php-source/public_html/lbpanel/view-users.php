<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();

include 'config/connection.php'; // Database connection

// Handle deletion request
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($delete_id > 0 && ($stmt = $conn->prepare("DELETE FROM users WHERE id = ?"))) {
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'User deleted successfully!';
        } else {
            $_SESSION['error'] = 'Error deleting user!';
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Invalid user id.';
    }

    header('Location: view-users.php');
    exit;
}

// Pagination settings
$usersPerPage = 10; // Number of users per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';

// Fetch total user count for pagination
$totalUsersRow = null;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE username LIKE ? OR name LIKE ? OR role LIKE ?");
    if ($countStmt) {
        $countStmt->bind_param('sss', $like, $like, $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalUsersRow = $countRes ? $countRes->fetch_assoc() : null;
        $countStmt->close();
    }
} else {
    $totalUsersResult = $conn->query("SELECT COUNT(*) as total FROM users");
    $totalUsersRow = $totalUsersResult ? $totalUsersResult->fetch_assoc() : null;
}
$totalUsers = (int)($totalUsersRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalUsers / $usersPerPage));

if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $usersPerPage;

// Fetch users for the current page
$users = [];
$stmt = $q !== ''
    ? $conn->prepare("SELECT * FROM users WHERE username LIKE ? OR name LIKE ? OR role LIKE ? ORDER BY id DESC LIMIT ?, ?")
    : $conn->prepare("SELECT * FROM users ORDER BY id DESC LIMIT ?, ?");
if ($stmt) {
    if ($q !== '') {
        $stmt->bind_param("sssii", $like, $like, $like, $offset, $usersPerPage);
    } else {
        $stmt->bind_param("ii", $offset, $usersPerPage);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $_SESSION['error'] = 'Unable to load users right now.';
}

// Flash messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Users</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
<div class="menu">
        <?php include 'includes/navbar.php';?>
    </div>
<div class="table-container">
    
    <div class="table-header">
        View Users
    </div>
    <div class="search-bar">
        <form method="get" action="view-users.php" class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control" id="searchInput" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search users by username, name, role...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-users.php">Clear</a><?php endif; ?>
        </form>
    </div>
    <!-- Display Success or Error Message -->
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <table class="table table-borderless text-center">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="userTable">
        <?php if (empty($users)): ?>
            <tr>
                <td colspan="5" class="text-muted py-4">No users found.</td>
            </tr>
        <?php else: foreach ($users as $user): ?>
            <tr data-id="<?= (int)$user['id'] ?>">
                <td><?= (int)$user['id'] ?></td>
                <td><?= htmlspecialchars($user['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($user['name'] ?? '') ?></td>
                <td><?= htmlspecialchars($user['role'] ?? '') ?></td>
                <td>
                    <a class="btn btn-action btn-edit" href="edit-user.php?id=<?= (int)$user['id'] ?>">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a class="btn btn-action btn-delete" href="?delete_id=<?= (int)$user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?');">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <div class="pagination-container">
        <nav>
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $i]), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
            </li>
        </ul>
    </nav>
    </div>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
