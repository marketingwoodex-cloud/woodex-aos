<?php
// This helper is never a public endpoint.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    http_response_code(403);
    exit;
}

if (!function_exists('wdx_session_start')) {
    function wdx_session_start() {
        if (session_status() === PHP_SESSION_ACTIVE) return true;
        // Use the hosting provider's session directory, never the web root.
        session_set_cookie_params([
            'httponly' => true,
            'secure' => true,
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        if (!session_start()) {
            error_log('Woodex staging session storage unavailable.');
            http_response_code(503);
            exit('Staging session storage unavailable.');
        }
        return true;
    }
}
