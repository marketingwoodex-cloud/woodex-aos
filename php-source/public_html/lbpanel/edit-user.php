<?php
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'includes/navbar.php';
include 'config/connection.php';

// Fetch the user ID from the query parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("User ID is required.");
}
$user_id = $conn->real_escape_string($_GET['id']);

// Fetch user details from the database
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found.");
}

// Handle form submission for updating the user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize inputs
    $username = $conn->real_escape_string($_POST['username']);
    $name = $conn->real_escape_string($_POST['name']);
    $role = $conn->real_escape_string($_POST['role']);

    // Only update the password if it's provided
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : $user['password'];

    // Update the user data in the database
    $update_sql = "UPDATE users SET username = ?, name = ?, password = ?, role = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('ssssi', $username, $name, $password, $role, $user_id);

    if ($update_stmt->execute()) {
        $success_message = "User updated successfully!";
        // Refetch updated data
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error_message = "Error: " . $update_stmt->error;
    }

    $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

<!-- Main Form -->
<div class="form-container">
    <div class="form-header">Edit User</div>
    <!-- Display Success or Error Message -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success text-center"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- Username -->
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <!-- Full Name -->
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password">Password <small>(Leave blank to keep existing password)</small></label>
            <input type="text" class="form-control" id="password" name="password" placeholder="Enter New Password">
        </div>

        <!-- Role -->
        <div class="form-group">
            <label for="role">Role</label>
            <select class="form-control" id="role" name="role" required>
                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="editor" <?php echo $user['role'] == 'editor' ? 'selected' : ''; ?>>Editor</option>
                <option value="viewer" <?php echo $user['role'] == 'viewer' ? 'selected' : ''; ?>>Viewer</option>
            </select>
        </div>


        <!-- Submit Button -->
        <div class="form-group text-center">
            <button type="submit" class="btn-submit">Update User</button>
            <a href="view-users.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
