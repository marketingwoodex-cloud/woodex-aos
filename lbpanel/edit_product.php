<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';
require_once __DIR__ . '/./config/product_categories.php';

function ensureColumnIfMissing($conn, $table, $column, $definition) {
    $safeTable = str_replace('`', '', (string)$table);
    $safeColumn = str_replace('`', '', (string)$column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

function saveProductEditImageUpload($file, $index, $uploadDir, &$uploadError) {
    if (empty($file['name'][$index])) {
        return '';
    }

    if (!isset($file['error'][$index]) || $file['error'][$index] !== UPLOAD_ERR_OK) {
        $uploadError = 'One of the product images failed to upload. Please try a smaller valid image.';
        return '';
    }

    if ((int)$file['size'][$index] > 5 * 1024 * 1024) {
        $uploadError = 'Each product image must be 5MB or less.';
        return '';
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $imageInfo = @getimagesize($file['tmp_name'][$index]);
    if (!$imageInfo || !in_array($imageInfo['mime'], $allowedMimes, true)) {
        $uploadError = 'Only JPG, PNG, WEBP, or GIF product images are allowed.';
        return '';
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $uploadError = 'Product image upload directory is not writable.';
        return '';
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($file['name'][$index]));
    $filename = uniqid('', true) . '_' . $safeName;
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'][$index], $targetPath)) {
        $uploadError = 'Failed to save product image.';
        return '';
    }

    return 'assets/images/products/' . $filename;
}

ensureColumnIfMissing($conn, 'products', 'is_best_seller', "TINYINT(1) NOT NULL DEFAULT 0 AFTER sold_quantity");
ensureColumnIfMissing($conn, 'products', 'is_sale_item', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_best_seller");
$hasBestSellerColumn = false;
$bestSellerColRes = $conn->query("SHOW COLUMNS FROM products LIKE 'is_best_seller'");
if ($bestSellerColRes && $bestSellerColRes->num_rows > 0) {
    $hasBestSellerColumn = true;
}
$hasSaleItemColumn = false;
$saleItemColRes = $conn->query("SHOW COLUMNS FROM products LIKE 'is_sale_item'");
if ($saleItemColRes && $saleItemColRes->num_rows > 0) {
    $hasSaleItemColumn = true;
}

// Validate product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: view-products.php');
    exit;
}

$productId = (int)$_GET['id'];

// Fetch product data
$product = $conn->query("SELECT * FROM products WHERE id = $productId")->fetch_assoc();
if (!$product) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: view-products.php');
    exit;
}

$selectedCategoryIds = wdx_get_product_category_ids($conn, $productId, (int)($product['category'] ?? 0));

// Fetch categories + brands
$categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);
$brands = $conn->query("SELECT id, brand_name FROM brands ORDER BY brand_name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch attributes and their values
$attributes = [];
$attrRes = $conn->query("SELECT * FROM attributes");
while ($attr = $attrRes->fetch_assoc()) {
    $attr['values'] = [];
    $valRes = $conn->query("SELECT * FROM attribute_values WHERE attribute_id = " . (int)$attr['id']);
    while ($val = $valRes->fetch_assoc()) {
        $attr['values'][] = $val;
    }
    $attributes[] = $attr;
}

// Check if product has variations
$hasVariations = $conn->query("SELECT COUNT(*) FROM product_variations WHERE product_id = $productId")->fetch_row()[0] > 0;
$productType = $product['product_type'];

// Decode JSON fields
$specifications = json_decode($product['specifications'], true) ?: [];
$images = json_decode($product['images'], true) ?: [];

// Fetch existing variations if this is a variable product
$variations = [];
if ($hasVariations) {
    $variations = $conn->query("SELECT * FROM product_variations WHERE product_id = $productId")->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = $_POST['productName'] ?? '';
    $productType = $_POST['productType'] ?? 'simple';
    $categoryIds = wdx_normalize_category_ids($_POST['categories'] ?? []);
    if (empty($categoryIds)) {
        $_SESSION['error'] = 'Please select at least one category.';
        header("Location: edit_product.php?id=$productId");
        exit;
    }
    $category = (int)$categoryIds[0];
    $brand = isset($_POST['brand']) ? (int)$_POST['brand'] : 0;
    $oldPrice = isset($_POST['oldPrice']) ? (float)$_POST['oldPrice'] : 0;
    $currentPrice = isset($_POST['currentPrice']) ? (float)$_POST['currentPrice'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $sku = $_POST['sku'] ?? '';
    $shortDescription = $_POST['shortDescription'] ?? '';
    $productDetails = $_POST['productDetails'] ?? '';
    $isBestSeller = isset($_POST['isBestSeller']) ? 1 : 0;
    $isSaleItem = isset($_POST['isSaleItem']) ? 1 : 0;

    // Process specifications
    $specs = [];
    if (isset($_POST['specifications'])) {
        foreach ($_POST['specifications'] as $spec) {
            if (!empty($spec['heading']) && !empty($spec['description'])) {
                $specs[] = [
                    'heading' => $spec['heading'],
                    'description' => $spec['description']
                ];
            }
        }
    }
    $specificationsJson = json_encode($specs);

    // Process images
    $imagesToKeep = $_POST['existingImages'] ?? [];
    $newImages = [];

    // Handle new image uploads
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = '../assets/images/products/';
        $uploadError = '';

        foreach ($_FILES['images']['name'] as $index => $imageName) {
            if (empty($imageName)) continue;
            $savedImage = saveProductEditImageUpload($_FILES['images'], $index, $uploadDir, $uploadError);
            if ($uploadError !== '') {
                $_SESSION['error'] = $uploadError;
                header("Location: edit_product.php?id=$productId");
                exit;
            }
            if ($savedImage !== '') {
                $newImages[] = $savedImage;
            }
        }
    }

    $allImages = array_merge($imagesToKeep, $newImages);
    if (empty($allImages)) {
        $_SESSION['error'] = 'At least one product image is required.';
        header("Location: edit_product.php?id=$productId");
        exit;
    }
    $imagesJson = json_encode($allImages);

    // Update product
    if ($hasBestSellerColumn && $hasSaleItemColumn) {
        $stmt = $conn->prepare("UPDATE products SET 
            product_name = ?, 
            product_type = ?,
            old_price = ?, 
            current_price = ?, 
            quantity = ?, 
            product_sku = ?,
            product_details = ?, 
            specifications = ?, 
            images = ?, 
            category = ?,
            brand_id = ?,
            short_description = ?,
            is_best_seller = ?,
            is_sale_item = ?
            WHERE id = ?");

        $stmt->bind_param('ssddissssiisiii', 
            $productName,
            $productType,
            $oldPrice, 
            $currentPrice, 
            $quantity, 
            $sku,
            $productDetails, 
            $specificationsJson, 
            $imagesJson,
            $category,
            $brand,
            $shortDescription,
            $isBestSeller,
            $isSaleItem,
            $productId
        );
    } elseif ($hasBestSellerColumn) {
        $stmt = $conn->prepare("UPDATE products SET 
            product_name = ?, 
            product_type = ?,
            old_price = ?, 
            current_price = ?, 
            quantity = ?, 
            product_sku = ?,
            product_details = ?, 
            specifications = ?, 
            images = ?, 
            category = ?,
            brand_id = ?,
            short_description = ?,
            is_best_seller = ?
            WHERE id = ?");

        $stmt->bind_param('ssddissssiisii', 
            $productName,
            $productType,
            $oldPrice, 
            $currentPrice, 
            $quantity, 
            $sku,
            $productDetails, 
            $specificationsJson, 
            $imagesJson,
            $category,
            $brand,
            $shortDescription,
            $isBestSeller,
            $productId
        );
    } elseif ($hasSaleItemColumn) {
        $stmt = $conn->prepare("UPDATE products SET 
            product_name = ?, 
            product_type = ?,
            old_price = ?, 
            current_price = ?, 
            quantity = ?, 
            product_sku = ?,
            product_details = ?, 
            specifications = ?, 
            images = ?, 
            category = ?,
            brand_id = ?,
            short_description = ?,
            is_sale_item = ?
            WHERE id = ?");

        $stmt->bind_param('ssddissssiisii', 
            $productName,
            $productType,
            $oldPrice, 
            $currentPrice, 
            $quantity, 
            $sku,
            $productDetails, 
            $specificationsJson, 
            $imagesJson,
            $category,
            $brand,
            $shortDescription,
            $isSaleItem,
            $productId
        );
    } else {
        $stmt = $conn->prepare("UPDATE products SET 
            product_name = ?, 
            product_type = ?,
            old_price = ?, 
            current_price = ?, 
            quantity = ?, 
            product_sku = ?,
            product_details = ?, 
            specifications = ?, 
            images = ?, 
            category = ?,
            brand_id = ?,
            short_description = ?
            WHERE id = ?");

        $stmt->bind_param('ssddissssiisi', 
            $productName,
            $productType,
            $oldPrice, 
            $currentPrice, 
            $quantity, 
            $sku,
            $productDetails, 
            $specificationsJson, 
            $imagesJson,
            $category,
            $brand,
            $shortDescription,
            $productId
        );
    }

    $conn->begin_transaction();
    if ($stmt->execute()) {
        if (!wdx_save_product_categories($conn, $productId, $categoryIds)) {
            $_SESSION['error'] = 'Failed to save product categories.';
            $conn->rollback();
            header("Location: edit_product.php?id=$productId");
            exit;
        }

        $_SESSION['success'] = 'Product updated successfully!';
        
        // Handle variations if this is a variable product
        if ($productType === 'variable' && isset($_POST['variations'])) {
            // First delete existing variations
            $conn->query("DELETE FROM product_variations WHERE product_id = $productId");
            
            // Insert new variations
            foreach ($_POST['variations'] as $variation) {
                if (empty($variation['price'])) continue;
                
                // Validate variation data
                if (!isset($variation['variation_data'])) {
                    $_SESSION['error'] = 'Variation data is missing for one or more variations';
                    continue;
                }

                // Ensure variation_data is properly formatted
                $variationData = is_array($variation['variation_data']) ? 
                    json_encode($variation['variation_data']) : 
                    $variation['variation_data'];
                
                if (json_decode($variationData) === null) {
                    $_SESSION['error'] = 'Invalid variation data format';
                    continue;
                }

                $variationKey = $conn->real_escape_string($variation['variation_key'] ?? '');
                $sku = $conn->real_escape_string($variation['sku'] ?? '');
                $price = (float)$variation['price'];
                $stock = (int)$variation['stock'];
                $image = $conn->real_escape_string($variation['image'] ?? '');

                $stmt = $conn->prepare("INSERT INTO product_variations 
                    (product_id, sku, price, stock, image, variation_data, variation_key) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isddsss', 
                    $productId,
                    $sku,
                    $price,
                    $stock,
                    $image,
                    $variationData,
                    $variationKey
                );
                
                try {
                    if (!$stmt->execute()) {
                        $_SESSION['error'] = 'Error saving variation: ' . $conn->error;
                        error_log('Variation error: ' . $conn->error);
                    }
                } catch (mysqli_sql_exception $e) {
                    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
                    error_log('Database exception: ' . $e->getMessage());
                }
                $stmt->close();
            }
        }
        $conn->commit();
    } else {
        $_SESSION['error'] = 'Error updating product: ' . $conn->error;
        $conn->rollback();
    }
   

    header("Location: edit_product.php?id=$productId");
    exit;
}

// Session messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Product</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for Icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .image-thumbnail {
      width: 100px;
      height: 100px;
      object-fit: cover;
      margin-right: 10px;
      margin-bottom: 10px;
      cursor: pointer;
      border: 2px solid transparent;
    }
    .image-thumbnail.selected {
      border-color: #0d6efd;
    }
  </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container mt-4">
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h4>Edit Product</h4>
    </div>
    <div class="card-body">
      <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
      <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" id="productForm">
        <div class="row">
          <div class="col-md-6">
            <label>Product Name</label>
            <input type="text" name="productName" class="form-control" value="<?= htmlspecialchars($product['product_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label>Product Type</label>
            <select name="productType" id="productType" class="form-select" required>
              <option value="simple" <?= $productType === 'simple' ? 'selected' : '' ?>>Simple</option>
              <option value="variable" <?= $productType === 'variable' ? 'selected' : '' ?>>Variable</option>
            </select>
          </div>
        </div>

        <div class="row mt-2">
          <div class="col-md-6">
            <label>Categories</label>
            <select name="categories[]" class="form-select" multiple size="6" required>
              <?php foreach ($categories as $c): ?>
                <?php $catId = (int)$c['id']; ?>
                <option value="<?= $catId ?>" <?= in_array($catId, $selectedCategoryIds, true) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['category_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple categories.</small>
          </div>
          <div class="col-md-6">
            <label>Brand</label>
            <select name="brand" class="form-select" required>
              <option value="">Select</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $product['brand_id'] == $b['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['brand_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row mt-2 simple-fields" style="<?= $productType === 'variable' ? 'display:none;' : '' ?>">
          <div class="col-md-4"><label>Old Price</label><input type="number" name="oldPrice" step="0.01" class="form-control" value="<?= htmlspecialchars($product['old_price']) ?>"></div>
          <div class="col-md-4"><label>Current Price</label><input type="number" name="currentPrice" step="0.01" class="form-control" value="<?= htmlspecialchars($product['current_price']) ?>"></div>
          <div class="col-md-4"><label>Stock</label><input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($product['quantity']) ?>"></div>
        </div>

        <div class="mt-2"><label>Product SKU</label><input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($product['product_sku']) ?>"></div>

        <div class="mt-3 p-2 border rounded" style="background:#f8fafc;">
          <label for="isBestSeller" class="d-flex align-items-center gap-2 m-0" style="text-transform:none;letter-spacing:0;font-weight:600;cursor:pointer;">
            <input type="checkbox" id="isBestSeller" name="isBestSeller" value="1" style="width:18px;height:18px;" <?= ($hasBestSellerColumn && (int)($product['is_best_seller'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span>Show this product in Best Sellers section</span>
          </label>
        </div>

        <div class="mt-2 p-2 border rounded" style="background:#f8fafc;">
          <label for="isSaleItem" class="d-flex align-items-center gap-2 m-0" style="text-transform:none;letter-spacing:0;font-weight:600;cursor:pointer;">
            <input type="checkbox" id="isSaleItem" name="isSaleItem" value="1" style="width:18px;height:18px;" <?= ($hasSaleItemColumn && (int)($product['is_sale_item'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span>Sale Item</span>
          </label>
        </div>

        <div class="mt-2"><label>Short Description</label><textarea name="shortDescription" class="form-control"><?= htmlspecialchars($product['short_description']) ?></textarea></div>
        <div class="mt-2"><label>Product Details</label><textarea name="productDetails" class="form-control" id="productDetails"><?= htmlspecialchars($product['product_details']) ?></textarea></div>


        <div class="mt-2">
          <label>Specifications</label>
          <div id="specifications">
            <?php if (empty($specifications)): ?>
              <div class="row g-2 mb-1">
                <div class="col-md-5"><input type="text" class="form-control" name="specifications[0][heading]" placeholder="Heading"></div>
                <div class="col-md-5"><input type="text" class="form-control" name="specifications[0][description]" placeholder="Description"></div>
                <div class="col-md-2"><button type="button" class="btn btn-success w-100" onclick="addSpecification()"><i class="fas fa-plus"></i></button></div>
              </div>
            <?php else: ?>
              <?php foreach ($specifications as $index => $spec): ?>
                <div class="row g-2 mb-1">
                  <div class="col-md-5"><input type="text" class="form-control" name="specifications[<?= $index ?>][heading]" value="<?= htmlspecialchars($spec['heading']) ?>" placeholder="Heading"></div>
                  <div class="col-md-5"><input type="text" class="form-control" name="specifications[<?= $index ?>][description]" value="<?= htmlspecialchars($spec['description']) ?>" placeholder="Description"></div>
                  <div class="col-md-2">
                    <?php if ($index === 0): ?>
                      <button type="button" class="btn btn-success w-100" onclick="addSpecification()"><i class="fas fa-plus"></i></button>
                    <?php else: ?>
                      <button type="button" class="btn btn-danger w-100" onclick="$(this).parent().parent().remove()"><i class="fas fa-minus"></i></button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-2">
          <label>Images</label>
          <div class="mb-3">
            <?php foreach ($images as $img): ?>
              <div class="d-inline-block me-2 mb-2">
                <img src="<?= $domain.htmlspecialchars($img) ?>" style="width:100px; height:100px; object-fit:cover;" class="border rounded">
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" name="existingImages[]" value="<?= htmlspecialchars($img) ?>" checked>
                  <label class="form-check-label">Keep</label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="file" name="images[]" multiple class="form-control">
        </div>

        <div id="variableSection" style="<?= $productType === 'variable' ? '' : 'display:none;' ?>">
  <h4>Select Attributes & Values</h4>
  <?php if (empty($attributes)): ?>
    <p class="text-danger">No attributes defined. <a href="manage-attributes.php">Add attributes</a>.</p>
  <?php else: ?>
    <?php 
    // Get all attribute IDs used in existing variations
    $usedAttributeIds = [];
    foreach ($variations as $var) {
        $varData = json_decode($var['variation_data'], true);
        foreach ($varData as $attrName => $attrData) {
            $attrId = array_search($attrName, array_column($attributes, 'name'));
            if ($attrId !== false) {
                $usedAttributeIds[$attributes[$attrId]['id']] = true;
            }
        }
    }
    ?>
    
    <?php foreach ($attributes as $attr): ?>
      <div class="mb-2">
        <strong><?= htmlspecialchars($attr['name']) ?>:</strong>
        <?php if (isset($usedAttributeIds[$attr['id']])): ?>
          <span class="badge bg-info ms-2">Used in variations</span>
        <?php endif; ?>
        <br>
        <?php foreach ($attr['values'] as $val): ?>
          <label class="me-2">
            <input type="checkbox" name="attributes[<?= $attr['id'] ?>][]" value="<?= htmlspecialchars($val['value']) ?>"
              <?php 
                // Check if this attribute value is used in any variation
                if ($hasVariations) {
                  foreach ($variations as $var) {
                    $varData = json_decode($var['variation_data'], true);
                    if (in_array($val['value'], array_column($varData, 'value'))) {
                      echo 'checked';
                      break;
                    }
                  }
                }
              ?>>
            <?php if ($attr['value_type'] === 'color'): ?>
              <span style="display:inline-block;width:20px;height:20px;background:<?= htmlspecialchars($val['value']) ?>;border:1px solid #ccc;"></span>
              <?= htmlspecialchars($val['label']) ?>
            <?php else: ?>
              <?= htmlspecialchars($val['value']) ?>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <button type="button" class="btn btn-info mb-2" id="generateVariations">Generate Variations</button>
    <div id="variationsContainer">
      <?php if ($hasVariations): ?>
        <div class="mb-3">
          <h5>Current Variations</h5>
          <?php foreach ($variations as $var): ?>
            <?php 
              $varData = json_decode($var['variation_data'], true);
              $attrValues = [];
              foreach ($varData as $attrName => $attrData) {
                  $attrValues[] = "<strong>{$attrName}</strong>: " . 
                                ($attrData['type'] === 'color' ? 
                                "<span style='display:inline-block;width:15px;height:15px;background:{$attrData['value']};border:1px solid #ccc;'></span> {$attrData['label']}" : 
                                $attrData['value']);
              }
            ?>
            <div class="variation-item border p-2 mb-2">
              <div class="row">
                <div class="col-md-4">
                  <?= implode('<br>', $attrValues) ?>
                </div>
                <div class="col-md-2">
                  <label>Price</label>
                  <input type="number" name="variations[<?= $var['id'] ?>][price]" value="<?= $var['price'] ?>" step="0.01" class="form-control">
                </div>
                <div class="col-md-2">
                  <label>Stock</label>
                  <input type="number" name="variations[<?= $var['id'] ?>][stock]" value="<?= $var['stock'] ?>" class="form-control">
                </div>
                <div class="col-md-2">
                  <label>SKU</label>
                  <input type="text" name="variations[<?= $var['id'] ?>][sku]" value="<?= $var['sku'] ?>" class="form-control">
                </div>
                <div class="col-md-2">
                  <label>Image</label>
                  <input type="text" name="variations[<?= $var['id'] ?>][image]" value="<?= $var['image'] ?>" class="form-control">
                </div>
              </div>
              <input type="hidden" name="variations[<?= $var['id'] ?>][variation_data]" value='<?= htmlspecialchars($var['variation_data']) ?>'>
              <input type="hidden" name="variations[<?= $var['id'] ?>][variation_key]" value='<?= htmlspecialchars($var['variation_key']) ?>'>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

        <button class="btn btn-primary mt-3">Update Product</button>
      </form>
    </div>
  </div>
</div>
<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
    // Initialize CKEditor for descriptions
    ClassicEditor.create(document.querySelector('#productDetails')).catch(error => console.error(error));

    // Toggle product type fields
    $('#productType').change(function() {
      if ($(this).val() === 'variable') {
        $('#variableSection').show();
        $('.simple-fields').hide();
        $('.simple-fields input').prop('required', false);
      } else {
        $('#variableSection').hide();
        $('.simple-fields').show();
        $('.simple-fields input').prop('required', true);
      }
    });

    // Generate variations
    $('#generateVariations').click(function() {
      if ($('input[name^="attributes["]:checked').length === 0) {
        alert('Please select at least one attribute value');
        return;
      }
      
      $.post('php/generate-variations.php', {
        attributes: $('input[name^="attributes["]:checked').serializeArray(),
        productId: <?= $productId ?>
      }, function(data) {
        $('#variationsContainer').html(data);
      }).fail(function() {
        alert('Error generating variations');
      });
    });

    // Toggle image selection
    function toggleImageSelection(element) {
      $(element).toggleClass('selected');
      const imgUrl = $(element).attr('src');
      const variationIndex = $(element).closest('.variation-item').data('index');
      
      if ($(element).hasClass('selected')) {
        $(`input[name="variations[${variationIndex}][image]"]`).val(imgUrl);
      } else {
        $(`input[name="variations[${variationIndex}][image]"]`).val('');
      }
    }

    // Add specification fields
    function addSpecification() {
      const count = $('#specifications .row').length;
      $('#specifications').append(`
        <div class="row g-2 mb-1">
          <div class="col-md-5"><input type="text" class="form-control" name="specifications[${count}][heading]" placeholder="Heading"></div>
          <div class="col-md-5"><input type="text" class="form-control" name="specifications[${count}][description]" placeholder="Description"></div>
          <div class="col-md-2"><button type="button" class="btn btn-danger w-100" onclick="$(this).parent().parent().remove()"><i class="fas fa-minus"></i></button></div>
        </div>`);
    }
</script>
</body>
</html>
