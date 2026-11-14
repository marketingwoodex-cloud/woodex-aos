<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/../config/auth_guard.php';
}

require_once __DIR__ . '/../config/session.php';
wdx_session_start();
include '../config/connection.php';

function ensure_column($conn, $table, $column, $definition) {
  $safeTable = str_replace('`', '', $table);
  $safeColumn = str_replace('`', '', $column);
  $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
  if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
  }
}

ensure_column($conn, 'hero_slides_alt', 'button_color', "VARCHAR(20) NOT NULL DEFAULT '#78be20' AFTER cta_url");
ensure_column($conn, 'hero_slides_alt', 'heading_color', "VARCHAR(20) NOT NULL DEFAULT '#ffffff' AFTER button_color");
ensure_column($conn, 'hero_slides_alt', 'description_color', "VARCHAR(20) NOT NULL DEFAULT '#deebf7' AFTER heading_color");

$slider_id = (int)($_POST['slider_id'] ?? 0);
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$cta_label = trim((string)($_POST['cta_label'] ?? ''));
$cta_url = trim((string)($_POST['cta_url'] ?? ''));
$button_color = trim((string)($_POST['button_color'] ?? '#78be20'));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $button_color)) {
  $button_color = '#78be20';
}
$heading_color = trim((string)($_POST['heading_color'] ?? '#ffffff'));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $heading_color)) {
  $heading_color = '#ffffff';
}
$description_color = trim((string)($_POST['description_color'] ?? '#deebf7'));
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $description_color)) {
  $description_color = '#deebf7';
}
$sort_order = (int)($_POST['sort_order'] ?? 1);
$is_published = (int)($_POST['is_published'] ?? 1);
$background_image_alt = trim((string)($_POST['background_image_alt'] ?? ''));
$start_at = !empty($_POST['start_at']) ? $_POST['start_at'] : null;
$end_at = !empty($_POST['end_at']) ? $_POST['end_at'] : null;

if ($slider_id <= 0 || $title === '') {
  header('Location: ../manage_hero_slider.php');
  exit;
}

$background_image_path = $_POST['existing_background_image'] ?? '';
if (!empty($_FILES['background_image']['name'])) {
  $dir = '../../assets/images/heroes/';
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  $fn = time() . '_bg_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $_FILES['background_image']['name']);
  $dest = $dir . $fn;
  if (move_uploaded_file($_FILES['background_image']['tmp_name'], $dest)) {
    $background_image_path = 'assets/images/heroes/' . $fn;
  }
}

if ($background_image_path === '') {
  header('Location: ../manage_hero_slider.php');
  exit;
}

if ($id > 0) {
  $stmt = $conn->prepare("UPDATE hero_slides_alt SET title=?, description=?, cta_label=?, cta_url=?, button_color=?, heading_color=?, description_color=?, background_image_path=?, background_image_alt=?, sort_order=?, is_published=?, start_at=?, end_at=? WHERE id=? AND slider_id=?");
  if ($stmt) {
    $stmt->bind_param('sssssssssiissii', $title, $description, $cta_label, $cta_url, $button_color, $heading_color, $description_color, $background_image_path, $background_image_alt, $sort_order, $is_published, $start_at, $end_at, $id, $slider_id);
    $stmt->execute();
    $stmt->close();
  }
} else {
  $stmt = $conn->prepare("INSERT INTO hero_slides_alt (slider_id, title, description, cta_label, cta_url, button_color, heading_color, description_color, background_image_path, background_image_alt, sort_order, is_published, start_at, end_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  if ($stmt) {
    $stmt->bind_param('isssssssssiiss', $slider_id, $title, $description, $cta_label, $cta_url, $button_color, $heading_color, $description_color, $background_image_path, $background_image_alt, $sort_order, $is_published, $start_at, $end_at);
    $stmt->execute();
    $stmt->close();
  }
}

header('Location: ../manage_hero_slider.php');
