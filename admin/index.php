<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Protect admin route (zero pipe characters used)
$isAdmin = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $isAdmin = true;
} elseif (!empty($_SESSION['admin_logged_in'])) {
    $isAdmin = true;
}

if (!$isAdmin) {
    header("Location: ../login.php");
    exit;
}

$flash = '';
$adminDisplay = 'Merchant Admin';
if (isset($_SESSION['user_name'])) {
    $adminDisplay = $_SESSION['user_name'];
} elseif (isset($_SESSION['admin_email'])) {
    $adminDisplay = $_SESSION['admin_email'];
}

if ($pdo instanceof PDO) {
    // Ensure orders & order_items tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        shipping_address TEXT NULL,
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

    // Detect `orders` table columns dynamically
    $orderCols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
    $orderIdCol = in_array('order_id', $orderCols) ? 'order_id' : 'id';
    $totalCol = in_array('total_amount', $orderCols) ? 'total_amount' : 'total';
    $dateCol = null;
    if (in_array('created_at', $orderCols)) {
        $dateCol = 'created_at';
    } elseif (in_array('order_date', $orderCols)) {
        $dateCol = 'order_date';
    }

    // 1. UPDATE ORDER STATUS
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
        $oid = (int)$_POST['order_id'];
        $newStatus = trim($_POST['status']);
        if (in_array('status', $orderCols)) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE $orderIdCol = ?");
            $stmt->execute([$newStatus, $oid]);
            $flash = "Order #MK-" . str_pad((string)$oid, 4, '0', STR_PAD_LEFT) . " status updated to '$newStatus'.";
        }
    }

    // 2. PUBLISH NEW PRODUCT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Electronics');
        $price = (float)($_POST['price'] ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name !== '' && $price > 0) {
            $prodCols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('description', $prodCols)) {
                $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image_url, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category, $price, $imageUrl, $description]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $category, $price, $imageUrl]);
            }
            $flash = "Product '$name' published to storefront.";
        }
    }

    // 3. UPDATE EXISTING PRODUCT
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
        $pid = (int)$_POST['product_id'];
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Electronics');
        $price = (float)($_POST['price'] ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($pid > 0 && $name !== '' && $price > 0) {
            $prodCols = $pdo->query("DESCRIBE products")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('description', $prodCols)) {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, image_url = ?, description = ? WHERE product_id = ?");
                $stmt->execute([$name, $category, $price, $imageUrl, $description, $pid]);
            } else {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, image_url = ? WHERE product_id = ?");
                $stmt->execute([$name, $category, $price, $imageUrl, $pid]);
            }
            $flash = "Updated product #$pid ('$name').";
        }
    }

    // 4. DELETE PRODUCT
    if (isset($_GET['delete_product'])) {
        $pid = (int)$_GET['delete_product'];
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->execute([$pid]);
        header("Location: index.php?tab=products&msg=deleted");
        exit;
    }

    if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
        $flash = "Product removed from catalog.";
    }

    // FETCH ALL PRODUCTS
    $products = $pdo->query("SELECT * FROM products ORDER BY product_id DESC")->fetchAll(PDO::FETCH_ASSOC);

    // CHECK IF EDITING A SPECIFIC PRODUCT
    $editProduct = null;
    if (isset($_GET['edit_product'])) {
        $editId = (int)$_GET['edit_product'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->execute([$editId]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // FETCH ALL ORDERS + LINE ITEMS
    $orderBy = $dateCol ? "$dateCol DESC" : "$orderIdCol DESC";
    $orders = $pdo->query("SELECT * FROM orders ORDER BY $orderBy")->fetchAll(PDO::FETCH_ASSOC);

    $totalRevenue = 0;
    $pendingCount = 0;

    foreach ($orders as &$ord) {
        $oid = $ord[$orderIdCol];
        $ord['_id'] = $oid;
        $ord['_total'] = (float)($ord[$totalCol] ?? 0);
        $ord['_date'] = ($dateCol && !empty($ord[$dateCol])) ? date('d M Y, H:i', strtotime($ord[$dateCol])) : 'Recent';
        $totalRevenue += $ord['_total'];

        if (strtolower($ord['status'] ?? 'pending') === 'pending') {
            $pendingCount++;
        }

        try {
            $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemStmt->execute([$oid]);
            $ord['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $ord['items'] = [];
        }
    }
    unset($ord);
} else {
    $products = [];
    $orders = [];
    $totalRevenue = 0;
    $pendingCount = 0;
    $editProduct = null;
}

$activeTab = isset($_GET['edit_product']) ? 'products' : ($_GET['tab'] ?? 'orders');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAKO Merchant Console | Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-canvas: #F4F5F7;
            --bg-sidebar: #111111;
            --bg-surface: #FFFFFF;
            --text-primary: #111213;
            --text-secondary: #61656B;
            --border-color: #E2E5E9;
            --accent-orange: #FF4D1C;
            --accent-orange-hover: #E03E12;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;
            background: var(--bg-sidebar);
            color: #F5F5F3;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .sidebar-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.06em;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            color: #FFFFFF;
            text-decoration: none;
        }

        .brand-dot {
            width: 12px;
            height: 12px;
            background: var(--accent-orange);
            display: inline-block;
        }

        .nav-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #71717A;
            padding: 0 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 14px;
            color: #A1A1AA;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: all 0.15s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }

        .nav-link.active {
            background: rgba(255, 77, 28, 0.16);
            color: #FFFFFF;
            border-left: 3px solid var(--accent-orange);
        }

        .pill-count {
            background: var(--accent-orange);
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 99px;
        }

        .pill-muted {
            background: rgba(255, 255, 255, 0.12);
            color: #D4D4D8;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 16px;
        }

        .admin-profile {
            padding: 8px 12px 14px;
            font-size: 12px;
            color: #A1A1AA;
        }

        .admin-profile strong {
            display: block;
            color: #FFFFFF;
            font-size: 13px;
            margin-top: 2px;
        }

        /* MAIN WORKSPACE */
        .workspace {
            flex: 1;
            padding: 32px 40px;
            max-width: 1360px;
            overflow-x: auto;
        }

        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .workspace-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }

        .workspace-header p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .btn-dark {
            background: #111111;
            color: #FFFFFF;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 8px;
            display: inline-block;
            border: none;
            cursor: pointer;
        }

        .btn-dark:hover {
            background: #27272A;
        }

        .btn-orange {
            background: var(--accent-orange);
            color: #FFFFFF;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 11px 20px;
            border-radius: 8px;
            display: inline-block;
            border: none;
            cursor: pointer;
        }

        .btn-orange:hover {
            background: var(--accent-orange-hover);
        }

        .flash-banner {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        /* KPI METRIC CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px 22px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .kpi-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }

        .kpi-number {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* CARDS & TABLES */
        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h2 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            background: #F9FAFB;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 14px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        tr:hover td {
            background: #FAFAFB;
        }

        .prod-thumb {
            width: 48px;
            height: 48px;
            object-fit: contain;
            background: #EFECE6;
            border-radius: 8px;
            padding: 4px;
            border: 1px solid var(--border-color);
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-processing { background: #DBEAFE; color: #1E40AF; }
        .status-shipped { background: #E0E7FF; color: #3730A3; }
        .status-delivered { background: #D1FAE5; color: #065F46; }
        .status-cancelled { background: #FEE2E2; color: #991B1B; }

        .item-tag {
            display: inline-block;
            background: #F3F4F6;
            color: #1F2937;
            font-size: 12px;
            font-weight: 500;
            padding: 3px 8px;
            border-radius: 5px;
            margin: 2px 4px 2px 0;
        }

        /* FORMS */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .field input, .field select, .field textarea, .inline-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            color: var(--text-primary);
            background: #FFFFFF;
        }

        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: var(--accent-orange);
        }

        .inline-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .inline-select {
            width: auto;
            padding: 7px 10px;
        }

        .action-edit {
            color: #111111;
            font-weight: 600;
            text-decoration: none;
            margin-right: 12px;
        }

        .action-edit:hover {
            text-decoration: underline;
        }

        .action-delete {
            color: #DC2626;
            font-weight: 600;
            text-decoration: none;
        }

        .action-delete:hover {
            text-decoration: underline;
        }

        @media (max-width: 960px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; }
            .kpi-grid, .form-grid { grid-template-columns: 1fr; }
            .workspace { padding: 20px; }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div>
            <a href="index.php" class="sidebar-brand">
                <span class="brand-dot"></span> MAKO
            </a>

            <div class="nav-section-label">Merchant Console</div>
            <a href="index.php?tab=orders" class="nav-link <?php echo $activeTab === 'orders' ? 'active' : ''; ?>">
                <span>Orders & Fulfillment</span>
                <span class="pill-count"><?php echo count($orders); ?></span>
            </a>
            <a href="index.php?tab=products" class="nav-link <?php echo $activeTab === 'products' ? 'active' : ''; ?>">
                <span>Catalog & Inventory</span>
                <span class="pill-muted"><?php echo count($products); ?></span>
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="admin-profile">
                Signed in as
                <strong><?php echo htmlspecialchars($adminDisplay); ?></strong>
            </div>
            <a href="../index.php" class="nav-link">← View Live Storefront</a>
            <a href="../logout.php" class="nav-link" style="color:#F87171;">Logout</a>
        </div>
    </aside>

    <!-- MAIN WORKSPACE -->
    <main class="workspace">
        <div class="workspace-header">
            <div>
                <h1><?php echo $activeTab === 'orders' ? 'Orders & Fulfillment' : 'Catalog & Inventory'; ?></h1>
                <p>MAKO Commerce Operations — Cape Town Dispatch</p>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="index.php?tab=products" class="btn-orange">+ Add Product</a>
                <a href="../index.php" class="btn-dark">Open Storefront ↗</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash-banner"><?php echo htmlspecialchars($flash); ?></div>
        <?php endif; ?>

        <!-- KPI SUMMARY STRIP -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-title">Gross Revenue</div>
                <div class="kpi-number">R <?php echo number_format($totalRevenue, 2); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Total Orders</div>
                <div class="kpi-number"><?php echo count($orders); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Pending Dispatch</div>
                <div class="kpi-number"><?php echo $pendingCount; ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-title">Published Models</div>
                <div class="kpi-number"><?php echo count($products); ?></div>
            </div>
        </div>

        <?php if ($activeTab === 'orders'): ?>
            <!-- ORDERS TABLE -->
            <div class="card">
                <div class="card-header">
                    <h2>Customer Orders</h2>
                    <span style="font-size:12px;color:var(--text-secondary);">Live Database Feed</span>
                </div>

                <?php if (!empty($orders)): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Destination</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Update Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $ord): ?>
                                    <?php $st = $ord['status'] ?? 'Pending'; ?>
                                    <tr>
                                        <td><strong>#MK-<?php echo str_pad((string)$ord['_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td style="color:var(--text-secondary);white-space:nowrap;"><?php echo htmlspecialchars($ord['_date']); ?></td>
                                        <td style="max-width:220px;"><?php echo htmlspecialchars($ord['shipping_address'] ?? '—'); ?></td>
                                        <td>
                                            <?php if (!empty($ord['items'])): ?>
                                                <?php foreach ($ord['items'] as $it): ?>
                                                    <span class="item-tag"><?php echo htmlspecialchars($it['product_name']); ?> × <?php echo (int)$it['quantity']; ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span style="color:var(--text-secondary);font-size:12px;">Order recorded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>R <?php echo number_format($ord['_total'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-pill status-<?php echo strtolower(htmlspecialchars($st)); ?>">
                                                <?php echo htmlspecialchars($st); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="index.php?tab=orders" class="inline-form">
                                                <input type="hidden" name="order_id" value="<?php echo $ord['_id']; ?>">
                                                <select name="status" class="inline-select">
                                                    <?php foreach (['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $opt): ?>
                                                        <option value="<?php echo $opt; ?>" <?php echo strcasecmp($st, $opt) === 0 ? 'selected' : ''; ?>>
                                                            <?php echo $opt; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_order_status" class="btn-dark" style="padding:7px 12px;">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-secondary);font-size:14px;padding:16px 0;">
                        No orders placed yet. Complete a checkout on the storefront to see live orders appear here.
                    </p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- EDIT OR ADD PRODUCT FORM -->
            <div class="card">
                <div class="card-header">
                    <h2><?php echo $editProduct ? 'Edit Model #' . (int)$editProduct['product_id'] : 'Add New Product to Inventory'; ?></h2>
                    <?php if ($editProduct): ?>
                        <a href="index.php?tab=products" style="font-size:12px;color:var(--text-secondary);text-decoration:none;">✕ Cancel Edit</a>
                    <?php endif; ?>
                </div>

                <form method="POST" action="index.php?tab=products">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="product_id" value="<?php echo (int)$editProduct['product_id']; ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="field">
                            <label>Product Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($editProduct['name'] ?? ''); ?>" placeholder="e.g. MAKO Apex Carbon" required>
                        </div>
                        <div class="field">
                            <label>Category</label>
                            <select name="category">
                                <?php
                                $cats = ['Electronics', 'Fashion', 'Sports & Outdoors', 'Beauty & Personal Care', 'Home & Appliances'];
                                $currentCat = $editProduct['category'] ?? 'Electronics';
                                foreach ($cats as $c):
                                ?>
                                    <option value="<?php echo $c; ?>" <?php echo $currentCat === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Price (ZAR R)</label>
                            <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($editProduct['price'] ?? ''); ?>" placeholder="1299.00" required>
                        </div>
                        <div class="field">
                            <label>Image URL</label>
                            <input type="text" name="image_url" value="<?php echo htmlspecialchars($editProduct['image_url'] ?? ''); ?>" placeholder="https://images.unsplash.com/...">
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:18px;">
                        <label>Technical Description</label>
                        <textarea name="description" rows="2" placeholder="Precision-engineered specifications..."><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($editProduct): ?>
                        <button type="submit" name="update_product" class="btn-orange">Save Product Changes</button>
                    <?php else: ?>
                        <button type="submit" name="add_product" class="btn-orange">+ Publish Product</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- EXISTING INVENTORY TABLE -->
            <div class="card">
                <div class="card-header">
                    <h2>Manage Existing Store Inventory (<?php echo count($products); ?>)</h2>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td style="color:var(--text-secondary);font-weight:600;">#<?php echo (int)$p['product_id']; ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars(!empty($p['image_url']) ? $p['image_url'] : 'https://via.placeholder.com/60'); ?>" class="prod-thumb" alt="">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['category'] ?? '—'); ?></td>
                                    <td><strong>R <?php echo number_format((float)$p['price'], 2); ?></strong></td>
                                    <td>
                                        <a href="index.php?tab=products&edit_product=<?php echo (int)$p['product_id']; ?>" class="action-edit">Edit</a>
                                        <a href="index.php?tab=products&delete_product=<?php echo (int)$p['product_id']; ?>" onclick="return confirm('Delete this product from the store?');" class="action-delete">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>