<?php
// Run from a trusted shell after importing a sanitized staging database.
// Never put this file in the web document root or pass passwords in arguments.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../config/bootstrap.php';
$username = 'stagingadmin';
$check = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$check->bind_param('s', $username);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    fwrite(STDERR, "A stagingadmin account already exists; nothing changed.\n");
    exit(1);
}
$check->close();
$password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
$hash = password_hash($password, PASSWORD_DEFAULT);
$name = 'Staging Admin';
$role = 'admin';
$stmt = $conn->prepare('INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $username, $name, $hash, $role);
if (!$stmt->execute()) {
    fwrite(STDERR, "Unable to create staging admin; inspect server logs.\n");
    exit(1);
}
$stmt->close();
echo "Staging admin username: {$username}\nTemporary password (shown once): {$password}\n";
echo "Store it securely and change it after logging in. Do not share it in chat.\n";
