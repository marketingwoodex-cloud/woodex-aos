<?php
require_once __DIR__ . '/./config/session.php';
wdx_session_start();
include 'config/connection.php'; // Include database connection file

// Initialize error message variable
$error_message = "";

require_once __DIR__ . '/config/login_throttle.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (wdx_throttle_blocked()) {
        http_response_code(429);
        header('Retry-After: ' . (wdx_throttle_remaining() * 60));
        $error_message = 'Too many failed attempts from this address. Try again in '
            . wdx_throttle_remaining() . ' minutes.';
    }

    // Sanitize and fetch input
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // Query to fetch user details
    $stmt = $conn->prepare("SELECT id, username, name, password, role FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $user['password'])) {
            wdx_throttle_reset();
            // New session id on privilege change — an id planted before login
            // must not survive it.
            session_regenerate_id(true);

            // Store user data in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Redirect to dashboard or appropriate page
            header("Location: dashboard.php");
            exit();
        } else {
            wdx_throttle_fail();
            $error_message = "Invalid username or password.";
        }
    } else {
        wdx_throttle_fail();
        $error_message = "Invalid username or password.";
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS for Larger Layout -->
    <style>
        /* Center the login box */
        body, html {
            height: 100%;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa; /* Plain background color */
        }
        /* Enlarged login form with subtle styling */
        .login-form {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 60px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
            color: #333;
            animation: fadeIn 1s ease;
        }
        /* Logo styling */
        .login-form img {
            display: block;
            margin: 0 auto 20px;
            width: 200px;
        }
        /* Input and button styling */
        .form-control {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #333;
            padding: 16px;
            font-size: 18px;
        }
        .form-control:focus {
            background-color: #e9ecef;
            border-color: #ced4da;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #333;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-size: 20px;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #555;
            transform: scale(1.02);
        }
        /* Fade-in animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="login-form text-center">
        <!-- Logo -->
        <img src="<?php echo $domain;?>assets/images/header-logo.png" alt="Logo">
        <h2 class="mb-4">Admin Login</h2>

        <!-- Display Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="post">
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Username" name="username" required>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" placeholder="Password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
    </div>

    <!-- Bootstrap JS and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
