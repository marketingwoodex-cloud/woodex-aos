<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access SEO redirects.';
    header('Location: index.php');
    exit;
}

function normalize_old_url($input) {
    $input = trim((string)$input);
    if ($input === '') {
        return '';
    }

    $parts = parse_url($input);
    $path = isset($parts['path']) ? (string)$parts['path'] : '';
    if ($path === '') {
        $path = '/';
    }

    $path = rawurldecode(str_replace('\\', '/', $path));
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('~/+~', '/', $path);
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

    return $path;
}

function normalize_target_url($input) {
    $input = trim((string)$input);
    if ($input === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $input)) {
        return $input;
    }

    $parts = parse_url($input);
    $path = isset($parts['path']) ? (string)$parts['path'] : '';
    if ($path === '') {
        $path = '/';
    }

    $path = str_replace('\\', '/', $path);
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('~/+~', '/', $path);
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }

    $target = $path;
    if (!empty($parts['query'])) {
        $target .= '?' . $parts['query'];
    }
    if (!empty($parts['fragment'])) {
        $target .= '#' . $parts['fragment'];
    }

    return $target;
}

$createSql = "
CREATE TABLE IF NOT EXISTS url_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    old_url VARCHAR(2048) NOT NULL,
    old_url_normalized VARCHAR(2048) NOT NULL,
    old_url_hash CHAR(32) NOT NULL,
    new_url VARCHAR(2048) NOT NULL,
    http_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_old_url_hash (old_url_hash),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createSql);

if (isset($_GET['toggle_id']) && is_numeric($_GET['toggle_id'])) {
    $toggleId = (int)$_GET['toggle_id'];
    $stmt = $conn->prepare("UPDATE url_redirects SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param('i', $toggleId);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Redirect status updated.';
    } else {
        $_SESSION['error'] = 'Could not update redirect status.';
    }
    $stmt->close();
    header('Location: seo-redirects.php');
    exit;
}

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM url_redirects WHERE id = ?");
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Redirect deleted successfully.';
    } else {
        $_SESSION['error'] = 'Failed to delete redirect.';
    }
    $stmt->close();
    header('Location: seo-redirects.php');
    exit;
}

$editRedirect = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT id, old_url, new_url, is_active FROM url_redirects WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editRedirect = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectId = isset($_POST['redirect_id']) ? (int)$_POST['redirect_id'] : 0;
    $oldUrlInput = trim((string)($_POST['old_url'] ?? ''));
    $newUrlInput = trim((string)($_POST['new_url'] ?? ''));

    $oldNormalized = normalize_old_url($oldUrlInput);
    $newNormalized = normalize_target_url($newUrlInput);

    if ($oldNormalized === '' || $newNormalized === '') {
        $_SESSION['error'] = 'Both old URL and new URL are required.';
    } else {
        $newPathForLoop = normalize_old_url($newNormalized);
        $isAbsoluteTarget = (bool)preg_match('~^https?://~i', $newNormalized);

        if (!$isAbsoluteTarget && $oldNormalized === $newPathForLoop) {
            $_SESSION['error'] = 'Old URL and new URL cannot be the same.';
        } else {
            $oldHash = md5($oldNormalized);

            $dupStmt = $conn->prepare("SELECT id FROM url_redirects WHERE old_url_hash = ? AND old_url_normalized = ? AND id <> ? LIMIT 1");
            $dupStmt->bind_param('ssi', $oldHash, $oldNormalized, $redirectId);
            $dupStmt->execute();
            $duplicate = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();

            if ($duplicate) {
                $_SESSION['error'] = 'A redirect for this old URL already exists.';
            } else {
                if ($redirectId > 0) {
                    $stmt = $conn->prepare("UPDATE url_redirects SET old_url = ?, old_url_normalized = ?, old_url_hash = ?, new_url = ? WHERE id = ?");
                    $stmt->bind_param('ssssi', $oldNormalized, $oldNormalized, $oldHash, $newNormalized, $redirectId);
                    $ok = $stmt->execute();
                    $stmt->close();

                    $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Redirect updated successfully.' : 'Failed to update redirect.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO url_redirects (old_url, old_url_normalized, old_url_hash, new_url, http_code, is_active) VALUES (?, ?, ?, ?, 301, 1)");
                    $stmt->bind_param('ssss', $oldNormalized, $oldNormalized, $oldHash, $newNormalized);
                    $ok = $stmt->execute();
                    $stmt->close();

                    $_SESSION[$ok ? 'success' : 'error'] = $ok ? '301 redirect added successfully.' : 'Failed to add redirect.';
                }
            }
        }
    }

    header('Location: seo-redirects.php');
    exit;
}

$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$redirects = [];
$listResult = $conn->query("SELECT id, old_url, new_url, http_code, is_active, hit_count, last_hit_at, updated_at FROM url_redirects ORDER BY id DESC");
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $redirects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Redirects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container form-container" style="max-width:1100px;">
    <h2 class="form-header">SEO 301 Redirects</h2>
    <p class="text-muted">Map old indexed URLs to new URLs. These redirects are permanent (301) for SEO.</p>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <input type="hidden" name="redirect_id" value="<?= (int)($editRedirect['id'] ?? 0) ?>">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label" for="old_url">Old URL (path or full URL)</label>
                <input type="text" class="form-control" id="old_url" name="old_url" required
                       placeholder="/old-page or https://domain.com/old-page"
                       value="<?= htmlspecialchars($editRedirect['old_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="new_url">New URL (path or full URL)</label>
                <input type="text" class="form-control" id="new_url" name="new_url" required
                       placeholder="/new-page or https://domain.com/new-page"
                       value="<?= htmlspecialchars($editRedirect['new_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100"><?= $editRedirect ? 'Update' : 'Add 301' ?></button>
                <?php if ($editRedirect): ?>
                    <a href="seo-redirects.php" class="btn btn-outline-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Old URL</th>
                    <th>New URL</th>
                    <th style="width:70px;">Code</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:80px;">Hits</th>
                    <th style="width:170px;">Last Hit</th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($redirects)): ?>
                    <tr><td colspan="8">No redirects added yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($redirects as $row): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td class="text-start"><code><?= htmlspecialchars($row['old_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td class="text-start"><code><?= htmlspecialchars($row['new_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><?= (int)$row['http_code'] ?></td>
                            <td>
                                <span class="badge <?= (int)$row['is_active'] === 1 ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= (int)$row['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= (int)$row['hit_count'] ?></td>
                            <td><?= htmlspecialchars($row['last_hit_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="seo-redirects.php?edit_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
                                <a href="seo-redirects.php?toggle_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white">
                                    <i class="fas fa-toggle-<?= (int)$row['is_active'] === 1 ? 'on' : 'off' ?>"></i>
                                    <?= (int)$row['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                                </a>
                                <a href="seo-redirects.php?delete_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this redirect?')"><i class="fas fa-trash"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
