<?php
session_start();

if (file_exists('../config/db.php')) {
    @include_once '../config/db.php';
}

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = null;
$message = '';
$isError = false;

// Fetch existing product data
if ($productId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $message = "Database error: " . $e->getMessage();
        $isError = true;
    }
}

// Handle Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $productId > 0) {
    $pName = trim($_POST['name']);
    $pCategory = trim($_POST['category']);
    $pPrice = floatval($_POST['price']);
    $pImg = trim($_POST['image_url']);

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $updateStmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, image_url = ? WHERE product_id = ?");
            $updateStmt->execute([$pName, $pCategory, $pPrice, $pImg, $productId]);
            
            $message = "Product updated successfully!";
            
            // Refresh product details
            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $message = "Error updating product: " . $e->getMessage();
            $isError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Takealot Admin</title>
    <link rel="stylesheet" href="../assets/style.css?v=2100">
</head>
<body>

    <header style="background-color: #0b1829;">
        <nav>
            <a href="index.php" class="logo">takealot<span>.admin</span></a>
            <div class="nav-links">
                <a href="index.php">← Back to Dashboard</a>
                <a href="../logout.php" class="auth-btn">Logout</a>
            </div>
        </nav>
    </header>

    <main style="max-width: 800px; margin: 30px auto;">
        <div class="section-header">
            <h2>Edit Product #<?php echo $productId; ?></h2>
        </div>

        <?php if ($product): ?>
            <div style="background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                
                <?php if (!empty($message)): ?>
                    <p style="color: <?php echo $isError ? '#e53e3e' : '#00a651'; ?>; font-weight: bold; font-size: 13px; margin-bottom: 15px;"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>

                <form action="edit_product.php?id=<?php echo $productId; ?>" method="POST">
                    <div class="form-group">
                        <label for="name">Product Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px;" required>
                            <option value="Electronics" <?php echo ($product['category'] === 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                            <option value="Fashion" <?php echo ($product['category'] === 'Fashion') ? 'selected' : ''; ?>>Fashion</option>
                            <option value="Home & Appliances" <?php echo ($product['category'] === 'Home & Appliances') ? 'selected' : ''; ?>>Home & Appliances</option>
                            <option value="Beauty & Personal Care" <?php echo ($product['category'] === 'Beauty & Personal Care') ? 'selected' : ''; ?>>Beauty & Personal Care</option>
                            <option value="Sports & Outdoors" <?php echo ($product['category'] === 'Sports & Outdoors') ? 'selected' : ''; ?>>Sports & Outdoors</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (ZAR R)</label>
                        <input type="number" step="0.01" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" value="<?php echo htmlspecialchars($product['image_url']); ?>" required>
                    </div>

                    <button type="submit" class="add-to-cart-btn" style="margin-top: 10px; padding: 12px;">Save Changes</button>
                </form>
            </div>
        <?php else: ?>
            <div style="background: #fff; padding: 30px; text-align: center; border-radius: 6px;">
                <h3>Product not found</h3>
                <p><a href="index.php" style="color: #0076bd;">Return to Dashboard</a></p>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Takealot Admin Portal.</p>
    </footer>

</body>
</html>