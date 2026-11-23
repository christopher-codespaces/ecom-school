<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$orders = [];

if ($pdo instanceof PDO) {
    try {
        // Ensure tables exist if not already created
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            order_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            shipping_address TEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL
        )");

        // Detect primary key name on `orders` (`order_id` or `id`)
        $cols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
        $idCol = in_array('order_id', $cols) ? 'order_id' : 'id';
        $dateCol = in_array('created_at', $cols) ? 'created_at' : (in_array('order_date', $cols) ? 'order_date' : null);

        $orderBy = $dateCol ? "$dateCol DESC" : "$idCol DESC";
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY $orderBy");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch line items for each order
        foreach ($orders as &$order) {
            $oid = $order[$idCol];
            $order['_id'] = $oid;
            $order['_date'] = $dateCol && !empty($order[$dateCol]) ? date('d M Y, H:i', strtotime($order[$dateCol])) : 'Recent Dispatch';

            try {
                $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemStmt->execute([$oid]);
                $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $order['items'] = [];
            }
        }
        unset($order);
    } catch (Exception $e) {
        $orders = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History | MAKO</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3300">
    <style>
        .order-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border-subtle);
            padding: 28px;
            margin-bottom: 24px;
        }
        .order-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 1px solid var(--border-subtle);
        }
        .order-id-tag {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.04em;
        }
        .status-pill {
            display: inline-block;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            border: 1px solid var(--accent-orange);
            color: var(--accent-orange);
        }
        .status-pill.delivered, .status-pill.completed {
            border-color: #22C55E;
            color: #22C55E;
        }
        .order-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .order-meta-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .order-items-list {
            border-top: 1px solid var(--border-subtle);
            padding-top: 14px;
        }
        .order-item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: #D1D1D6;
        }
        @media (max-width: 768px) {
            .order-meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="nav-container">
            <a href="index.php" class="brand-logo"><span class="logo-mark"></span> MAKO</a>
            <div class="nav-actions">
                <a href="index.php" class="nav-link-muted">← STOREFRONT</a>
                <a href="cart.php" class="cart-link">BAG <span><?php echo !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?></span></a>
                <a href="logout.php" class="nav-link-muted">LOGOUT</a>
            </div>
        </nav>
    </header>

    <main class="page-container">
        <div class="section-header-row">
            <div>
                <span class="eyebrow-tag">MEMBER ARCHIVE // <?php echo strtoupper(htmlspecialchars($_SESSION['user_name'] ?? 'ATHLETE')); ?></span>
                <h1>MY ORDERS.</h1>
            </div>
            <span class="result-counter"><?php echo count($orders); ?> DISPATCHES</span>
        </div>

        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <?php $status = $order['status'] ?? 'Pending'; ?>
                <div class="order-card">
                    <div class="order-card-header">
                        <div>
                            <span class="order-id-tag">ORDER #MK-<?php echo str_pad((string)$order['_id'], 4, '0', STR_PAD_LEFT); ?></span>
                            <span style="display:block;font-size:12px;color:var(--text-muted);margin-top:4px;"><?php echo htmlspecialchars($order['_date']); ?></span>
                        </div>
                        <span class="status-pill <?php echo strtolower(htmlspecialchars($status)); ?>">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>

                    <div class="order-meta-grid">
                        <div>
                            <span class="order-meta-label">TOTAL PAID</span>
                            <strong>R <?php echo number_format((float)($order['total_amount'] ?? $order['total'] ?? 0), 2); ?></strong>
                        </div>
                        <div style="grid-column: span 2;">
                            <span class="order-meta-label">DISPATCH DESTINATION</span>
                            <span><?php echo htmlspecialchars($order['shipping_address'] ?? 'Standard Courier Address'); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($order['items'])): ?>
                        <div class="order-items-list">
                            <span class="order-meta-label" style="margin-bottom:8px;">ITEMS IN SHIPMENT</span>
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item-row">
                                    <span><?php echo htmlspecialchars($item['product_name'] ?? 'Model #' . $item['product_id']); ?> × <?php echo (int)$item['quantity']; ?></span>
                                    <span>R <?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state-box">
                <h3>NO ORDERS RECORDED YET</h3>
                <p>Once you complete checkout while signed in, your dispatch history and tracking status will appear here.</p>
                <a href="index.php#core-models" class="btn-primary">EXPLORE CORE MODELS</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>