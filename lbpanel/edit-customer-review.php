<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to edit customer reviews.';
    header('Location: index.php');
    exit;
}

function ensure_customer_reviews_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS customer_reviews (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      customer_name VARCHAR(150) NOT NULL,
      review_text TEXT NOT NULL,
      rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
      status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      review_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      profile_image VARCHAR(2048) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_customer_reviews_active_date (is_active, status, review_date, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $statusCol = $conn->query("SHOW COLUMNS FROM customer_reviews LIKE 'status'");
    if ($statusCol && $statusCol->num_rows === 0) {
        $conn->query("ALTER TABLE customer_reviews ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER rating");
    }
}

function upload_customer_review_image($file, $dir = '../assets/images/customer-reviews/') {
    if (empty($file) || empty($file['name'])) {
        return '';
    }
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed.');
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Only JPG, JPEG, PNG, WEBP are allowed.');
    }

    $info = @getimagesize((string)$file['tmp_name']);
    if ($info === false) {
        throw new Exception('Invalid image file.');
    }

    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $fullPath = $dir . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
        throw new Exception('Unable to save uploaded image.');
    }

    return 'assets/images/customer-reviews/' . $filename;
}

function normalize_review_datetime($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return date('Y-m-d H:i:s');
    }
    $ts = strtotime($value);
    if (!$ts) {
        throw new Exception('Invalid review date/time format.');
    }
    return date('Y-m-d H:i:s', $ts);
}

function delete_review_image_file($relativePath) {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '' || strpos($relativePath, 'assets/images/customer-reviews/') !== 0) {
        return;
    }
    $full = '../' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

ensure_customer_reviews_table($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid review ID.';
    header('Location: customer-reviews.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM customer_reviews WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$review) {
    $_SESSION['error'] = 'Review not found.';
    header('Location: customer-reviews.php');
    exit;
}

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customerName = trim((string)($_POST['customer_name'] ?? ''));
        $reviewText = trim((string)($_POST['review_text'] ?? ''));
        $rating = (int)($_POST['rating'] ?? 5);
        $status = (string)($_POST['status'] ?? 'approved');
        $reviewDate = normalize_review_datetime($_POST['review_date'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($customerName === '' || $reviewText === '') {
            throw new Exception('Customer name and review text are required.');
        }
        if ($rating < 1 || $rating > 5) {
            throw new Exception('Rating must be between 1 and 5.');
        }
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'approved';
        }

        $profileImage = (string)($review['profile_image'] ?? '');

        if (isset($_POST['remove_profile_image'])) {
            delete_review_image_file($profileImage);
            $profileImage = '';
        }

        if (!empty($_FILES['profile_image']['name'])) {
            $newImage = upload_customer_review_image($_FILES['profile_image']);
            if ($newImage !== '') {
                delete_review_image_file($profileImage);
                $profileImage = $newImage;
            }
        }

        $stmt = $conn->prepare("UPDATE customer_reviews SET customer_name = ?, review_text = ?, rating = ?, status = ?, review_date = ?, profile_image = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param('ssisssii', $customerName, $reviewText, $rating, $status, $reviewDate, $profileImage, $isActive, $id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update review: ' . $stmt->error);
        }
        $stmt->close();

        $_SESSION['success'] = 'Customer review updated successfully.';
        header('Location: edit-customer-review.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$stmt = $conn->prepare("SELECT * FROM customer_reviews WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$review = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Customer Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container form-container">
    <h2 class="form-header">Edit Customer Review</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label>Customer Name *</label>
                <input type="text" class="form-control" name="customer_name" required value="<?= htmlspecialchars((string)($review['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-3">
                <label>Rating *</label>
                <select class="form-select" name="rating" required>
                    <?php $selectedRating = (int)($review['rating'] ?? 5); ?>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= $selectedRating === $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label>Date / Time</label>
                <input type="datetime-local" class="form-control" name="review_date" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime((string)($review['review_date'] ?? 'now'))), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-md-3">
                <label>Approval</label>
                <?php $selectedStatus = (string)($review['status'] ?? 'approved'); ?>
                <select class="form-select" name="status">
                    <option value="approved" <?= $selectedStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="rejected" <?= $selectedStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <div class="col-md-12">
                <label>Review Text *</label>
                <textarea class="form-control" name="review_text" rows="5" required><?= htmlspecialchars((string)($review['review_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="col-md-6">
                <label>Profile Image (optional)</label>
                <?php if (!empty($review['profile_image'])): ?>
                    <div class="mb-2">
                        <img src="../<?= htmlspecialchars((string)$review['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:72px;height:72px;border-radius:50%;object-fit:cover;">
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="remove_profile_image" id="remove_profile_image">
                        <label class="form-check-label" for="remove_profile_image">Remove current image</label>
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control" name="profile_image" accept="image/*">
            </div>

            <div class="col-md-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="is_active" id="is_active" <?= (int)($review['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Active on frontend</label>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Update Review</button>
            <a href="customer-reviews.php" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
