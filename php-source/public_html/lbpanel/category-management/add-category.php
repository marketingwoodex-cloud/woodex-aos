<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

function ensureColumnIfMissing($conn, $table, $column, $definition) {
    $safeTable = str_replace('`', '', $table);
    $safeColumn = str_replace('`', '', $column);
    $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
    }
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

// Get all active categories for parent dropdown
$categories = [];
$result = $conn->query("SELECT id, category_name, level FROM categories WHERE is_active = TRUE ORDER BY path");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize inputs
    $categoryName = $conn->real_escape_string($_POST['categoryName']);
    $parentId = isset($_POST['parentId']) && $_POST['parentId'] !== '' ? (int)$_POST['parentId'] : null;
    
    // Generate SEO-friendly URL
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

    // Handle file uploads
    $backgroundImage = uploadCategoryImage('backgroundImage', '../../assets/images/categories/backgrounds/', 'assets/images/categories/backgrounds/', $error_message);
    $mainImage = uploadCategoryImage('mainImage', '../../assets/images/categories/', 'assets/images/categories/', $error_message);

    // Validate parent_id exists if provided
    if ($parentId !== null) {
        $checkParent = $conn->prepare("SELECT id FROM categories WHERE id = ? AND is_active = TRUE");
        $checkParent->bind_param("i", $parentId);
        $checkParent->execute();
        $checkParent->store_result();
        
        if ($checkParent->num_rows === 0) {
            $error_message = "Error: Selected parent category does not exist or is inactive";
            $parentId = null; // Reset to null to prevent FK violation
        }
        $checkParent->close();
    }

    // Calculate level based on parent
    $level = 1; // Default to top-level
    if ($parentId !== null) {
        $levelQuery = $conn->prepare("SELECT level FROM categories WHERE id = ?");
        $levelQuery->bind_param("i", $parentId);
        $levelQuery->execute();
        $levelQuery->bind_result($parentLevel);
        $levelQuery->fetch();
        $level = $parentLevel + 1;
        $levelQuery->close();
        
        // Validate hierarchy depth
        if ($level > 3) {
            $error_message = "Error: Maximum category depth (3 levels) exceeded";
        }
    }

    if (!isset($error_message)) {
        // Insert the new category (without path initially)
        if ($hasHeroLayoutColumn && $hasSeriesHeroFields && $hasHomeSectionColumns) {
            $stmt = $conn->prepare("INSERT INTO categories 
                                  (category_name, slug, short_description, long_description, 
                                   background_image, main_image, hero_layout, hero_series_tag, hero_series_subtitle,
                                   hero_series_description, hero_series_bullets, hero_primary_btn_label,
                                   hero_primary_btn_url, hero_secondary_btn_label, hero_secondary_btn_url,
                                   show_in_hot_products,
                                   parent_id, level, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

            $stmt->bind_param('sssssssssssssssiii', $categoryName, $seoFriendlyUrl, $shortDescription,
                             $longDescription, $backgroundImage, $mainImage, $heroLayout,
                             $seriesTag, $seriesSubtitle, $seriesDescription, $seriesBulletsJson,
                             $seriesPrimaryLabel, $seriesPrimaryUrl, $seriesSecondaryLabel,
                             $seriesSecondaryUrl, $showInHot,
                             $parentId, $level);
        } elseif ($hasHeroLayoutColumn && $hasSeriesHeroFields) {
            $stmt = $conn->prepare("INSERT INTO categories 
                                  (category_name, slug, short_description, long_description, 
                                   background_image, main_image, hero_layout, hero_series_tag, hero_series_subtitle,
                                   hero_series_description, hero_series_bullets, hero_primary_btn_label,
                                   hero_primary_btn_url, hero_secondary_btn_label, hero_secondary_btn_url,
                                   parent_id, level, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

            $stmt->bind_param('sssssssssssssssii', $categoryName, $seoFriendlyUrl, $shortDescription,
                             $longDescription, $backgroundImage, $mainImage, $heroLayout,
                             $seriesTag, $seriesSubtitle, $seriesDescription, $seriesBulletsJson,
                             $seriesPrimaryLabel, $seriesPrimaryUrl, $seriesSecondaryLabel,
                             $seriesSecondaryUrl, $parentId, $level);
        } elseif ($hasHeroLayoutColumn && $hasHomeSectionColumns) {
            $stmt = $conn->prepare("INSERT INTO categories 
                                  (category_name, slug, short_description, long_description, 
                                   background_image, main_image, hero_layout,
                                   show_in_hot_products,
                                   parent_id, level, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

            $stmt->bind_param('sssssssiii', $categoryName, $seoFriendlyUrl, $shortDescription,
                             $longDescription, $backgroundImage, $mainImage, $heroLayout,
                             $showInHot,
                             $parentId, $level);
        } elseif ($hasHeroLayoutColumn) {
            $stmt = $conn->prepare("INSERT INTO categories 
                                  (category_name, slug, short_description, long_description, 
                                   background_image, main_image, hero_layout, parent_id, level, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

            $stmt->bind_param('sssssssii', $categoryName, $seoFriendlyUrl, $shortDescription,
                             $longDescription, $backgroundImage, $mainImage, $heroLayout, $parentId, $level);
        } elseif ($hasHomeSectionColumns) {
            $stmt = $conn->prepare("INSERT INTO categories 
                                  (category_name, slug, short_description, long_description, 
                                   background_image, main_image,
                                   show_in_hot_products,
                                   parent_id, level, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

            $stmt->bind_param('ssssssiii', $categoryName, $seoFriendlyUrl, $shortDescription,
                             $longDescription, $backgroundImage, $mainImage,
                             $showInHot,
                             $parentId, $level);
        } else {
            $stmt = $conn->prepare("INSERT INTO categories 
                                  (category_name, slug, short_description, long_description, 
                                   background_image, main_image, parent_id, level, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)");

            $stmt->bind_param('ssssssii', $categoryName, $seoFriendlyUrl, $shortDescription,
                             $longDescription, $backgroundImage, $mainImage, $parentId, $level);
        }
        
        if ($stmt->execute()) {
            $newCategoryId = $stmt->insert_id;
            
            // Now update the path based on parent
            if ($parentId !== null) {
                $parentPath = $conn->query("SELECT path FROM categories WHERE id = $parentId")->fetch_row()[0];
                $newPath = $parentPath . '/' . $newCategoryId;
            } else {
                $newPath = $newCategoryId;
            }
            
            $updatePath = $conn->prepare("UPDATE categories SET path = ? WHERE id = ?");
            $updatePath->bind_param('si', $newPath, $newCategoryId);
            $updatePath->execute();
            $updatePath->close();

            $updateHomeCarousel = $conn->prepare("UPDATE categories SET show_in_home_carousel = ? WHERE id = ?");
            $updateHomeCarousel->bind_param('ii', $showInHomeCarousel, $newCategoryId);
            $updateHomeCarousel->execute();
            $updateHomeCarousel->close();
             
            $_SESSION['success'] = "Category added successfully!";
            header("Location: add-category.php");
            exit;
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .indented-option { padding-left: 20px; }
        .level-1 { font-weight: bold; }
        .level-2 { padding-left: 20px; font-style: italic; }
        .level-3 { padding-left: 40px; color: #666; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container form-container">
    <h2 class="form-header">Add New Category</h2>

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
                <!-- Parent Category -->
                <div class="form-group mb-3">
                    <label for="parentId" class="form-label">Parent Category</label>
                    <select class="form-select" id="parentId" name="parentId">
                        <option value="">-- Top Level Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" 
                                    class="level-<?= $category['level'] ?>"
                                    <?= isset($_POST['parentId']) && $_POST['parentId'] == $category['id'] ? 'selected' : '' ?>>
                                <?= str_repeat('&nbsp;&nbsp;', $category['level'] - 1) . $category['category_name'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Leave blank for top-level category</small>
                </div>

                <!-- Category Name -->
                <div class="form-group">
                    <label for="categoryName">Category Name</label>
                    <input type="text" class="form-control" id="categoryName" name="categoryName" placeholder="Enter category name" required>
                </div>

                <div class="form-group mb-3">
                    <label for="heroLayout" class="form-label">Hero Style</label>
                    <select class="form-select" id="heroLayout" name="heroLayout">
                        <option value="default" <?= (($_POST['heroLayout'] ?? 'default') === 'default') ? 'selected' : '' ?>>Default Hero</option>
                        <option value="series" <?= (($_POST['heroLayout'] ?? '') === 'series') ? 'selected' : '' ?>>Series Hero</option>
                    </select>
                    <small class="form-text text-muted">Choose how this category hero appears on the single category page.</small>
                </div>

                <div class="form-group mb-3 border rounded p-3" style="background:#fbfcfd;">
                    <label class="form-label fw-bold mb-2">Homepage Product Sections</label>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="showInHot" name="showInHot" <?= isset($_POST['showInHot']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="showInHot">Mark as Hot Products</label>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="showInHomeCarousel" name="showInHomeCarousel" <?= isset($_POST['showInHomeCarousel']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="showInHomeCarousel">Mark as Home Carousel</label>
                    </div>
                    <?php if (!$hasHomeSectionColumns): ?>
                        <small class="text-warning d-block mt-2">Database update required to save homepage section flags.</small>
                    <?php endif; ?>
                </div>

                <div id="seriesHeroFields" style="display:none; border:1px solid #e9ecef; border-radius:8px; padding:12px; margin-bottom:16px; background:#fafbfc;">
                    <h6 class="mb-3">Series Hero Content</h6>
                    <?php if (!$hasSeriesHeroFields): ?>
                        <div class="alert alert-warning py-2 mb-3">Database migration is required to save series bullets and buttons fields.</div>
                    <?php endif; ?>

                    <div class="form-group mb-2">
                        <label for="seriesTag">Top Left Tag</label>
                        <input type="text" class="form-control" id="seriesTag" name="seriesTag" value="<?= htmlspecialchars($_POST['seriesTag'] ?? '') ?>" placeholder="e.g. Privacy">
                    </div>

                    <div class="form-group mb-2">
                        <label for="seriesSubtitle">Subtitle (green text)</label>
                        <input type="text" class="form-control" id="seriesSubtitle" name="seriesSubtitle" value="<?= htmlspecialchars($_POST['seriesSubtitle'] ?? '') ?>" placeholder="e.g. Privacy-focused workstation solutions">
                    </div>

                    <div class="form-group mb-2">
                        <label for="seriesDescription">Series Description</label>
                        <textarea class="form-control" id="seriesDescription" name="seriesDescription" rows="3" placeholder="Describe this series"><?= htmlspecialchars($_POST['seriesDescription'] ?? '') ?></textarea>
                    </div>

                    <label class="form-label mt-1">Bullet Points</label>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="form-group mb-2">
                            <input type="text" class="form-control" name="seriesBullets[]" value="<?= htmlspecialchars($_POST['seriesBullets'][$i] ?? '') ?>" placeholder="Bullet <?= $i + 1 ?>">
                        </div>
                    <?php endfor; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label for="seriesPrimaryLabel">Primary Button Label</label>
                                <input type="text" class="form-control" id="seriesPrimaryLabel" name="seriesPrimaryLabel" value="<?= htmlspecialchars($_POST['seriesPrimaryLabel'] ?? 'View Full Series') ?>">
                            </div>
                            <div class="form-group mb-2">
                                <label for="seriesPrimaryUrl">Primary Button URL</label>
                                <input type="text" class="form-control" id="seriesPrimaryUrl" name="seriesPrimaryUrl" value="<?= htmlspecialchars($_POST['seriesPrimaryUrl'] ?? '#cat-products-grid') ?>" placeholder="#cat-products-grid or full URL">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-2">
                                <label for="seriesSecondaryLabel">Secondary Button Label</label>
                                <input type="text" class="form-control" id="seriesSecondaryLabel" name="seriesSecondaryLabel" value="<?= htmlspecialchars($_POST['seriesSecondaryLabel'] ?? 'Request Quote') ?>">
                            </div>
                            <div class="form-group mb-2">
                                <label for="seriesSecondaryUrl">Secondary Button URL</label>
                                <input type="text" class="form-control" id="seriesSecondaryUrl" name="seriesSecondaryUrl" value="<?= htmlspecialchars($_POST['seriesSecondaryUrl'] ?? 'e-quotation') ?>" placeholder="e-quotation or full URL">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Short Description -->
                <div class="form-group">
                    <label for="shortDescription">Short Description</label>
                    <textarea class="form-control" id="shortDescription" name="shortDescription" rows="3" placeholder="Enter a short description"></textarea>
                </div>

                <!-- Background Image -->
                <div class="form-group">
                    <label for="backgroundImage">Background Image</label>
                    <input type="file" class="form-control-file" id="backgroundImage" name="backgroundImage" accept="image/*" required>
                </div>
            </div>

            <!-- Second Column -->
            <div class="col-md-6">
                <!-- Main Image -->
                <div class="form-group">
                    <label for="mainImage">Main Image</label>
                    <input type="file" class="form-control-file" id="mainImage" name="mainImage" accept="image/*" required>
                </div>

                <!-- Long Description -->
                <div class="form-group">
                    <label for="longDescription">Long Description</label>
                    <textarea class="form-control" id="longDescription" name="longDescription" rows="6" placeholder="Enter long description"></textarea>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="text-center">
            <button type="submit" class="btn btn-primary mt-3">Add Category</button>
        </div>
    </form>
</div>

<!-- JavaScript and CKEditor -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
<script>
    // Initialize editor only for long content field
    ClassicEditor.create(document.querySelector('#longDescription')).catch(error => console.error(error));

    // Dynamic parent category validation
    document.getElementById('parentId').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const parentLevel = selectedOption.className.replace('level-', '');
            if (parentLevel >= 3) {
                alert('Warning: You cannot add subcategories to a 3rd level category');
                this.value = '';
            }
        }
    });

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
