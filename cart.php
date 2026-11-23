<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['add'])) {
    $id = (int)$_GET['add'];
    if ($id > 0) {
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
    }
    header("Location: cart.php");
    exit;
}

if (isset($_GET['dec'])) {
    $id = (int)$_GET['dec'];
    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]--;
        if ($_SESSION['cart'][$id] <= 0) {
            unset($_SESSION['cart'][$id]);
        }
    }
    header("Location: cart.php");
    exit;
}

if (isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$id]);
    header("Location: cart.php");
    exit;
}

$cartItems = [];
$subtotal = 0;

if ($pdo instanceof PDO && !empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $qty = $_SESSION['cart'][$row['product_id']];
        $lineTotal = $row['price'] * $qty;
        $subtotal += $lineTotal;
        $row['qty'] = $qty;
        $row['line_total'] = $lineTotal;
        $cartItems[] = $row;
    }
}

$shipping = ($subtotal > 0 && $subtotal < 450) ? 75.00 : 0.00;
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Bag | MAKO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3200">
</head>
<body>
    <header class="site-header">
        <nav class="nav-container">
            <a href="index.php" class="brand-logo"><span class="logo-mark"></span> MAKO</a>
            <a href="index.php" class="nav-link-muted">← CONTINUE SHOPPING</a>
        </nav>
    </header>

    <main class="page-container">
        <div class="section-header-row">
            <h1>YOUR BAG.</h1>
            <span class="result-counter"><?php echo array_sum($_SESSION['cart']); ?> ITEMS</span>
        </div>

        <?php if (!empty($cartItems)): ?>
            <div class="split-checkout-grid">
                <div class="dark-box">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>MODEL</th>
                                <th>QTY</th>
                                <th>TOTAL</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="cart-item-info">
                                            <img src="<?php echo htmlspecialchars(!empty($item['image_url']) ? $item['image_url'] : 'https://via.placeholder.com/100'); ?>" class="cart-thumb" alt="">
                                            <div>
                                                <strong style="display:block;font-size:14px;"><?php echo htmlspecialchars($item['name']); ?></strong>
                                                <span style="font-size:12px;color:var(--text-muted);">R <?php echo number_format($item['price'], 2); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="qty-pill">
                                            <a href="cart.php?dec=<?php echo $item['product_id']; ?>">−</a>
                                            <span><?php echo $item['qty']; ?></span>
                                            <a href="cart.php?add=<?php echo $item['product_id']; ?>">+</a>
                                        </div>
                                    </td>
                                    <td style="font-weight:700;">R <?php echo number_format($item['line_total'], 2); ?></td>
                                    <td style="text-align:right;">
                                        <a href="cart.php?remove=<?php echo $item['product_id']; ?>" style="color:var(--text-muted);text-decoration:none;font-size:12px;">REMOVE</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dark-box">
                    <h3 style="margin-bottom:20px;">ORDER SUMMARY</h3>
                    <div class="summary-line">
                        <span>Subtotal</span>
                        <span>R <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-line">
                        <span>Express Delivery</span>
                        <span><?php echo $shipping === 0.00 ? 'FREE' : 'R ' . number_format($shipping, 2); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>TOTAL</span>
                        <span>R <?php echo number_format($total, 2); ?></span>
                    </div>
                    <a href="checkout.php" class="btn-primary" style="width:100%;text-align:center;margin-top:24px;">PROCEED TO CHECKOUT</a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-box">
                <h3>YOUR BAG IS EMPTY</h3>
                <p>Explore our core models to add performance gear to your bag.</p>
                <a href="index.php" class="btn-primary">EXPLORE STORE</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>