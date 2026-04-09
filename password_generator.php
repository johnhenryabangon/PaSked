<?php
/**
 * Password Hashing Utility for PaSked Admin Accounts
 * 
 * This script helps you create secure password hashes for admin accounts.
 * Run this script in your browser to generate password hashes.
 */

$password_generated = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'];
    if (!empty($password)) {
        $password_generated = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator - PaSked</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h1 style="text-align: center; color: var(--accent-blue); margin-bottom: 1rem;">
                Password Hash Generator
            </h1>
            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 2rem;">
                Create secure password hashes for admin accounts
            </p>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="password" class="form-label">Enter Password:</label>
                    <input type="text" id="password" name="password" class="form-input" 
                           placeholder="Enter the password you want to hash" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Generate Hash
                </button>
            </form>

            <?php if ($password_generated): ?>
                <div style="margin-top: 2rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 6px;">
                    <label class="form-label">Generated Hash (copy this):</label>
                    <textarea class="form-input" rows="3" readonly onclick="this.select()"><?php echo htmlspecialchars($password_generated); ?></textarea>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        Use this hash in the database's admins table password field.
                    </p>
                </div>
            <?php endif; ?>

            <div style="margin-top: 2rem; padding: 1rem; background: var(--bg-secondary); border-radius: 6px; font-size: 0.85rem; color: var(--text-secondary);">
                <strong>Instructions:</strong><br>
                1. Enter your desired password above<br>
                2. Click "Generate Hash"<br>
                3. Copy the generated hash<br>
                4. Use it when inserting/updating admin records in the database
            </div>

            <div style="text-align: center; margin-top: 1rem;">
                <a href="index.php" class="btn btn-primary">← Back to Main Site</a>
            </div>
        </div>
    </div>
</body>
</html>