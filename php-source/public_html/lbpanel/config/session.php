<?php

if (!function_exists('wdx_session_start')) {
    function wdx_session_start() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        // Cookies: not readable from JavaScript, not sent on cross-site POSTs,
        // and HTTPS-only when the request already arrived over TLS.
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.use_strict_mode', '1');
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            @ini_set('session.cookie_secure', '1');
        }

        // Session files must never live inside the web root: every file there is
        // downloadable, and a readable session file is a hijackable login.
        $candidates = [
            dirname(__DIR__, 3) . '/sessions',   // one level above public_html
            dirname(__DIR__, 2) . '/sessions',   // legacy: inside public_html
        ];
        $sessionDir = false;
        foreach ($candidates as $candidate) {
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0775, true);
            }
            $real = realpath($candidate);
            if ($real !== false && is_writable($real)) {
                $sessionDir = $real;
                break;
            }
        }

        if ($sessionDir !== false) {
            session_save_path($sessionDir);
        }

        return session_start();
    }
}
