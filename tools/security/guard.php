<?php
/**
 * Security + compatibility shim for the local run of the Woodex application.
 *
 * Loaded by tools/php-run/server.mjs as PHP's `auto_prepend_file`, so it runs
 * before every application script. The application files in php-source/ are NOT
 * modified — this file is the local equivalent of the patches described in
 * tools/security/README.md, which you apply on the cPanel host.
 *
 * What it does
 * ------------
 * 1. Working directory: Apache runs a script from its own folder and the POS
 *    module / category manager rely on it (`require '../config/connection.php'`).
 * 2. Sessions: the PHP-WASM runtime leaks session state between requests — a
 *    session started by the previous visitor stays active, the previous Cookie
 *    header stays in $_SERVER, PHP's id generator returns the same id twice, and
 *    writes land in the previous visitor's file. The guard therefore owns
 *    sessions completely: it decides the id, reads the data itself, and writes
 *    it back to the file for that id (PHP's own file handler is disabled).
 * 3. Admin authentication: the application only guards 20 of its 79 admin
 *    scripts. Every /lbpanel/* request now needs a session, except the login
 *    form, logout and static assets. AJAX endpoints get JSON 401, not a redirect.
 * 4. Session fixation: the id is rotated once, right after a successful login.
 * 5. Baseline response headers (no X-Frame-Options — the preview is embedded in
 *    an iframe, so framing has to stay allowed).
 */

// ── debug ──────────────────────────────────────────────────────────────────
$__wdx_debug_flag = dirname(__DIR__, 2) . '/.tmp/guard-debug.on';
$__wdx_log_file = @is_file($__wdx_debug_flag) ? dirname(__DIR__, 2) . '/.tmp/guard-debug.log' : null;
$__wdx_log = static function (string $message) use ($__wdx_log_file): void {
    if ($__wdx_log_file !== null) {
        @file_put_contents($__wdx_log_file, date('H:i:s') . ' ' . $message . "\n", FILE_APPEND);
    }
};
$__wdx_log(sprintf('ENTRY %s %s status=%d id=%s xcookie=%s',
    $_SERVER['REQUEST_METHOD'] ?? '?', $_SERVER['SCRIPT_NAME'] ?? '?', session_status(),
    var_export(session_id(), true), $_SERVER['HTTP_X_WDX_COOKIE'] ?? '(unset)'));

// ── 1. run from the script's own directory, like Apache ─────────────────────
$__wdx_script = $_SERVER['SCRIPT_FILENAME'] ?? '';
if ($__wdx_script !== '' && @is_file($__wdx_script)) {
    @chdir(dirname($__wdx_script));
}
unset($__wdx_script);

// ── 5. baseline headers ────────────────────────────────────────────────────
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ── 2. sessions: identity, storage and isolation ───────────────────────────
/**
 * A no-op save handler. The application may call session_start(),
 * session_write_close() or session_destroy() as often as it likes; the data is
 * read and written by the guard instead, against the id the guard chose.
 */
final class WdxGuardSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false { return ''; }
    public function write(string $id, string $data): bool { return true; }
    public function destroy(string $id): bool { return true; }
    public function gc(int $max_lifetime): int|false { return 0; }
}

if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}
$_SESSION = [];
session_set_save_handler(new WdxGuardSessionHandler(), false);

if (($_SERVER['HTTP_X_WDX_COOKIE'] ?? 'present') === 'none') {
    unset($_SERVER['HTTP_COOKIE']);
    $_COOKIE = [];
}
if (($_SERVER['HTTP_X_WDX_AJAX'] ?? 'present') === 'none') {
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
}

// The application's own helper decides where session files live.
$__wdx_session_helper = dirname(__DIR__, 2) . '/php-source/public_html/lbpanel/config/session.php';
if (!function_exists('wdx_session_start') && @is_file($__wdx_session_helper)) {
    require_once $__wdx_session_helper;
}
$__wdx_start_session = static function (): void {
    if (function_exists('wdx_session_start')) {
        @wdx_session_start();
    } elseif (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
};

$__wdx_session_file = static function (string $sid): string {
    return rtrim((string) session_save_path(), '/') . '/sess_' . $sid;
};

// The runner owns session identity and sends it in a header (PHP's own cookie
// handling is switched off: it produced the leaks described above).
$__wdx_client_sid = (string)($_SERVER['HTTP_X_WDX_SID'] ?? '');
if (!preg_match('/^[A-Za-z0-9]{16,128}$/', $__wdx_client_sid)) {
    $__wdx_client_sid = '';
}
$__wdx_sid = $__wdx_client_sid !== '' ? $__wdx_client_sid : bin2hex(random_bytes(16));

@session_id($__wdx_sid);
$__wdx_start_session();
$__wdx_sid = session_id();
$__wdx_file = $__wdx_session_file($__wdx_sid);

// The file for this id is the only truth about this visitor's data.
$_SESSION = [];
$__wdx_raw = @file_get_contents($__wdx_file);
if (is_string($__wdx_raw) && $__wdx_raw !== '') {
    @session_decode($__wdx_raw);
}

/**
 * Write the session back to the file of *this* id. PHP's own write is
 * unreliable in this runtime (it flushed one visitor's cart into another
 * visitor's file), so the guard persists $_SESSION itself.
 */
register_shutdown_function(static function () use (&$__wdx_sid, $__wdx_session_file): void {
    // Note: session_id() at shutdown time can already be the *previous*
    // request's id in this runtime, so the guard's own id is used instead.
    $sid = (string) $__wdx_sid;
    if ($sid === '') {
        return;
    }
    $data = session_status() === PHP_SESSION_ACTIVE ? (string) @session_encode() : '';
    $file = $__wdx_session_file($sid);
    if ($data === '') {
        @unlink($file);
        return;
    }
    @file_put_contents($file, $data, LOCK_EX);
});

$__wdx_log(sprintf('SID %s client=%s file=%s loaded=%d bytes', $__wdx_sid,
    $__wdx_client_sid === '' ? '(none)' : $__wdx_client_sid,
    @is_file($__wdx_file) ? 'exists' : 'new',
    is_string($__wdx_raw) ? strlen($__wdx_raw) : -1));
unset($__wdx_raw);

// ── 3 + 4. admin authentication and session rotation ───────────────────────
$__wdx_script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__wdx_is_admin = str_starts_with($__wdx_script_name, '/lbpanel/');

if ($__wdx_is_admin) {
    $__wdx_open = in_array($__wdx_script_name, [
        '/lbpanel/index.php',        // login form
        '/lbpanel/logout.php',
        '/lbpanel/forgot-password.php',
    ], true)
        || str_starts_with($__wdx_script_name, '/lbpanel/includes/')
        || str_starts_with($__wdx_script_name, '/lbpanel/assets/')
        || str_starts_with($__wdx_script_name, '/lbpanel/ckeditor/');

    $__wdx_mutating = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET';
    $__wdx_is_login_post = $__wdx_script_name === '/lbpanel/index.php' && $__wdx_mutating
        && isset($_POST['username']);
    $__wdx_is_ajax = str_contains($__wdx_script_name, '/php/')
        || str_contains($__wdx_script_name, '/api/')
        || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

    if ($__wdx_is_login_post) {
        $_SESSION['__wdx_rotate'] = 1;
    } elseif (!empty($_SESSION['__wdx_rotate']) && !empty($_SESSION['username'])) {
        // Rotate after login: new random id, data carried over, old file gone.
        $__wdx_data = $_SESSION;
        unset($__wdx_data['__wdx_rotate']);
        $__wdx_old_file = $__wdx_file;
        $__wdx_old_sid = $__wdx_sid;
        @session_write_close();
        $_SESSION = [];
        $__wdx_sid = bin2hex(random_bytes(16));
        @session_id($__wdx_sid);
        $__wdx_start_session();
        $_SESSION = $__wdx_data;
        $__wdx_file = $__wdx_session_file($__wdx_sid);
        @unlink($__wdx_old_file);
        if (!headers_sent()) {
            header('X-WDX-Session-Id: ' . $__wdx_sid);
        }
        $__wdx_log(sprintf('ROTATE %s -> %s', $__wdx_old_sid, $__wdx_sid));
    }

    if (!$__wdx_open && empty($_SESSION['username'])) {
        if ($__wdx_is_ajax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Authentication required']);
        } else {
            $__wdx_target = (string)($_SERVER['REQUEST_URI'] ?? '/lbpanel/dashboard.php');
            header('Location: /lbpanel/index.php?next=' . rawurlencode($__wdx_target));
            http_response_code(302);
        }
        exit;
    }
}

$__wdx_log(sprintf('DONE sid=%s cart=%s', $__wdx_sid,
    isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 'none'));

unset(
    $__wdx_debug_flag,
    $__wdx_log,
    $__wdx_log_file,
    $__wdx_script_name,
    $__wdx_is_admin,
    $__wdx_open,
    $__wdx_mutating,
    $__wdx_is_login_post,
    $__wdx_is_ajax,
    $__wdx_target,
    $__wdx_data,
    $__wdx_old_file,
    $__wdx_old_sid,
    $__wdx_session_file,
    $__wdx_start_session,
    $__wdx_session_helper,
    $__wdx_file,
    $__wdx_client_sid
);
