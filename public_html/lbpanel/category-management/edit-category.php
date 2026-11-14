<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}

require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

function ensureColumnIfMissing($conn, $table, $column, $definition) {
    $safeTable = str_replace('`', '', $table);
    $safeColumn = str_replace('`', '', $column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

$hasHeroLayoutColumn = false;
$heroColRes = $conn->query("SHOW COLUMNS FROM categories LIKE 'hero_layout'");
if ($heroColRes && $heroColRes->num_rows > 0) {
    $hasHeroLayoutColumn = true;
}

$hasSeriesHeroFields = false;
$seriesColRes = $conn->query("SHOW COLUMNS FROM categories LIKE 'hero_series_tag'");
if ($seriesColRes && $seriesColRes->num_rows > 0) {
    $hasSeriesHeroFields = true;
}

ensureColumnIfMissing($conn, 'categories', 'show_in_hot_products', "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
ensureColumnIfMissing($conn, 'categories', 'show_in_home_carousel', "TINYINT(1) NOT NULL DEFAULT 0 AFTER show_in_hot_products");

$hasHomeSectionColumns = false;
$homeHotCol = $conn->query("SHOW COLUMNS FROM categories LIKE 'show_in_hot_products'");
$homeCarouselCol = $conn->query("SHOW COLUMNS FROM categories LIKE 'show_in_home_carousel'");
if ($homeHotCol && $homeHotCol->num_rows > 0 && $homeCarouselCol && $homeCarouselCol->num_rows > 0) {
    $hasHomeSectionColumns = true;
}

// Function to generate a unique filename
function generateUniqueFilename($directory, $filename) {
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $newFilename = $filename;
    $counter = 1;

    while (file_exists($directory . $newFilename)) {
        $newFilename = $baseName . '-' . $counter . '.' . $fileExtension;
        $counter++;
    }

    return $newFilename;
}

function uploadCategoryImage($fieldName, $directory, $publicPrefix, &$error_message) {
    if (empty($_FILES[$fieldName]['name'])) {
        return '';
    }

    if (!isset($_FILES[$fieldName]['error']) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'Error: Failed to upload image. Please try a smaller valid image.';
        return '';
    }

    if ((int)$_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        $error_message = 'Error: Image size must be 5MB or less.';
        return '';
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $imageInfo = @getimagesize($_FILES[$fieldName]['tmp_name']);
    if (!$imageInfo || !in_array($imageInfo['mime'], $allowedMimes, true)) {
        $error_message = 'Error: Only JPG, PNG, WEBP, or GIF images are allowed.';
        return '';
    }

    if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
        $error_message = 'Error: Upload directory is not writable.';
        return '';
    }

    $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '-', basename($_FILES[$fieldName]['name']));
    $uniqueFilename = generateUniqueFilename($directory, $safeFilename);
    $targetPath = $directory . $uniqueFilename;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
        $error_message = 'Error: Failed to save uploaded image.';
        return '';
    }

    return $publicPrefix . $uniqueFilename;
}

// Fetch existing category data if ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    $stmt->close();

    if (!$category) {
        die("Category not found.");
    }
} else {
    die("Invalid ID.");
}

$categories = [];
$categoryListResult = $conn->query("SELECT id, category_name, level, parent_id, path, is_active FROM categories ORDER BY path ASC, category_name ASC");
if ($categoryListResult) {
    while ($row = $categoryListResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize inputs
    $categoryName = $conn->real_escape_string($_POST['categoryName']);
    $seoFriendlyUrl = strtolower($categoryName);
    $seoFriendlyUrl = preg_replace('/[^a-z0-9\s-]/', '', $seoFriendlyUrl);
    $seoFriendlyUrl = preg_replace('/[\s-]+/', '-', $seoFriendlyUrl);
    $seoFriendlyUrl = trim($seoFriendlyUrl, '-');
    $shortDescription = $conn->real_escape_string($_POST['shortDescription']);
    $longDescription = $conn->real_escape_string($_POST['longDescription']);
    $heroLayout = $_POST['heroLayout'] ?? 'default';
    if ($heroLayout !== 'series' && $heroLayout !== 'default') {
        $heroLayout = 'default';
    }

    $seriesTag = trim((string)($_POST['seriesTag'] ?? ''));
    $seriesSubtitle = trim((string)($_POST['seriesSubtitle'] ?? ''));
    $seriesDescription = trim((string)($_POST['seriesDescription'] ?? ''));
    $seriesBulletsInput = isset($_POST['seriesBullets']) && is_array($_POST['seriesBullets']) ? $_POST['seriesBullets'] : [];
    $seriesBullets = [];
    foreach ($seriesBulletsInput as $bullet) {
        $bullet = trim((string)$bullet);
        if ($bullet !== '') {
            $seriesBullets[] = $bullet;
        }
    }
    $seriesBulletsJson = json_encode($seriesBullets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $seriesPrimaryLabel = trim((string)($_POST['seriesPrimaryLabel'] ?? 'View Full Series'));
    $seriesPrimaryUrl = trim((string)($_POST['seriesPrimaryUrl'] ?? '#cat-products-grid'));
    $seriesSecondaryLabel = trim((string)($_POST['seriesSecondaryLabel'] ?? 'Request Quote'));
    $seriesSecondaryUrl = trim((string)($_POST['seriesSecondaryUrl'] ?? 'e-quotation'));
    $showInHot = isset($_POST['showInHot']) ? 1 : 0;
    $showInHomeCarousel = isset($_POST['showInHomeCarousel']) ? 1 : 0;

    $parentId = isset($_POST['parentId']) && $_POST['parentId'] !== '' ? (int)$_POST['parentId'] : null;
    $currentPath = trim((string)($category['path'] ?? ''));
    if ($currentPath === '') {
        $currentPath = (string)$id;
    }
    $currentLevel = (int)($category['level'] ?? 1);
    $newLevel = 1;
    $newPath = (string)$id;

    $subtreeDepth = 0;
    $descendantPattern = $currentPath . '/%';
    $depthStmt = $conn->prepare("SELECT MAX(level) AS max_level FROM categories WHERE id = ? OR path LIKE ?");
    if ($depthStmt) {
        $depthStmt->bind_param('is', $id, $descendantPattern);
        $depthStmt->execute();
        $depthRow = $depthStmt->get_result()->fetch_assoc();
        $maxSubtreeLevel = (int)($depthRow['max_level'] ?? $currentLevel);
        $subtreeDepth = max(0, $maxSubtreeLevel - $currentLevel);
        $depthStmt->close();
    }

    if ($parentId !== null) {
        if ($parentId === $id) {
            $error_message = "Error: A category cannot be its own parent.";
        } else {
            $parentStmt = $conn->prepare("SELECT id, level, path FROM categories WHERE id = ? LIMIT 1");
            $parentStmt->bind_param('i', $parentId);
            $parentStmt->execute();
            $parentCategory = $parentStmt->get_result()->fetch_assoc();
            $parentStmt->close();

            if (!$parentCategory) {
                $error_message = "Error: Selected parent category does not exist.";
            } else {
                $parentPath = trim((string)($parentCategory['path'] ?? ''));
                if ($parentPath === '') {
                    $parentPath = (string)$parentId;
                }

                if ($parentPath === $currentPath || strpos($parentPath, $currentPath . '/') === 0) {
                    $error_message = "Error: A category cannot be moved under itself or one of its child categories.";
                } else {
                    $newLevel = (int)$parentCategory['level'] + 1;
                    $newPath = $parentPath . '/' . $id;
                }
            }
        }
    }

    if (!isset($error_message) && ($newLevel + $subtreeDepth) > 3) {
        $error_message = "Error: Maximum category depth (3 levels) exceeded.";
    }

    if (!isset($error_message)) {
        // Handle file uploads for backgroundImage and mainImage
        $backgroundImage = $category['background_image']; // Default to existing
        $uploadedBackgroundImage = uploadCategoryImage('backgroundImage', '../../assets/images/categories/backgrounds/', 'assets/images/categories/backgrounds/', $error_message);
        if ($uploadedBackgroundImage !== '') {
            $backgroundImage = $uploadedBackgroundImage;
        }

        $mainImage = $category['main_image']; // Default to existing
        if (!isset($error_message)) {
            $uploadedMainImage = uploadCategoryImage('mainImage', '../../assets/images/categories/', 'assets/images/categories/', $error_message);
            if ($uploadedMainImage !== '') {
                $mainImage = $uploadedMainImage;
            }
        }

        // Update database
        if (!isset($error_message)) {
        if ($hasHeroLayoutColumn && $hasSeriesHeroFields && $hasHomeSectionColumns) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, short_description = ?, long_description = ?, background_image = ?, main_image = ?, hero_layout = ?, hero_series_tag = ?, hero_series_subtitle = ?, hero_series_description = ?, hero_series_bullets = ?, hero_primary_btn_label = ?, hero_primary_btn_url = ?, hero_secondary_btn_label = ?, hero_secondary_btn_url = ?, show_in_hot_products = ?, parent_id = ?, level = ? WHERE id = ?");
            $stmt->bind_param('sssssssssssssssiiii', $categoryName, $seoFriendlyUrl, $shortDescription, $longDescription, $backgroundImage, $mainImage, $heroLayout, $seriesTag, $seriesSubtitle, $seriesDescription, $seriesBulletsJson, $seriesPrimaryLabel, $seriesPrimaryUrl, $seriesSecondaryLabel, $seriesSecondaryUrl, $showInHot, $parentId, $newLevel, $id);
        } elseif ($hasHeroLayoutColumn && $hasSeriesHeroFields) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, short_description = ?, long_description = ?, background_image = ?, main_image = ?, hero_layout = ?, hero_series_tag = ?, hero_series_subtitle = ?, hero_series_description = ?, hero_series_bullets = ?, hero_primary_btn_label = ?, hero_primary_btn_url = ?, hero_secondary_btn_label = ?, hero_secondary_btn_url = ?, parent_id = ?, level = ? WHERE id = ?");
            $stmt->bind_param('sssssssssssssssiii', $categoryName, $seoFriendlyUrl, $shortDescription, $longDescription, $backgroundImage, $mainImage, $heroLayout, $seriesTag, $seriesSubtitle, $seriesDescription, $seriesBulletsJson, $seriesPrimaryLabel, $seriesPrimaryUrl, $seriesSecondaryLabel, $seriesSecondaryUrl, $parentId, $newLevel, $id);
        } elseif ($hasHeroLayoutColumn && $hasHomeSectionColumns) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, short_description = ?, long_description = ?, background_image = ?, main_image = ?, hero_layout = ?, show_in_hot_products = ?, parent_id = ?, level = ? WHERE id = ?");
            $stmt->bind_param('sssssssiiii', $categoryName, $seoFriendlyUrl, $shortDescription, $longDescription, $backgroundImage, $mainImage, $heroLayout, $showInHot, $parentId, $newLevel, $id);
        } elseif ($hasHeroLayoutColumn) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, short_description = ?, long_description = ?, background_image = ?, main_image = ?, hero_layout = ?, parent_id = ?, level = ? WHERE id = ?");
            $stmt->bind_param('sssssssiii', $categoryName, $seoFriendlyUrl, $shortDescription, $longDescription, $backgroundImage, $mainImage, $heroLayout, $parentId, $newLevel, $id);
        } elseif ($hasHomeSectionColumns) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, short_description = ?, long_description = ?, background_image = ?, main_image = ?, show_in_hot_products = ?, parent_id = ?, level = ? WHERE id = ?");
            $stmt->bind_param('ssssssiiii', $categoryName, $seoFriendlyUrl, $shortDescription, $longDescription, $backgroundImage, $mainImage, $showInHot, $parentId, $newLevel, $id);
        } else {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, short_description = ?, long_description = ?, background_image = ?, main_image = ?, parent_id = ?, level = ? WHERE id = ?");
            $stmt->bind_param('ssssssiii', $categoryName, $seoFriendlyUrl, $shortDescription, $longDescription, $backgroundImage, $mainImage, $parentId, $newLevel, $id);
        }

        $conn->begin_transaction();
        try {
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $pathStmt = $conn->prepare("UPDATE categories SET path = ? WHERE id = ?");
            $pathStmt->bind_param('si', $newPath, $id);
            if (!$pathStmt->execute()) {
                throw new Exception($pathStmt->error);
            }
            $pathStmt->close();

            $homeCarouselStmt = $conn->prepare("UPDATE categories SET show_in_home_carousel = ? WHERE id = ?");
            $homeCarouselStmt->bind_param('ii', $showInHomeCarousel, $id);
            if (!$homeCarouselStmt->execute()) {
                throw new Exception($homeCarouselStmt->error);
            }
            $homeCarouselStmt->close();

            $descendants = [];
            $descStmt = $conn->prepare("SELECT id, path, level FROM categories WHERE path LIKE ? ORDER BY CHAR_LENGTH(path) ASC");
            $descStmt->bind_param('s', $descendantPattern);
            $descStmt->execute();
            $descRes = $descStmt->get_result();
            while ($desc = $descRes->fetch_assoc()) {
                $descendants[] = $desc;
            }
            $descStmt->close();

            if (!empty($descendants)) {
                $levelDelta = $newLevel - $currentLevel;
                $updateDescStmt = $conn->prepare("UPDATE categories SET path = ?, level = ? WHERE id = ?");
                foreach ($descendants as $desc) {
                    $descPath = (string)$desc['path'];
                    $suffix = substr($descPath, strlen($currentPath));
                    $updatedDescPath = $newPath . $suffix;
                    $updatedDescLevel = (int)$desc['level'] + $levelDelta;
                    $descId = (int)$desc['id'];
                    $updateDescStmt->bind_param('sii', $updatedDescPath, $updatedDescLevel, $descId);
                    if (!$updateDescStmt->execute()) {
                        throw new Exception($updateDescStmt->error);
                    }
                }
                $updateDescStmt->close();
            }

            $conn->commit();
            $success_message = "Category updated successfully!";

            // Refresh category data
            $ustmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
            $ustmt->bind_param('i', $id);
            $ustmt->execute();
            $uresult = $ustmt->get_result();
            $category = $uresult->fetch_assoc();
            $ustmt->close();

            $categories = [];
            $categoryListResult = $conn->query("SELECT id, category_name, level, parent_id, path, is_active FROM categories ORDER BY path ASC, category_name ASC");
            if ($categoryListResult) {
                while ($row = $categoryListResult->fetch_assoc()) {
                    $categories[] = $row;
                }
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }

        $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .level-1 { font-weight: bold; }
        .level-2 { padding-left: 20px; font-style: italic; }
        .level-3 { padding-left: 40px; color: #666; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container form-container">
    <h2 class="form-header">Edit Category</h2>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="row">
            <!-- First Column -->
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="parentId" class="form-label">Parent Category</label>
                    <select class="form-select" id="parentId" name="parentId">
                        <option value="">-- Top Level Category --</option>
                        <?php
                            $selectedParentId = isset($_POST['parentId'])
                                ? ($_POST['parentId'] !== '' ? (int)$_POST['parentId'] : null)
                                : (isset($category['parent_id']) ? (int)$category['parent_id'] : null);
                            $currentCategoryPath = trim((string)($category['path'] ?? ''));
                            if ($currentCategoryPath === '') {
                                $currentCategoryPath = (string)$id;
                            }
                        ?>
                        <?php foreach ($categories as $parentOption): ?>
                            <?php
                                $optionId = (int)$parentOption['id'];
                                $optionPath = trim((string)($parentOption['path'] ?? ''));
                                if ($optionPath === '') {
                                    $optionPath = (string)$optionId;
                                }
                                if ($optionId === $id || $optionPath === $currentCategoryPath || strpos($optionPath, $currentCategoryPath . '/') === 0) {
                                    continue;
                                }
                                $optionLevel = (int)($parentOption['level'] ?? 1);
                            ?>
                            <option value="<?= $optionId ?>"
                                    class="level-<?= $optionLevel ?>"
                                    <?= $selectedParentId === $optionId ? 'selected' : '' ?>>
                                <?= str_repeat('&nbsp;&nbsp;', max(0, $optionLevel - 1)) . htmlspecialchars((string)$parentOption['category_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Leave blank for a top-level category.</small>
                </div>

                <div class="form-group">
                    <label for="categoryName">Category Name</label>
                    <input type="text" class="form-control" id="categoryName" name="categoryName" value="<?php echo htmlspecialchars($category['category_name']); ?>" required>
                </div>

                <div class="form-group mb-3">
                    <label for="heroLayout" class="form-label">Hero Style</label>
                    <select class="form-select" id="heroLayout" name="heroLayout">
                        <option value="default" <?= (($category['hero_layout'] ?? 'default') === 'default') ? 'selected' : '' ?>>Default Hero</option>
                        <option value="series" <?= (($category['hero_layout'] ?? '') === 'series') ? 'selected' : '' ?>>Series Hero</option>
                    </select>
                    <small class="form-text text-muted">Choose how this category hero appears on the single category page.</small>
                </div>

                <div class="form-group mb-3 border rounded p-3" style="background:#fbfcfd;">
                    <label class="form-label fw-bold mb-2">Homepage Product Sections</label>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="showInHot" name="showInHot" <?= isset($_POST['showInHot']) ? 'checked' : ((int)($category['show_in_hot_products'] ?? 0) === 1 ? 'checked' : '') ?>>
                        <label class="form-check-label" for="showInHot">Mark as Hot Products</label>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="showInHomeCarousel" name="showInHomeCarousel" <?= isset($_POST['showInHomeCarousel']) ? 'checked' : ((int)($category['show_in_home_carousel'] ?? 0) === 1 ? 'checked' : '') ?>>
                        <label class="form-check-label" for="showInHomeCarousel">Mark as Home Carousel</label>
                    </div>
                    <?php if (!$hasHomeSectionColumns): ?>
                        <small class="text-warning d-block mt-2">Database update required to save homepage section flags.</small>
                    <?php endif; ?>
                </div>

                <?php
                    $seriesBulletsData = [];
                    if (!empty($category['hero_series_bullets'])) {
                        $decodedBullets = json_decode($category['hero_series_bullets'], true);
                        if (is_array($decodedBullets)) {
                            $seriesBulletsData = $decodedBullets;
                        }
                    }
                ?>
                <div id="seriesHeroFields" style="display:none; border:1px solid #e9ecef; border-radius:8px; padding:12px; margin-bottom:16px; background:#fafbfc;">
                    <h6 class="mb-3">Series Hero Content</h6>
                    <?php if (!$hasSeriesHeroFields): ?>
                        <div class="alert alert-warning py-2 mb-3">Database migration is required to save series bullets and buttons fields.</div>
                    <?php endif; ?>

                    <div class="form-group mb-2">
                        <label for="seriesTag">Top Left Tag</label>
                        <input type="text" class="form-control" id="seriesTag" name="seriesTag" value="<?= htmlspecialchars($_POST['seriesTag'] ?? ($category['hero_series_tag'] ?? '')) ?>" placeholder="e.g. Privacy">
                    </div>

                    <div class="form-group mb-2">
                        <label for="seriesSubtitle">Subtitle (green text)</label>
                        <input type="text" class="form-control" id="seriesSubtitle" name="seriesSubtitle" value="<?= htmlspecialchars($_POST['seriesSubtitle'] ?? ($category['hero_series_subtitle'] ?? '')) ?>" placeholder="e.g. Privacy-focused workstation solutions">
                    </div>

                    <div class="form-group mb-2">
                        <label for="seriesDescription">Series Description</label>
                        <textarea class="form-control" id="seriesDescription" name="seriesDescription" rows="3" placeholder="Describe this series"><?= htmlspecialchars($_POST['seriesDescription'] ?? ($category['hero_series_description'] ?? '')) ?></textarea>
                    </div>

                    <label class="form-label mt-1">Bullet Points</label>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="form-group mb-2">
                            <input type="text" class="form-control" name="seriesBullets[]" value="<?= htmlspecialchars($_POST['seriesBullets'][$i] ?? ($seriesBulletsData[$i] ?? '')) ?>" placeholder="Bullet <?= $i + 1 ?>">
                        </div>
                    <?php endfor; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label for="seriesPrimaryLabel">Primary Button Label</label>
                                <input type="text" class="form-control" id="seriesPrimaryLabel" name="seriesPrimaryLabel" value="<?= htmlspecialchars($_POST['seriesPrimaryLabel'] ?? ($category['hero_primary_btn_label'] ?? 'View Full Series')) ?>">
                            </div>
                            <div class="form-group mb-2">
                                <label for="seriesPrimaryUrl">Primary Button URL</label>
                                <input type="text" class="form-control" id="seriesPrimaryUrl" name="seriesPrimaryUrl" value="<?= htmlspecialchars($_POST['seriesPrimaryUrl'] ?? ($category['hero_primary_btn_url'] ?? '#cat-products-grid')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label for="seriesSecondaryLabel">Secondary Button Label</label>
                                <input type="text" class="form-control" id="seriesSecondaryLabel" name="seriesSecondaryLabel" value="<?= htmlspecialchars($_POST['seriesSecondaryLabel'] ?? ($category['hero_secondary_btn_label'] ?? 'Request Quote')) ?>">
                            </div>
                            <div class="form-group mb-2">
                                <label for="seriesSecondaryUrl">Secondary Button URL</label>
                                <input type="text" class="form-control" id="seriesSecondaryUrl" name="seriesSecondaryUrl" value="<?= htmlspecialchars($_POST['seriesSecondaryUrl'] ?? ($category['hero_secondary_btn_url'] ?? 'e-quotation')) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="shortDescription">Short Description</label>
                    <textarea class="form-control" id="shortDescription" name="shortDescription" rows="3" required><?php echo htmlspecialchars($category['short_description']); ?></textarea>
                </div>

                <!-- Background Image -->
                <div class="form-group">
                    <label for="backgroundImage">Background Image</label>
                    <input type="file" class="form-control-file" id="backgroundImage" name="backgroundImage">
                    <p>Current: <img src="../../<?php echo $category['background_image']; ?>" alt="Background Image" style="width: 100px;"></p>
                </div>
            </div>

            <!-- Second Column -->
            <div class="col-md-6">
                <!-- Main Image -->
                <div class="form-group">
                    <label for="mainImage">Main Image</label>
                    <input type="file" class="form-control-file" id="mainImage" name="mainImage">
                    <p>Current: <img src="../../<?php echo $category['main_image']; ?>" alt="Main Image" style="width: 100px;"></p>
                </div>

                <div class="form-group">
                    <label for="longDescription">Long Description</label>
                    <textarea class="form-control" id="longDescription" name="longDescription" rows="6"><?php echo htmlspecialchars($category['long_description']); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="text-center">
            <button type="submit" class="btn btn-primary mt-3">Update Category</button>
        </div>
    </form>
</div>

<!-- Bootstrap JS, jQuery, and CKEditor -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
    ClassicEditor.create(document.querySelector('#longDescription')).catch(error => console.error(error));

    function toggleSeriesHeroFields() {
        const heroLayout = document.getElementById('heroLayout');
        const seriesFields = document.getElementById('seriesHeroFields');
        if (!heroLayout || !seriesFields) return;
        seriesFields.style.display = heroLayout.value === 'series' ? 'block' : 'none';
    }

    document.getElementById('heroLayout').addEventListener('change', toggleSeriesHeroFields);
    toggleSeriesHeroFields();
</script>
</body>
</html>
