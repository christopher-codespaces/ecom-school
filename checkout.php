<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

$cartItems = [];
$subtotal = 0;

if ($pdo instanceof PDO) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $qty = $_SESSION['cart'][$row['product_id']];
        $subtotal += $row['price'] * $qty;
        $row['qty'] = $qty;
        $cartItems[] = $row;
    }
}

$shipping = ($subtotal < 450) ? 75.00 : 0.00;
$total = $subtotal + $shipping;
$orderPlaced = false;
$placedOrderId = null;
$checkoutError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($fullName !== '' && $address !== '' && $city !== '') {
        try {
            if ($pdo instanceof PDO) {
                $cols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
                $userId = $_SESSION['user_id'] ?? null;
                $fullAddress = "$fullName, $address, $city";

                if (in_array('shipping_address', $cols) && in_array('status', $cols)) {
                    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, shipping_address, status) VALUES (?, ?, ?, 'Pending')");
                    $stmt->execute([$userId, $total, $fullAddress]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount) VALUES (?, ?)");
                    $stmt->execute([$userId, $total]);
                }

                $placedOrderId = $pdo->lastInsertId();

                if ($placedOrderId) {
                    $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                    foreach ($cartItems as $item) {
                        $itemStmt->execute([
                            $placedOrderId,
                            $item['product_id'],
                            $item['name'],
                            $item['qty'],
                            $item['price']
                        ]);
                    }
                }

                $_SESSION['cart'] = [];
                $orderPlaced = true;
            }
        } catch (Exception $e) {
            $checkoutError = "Order could not be saved: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | MAKO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3400">
</head>
<body>
    <header class="site-header">
        <nav class="nav-container">
            <a href="index.php" class="brand-logo"><span class="logo-mark"></span> MAKO</a>
            <a href="cart.php" class="nav-link-muted">← BACK TO BAG</a>
        </nav>
    </header>

    <main class="page-container">
        <?php if ($orderPlaced): ?>
            <div class="empty-state-box">
                <span class="eyebrow-tag">DISPATCH CONFIRMED <?php echo $placedOrderId ? '// #MK-' . str_pad((string)$placedOrderId, 4, '0', STR_PAD_LEFT) : ''; ?></span>
                <h1 style="font-size:38px;margin-bottom:12px;">ORDER RECEIVED.</h1>
                <p>Thank you. Your gear is being prepared for priority courier dispatch.</p>
                <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="orders.php" class="btn-primary">VIEW MY ORDERS</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn-ghost">RETURN TO STOREFRONT</a>
                </div>
            </div>
        <?php else: ?>
            <div class="section-header-row">
                <h1>CHECKOUT.</h1>
                <span class="result-counter">ENCRYPTED DISPATCH</span>
            </div>

            <?php if ($checkoutError): ?>
                <div class="alert-banner"><?php echo htmlspecialchars($checkoutError); ?></div>
            <?php endif; ?>

            <div class="split-checkout-grid">
                <form method="POST" action="checkout.php" class="dark-box">
                    <h3 style="margin-bottom:20px;">SHIPPING DETAILS</h3>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert-banner">
                            Tip: <a href="login.php" style="color:#fff;font-weight:700;">Sign in</a> before placing your order so it saves to your Order History.
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Recipient Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Street Address</label>
                        <input type="text" name="address" placeholder="14 Bree Street, Cape Town City Centre" required>
                    </div>
                    <div class="form-group">
                        <label>City & Postal Code</label>
                        <input type="text" name="city" placeholder="Cape Town, 8001" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="card">Credit / Debit Card (Instant EFT)</option>
                            <option value="eft">Direct Bank Transfer</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-full">COMPLETE ORDER — R <?php echo number_format($total, 2); ?></button>
                </form>

                <div class="dark-box">
                    <h3 style="margin-bottom:20px;">IN YOUR BAG</h3>
                    <?php foreach ($cartItems as $item): ?>
                        <div class="summary-line">
                            <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['qty']; ?></span>
                            <span>R <?php echo number_format($item['price'] * $item['qty'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-line" style="margin-top:16px;">
                        <span>Shipping</span>
                        <span><?php echo $shipping === 0.00 ? 'FREE' : 'R ' . number_format($shipping, 2); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>TOTAL DUE</span>
                        <span>R <?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>