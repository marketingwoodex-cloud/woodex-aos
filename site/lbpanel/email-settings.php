<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';
require_once __DIR__ . '/includes/email_config.php';

// Require login
if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access email settings.';
    header('Location: index.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = [
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? 465,
        'smtp_secure' => $_POST['smtp_secure'] ?? 'ssl',
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '', // blank => keep existing
        'from_email' => $_POST['from_email'] ?? '',
        'from_name' => $_POST['from_name'] ?? 'Woodex',
        'reply_to_email' => $_POST['reply_to_email'] ?? '',
        'reply_to_name' => $_POST['reply_to_name'] ?? 'Woodex',
        'order_notify_email' => $_POST['order_notify_email'] ?? '',
        'contact_team_email' => $_POST['contact_team_email'] ?? '',
        'support_email' => $_POST['support_email'] ?? '',
    ];

    // Basic validation when enabled
    if (!empty($post['enabled'])) {
        if (trim($post['smtp_host']) === '' || trim($post['smtp_username']) === '' || trim($post['from_email']) === '') {
            $_SESSION['error'] = 'SMTP Host, Username, and From Email are required when email is enabled.';
            header('Location: email-settings.php');
            exit;
        }
        if (!filter_var($post['from_email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'From Email is not a valid email address.';
            header('Location: email-settings.php');
            exit;
        }
    }

    if (update_email_settings($conn, $post)) {
        $_SESSION['success'] = 'Email settings updated.';
    } else {
        $_SESSION['error'] = 'Failed to update email settings.';
    }

    header('Location: email-settings.php');
    exit;
}

$settings = get_email_settings($conn);

if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

$masked = '';
if (!empty($settings['smtp_password'])) {
    $masked = str_repeat('*', 10);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .settings-container{max-width:1000px}
    .help{font-size:.9rem;color:#6b7280}
  </style>
</head>
<body>
  <?php include 'includes/navbar.php'; ?>

  <div class="settings-container">
    <div class="settings-header">
      <h4>Email Settings (SMTP)</h4>
    </div>

    <?php if ($successMessage): ?>
      <div class="alert alert-success alert-dismissible fade show mt-4" role="alert">
        <?= htmlspecialchars($successMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php elseif ($errorMessage): ?>
      <div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
        <?= htmlspecialchars($errorMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <form method="post" class="mt-4">
      <div class="row g-3">
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="enabled" name="enabled" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="enabled">Enable outgoing emails</label>
          </div>
          <div class="help">If disabled, checkout/order/contact emails will not be sent (orders still place normally).</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">SMTP Host</label>
          <input class="form-control" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="mail.yourdomain.com">
        </div>
        <div class="col-md-3">
          <label class="form-label">Port</label>
          <input class="form-control" type="number" name="smtp_port" value="<?= (int)($settings['smtp_port'] ?? 465) ?>" min="1" max="65535">
        </div>
        <div class="col-md-3">
          <label class="form-label">Encryption</label>
          <?php $sec = (string)($settings['smtp_secure'] ?? 'ssl'); ?>
          <select class="form-select" name="smtp_secure">
            <option value="ssl" <?= $sec==='ssl'?'selected':'' ?>>SSL</option>
            <option value="tls" <?= $sec==='tls'?'selected':'' ?>>TLS</option>
            <option value="" <?= $sec===''?'selected':'' ?>>None</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">SMTP Username</label>
          <input class="form-control" name="smtp_username" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">SMTP Password</label>
          <input class="form-control" type="password" name="smtp_password" value="" placeholder="<?= $masked ? 'Saved (leave blank to keep)' : 'Enter password' ?>">
          <div class="help">Password is stored in DB. Leave blank to keep existing.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">From Email</label>
          <input class="form-control" name="from_email" value="<?= htmlspecialchars($settings['from_email'] ?? '') ?>" placeholder="noreply@yourdomain.com">
        </div>
        <div class="col-md-6">
          <label class="form-label">From Name</label>
          <input class="form-control" name="from_name" value="<?= htmlspecialchars($settings['from_name'] ?? 'Woodex') ?>" placeholder="Woodex">
        </div>

        <div class="col-md-6">
          <label class="form-label">Reply-To Email (optional)</label>
          <input class="form-control" name="reply_to_email" value="<?= htmlspecialchars($settings['reply_to_email'] ?? '') ?>" placeholder="info@yourdomain.com">
        </div>
        <div class="col-md-6">
          <label class="form-label">Reply-To Name (optional)</label>
          <input class="form-control" name="reply_to_name" value="<?= htmlspecialchars($settings['reply_to_name'] ?? 'Woodex') ?>" placeholder="Woodex">
        </div>

        <div class="col-md-4">
          <label class="form-label">Order Notify Email (admin)</label>
          <input class="form-control" name="order_notify_email" value="<?= htmlspecialchars($settings['order_notify_email'] ?? '') ?>" placeholder="info@yourdomain.com">
        </div>
        <div class="col-md-4">
          <label class="form-label">Contact Team Email</label>
          <input class="form-control" name="contact_team_email" value="<?= htmlspecialchars($settings['contact_team_email'] ?? '') ?>" placeholder="support@yourdomain.com">
        </div>
        <div class="col-md-4">
          <label class="form-label">Support Email (Reply-To)</label>
          <input class="form-control" name="support_email" value="<?= htmlspecialchars($settings['support_email'] ?? '') ?>" placeholder="support@yourdomain.com">
        </div>

        <div class="col-12 text-center mt-2">
          <button type="submit" class="btn btn-primary">Save Email Settings</button>
        </div>
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
