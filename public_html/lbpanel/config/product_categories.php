<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}


if (!function_exists('wdx_ensure_product_categories')) {
    function wdx_ensure_product_categories($conn) {
        if (!$conn instanceof mysqli) {
            return false;
        }

        $conn->query("CREATE TABLE IF NOT EXISTS product_categories (
            product_id INT NOT NULL,
            category_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (product_id, category_id),
            KEY idx_product_categories_category (category_id),
            KEY idx_product_categories_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("INSERT IGNORE INTO product_categories (product_id, category_id)
            SELECT id, category
            FROM products
            WHERE category IS NOT NULL AND category > 0");

        return true;
    }
}

if (!function_exists('wdx_normalize_category_ids')) {
    function wdx_normalize_category_ids($raw) {
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('wdx_get_product_category_ids')) {
    function wdx_get_product_category_ids($conn, $productId, $fallbackCategoryId = 0) {
        wdx_ensure_product_categories($conn);

        $ids = [];
        $productId = (int)$productId;
        if ($productId > 0 && ($stmt = $conn->prepare("SELECT category_id FROM product_categories WHERE product_id = ? ORDER BY category_id ASC"))) {
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['category_id'];
            }
            $stmt->close();
        }

        if (empty($ids) && (int)$fallbackCategoryId > 0) {
            $ids[] = (int)$fallbackCategoryId;
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('wdx_save_product_categories')) {
    function wdx_save_product_categories($conn, $productId, $categoryIds) {
        wdx_ensure_product_categories($conn);

        $productId = (int)$productId;
        $categoryIds = wdx_normalize_category_ids($categoryIds);
        if ($productId <= 0 || empty($categoryIds)) {
            return false;
        }

        if ($deleteStmt = $conn->prepare("DELETE FROM product_categories WHERE product_id = ?")) {
            $deleteStmt->bind_param('i', $productId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        $insertStmt = $conn->prepare("INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)");
        if (!$insertStmt) {
            return false;
        }

        foreach ($categoryIds as $categoryId) {
            $insertStmt->bind_param('ii', $productId, $categoryId);
            $insertStmt->execute();
        }
        $insertStmt->close();

        // Keep the legacy single category column synced for older code paths.
        $legacyCategory = (int)$categoryIds[0];
        if ($syncStmt = $conn->prepare("UPDATE products SET category = ? WHERE id = ?")) {
            $syncStmt->bind_param('ii', $legacyCategory, $productId);
            $syncStmt->execute();
            $syncStmt->close();
        }

        return true;
    }
}
