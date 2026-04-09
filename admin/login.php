<?php
session_start();
include '../config/db_config.php';

$error_message = '';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            $query = "SELECT admin_id, username, password, court_id FROM admins WHERE username = ?";
            $stmt = executeQuery($query, [$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                // Successful login
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_court_id'] = $admin['court_id'];

                header('Location: dashboard.php');
                exit();
            } else {
                $error_message = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error_message = 'Login failed. Please try again.';
        }
    } else {
        $error_message = 'Please enter both username and password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PaSked</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/main.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header Navigation -->
    <header class="header">
        <div class="nav-container">
            <a href="../index.php" class="logo">
                <img class="logo-icon" src="../assets/images/paskedlogo.png" alt="Logo">
                PaSked
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="../index.php">Back to Courts</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="login-container">
        <form class="login-form" method="POST" action="" style="background: rgba(89, 89, 171, 0.1);
    -webkit-backdrop-filter: blur(17px);
    backdrop-filter: blur(17px);">
            <div class="login-title">
                <h1 style="color: #fff;">PaSked Admin</h1>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">Login to manage your court bookings</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       required autofocus>
            </div>

            <div class="form-group" style="position: relative;">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required style="padding-right: 40px;" />
                <button type="button" id="togglePassword" title="Show or hide password" 
                    style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; color: #aaa;">
                    Show
                </button>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Login
                </button>
            </div>
        </form>
    </div>
    
    <script>
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');

        togglePasswordButton.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePasswordButton.textContent = isPassword ? 'Hide' : 'Show';
        });
    </script>
</body>
</html>