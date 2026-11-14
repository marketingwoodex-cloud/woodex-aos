<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to manage hot products.';
    header('Location: index.php');
    exit;
}

function hotProductsEnsureColumn($conn, $table, $column, $definition) {
    $safeTable = str_replace('`', '', (string)$table);
    $safeColumn = str_replace('`', '', (string)$column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

hotProductsEnsureColumn($conn, 'categories', 'show_in_hot_products', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
hotProductsEnsureColumn($conn, 'categories', 'hot_products_sort_order', "INT NOT NULL DEFAULT 0 AFTER show_in_hot_products");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedIds = $_POST['category_ids'] ?? [];
    $sortOrders = $_POST['sort_order'] ?? [];
    $selected = [];

    if (is_array($selectedIds)) {
        foreach ($selectedIds as $index => $id) {
            $catId = (int)$id;
            if ($catId <= 0) {
                continue;
            }
            $order = 0;
            if (is_array($sortOrders) && isset($sortOrders[$catId])) {
                $order = (int)$sortOrders[$catId];
            }
            $selected[$catId] = [
                'id' => $catId,
                'order' => $order > 0 ? $order : ($index + 1),
                'input_index' => (int)$index,
            ];
        }
    }

    usort($selected, function ($a, $b) {
        if ($a['order'] === $b['order']) {
            return $a['input_index'] <=> $b['input_index'];
        }
        return $a['order'] <=> $b['order'];
    });

    $conn->begin_transaction();
    try {
        if (!$conn->query("UPDATE categories SET show_in_hot_products = 0, hot_products_sort_order = 0")) {
            throw new Exception('Failed to reset hot product categories.');
        }

        if (!empty($selected)) {
            $stmt = $conn->prepare("UPDATE categories SET show_in_hot_products = 1, hot_products_sort_order = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Failed to prepare category update.');
            }
            $position = 1;
            foreach ($selected as $item) {
                $catId = (int)$item['id'];
                $stmt->bind_param('ii', $position, $catId);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to save selected categories.');
                }
                $position++;
            }
            $stmt->close();
        }

        $conn->commit();
        $_SESSION['success'] = 'Hot Products category order saved successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: hot-products-settings.php');
    exit;
}

$categories = [];
$result = $conn->query("SELECT c1.id, c1.category_name, c1.level, c1.is_active, c1.show_in_hot_products, c1.hot_products_sort_order, c2.category_name AS parent_name
    FROM categories c1
    LEFT JOIN categories c2 ON c1.parent_id = c2.id
    ORDER BY c1.show_in_hot_products DESC, CASE WHEN c1.hot_products_sort_order > 0 THEN c1.hot_products_sort_order ELSE 999999 END ASC, c1.path ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hot Products Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .category-name.level-2 { padding-left: 18px; }
        .category-name.level-3 { padding-left: 36px; }
        .sort-input { max-width: 110px; }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container" style="max-width:1100px; margin-top:24px; margin-bottom:40px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="mb-1">Hot Products Settings</h2>
            <p class="text-muted mb-0">Select the categories that appear as Hot Products tabs and set their frontend order.</p>
        </div>
        <a href="category-management/view-categories.php" class="btn btn-outline-secondary">View Categories</a>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="card">
            <div class="card-header"><strong>Category Display Order</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:90px;">Show</th>
                                <th>Category</th>
                                <th>Parent</th>
                                <th style="width:140px;">Tab Order</th>
                                <th style="width:120px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No categories found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                    <?php
                                        $catId = (int)$category['id'];
                                        $isSelected = (int)($category['show_in_hot_products'] ?? 0) === 1;
                                        $sortOrder = (int)($category['hot_products_sort_order'] ?? 0);
                                        $level = (int)($category['level'] ?? 1);
                                    ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="category_ids[]" value="<?= $catId ?>" <?= $isSelected ? 'checked' : '' ?>>
                                        </td>
                                        <td>
                                            <span class="category-name level-<?= $level ?>">
                                                <?= htmlspecialchars((string)$category['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= $category['parent_name'] ? htmlspecialchars((string)$category['parent_name'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">None</span>' ?></td>
                                        <td>
                                            <input class="form-control sort-input" type="number" min="1" name="sort_order[<?= $catId ?>]" value="<?= $sortOrder > 0 ? $sortOrder : '' ?>" placeholder="1">
                                        </td>
                                        <td>
                                            <?php if ((int)$category['is_active'] === 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Hot Products Order</button>
            <button type="reset" class="btn btn-outline-secondary">Reset Unsaved Changes</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
