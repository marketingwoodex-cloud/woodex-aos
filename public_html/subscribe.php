<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start(); // If needed, else can remove.
include 'lbpanel/config/connection.php'; // Include your DB connection

header('Content-Type: application/json');

// Get the email from POST
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM subscribers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Already subscribed
    echo json_encode(['success' => false, 'message' => 'This email is already subscribed.']);
    $stmt->close();
    exit;
}
$stmt->close();

// Insert new subscriber
$stmt = $conn->prepare("INSERT INTO subscribers (email) VALUES (?)");
$stmt->bind_param("s", $email);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
$stmt->close();
exit;
