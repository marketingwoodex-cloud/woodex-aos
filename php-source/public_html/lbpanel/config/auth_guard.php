<?php
/**
 * Admin session guard.
 *
 * Included at the top of every script in lbpanel/ by tools/security/patch-app.py.
 * Before this file existed, 59 of the 79 admin scripts ran without checking the
 * session at all — including the POS module and every write endpoint.
 */

if (!function_exists('wdx_session_start')) {
    require_once __DIR__ . '/session.php';
}
wdx_session_start();

$__wdx_script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__wdx_is_ajax = str_contains($__wdx_script, '/php/')
    || str_contains($__wdx_script, '/pos/api/')
    || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'cors');

if (empty($_SESSION['username'])) {
    if ($__wdx_is_ajax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication required']);
    } else {
        // Works whether the panel sits at the domain root or in a subfolder.
        $__wdx_root = preg_replace('#/lbpanel/.*$#', '', $__wdx_script);
        $__wdx_next = (string)($_SERVER['REQUEST_URI'] ?? '/lbpanel/index.php');
        header('Location: ' . $__wdx_root . '/lbpanel/index.php?next=' . rawurlencode($__wdx_next));
        http_response_code(302);
    }
    exit;
}

unset($__wdx_script, $__wdx_is_ajax, $__wdx_root, $__wdx_next);
