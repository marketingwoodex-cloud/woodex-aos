<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
require_once __DIR__ . '/config/connection.php';

header('Content-Type: application/json; charset=UTF-8');

$isLoggedIn = !empty($_SESSION['username']) || !empty($_SESSION['user_id']);
if (!$isLoggedIn) {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Method not allowed']]);
    exit;
}

if (empty($_FILES['upload']) || !is_array($_FILES['upload'])) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'No file uploaded']]);
    exit;
}

$file = $_FILES['upload'];

if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Upload failed']]);
    exit;
}

$maxBytes = 5 * 1024 * 1024;
if (!empty($file['size']) && (int)$file['size'] > $maxBytes) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Image is too large (max 5MB).']]);
    exit;
}

$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Unsupported image type. Use JPG, PNG, or WEBP.']]);
    exit;
}

$info = @getimagesize((string)$file['tmp_name']);
if ($info === false) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Invalid image file.']]);
    exit;
}

$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'blogs' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Unable to create upload directory.']]);
    exit;
}

$name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$fullPath = $uploadDir . $name;

if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'Unable to save uploaded image.']]);
    exit;
}

$baseDomain = !empty($domain) ? rtrim((string)$domain, '/') . '/' : '';
if ($baseDomain !== '') {
    $url = $baseDomain . 'assets/images/blogs/' . $name;
} else {
    $scriptDir = str_replace('\\', '/', (string)dirname($_SERVER['SCRIPT_NAME'] ?? '/lbpanel'));
    $appBase = preg_replace('~/lbpanel/?$~', '', $scriptDir);
    $appBase = $appBase === '' ? '/' : $appBase;
    $url = rtrim($appBase, '/') . '/assets/images/blogs/' . $name;
}

echo json_encode(['url' => $url]);
exit;
