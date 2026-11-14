<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to manage sale settings.';
    header('Location: index.php');
    exit;
}

function ensureSaleSettingsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS sale_page_settings (
      id TINYINT UNSIGNED PRIMARY KEY,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      hero_background VARCHAR(2048) NOT NULL DEFAULT 'assets/images/herobg.webp',
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $exists = $conn->query("SELECT id FROM sale_page_settings WHERE id = 1 LIMIT 1");
    if ($exists && $exists->num_rows === 0) {
        $conn->query("INSERT INTO sale_page_settings (id, is_active, hero_background) VALUES (1, 1, 'assets/images/herobg.webp')");
    }
}

function uploadSaleHeroImage($file, $dir = '../assets/images/heroes/') {
    if (empty($file) || empty($file['name'])) {
        return '';
    }
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed.');
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Only JPG, JPEG, PNG, and WEBP are allowed.');
    }
    $imgInfo = @getimagesize((string)$file['tmp_name']);
    if ($imgInfo === false) {
        throw new Exception('Invalid image file.');
    }
    $filename = 'sale_hero_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $fullPath = $dir . $filename;
    if (!move_uploaded_file((string)$file['tmp_name'], $fullPath)) {
        throw new Exception('Unable to save uploaded image.');
    }
    return 'assets/images/heroes/' . $filename;
}

ensureSaleSettingsTable($conn);

$settings = ['is_active' => 1, 'hero_background' => 'assets/images/herobg.webp'];
$res = $conn->query("SELECT is_active, hero_background FROM sale_page_settings WHERE id = 1 LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $settings['is_active'] = (int)($row['is_active'] ?? 1) === 1 ? 1 : 0;
    $hero = trim((string)($row['hero_background'] ?? ''));
    if ($hero !== '') {
        $settings['hero_background'] = $hero;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $heroBackground = $settings['hero_background'];

        if (!empty($_FILES['hero_background']['name'])) {
            $uploaded = uploadSaleHeroImage($_FILES['hero_background']);
            if ($uploaded !== '') {
                $heroBackground = $uploaded;
            }
        }

        $stmt = $conn->prepare("UPDATE sale_page_settings SET is_active = ?, hero_background = ? WHERE id = 1");
        $stmt->bind_param('is', $isActive, $heroBackground);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save settings: ' . $stmt->error);
        }
        $stmt->close();

        $_SESSION['success'] = 'Sale page settings updated successfully.';
        header('Location: sale-settings.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: sale-settings.php');
        exit;
    }
}

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$heroPreview = preg_match('~^https?://~i', $settings['hero_background'])
    ? $settings['hero_background']
    : ('../' . ltrim($settings['hero_background'], '/'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Page Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container form-container" style="max-width:900px;">
    <h2 class="form-header">Sale Page Settings</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3 p-3 border rounded" style="background:#f8fafc;">
            <label class="d-flex align-items-center gap-2 m-0" style="text-transform:none;letter-spacing:0;font-weight:700;">
                <input type="checkbox" name="is_active" value="1" style="width:18px;height:18px;" <?= $settings['is_active'] === 1 ? 'checked' : '' ?>>
                <span>Activate Sale Page</span>
            </label>
            <small class="text-muted d-block mt-2">When disabled, <code>/sale</code> will show unavailable and Sale links will be hidden from header.</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Sale Page Background Image (Hero)</label>
            <input type="file" class="form-control" name="hero_background" accept="image/*">
            <small class="text-muted">Recommended wide hero image (e.g. 1920x700).</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Current Hero Background</label>
            <div class="border rounded p-2 bg-white">
                <img src="<?= htmlspecialchars($heroPreview, ENT_QUOTES, 'UTF-8') ?>" alt="Sale Hero" style="width:100%;max-height:260px;object-fit:cover;border-radius:6px;">
                <small class="d-block text-muted mt-2"><?= htmlspecialchars($settings['hero_background'], ENT_QUOTES, 'UTF-8') ?></small>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Sale Settings</button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
