<?php
include __DIR__ . '/lbpanel/config/connection.php';

function wdx_base_path_from_script() {
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/redirect.php';
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '' || $dir === '.') {
        return '';
    }
    return '/' . trim($dir, '/');
}

function wdx_normalize_path($path) {
    $path = rawurldecode(str_replace('\\', '/', (string)$path));
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('~/+~', '/', $path);
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }
    return $path;
}

function wdx_is_absolute_url($url) {
    return (bool)preg_match('~^https?://~i', (string)$url);
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$tableExists = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'url_redirects'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $tableExists = true;
}

if (!$tableExists) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$basePath = wdx_base_path_from_script();
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = (string)parse_url($requestUri, PHP_URL_PATH);
$requestQuery = (string)parse_url($requestUri, PHP_URL_QUERY);

$siteRelativePath = $requestPath;
if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $siteRelativePath = substr($requestPath, strlen($basePath));
}
$siteRelativePath = wdx_normalize_path($siteRelativePath);

$oldHash = md5($siteRelativePath);
$stmt = $conn->prepare("SELECT id, new_url, http_code FROM url_redirects WHERE old_url_hash = ? AND old_url_normalized = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param('ss', $oldHash, $siteRelativePath);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$target = trim((string)$row['new_url']);
if ($target === '') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$targetForHeader = $target;
if (!wdx_is_absolute_url($target)) {
    $targetPath = (string)parse_url($target, PHP_URL_PATH);
    $targetPath = wdx_normalize_path($targetPath);

    if ($targetPath === $siteRelativePath) {
        http_response_code(404);
        echo 'Not Found';
        exit;
    }

    $targetForHeader = ($basePath !== '' ? $basePath : '') . $targetPath;

    $targetQuery = (string)parse_url($target, PHP_URL_QUERY);
    $targetFragment = (string)parse_url($target, PHP_URL_FRAGMENT);

    if ($targetQuery !== '') {
        $targetForHeader .= '?' . $targetQuery;
        if ($requestQuery !== '') {
            $targetForHeader .= '&' . $requestQuery;
        }
    } elseif ($requestQuery !== '') {
        $targetForHeader .= '?' . $requestQuery;
    }

    if ($targetFragment !== '') {
        $targetForHeader .= '#' . $targetFragment;
    }
} else {
    $targetQuery = (string)parse_url($target, PHP_URL_QUERY);
    $targetFragment = (string)parse_url($target, PHP_URL_FRAGMENT);
    if ($requestQuery !== '' && $targetQuery === '') {
        $targetForHeader .= '?' . $requestQuery;
        if ($targetFragment !== '') {
            $targetForHeader .= '#' . $targetFragment;
        }
    }
}

$code = (int)$row['http_code'];
if (!in_array($code, [301, 302, 307, 308], true)) {
    $code = 301;
}

$hitStmt = $conn->prepare("UPDATE url_redirects SET hit_count = hit_count + 1, last_hit_at = NOW() WHERE id = ?");
$hitStmt->bind_param('i', $row['id']);
$hitStmt->execute();
$hitStmt->close();

header('Location: ' . $targetForHeader, true, $code);
exit;
