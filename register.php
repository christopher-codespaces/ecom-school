<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!($pdo instanceof PDO)) {
        $error = 'Database connection offline.';
    } elseif ($username === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } else {
        try {
            // Inspect actual columns in `users` table
            $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);

            $nameCol = in_array('username', $cols) ? 'username' : (in_array('full_name', $cols) ? 'full_name' : 'name');
            $passCol = in_array('password_hash', $cols) ? 'password_hash' : (in_array('user_password', $cols) ? 'user_password' : 'password');

            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check->execute([$email]);

            if ($check->fetchColumn() > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                if (in_array('role', $cols)) {
                    $stmt = $pdo->prepare("INSERT INTO users ($nameCol, email, $passCol, role) VALUES (?, ?, ?, 'customer')");
                    $stmt->execute([$username, $email, $hashed]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users ($nameCol, email, $passCol) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed]);
                }

                $success = 'Account created. You can now sign in.';
            }
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join MAKO | Create Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3200">
</head>
<body>
    <header class="site-header">
        <nav class="nav-container">
            <a href="index.php" class="brand-logo"><span class="logo-mark"></span> MAKO</a>
            <a href="index.php" class="nav-link-muted">← BACK TO STORE</a>
        </nav>
    </header>

    <main class="auth-wrapper">
        <div class="auth-panel">
            <span class="eyebrow-tag">MEMBERSHIP</span>
            <h1>CREATE ACCOUNT.</h1>
            <p class="auth-subtitle">Join for complimentary shipping over R450 and early drop access.</p>

            <?php if ($error): ?>
                <div class="alert-banner"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-banner success"><?php echo htmlspecialchars($success); ?> <a href="login.php" style="color:#fff;font-weight:700;">Sign In →</a></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label>Full Name / Username</label>
                    <input type="text" name="username" placeholder="Jordan Vance" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="jordan@domain.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a strong password" required>
                </div>
                <button type="submit" class="btn-full">REGISTER MEMBERSHIP</button>
            </form>

            <p class="auth-switch">Already a member? <a href="login.php">Sign in</a></p>
        </div>
    </main>
</body>
</html>