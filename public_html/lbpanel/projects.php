<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access projects.';
    header('Location: index.php');
    exit;
}

function ensure_projects_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS projects (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      slug VARCHAR(255) NOT NULL,
      category VARCHAR(120) NOT NULL,
      client_name VARCHAR(180) NOT NULL,
      location_city VARCHAR(120) NOT NULL,
      area_sqft VARCHAR(80) NOT NULL,
      short_description TEXT NOT NULL,
      detail_content LONGTEXT NOT NULL,
      cover_image VARCHAR(2048) NOT NULL,
      gallery_json LONGTEXT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      sort_order INT NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_projects_slug (slug),
      KEY idx_projects_active_sort (is_active, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_projects_table($conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrf, $token)) {
        $_SESSION['error'] = 'Invalid request token.';
        header('Location: projects.php');
        exit;
    }

    if (isset($_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        $stmt = $conn->prepare("UPDATE projects SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Project status updated.' : 'Failed to update status.';
    }

    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Project deleted successfully.' : 'Failed to delete project.';
    }

    header('Location: projects.php');
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

if ($q !== '') {
    $like = '%' . $q . '%';
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM projects WHERE title LIKE ? OR category LIKE ? OR client_name LIKE ? OR location_city LIKE ?");
    $countStmt->bind_param('ssss', $like, $like, $like, $like);
    $countStmt->execute();
    $totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $stmt = $conn->prepare("SELECT id, title, slug, category, client_name, location_city, area_sqft, cover_image, is_active, sort_order, created_at FROM projects WHERE title LIKE ? OR category LIKE ? OR client_name LIKE ? OR location_city LIKE ? ORDER BY sort_order ASC, id DESC LIMIT ?, ?");
    $stmt->bind_param('ssssii', $like, $like, $like, $like, $offset, $perPage);
} else {
    $countRes = $conn->query("SELECT COUNT(*) AS total FROM projects");
    $totalRows = (int)($countRes ? ($countRes->fetch_assoc()['total'] ?? 0) : 0);

    $stmt = $conn->prepare("SELECT id, title, slug, category, client_name, location_city, area_sqft, cover_image, is_active, sort_order, created_at FROM projects ORDER BY sort_order ASC, id DESC LIMIT ?, ?");
    $stmt->bind_param('ii', $offset, $perPage);
}

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
    <title>Projects List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="table-container">
    <div class="table-header">Projects</div>

    <div class="d-flex justify-content-end mb-3">
        <a href="add-project.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Project</a>
    </div>

    <form method="GET" class="search-bar mb-3">
        <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by title, category, client, location">
    </form>

    <?php if ($successMessage): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <table class="table table-borderless text-center align-middle">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Title</th>
                <th>Category</th>
                <th>Client</th>
                <th>Location</th>
                <th>Sq Ft</th>
                <th>Sort</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="text-muted">No projects found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php $img = !empty($r['cover_image']) ? ('../' . ltrim($r['cover_image'], '/')) : ''; ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td>
                            <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:82px;height:52px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-start"><?= htmlspecialchars($r['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['category'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['client_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['location_city'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['area_sqft'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int)($r['sort_order'] ?? 0) ?></td>
                        <td>
                            <span class="badge <?= ((int)($r['is_active'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                <?= ((int)($r['is_active'] ?? 0) === 1) ? 'Active' : 'Hidden' ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit-project.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>

                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Toggle this project status?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="toggle_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning">Toggle</button>
                            </form>

                            <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this project?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>

                            <a target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($domain . 'projects/' . ($r['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Preview</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $i]), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
