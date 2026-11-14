<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';
require_once __DIR__ . '/../config/product_categories.php';

function ensureColumnIfMissing($conn, $table, $column, $definition) {
    $safeTable = str_replace('`', '', (string)$table);
    $safeColumn = str_replace('`', '', (string)$column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

function saveProductImageUpload($file, $index, $uploadDir) {
    if (empty($file['name'][$index])) {
        return '';
    }

    if (!isset($file['error'][$index]) || $file['error'][$index] !== UPLOAD_ERR_OK) {
        throw new Exception('One of the product images failed to upload. Please try a smaller valid image.');
    }

    if ((int)$file['size'][$index] > 5 * 1024 * 1024) {
        throw new Exception('Each product image must be 5MB or less.');
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $imageInfo = @getimagesize($file['tmp_name'][$index]);
    if (!$imageInfo || !in_array($imageInfo['mime'], $allowedMimes, true)) {
        throw new Exception('Only JPG, PNG, WEBP, or GIF product images are allowed.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        throw new Exception('Product image upload directory is not writable.');
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($file['name'][$index]));
    $fileName = time() . '_' . $index . '_' . $safeName;
    $path = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'][$index], $path)) {
        throw new Exception('Failed to save product image.');
    }

    return 'assets/images/products/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $productName = trim($_POST['productName']);
        $productType = $_POST['productType'];
        $sku = trim($_POST['sku']);
        $shortDescription = trim($_POST['shortDescription']);
        $productDetails = trim($_POST['productDetails']);
        $categoryIds = wdx_normalize_category_ids($_POST['categories'] ?? []);
        if (empty($categoryIds)) {
            throw new Exception("Please select at least one category");
        }
        $category = (int)$categoryIds[0];
        $brand = (int)$_POST['brand'];
        $isBestSeller = isset($_POST['isBestSeller']) ? 1 : 0;
        $isSaleItem = isset($_POST['isSaleItem']) ? 1 : 0;

        ensureColumnIfMissing($conn, 'products', 'is_best_seller', "TINYINT(1) NOT NULL DEFAULT 0 AFTER sold_quantity");
        ensureColumnIfMissing($conn, 'products', 'is_sale_item', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_best_seller");
        $hasBestSellerColumn = false;
        $bestColRes = $conn->query("SHOW COLUMNS FROM products LIKE 'is_best_seller'");
        if ($bestColRes && $bestColRes->num_rows > 0) {
            $hasBestSellerColumn = true;
        }
        $hasSaleItemColumn = false;
        $saleColRes = $conn->query("SHOW COLUMNS FROM products LIKE 'is_sale_item'");
        if ($saleColRes && $saleColRes->num_rows > 0) {
            $hasSaleItemColumn = true;
        }

        // Generate slug
        $slug = strtolower(trim($productName));
        
        // Replace & with 'and'
        $slug = str_replace('&', 'and', $slug);
        
        // Replace any non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        // Check for slug uniqueness
        $originalSlug = $slug;
        $check = $conn->prepare("SELECT id FROM products WHERE slug = ?");
        $check->bind_param("s", $slug);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            // Append timestamp to ensure uniqueness
            $slug = $originalSlug . '-' . time();
        }
        // Process images
        $uploadedImages = [];
        $uploadDir = '../../assets/images/products/';
        if (empty($_FILES['images']['name']) || !is_array($_FILES['images']['name'])) {
            throw new Exception("At least one product image is required");
        }

        foreach ($_FILES['images']['name'] as $k => $name) {
            $savedImage = saveProductImageUpload($_FILES['images'], $k, $uploadDir);
            if ($savedImage !== '') {
                $uploadedImages[] = $savedImage;
            }
        }
        
        if (empty($uploadedImages)) {
            throw new Exception("At least one product image is required");
        }
        
        $imagesJson = json_encode($uploadedImages);

        // Process specifications
        $specifications = [];
        if (isset($_POST['specifications'])) {
            foreach ($_POST['specifications'] as $spec) {
                if (!empty($spec['heading']) && !empty($spec['description'])) {
                    $specifications[] = [
                        'heading' => $spec['heading'],
                        'description' => $spec['description']
                    ];
                }
            }
        }
        $specificationsJson = json_encode($specifications);

        // Insert product
        if ($hasBestSellerColumn && $hasSaleItemColumn) {
            $stmt = $conn->prepare("INSERT INTO products 
                (product_name, short_description, product_details, product_type, category, brand_id, product_sku, 
                old_price, current_price, quantity, images, specifications, slug, is_best_seller, is_sale_item)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        } elseif ($hasBestSellerColumn) {
            $stmt = $conn->prepare("INSERT INTO products 
                (product_name, short_description, product_details, product_type, category, brand_id, product_sku, 
                old_price, current_price, quantity, images, specifications, slug, is_best_seller)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        } elseif ($hasSaleItemColumn) {
            $stmt = $conn->prepare("INSERT INTO products 
                (product_name, short_description, product_details, product_type, category, brand_id, product_sku, 
                old_price, current_price, quantity, images, specifications, slug, is_sale_item)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $stmt = $conn->prepare("INSERT INTO products 
                (product_name, short_description, product_details, product_type, category, brand_id, product_sku, 
                old_price, current_price, quantity, images, specifications, slug)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        }
        
        // Set prices/quantity for simple products
        $oldPrice = $currentPrice = $quantity = 0;
        if ($productType === 'simple') {
            $oldPrice = (float)$_POST['oldPrice'];
            $currentPrice = (float)$_POST['currentPrice'];
            $quantity = (int)$_POST['quantity'];
        }
        
        if ($hasBestSellerColumn && $hasSaleItemColumn) {
            $stmt->bind_param("ssssiisddisssii",
                $productName,
                $shortDescription,
                $productDetails,
                $productType,
                $category,
                $brand,
                $sku,
                $oldPrice,
                $currentPrice,
                $quantity,
                $imagesJson,
                $specificationsJson,
                $slug,
                $isBestSeller,
                $isSaleItem
            );
        } elseif ($hasBestSellerColumn) {
            $stmt->bind_param("ssssiisddisssi",
                $productName,
                $shortDescription,
                $productDetails,
                $productType,
                $category,
                $brand,
                $sku,
                $oldPrice,
                $currentPrice,
                $quantity,
                $imagesJson,
                $specificationsJson,
                $slug,
                $isBestSeller
            );
        } elseif ($hasSaleItemColumn) {
            $stmt->bind_param("ssssiisddisssi",
                $productName,
                $shortDescription,
                $productDetails,
                $productType,
                $category,
                $brand,
                $sku,
                $oldPrice,
                $currentPrice,
                $quantity,
                $imagesJson,
                $specificationsJson,
                $slug,
                $isSaleItem
            );
        } else {
            $stmt->bind_param("ssssiisddisss",
                $productName,
                $shortDescription,
                $productDetails,
                $productType,
                $category,
                $brand,
                $sku,
                $oldPrice,
                $currentPrice,
                $quantity,
                $imagesJson,
                $specificationsJson,
                $slug
            );
        }
        
        $conn->begin_transaction();
        if (!$stmt->execute()) {
            throw new Exception($stmt->error ?: 'Failed to save product');
        }
        $productId = $stmt->insert_id;

        if (!wdx_save_product_categories($conn, $productId, $categoryIds)) {
            throw new Exception("Failed to save product categories");
        }

        $conn->commit();
        
        // Store product ID in session for next step if variable product
        if ($productType === 'variable') {
            $_SESSION['current_product_id'] = $productId;
            $_SESSION['success'] = "Product created successfully! Now add variations.";
            header("Location: ../add-products.php");
            exit;
        } else {
            $_SESSION['success'] = "Simple product added successfully!";
            header("Location: ../view-products.php");
            exit;
        }

    } catch (Exception $e) {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: ../add-products.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../add-products.php");
    exit;
}
