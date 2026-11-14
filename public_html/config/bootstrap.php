<?php
// Staging only. Secrets belong outside the Git checkout and web document root.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}
if (PHP_SAPI !== 'cli') {
    $requestedHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if ($requestedHost !== 'staging.blackibex.net') {
        http_response_code(400);
        exit('Invalid staging host.');
    }
}
$woodexRoot = dirname(__DIR__);
$woodexConfigPath = getenv('WDX_CONFIG_FILE') ?: dirname($woodexRoot, 2) . '/.woodex-staging.php';
$woodexRealConfig = realpath($woodexConfigPath);
if ($woodexRealConfig === false || str_starts_with($woodexRealConfig, $woodexRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(503);
    exit('Staging is not configured. Contact the site administrator.');
}
$woodexConfig = require $woodexRealConfig;
if (!is_array($woodexConfig) || empty($woodexConfig['db_host']) || empty($woodexConfig['db_name']) || empty($woodexConfig['db_user']) || !isset($woodexConfig['db_password'])) {
    http_response_code(503);
    exit('Staging is not configured. Contact the site administrator.');
}
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($woodexConfig['db_host'], $woodexConfig['db_user'], $woodexConfig['db_password'], $woodexConfig['db_name'], (int)($woodexConfig['db_port'] ?? 3306));
if ($conn->connect_error) {
    error_log('Woodex staging database connection failed.');
    http_response_code(503);
    exit('Staging database unavailable.');
}
$conn->set_charset('utf8mb4');
if (!defined('DB_HOST')) define('DB_HOST', $woodexConfig['db_host']);
if (!defined('DB_USER')) define('DB_USER', $woodexConfig['db_user']);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', $woodexConfig['db_password']);
if (!defined('DB_NAME')) define('DB_NAME', $woodexConfig['db_name']);
// Fixed staging origin avoids host-header injection and accidental links to production.
$domain = 'https://staging.blackibex.net/';
$admindomain = $domain . 'lbpanel/';

// URL join helper for assets/images (avoids duplicated subfolder like /woodex/woodex/...)
if (!function_exists('wdx_join_url')) {
    function wdx_join_url($base, $path) {
        $path = (string)$path;
        if ($path === '') return '';
        $path = str_replace('\\', '/', $path);
        if (preg_match('~^https?://~i', $path)) return $path;

        $base = rtrim((string)$base, '/') . '/';
        $basePath = '/';
        $p = parse_url($base, PHP_URL_PATH);
        if (is_string($p) && $p !== '') {
            $basePath = '/' . trim($p, '/') . '/';
        }
        $bpTrim = trim($basePath, '/');
        $origin = rtrim(preg_replace('~' . preg_quote($basePath, '~') . '$~', '/', $base), '/');

        if ($bpTrim !== '') {
            if (strpos($path, '/' . $bpTrim . '/') === 0) return $origin . $path;
            if (strpos($path, $bpTrim . '/') === 0) return $origin . '/' . $path;
        }

        if (strpos($path, '/assets/') === 0) return $origin . rtrim($basePath, '/') . $path;
        if (strpos($path, 'assets/') === 0) return $base . $path;
        if (strpos($path, '/') === 0) return $origin . rtrim($basePath, '/') . $path;
        return $base . $path;
    }
}
