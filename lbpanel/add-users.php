<?php
// Protect direct access; includes are authorized by their entry point.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once __DIR__ . '/config/auth_guard.php';
}
 
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'includes/navbar.php'; 
include 'config/connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve and sanitize inputs
    $username = $conn->real_escape_string($_POST['username']);
    $name = $conn->real_escape_string($_POST['name']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Encrypt password
    $role = $conn->real_escape_string($_POST['role']);

    // Insert the data into the database
    $stmt = $conn->prepare("INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $username, $name, $password, $role);

    if ($stmt->execute()) {
        $success_message = "User added successfully!";
    } else {
        $error_message = "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

<!-- Main Form -->
<div class="form-container">
    <div class="form-header">Add New User</div>
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
            <input type="text" class="form-control" id="username" name="username" placeholder="Enter Username" required>
        </div>

        <!-- Full Name -->
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" class="form-control" id="name" name="name" placeholder="Enter Full Name" required>
        </div>

        <!-- Password -->
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Enter Password" required>
        </div>

        <!-- Role -->
        <div class="form-group">
            <label for="role">Role</label>
            <select class="form-control" id="role" name="role" required>
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
            </select>
        </div>

        <!-- Submit Button -->
        <div class="form-group text-center">
            <button type="submit" class="btn-submit">Add User</button>
            <button type="reset" class="btn-cancel">Cancel</button>
        </div>
    </form>
</div>

<!-- Bootstrap JS, jQuery, and Font Awesome -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
