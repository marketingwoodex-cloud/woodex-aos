<?php
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
  $stmt = $conn->prepare("DELETE FROM hero_slides_alt WHERE id=?");
  if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
  }
}

header('Location: ../manage_hero_slider.php');
