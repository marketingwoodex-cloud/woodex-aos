<?php
// Credentials do not live in this file: create config/connection.local.php
// (excluded from git) with WDX_DB_* constants, or set the LIVE_DB_* / DB_*
// environment variables on the host. See docs/deploy-hostinger.md.
$__wdx_local_cfg = __DIR__ . '/connection.local.php';
if (is_file($__wdx_local_cfg)) {
    require_once $__wdx_local_cfg;
}

// Local runner only: route prepared statements through escaped text queries
// when the shim happens to be present. It is never deployed to the host.
if (is_file(__DIR__ . '//../../wdx_mysqli.php')) {
    require_once __DIR__ . '//../../wdx_mysqli.php';
}

// Database configuration with automatic environment detection.
// Localhost uses local credentials; live host uses live credentials.

$hostHeader = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$hostOnly = preg_replace('/:\d+$/', '', $hostHeader);
$isDotLocal = (substr($hostOnly, -6) === '.local');
$isLocalEnv = in_array($hostOnly, ['localhost', '127.0.0.1', '::1'], true)
    || $isDotLocal;

// Local DB credentials (localhost/XAMPP)
$localDb = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'woodex',
];

// Live DB credentials
// Priority: environment vars -> predefined LIVE_DB_* constants -> hardcoded fallback.
$liveDb = [
    'host' => getenv('LIVE_DB_HOST') ?: (defined('LIVE_DB_HOST') ? LIVE_DB_HOST : 'localhost'),
    'user' => getenv('LIVE_DB_USER') ?: (defined('LIVE_DB_USER') ? LIVE_DB_USER : 'u128159657_woodbaks'),
    'pass' => getenv('LIVE_DB_PASSWORD') ?: (defined('LIVE_DB_PASSWORD') ? LIVE_DB_PASSWORD : 'Science@32Khanorbit'),
    'name' => getenv('LIVE_DB_NAME') ?: (defined('LIVE_DB_NAME') ? LIVE_DB_NAME : 'u128159657_woodexbaks'),
];

// Optional generic env var support (some hosts expose DB_* only)
if ($liveDb['host'] === '' && getenv('DB_HOST')) $liveDb['host'] = (string)getenv('DB_HOST');
if ($liveDb['user'] === '' && getenv('DB_USER')) $liveDb['user'] = (string)getenv('DB_USER');
if ($liveDb['pass'] === '' && getenv('DB_PASSWORD')) $liveDb['pass'] = (string)getenv('DB_PASSWORD');
if ($liveDb['name'] === '' && getenv('DB_NAME')) $liveDb['name'] = (string)getenv('DB_NAME');

// Candidate order: local on localhost, live on production.
$candidates = $isLocalEnv ? [$localDb, $liveDb] : [$liveDb, $localDb];

$conn = null;
$selectedDb = null;
foreach ($candidates as $cfg) {
    if (empty($cfg['host']) || empty($cfg['user']) || empty($cfg['name'])) {
        continue;
    }
    $driver = class_exists('WdxMysqli') ? 'WdxMysqli' : 'mysqli';
    $tmp = @new $driver($cfg['host'], $cfg['user'], (string)$cfg['pass'], $cfg['name']);
    if (!$tmp->connect_error) {
        $conn = $tmp;
        $selectedDb = $cfg;
        break;
    }
}

if (!$conn instanceof mysqli) {
    die('Database connection failed. Please verify environment DB credentials.');
}

// Backward-compatible constants (if other files rely on them)
if (!defined('DB_HOST')) define('DB_HOST', (string)$selectedDb['host']);
if (!defined('DB_USER')) define('DB_USER', (string)$selectedDb['user']);
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', (string)$selectedDb['pass']);
if (!defined('DB_NAME')) define('DB_NAME', (string)$selectedDb['name']);

// Set the character set to utf8mb4 for security and compatibility
$conn->set_charset('utf8mb4');

// Base URLs
// Compute dynamically so internal links work on both:
// - local (e.g. http://localhost/woodex/)
// - live (e.g. https://woodexfurniture.pk/)
$domain = 'https://www.woodexfurniture.pk/';
$admindomain = $domain . 'lbpanel/';

if (!empty($_SERVER['HTTP_HOST'])) {
    // Detect scheme robustly (handles reverse proxies / load balancers)
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $p = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        $scheme = ($p === 'https') ? 'https' : 'http';
    } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
        $scheme = ($_SERVER['REQUEST_SCHEME'] === 'https') ? 'https' : 'http';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        $scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'];

    // Project root is two levels up from this file: lbpanel/config/ -> project root
    $projectRoot = realpath(__DIR__ . '/../../');
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';

    $basePath = '';
    if ($projectRoot && $docRoot && strpos($projectRoot, $docRoot) === 0) {
        $rel = substr($projectRoot, strlen($docRoot));
        $rel = str_replace('\\', '/', $rel);
        $rel = '/' . trim($rel, '/');
        $basePath = ($rel === '/') ? '' : $rel;
    }

    $domain = $scheme . '://' . $host . $basePath . '/';
    $admindomain = $domain . 'lbpanel/';
}

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
