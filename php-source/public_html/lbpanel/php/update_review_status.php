<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
header('Content-Type: application/json');
require_once '../config/connection.php';

// Require login
if (!isset($_SESSION['username'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0 || !in_array($status, ['pending','approved','rejected'], true)) {
    echo json_encode(['success'=>false,'message'=>'Invalid parameters']);
    exit;
}

$stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success'=>$ok, 'message'=>$ok ? 'Status updated' : 'Update failed']);
