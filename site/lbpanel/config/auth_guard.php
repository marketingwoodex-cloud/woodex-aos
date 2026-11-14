<?php
require_once __DIR__ . '/session.php';
wdx_session_start();
// Restrict staging administration to a dedicated admin account.
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        http_response_code(401);
        header('Content-Type: application/json');
        exit('{"error":"Authentication required"}');
    }
    header('Location: /lbpanel/');
    exit;
}
