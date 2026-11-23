<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (file_exists('config/db.php')) {
    @include_once 'config/db.php';
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    if (empty($email) || empty($newPassword)) {
        $message = "Please enter both email and a new password.";
    } else {
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                // Updates your exact column: password_hash
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $stmt->execute([$hash, $email]);

                header('Location: login.php?reset=1');
                exit;
            } catch (Exception $e) {
                $message = "Error updating password: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Takealot Clone</title>
    <link rel="stylesheet" href="assets/style.css?v=2200">
</head>
<body>

    <header>
        <nav>
            <a href="index.php" class="logo">takealot<span>.clone</span></a>
            <div class="nav-links">
                <a href="login.php">Back to Login</a>
            </div>
        </nav>
    </header>

    <main class="auth-wrapper">
        <div class="auth-card">
            <h2>Reset Password</h2>

            <?php if (!empty($message)): ?>
                <p style="color: #e53e3e; font-size: 13px; text-align: center; margin-bottom: 15px;"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <form action="forgot_password.php" method="POST">
                <div class="form-group">
                    <label for="email">Account Email</label>
                    <input type="email" id="email" name="email" placeholder="name@example.com" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                </div>

                <button type="submit" class="add-to-cart-btn" style="margin-top: 10px;">Update Password</button>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Takealot Replica Store. Built with PHP & MySQL.</p>
    </footer>

</body>
</html>