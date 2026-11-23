<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($pdo instanceof PDO && $email !== '' && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $storedHash = $user['password_hash'] ?? $user['user_password'] ?? $user['password'] ?? '';

        if ($user && $storedHash !== '' && (password_verify($password, $storedHash) || $password === $storedHash)) {
            $_SESSION['user_id'] = $user['user_id'] ?? $user['id'];
            $_SESSION['user_name'] = $user['username'] ?? $user['full_name'] ?? $user['name'] ?? 'Member';
            $_SESSION['role'] = $user['role'] ?? 'customer';

            if ($_SESSION['role'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $error = 'Invalid email address or password.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login | MAKO</title>
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
            <span class="eyebrow-tag">MEMBER PORTAL</span>
            <h1>SIGN IN.</h1>
            <p class="auth-subtitle">Access your saved bag, order history, and priority releases.</p>

            <?php if ($error): ?>
                <div class="alert-banner"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="athlete@domain.com" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-full">ENTER STORE</button>
            </form>

            <p class="auth-switch">New to MAKO? <a href="register.php">Create an account</a></p>
        </div>
    </main>
</body>
</html>