<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access top bar settings.';
    header('Location: index.php');
    exit;
}

function ensure_topbar_settings_table($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS topbar_settings (
      id TINYINT UNSIGNED PRIMARY KEY,
      heading_text VARCHAR(255) NOT NULL,
      heading_url VARCHAR(2048) NOT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $exists = $conn->query("SELECT id FROM topbar_settings WHERE id = 1 LIMIT 1");
    if ($exists && $exists->num_rows === 0) {
        $defaultHeading = 'Up to 60% Off Sale Collection';
        $defaultUrl = 'shop';
        $stmt = $conn->prepare("INSERT INTO topbar_settings (id, heading_text, heading_url, is_active) VALUES (1, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param('ss', $defaultHeading, $defaultUrl);
            $stmt->execute();
            $stmt->close();
        }
    }
}

ensure_topbar_settings_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_topbar_settings'])) {
    $headingText = trim((string)($_POST['heading_text'] ?? ''));
    $headingUrl = trim((string)($_POST['heading_url'] ?? ''));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($headingText === '') {
        $_SESSION['error'] = 'Top bar heading is required.';
    } else {
        if ($headingUrl === '') {
            $headingUrl = '#';
        }

        $stmt = $conn->prepare("UPDATE topbar_settings SET heading_text = ?, heading_url = ?, is_active = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param('ssi', $headingText, $headingUrl, $isActive);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Top bar settings saved successfully.';
            } else {
                $_SESSION['error'] = 'Failed to save top bar settings.';
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Failed to prepare top bar settings update.';
        }
    }

    header('Location: topbar-settings.php');
    exit;
}

$settings = [
    'heading_text' => 'Up to 60% Off Sale Collection',
    'heading_url' => 'shop',
    'is_active' => 1,
];

$res = $conn->query("SELECT heading_text, heading_url, is_active FROM topbar_settings WHERE id = 1 LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $settings['heading_text'] = (string)($row['heading_text'] ?? $settings['heading_text']);
    $settings['heading_url'] = (string)($row['heading_url'] ?? $settings['heading_url']);
    $settings['is_active'] = (int)($row['is_active'] ?? 1) === 1 ? 1 : 0;
}

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Bar Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container" style="max-width: 900px; margin-top: 24px; margin-bottom: 40px;">
    <h2 class="mb-3">Top Bar Settings</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><strong>Rotating Top Bar Text</strong></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="save_topbar_settings" value="1">

                <div class="mb-3">
                    <label class="form-label" for="heading_text">Top Bar Heading</label>
                    <input type="text" class="form-control" id="heading_text" name="heading_text" required maxlength="255" value="<?php echo htmlspecialchars($settings['heading_text'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Up to 60% Off Sale Collection">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="heading_url">Heading URL</label>
                    <input type="text" class="form-control" id="heading_url" name="heading_url" maxlength="2048" value="<?php echo htmlspecialchars($settings['heading_url'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="shop or https://example.com/deal">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $settings['is_active'] === 1 ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">Show text in top bar</label>
                </div>

                <button type="submit" class="btn btn-primary">Save Top Bar Settings</button>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
