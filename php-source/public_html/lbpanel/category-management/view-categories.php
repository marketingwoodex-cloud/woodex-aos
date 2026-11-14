<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

$hasHeroLayoutColumn = false;
$heroColRes = $conn->query("SHOW COLUMNS FROM categories LIKE 'hero_layout'");
if ($heroColRes && $heroColRes->num_rows > 0) {
    $hasHeroLayoutColumn = true;
}

$hasHomeSectionColumns = false;
$homeHotCol = $conn->query("SHOW COLUMNS FROM categories LIKE 'show_in_hot_products'");
if ($homeHotCol && $homeHotCol->num_rows > 0) {
    $hasHomeSectionColumns = true;
}

$message = "";

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $category_id = $_GET['toggle_status'];
    $stmt = $conn->prepare("UPDATE categories SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success text-center'>Category status updated!</div>";
    } else {
        $message = "<div class='alert alert-danger text-center'>Error updating status!</div>";
    }
    $stmt->close();
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $checkChildren = $conn->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $checkChildren->bind_param("i", $delete_id);
    $checkChildren->execute();
    $checkChildren->bind_result($childCount);
    $checkChildren->fetch();
    $checkChildren->close();
    
    if ($childCount > 0) {
        $message = "<div class='alert alert-danger text-center'>Cannot delete category - it has subcategories!</div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success text-center'>Category Deleted Successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger text-center'>Error Deleting Category!</div>";
        }
        $stmt->close();
    }
}

// Pagination
$categoriesPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$offset = ($page - 1) * $categoriesPerPage;

// Fetch categories
$totalcategories = 0;
if ($q !== '') {
    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM categories c1 LEFT JOIN categories c2 ON c1.parent_id = c2.id WHERE c1.category_name LIKE ? OR COALESCE(c2.category_name, '') LIKE ?");
    if ($countStmt) {
        $countStmt->bind_param('ss', $like, $like);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $totalcategories = (int)($countRes->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }
} else {
    $totalcategoriesResult = $conn->query("SELECT COUNT(*) as total FROM categories");
    $totalcategories = (int)($totalcategoriesResult->fetch_assoc()['total'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalcategories / $categoriesPerPage));
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $categoriesPerPage;

$selectHero = $hasHeroLayoutColumn ? ", c1.hero_layout" : ", 'default' AS hero_layout";
$selectHomeSections = $hasHomeSectionColumns
    ? ", c1.show_in_hot_products"
    : ", 0 AS show_in_hot_products";
$stmt = $q !== '' ? $conn->prepare("
    SELECT c1.id, c1.category_name, c1.level, c1.is_active{$selectHero}{$selectHomeSections},
           c1.parent_id, c2.category_name as parent_name
    FROM categories c1
    LEFT JOIN categories c2 ON c1.parent_id = c2.id
    WHERE c1.category_name LIKE ? OR COALESCE(c2.category_name, '') LIKE ?
    ORDER BY c1.path ASC
    LIMIT ?, ?
") : $conn->prepare("
    SELECT c1.id, c1.category_name, c1.level, c1.is_active{$selectHero}{$selectHomeSections},
           c1.parent_id, c2.category_name as parent_name
    FROM categories c1
    LEFT JOIN categories c2 ON c1.parent_id = c2.id
    ORDER BY c1.path ASC
    LIMIT ?, ?
");
if ($q !== '') {
    $stmt->bind_param("ssii", $like, $like, $offset, $categoriesPerPage);
} else {
    $stmt->bind_param("ii", $offset, $categoriesPerPage);
}
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .category-name { display: inline-block; }
        .level-1 { font-weight: bold; padding-left: 0; }
        .level-2 { padding-left: 20px; font-style: italic; }
        .level-3 { padding-left: 40px; color: #666; }
        .parent-info { font-size: 0.8rem; color: #6c757d; }
        .toggle-btn { cursor: pointer; }
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .table-container { margin-top: 20px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php';?>
<div class="container table-container">
    <div class="header-actions">
        <h2 class="table-header">Category Inventory</h2>
        <div>
            <a href="add-category.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Category
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-bar mb-3">
        <form method="get" action="view-categories.php" class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control" id="searchInput" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search categories by name or parent...">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="view-categories.php">Clear</a><?php endif; ?>
        </form>
    </div>
    
    <?php if (isset($message)): ?>
        <?php echo $message; ?>
    <?php endif; ?>
    
    <table class="table table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Category Name</th>
                <th>Level</th>
                <th>Parent</th>
                <th>Hero Style</th>
                <th>Hot Products</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="CategoryTable">
            <?php foreach ($categories as $category): ?>
            <tr data-id="<?= $category['id'] ?>" class="level-<?= $category['level'] ?>">
                <td><?= htmlspecialchars($category['id']) ?></td>
                <td><?= htmlspecialchars($category['category_name']) ?></td>
                <td>
                    <?= [1 => 'Main', 2 => 'Sub', 3 => 'Child'][$category['level'] ?? 'Unknown'] ?>
                </td>
                <td>
                    <?= $category['parent_id'] ? 
                        htmlspecialchars($category['parent_name'])." (ID: ".$category['parent_id'].")" : 
                        '<span class="text-muted">None</span>' ?>
                </td>
                <td>
                    <?php $heroLayout = ($category['hero_layout'] ?? 'default') === 'series' ? 'Series' : 'Default'; ?>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($heroLayout) ?></span>
                </td>
                <td>
                    <?php if ((int)($category['show_in_hot_products'] ?? 0) === 1): ?>
                        <span class="badge bg-dark">Marked</span>
                    <?php else: ?>
                        <span class="text-muted">No</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="toggle-btn status-<?= $category['is_active'] ? 'active' : 'inactive' ?>"
                          onclick="toggleStatus(<?= $category['id'] ?>)">
                        <i class="fas fa-toggle-<?= $category['is_active'] ? 'on' : 'off' ?>"></i>
                        <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td>
                    <a href="edit-category.php?id=<?= $category['id'] ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?delete_id=<?= $category['id'] ?>" 
                       class="btn btn-sm btn-danger" 
                       onclick="return confirm('Delete this category?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav>
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $i]), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= htmlspecialchars(http_build_query(['q' => $q, 'page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">Next</a>
            </li>
        </ul>
    </nav>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle status function
    function toggleStatus(categoryId) {
        window.location.href = '?toggle_status=' + categoryId;
    }

    // Auto-hide success message after 3 seconds
    $(document).ready(function(){
        setTimeout(function(){
            $('.alert-success').fadeOut('slow');
        }, 3000);
    });
</script>
</body>
</html>
