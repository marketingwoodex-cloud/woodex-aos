<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}

require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php';

// Ensure user is logged in and username is in session
if (!isset($_SESSION['username'])) {
    $_SESSION['error'] = 'You must be logged in to access settings.';
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['name'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validate required fields
    if (empty($fullName)) {
        $_SESSION['error'] = 'Full Name cannot be empty.';
        header('Location: setting.php');
        exit;
    }

    // Fetch existing user data
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $userResult = $stmt->get_result();
    if ($userResult->num_rows === 0) {
        $_SESSION['error'] = 'User not found.';
        header('Location: setting.php');
        exit;
    }
    $userData = $userResult->fetch_assoc();
    $userId = $userData['id'];
    $stmt->close();

    // If passwords are provided, check matching and hash
    if (!empty($password) || !empty($confirmPassword)) {
        if ($password !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match.';
            header('Location: setting.php');
            exit;
        }
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $updateStmt = $conn->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
        $updateStmt->bind_param('ssi', $fullName, $hashedPassword, $userId);
    } else {
        // No password change
        $updateStmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
        $updateStmt->bind_param('si', $fullName, $userId);
    }

    if ($updateStmt->execute()) {
        $_SESSION['success'] = 'Settings updated successfully!';
    } else {
        $_SESSION['error'] = 'Error updating settings: ' . $conn->error;
    }
    $updateStmt->close();

    header('Location: setting.php');
    exit;
}

// Fetch the current user data to populate the form
$stmt = $conn->prepare("SELECT username, name FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check for session messages
$successMessage = '';
$errorMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<?php include 'includes/navbar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="settings-container">
    <div class="settings-header">
        <h4>Manage Settings</h4>
    </div>

    <!-- Display Success or Error Message -->
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show mt-4" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Settings Form -->
    <form action="" method="POST" class="mt-4">
        <div class="row">
            <div class="col-md-6">
                <!-- Username (Non-editable) -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($userRow['username']) ?>" readonly>
                </div>

                <!-- Name -->
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($userRow['name']) ?>" required>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Change Password -->
                <div class="form-group">
                    <label for="password">Change Password</label>
                    <input type="text" class="form-control" id="password" name="password" placeholder="New Password">
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="text" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password">
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="form-group text-center">
            <button type="submit" class="btn btn-primary">Save Settings</button>
            <button type="reset" class="btn btn-secondary">Reset</button>
        </div>
    </form>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
