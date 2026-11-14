<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $couponCode = $_POST['couponCode'] ?? '';
    $description = $_POST['description'] ?? '';
    $discountType = $_POST['discountType'] ?? '';
    $discountAmount = $_POST['discountAmount'] ?? '';
    $validityPeriod = $_POST['validityPeriod'] ?? '';
    $appliesTo = $_POST['appliesTo'] ?? 'all';
    $relatedId = $_POST['relatedId'] ?? null;
    $status = $_POST['status'] ?? '';

    // Validate inputs
    if (empty($couponCode) || empty($description) || empty($discountType) || empty($discountAmount) || empty($validityPeriod) || empty($appliesTo) || empty($status)) {
        $_SESSION['error'] = 'Please fill out all required fields.';
        header('Location: ../add-coupon.php');
        exit;
    }

    // Set relatedId to null if appliesTo is "all"
    if ($appliesTo === 'all') {
        $relatedId = null;
    }

    // Insert the coupon into the database
    $stmt = $conn->prepare("
        INSERT INTO coupons (coupon_code, description, discount_type, discount_amount, validity_period, applies_to, related_id, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssssss', $couponCode, $description, $discountType, $discountAmount, $validityPeriod, $appliesTo, $relatedId, $status);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Coupon added successfully!';
    } else {
        $_SESSION['error'] = 'Error adding coupon: ' . $conn->error;
    }
    $stmt->close();

    header('Location: ../add-coupon.php');
    exit;
} else {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: ../add-coupon.php');
    exit;
}
?>
