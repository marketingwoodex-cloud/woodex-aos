<?php
require_once __DIR__ . '/../config/auth_guard.php';
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

ensure_column($conn, 'hero_slides', 'description', "TEXT NULL AFTER title");
ensure_column($conn, 'hero_slides', 'button_color', "VARCHAR(20) NOT NULL DEFAULT '#78be20' AFTER cta_url");
ensure_column($conn, 'hero_slides', 'heading_color', "VARCHAR(20) NOT NULL DEFAULT '#111111' AFTER button_color");
ensure_column($conn, 'hero_slides', 'description_color', "VARCHAR(20) NOT NULL DEFAULT '#4f5d6b' AFTER heading_color");

$slider_id   = (int)($_POST['slider_id'] ?? 0);
$id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$kicker      = trim($_POST['kicker'] ?? '');
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$cta_label   = trim($_POST['cta_label'] ?? '');
$cta_url     = trim($_POST['cta_url'] ?? '');
$button_color = trim($_POST['button_color'] ?? '#78be20');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $button_color)) {
  $button_color = '#78be20';
}
$heading_color = trim($_POST['heading_color'] ?? '#111111');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $heading_color)) {
  $heading_color = '#111111';
}
$description_color = trim($_POST['description_color'] ?? '#4f5d6b');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $description_color)) {
  $description_color = '#4f5d6b';
}
$text_col    = ($_POST['text_col'] ?? 'left') === 'right' ? 'right' : 'left';
$sort_order  = (int)($_POST['sort_order'] ?? 1);
$is_published= (int)($_POST['is_published'] ?? 1);
$image_alt   = trim($_POST['image_alt'] ?? '');
$start_at    = !empty($_POST['start_at']) ? $_POST['start_at'] : null;
$end_at      = !empty($_POST['end_at']) ? $_POST['end_at'] : null;

if (!$slider_id || !$title) {
  header('Location: ../manage_hero_slider.php'); exit;
}

$image_path = $_POST['existing_image'] ?? '';
if (!empty($_FILES['image']['name'])) {
  $dir = '../../assets/images/heroes/';
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  $fn = time().'_'.preg_replace('/[^a-zA-Z0-9\._-]/','', $_FILES['image']['name']);
  $dest = $dir.$fn;
  if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
    $image_path = 'assets/images/heroes/'.$fn;
  }
}

if ($id) {
  $stmt = $conn->prepare("UPDATE hero_slides SET kicker=?, title=?, description=?, cta_label=?, cta_url=?, button_color=?, heading_color=?, description_color=?, image_path=?, image_alt=?, text_col=?, sort_order=?, is_published=?, start_at=?, end_at=? WHERE id=? AND slider_id=?");
  if (!$stmt) { header('Location: ../manage_hero_slider.php'); exit; }
  $stmt->bind_param("sssssssssssiissii",
    $kicker, $title, $description, $cta_label, $cta_url, $button_color, $heading_color, $description_color, $image_path, $image_alt, $text_col, $sort_order, $is_published, $start_at, $end_at, $id, $slider_id
  );
  $stmt->execute(); $stmt->close();
} else {
  $stmt = $conn->prepare("INSERT INTO hero_slides (slider_id, kicker, title, description, cta_label, cta_url, button_color, heading_color, description_color, image_path, image_alt, text_col, sort_order, is_published, start_at, end_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
  if (!$stmt) { header('Location: ../manage_hero_slider.php'); exit; }
  $stmt->bind_param("isssssssssssiiss",
    $slider_id, $kicker, $title, $description, $cta_label, $cta_url, $button_color, $heading_color, $description_color, $image_path, $image_alt, $text_col, $sort_order, $is_published, $start_at, $end_at
  );
  $stmt->execute(); $stmt->close();
}

header('Location: ../manage_hero_slider.php');
