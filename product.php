<?php
session_start();
require_once __DIR__ . '/config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($pdo instanceof PDO && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$product) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> | MAKO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3200">
</head>
<body>
    <header class="site-header">
        <nav class="nav-container">
            <a href="index.php" class="brand-logo"><span class="logo-mark"></span> MAKO</a>
            <div class="nav-actions">
                <a href="index.php" class="nav-link-muted">← CATALOG</a>
                <a href="cart.php" class="cart-link">BAG <span><?php echo !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?></span></a>
            </div>
        </nav>
    </header>

    <main class="page-container">
        <div class="product-detail-grid">
            <div class="product-detail-stage">
                <img src="<?php echo htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : 'https://via.placeholder.com/500'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>

            <div>
                <span class="eyebrow-tag"><?php echo strtoupper(htmlspecialchars($product['category'] ?? 'CORE EDITION')); ?></span>
                <h1 style="font-size: 42px; margin-bottom: 14px;"><?php echo htmlspecialchars($product['name']); ?></h1>
                <p style="font-size: 24px; font-weight: 700; margin-bottom: 24px;">R <?php echo number_format($product['price'], 2); ?></p>
                <p style="color: var(--text-muted); font-size: 15px; margin-bottom: 32px; line-height: 1.7;">
                    <?php echo nl2br(htmlspecialchars(!empty($product['description']) ? $product['description'] : 'Engineered for high-output performance and extreme durability.')); ?>
                </p>

                <a href="cart.php?add=<?php echo $product['product_id']; ?>" class="btn-primary" style="width:100%; text-align:center;">+ ADD TO SHOPPING BAG</a>

                <ul class="spec-list" style="margin-top: 36px;">
                    <li><strong>01.</strong> Complimentary courier delivery on orders over R450.</li>
                    <li><strong>02.</strong> 30-day performance guarantee & returns.</li>
                    <li><strong>03.</strong> Dispatched directly from Cape Town warehouse.</li>
                </ul>
            </div>
        </div>
    </main>
</body>
</html>