<?php
header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../../lbpanel/config/connection.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['success' => false, 'message' => 'Database connection is not available.']);
    exit;
}

ensure_customer_reviews_table($conn);

$customerName = trim((string)($_POST['customer_name'] ?? ''));
$reviewText = trim((string)($_POST['review_text'] ?? ''));
$rating = (int)($_POST['rating'] ?? 0);

if ($customerName === '' || $reviewText === '') {
    echo json_encode(['success' => false, 'message' => 'Name and review are required.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}

$reviewDate = date('Y-m-d H:i:s');
$status = 'pending';
$isActive = 1;
$emptyImage = '';

try {
    $stmt = $conn->prepare("INSERT INTO customer_reviews (customer_name, review_text, rating, status, review_date, profile_image, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Unable to prepare review insert query.');
    }
    $stmt->bind_param('ssisssi', $customerName, $reviewText, $rating, $status, $reviewDate, $emptyImage, $isActive);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        throw new Exception('Failed to submit review.');
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unable to submit review right now.']);
    exit;
}

ob_clean();

echo json_encode(['success' => true, 'message' => 'Review submitted']);
