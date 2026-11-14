<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

include "config/connection.php";

// 1. Pagination Variables
$results_per_page = 10; 
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$offset = ($page - 1) * $results_per_page;

// 2. Fetch total subscribers for pagination
$total_subscribers = 0;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(id) AS total FROM subscribers WHERE email LIKE ?");
    if ($countStmt) {
        $countStmt->bind_param('s', $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $total_subscribers = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }
} else {
    $countSql = "SELECT COUNT(id) AS total FROM subscribers";
    $countResult = $conn->query($countSql);
    $countRow = $countResult ? $countResult->fetch_assoc() : null;
    $total_subscribers = (int)($countRow['total'] ?? 0);
}

// 3. Calculate total pages
$total_pages = max(1, (int)ceil($total_subscribers / $results_per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $results_per_page;

// 4. Fetch subscriber data for current page
$result = false;
if ($q !== '') {
    $stmt = $conn->prepare("SELECT id, email, created_at FROM subscribers WHERE email LIKE ? ORDER BY id DESC LIMIT ?, ?");
    if ($stmt) {
        $stmt->bind_param('sii', $like, $offset, $results_per_page);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }
} else {
    $sql = "SELECT id, email, created_at FROM subscribers ORDER BY id DESC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $offset, $results_per_page);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Subscribers</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="table-container">
        <div class="table-header">
            Subscriber List
        </div>
        <div class="search-bar">
            <form method="get" action="view-subscribers.php" class="d-flex gap-2 align-items-center">
                <input type="text" class="form-control" id="searchInput" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search subscriber by email...">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-subscribers.php">Clear</a><?php endif; ?>
            </form>
        </div>
        <table class="table table-borderless text-center">
            <thead>
                <tr>
                    <th>Sr</th>
                    <th>Email</th>
                    <th>Subscription Date</th>
                </tr>
            </thead>
            <tbody id="subscriberTable">
                <?php
                // If we have data, display it; otherwise, show a "No subscribers found" row.
                if ($result && $result->num_rows > 0) {
                    // Determine starting Sr (line) number
                    $sr = $offset + 1;
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $sr++ . "</td>";
                        echo "<td><span class='email'>" . htmlspecialchars($row['email']) . "</span></td>";
                        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No subscribers found.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div class="pagination-container">
            <nav>
                <ul class="pagination">
                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Numbered Links -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i == $page ? 'active' : ''); ?>">
                            <a class="page-link" href="?<?php echo htmlspecialchars(http_build_query(['q' => $q, 'page' => $i]), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Next Button -->
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

    <!-- Bootstrap JS, jQuery, and Font Awesome -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
