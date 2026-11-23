<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Load database configuration
require_once __DIR__ . '/config/db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$products = [];

// FETCH STRICTLY FROM MYSQL DATABASE
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $sql = "SELECT * FROM products WHERE 1=1";
        $params = [];

        // Filter by Search Query
        if ($search !== '') {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%" . $search . "%";
            $params[] = "%" . $search . "%";
        }

        // Filter by Category
        if ($category !== '') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY product_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $products = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAKO | Engineered Performance Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=3300">
</head>
<body>

    <!-- TOP ANNOUNCEMENT BAR -->
    <div class="top-bar">
        COMPLIMENTARY EXPRESS DELIVERY ON ORDERS OVER R450 — EDITION 01 LIVE
    </div>

    <!-- NAVIGATION -->
    <header class="site-header">
        <nav class="nav-container">
            <a href="index.php" class="brand-logo">
                <span class="logo-mark"></span> MAKO
            </a>

            <div class="nav-center-links">
                <a href="index.php">ALL MODELS</a>
                <a href="index.php?category=Electronics">ELECTRONICS</a>
                <a href="index.php?category=Fashion">APPAREL</a>
                <a href="index.php?category=Sports %26 Outdoors">ENDURANCE</a>
                <a href="index.php?category=Beauty %26 Personal Care">RECOVERY</a>
            </div>

            <div class="nav-actions">
                <form action="index.php" method="GET" class="minimal-search">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search catalog...">
                    <button type="submit" aria-label="Search">⌕</button>
                </form>

                <a href="cart.php" class="cart-link">
                    BAG <span id="cart-count"><?php echo !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?></span>
                </a>

                <?php if (isset($_SESSION['user_name'])): ?>
                    <span class="user-tag"><?php echo strtoupper(htmlspecialchars($_SESSION['user_name'])); ?></span>
                    <a href="orders.php" class="nav-link-muted">ORDERS</a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin/index.php" class="nav-btn-outline">ADMIN</a>
                    <?php endif; ?>
                    <a href="logout.php" class="nav-link-muted">LOGOUT</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link-muted">LOGIN</a>
                    <a href="register.php" class="nav-btn-accent">JOIN</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- FULL-BLEED RUNNER VIDEO HERO (DIRECT FROM ASSETS, NO FALLBACK IMAGE) -->
    <?php if ($category === '' && $search === ''): ?>
    <section class="hero-video-section">
        <video class="hero-bg-video" autoplay muted loop playsinline>
            <source src="assets/runner.mp4" type="video/mp4">
        </video>
        <div class="hero-overlay"></div>

        <div class="hero-content reveal">
            <span class="eyebrow-tag">PERFORMANCE SERIES // 2026</span>
            <h1>MOVE<br>FASTER.</h1>
            <p>Precision-engineered gear built for high-output endurance, harsh terrain, and uncompromising clarity.</p>
            <div class="hero-cta-group">
                <a href="#core-models" class="btn-primary">EXPLORE CORE MODELS</a>
                <a href="#directions" class="btn-ghost">CHOOSE DISCIPLINE</a>
            </div>
        </div>
    </section>

    <!-- TRUST & SPEC STRIP -->
    <div class="spec-strip">
        <div class="spec-strip-inner">
            <span>01 / CARBON-GRADE DURABILITY</span>
            <span>02 / WEATHER-SEALED OPTICS & GEAR</span>
            <span>03 / 30-DAY FIELD TRIAL</span>
            <span>04 / DIRECT DISPATCH</span>
        </div>
    </div>

    <!-- CHOOSE YOUR DIRECTION (4-COLUMN CATEGORY CARDS) -->
    <section id="directions" class="section-wrapper reveal">
        <div class="section-header-row">
            <h2>CHOOSE YOUR DIRECTION.</h2>
            <a href="index.php" class="view-all-link">VIEW ALL CATALOG →</a>
        </div>

        <div class="direction-grid">
            <a href="index.php?category=Sports %26 Outdoors" class="direction-card">
                <img src="https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?auto=format&fit=crop&w=800&q=80" alt="Run">
                <div class="direction-overlay">
                    <span class="card-index">01 // TRAIL & ROAD</span>
                    <h3>RUN</h3>
                    <p>Ultralight essentials engineered for tempo and ultra distances.</p>
                </div>
            </a>

            <a href="index.php?category=Electronics" class="direction-card">
                <img src="https://images.unsplash.com/photo-1517649763962-0c623066013b?auto=format&fit=crop&w=800&q=80" alt="Tech & Telemetry">
                <div class="direction-overlay">
                    <span class="card-index">02 // TELEMETRY & AUDIO</span>
                    <h3>TECH</h3>
                    <p>Acoustics and performance hardware built to endure sweat and pace.</p>
                </div>
            </a>

            <a href="index.php?category=Fashion" class="direction-card">
                <img src="https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80" alt="Apparel">
                <div class="direction-overlay">
                    <span class="card-index">03 // TECHNICAL WEAR</span>
                    <h3>APPAREL</h3>
                    <p>Breathable textiles tailored for unrestricted athletic movement.</p>
                </div>
            </a>

            <a href="index.php?category=Home %26 Appliances" class="direction-card">
                <img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=800&q=80" alt="Alpine & Basecamp">
                <div class="direction-overlay">
                    <span class="card-index">04 // BASECAMP & UTILITY</span>
                    <h3>ALPINE</h3>
                    <p>All-weather equipment designed for elevated environments.</p>
                </div>
            </a>
        </div>
    </section>

    <!-- EDITORIAL FEATURE SPLIT -->
    <section class="section-wrapper editorial-split reveal">
        <div class="editorial-media">
            <img src="https://images.unsplash.com/photo-1572635196237-14b3f281503f?auto=format&fit=crop&w=900&q=80" alt="Engineered Detail">
        </div>
        <div class="editorial-copy">
            <span class="eyebrow-tag">DESIGN PHILOSOPHY</span>
            <h2>ENGINEERED,<br>NOT DECORATED.</h2>
            <p>Every gram is accounted for. We strip away unnecessary ornament to focus strictly on structural balance, impact resistance, and tactile control under fatigue.</p>
            <ul class="spec-list">
                <li><strong>01.</strong> Field-tested by endurance athletes across coastal and alpine routes.</li>
                <li><strong>02.</strong> Precision-milled hardware and high-tensile materials.</li>
                <li><strong>03.</strong> Backed by lifetime structural warranty.</li>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <!-- CORE MODELS (LIVE DATABASE PRODUCTS GRID) -->
    <main id="core-models" class="section-wrapper reveal">
        <div class="section-header-row">
            <div>
                <h2>
                    <?php 
                        if ($category !== '') {
                            echo "CATEGORY // " . strtoupper(htmlspecialchars($category));
                        } elseif ($search !== '') {
                            echo "SEARCH // \"" . strtoupper(htmlspecialchars($search)) . "\"";
                        } else {
                            echo "CORE MODELS.";
                        }
                    ?>
                </h2>
                <?php if ($category !== '' || $search !== ''): ?>
                    <a href="index.php" class="clear-filter-link">← RESET FILTERS</a>
                <?php endif; ?>
            </div>
            <span class="result-counter"><?php echo count($products); ?> ITEMS AVAILABLE</span>
        </div>

        <div class="product-grid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <article class="product-card reveal">
                        <a href="product.php?id=<?php echo $product['product_id']; ?>" class="product-studio-box" style="text-decoration:none;">
                            <span class="product-badge"><?php echo strtoupper(htmlspecialchars(!empty($product['category']) ? $product['category'] : 'CORE')); ?></span>
                            <img src="<?php echo htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : 'https://via.placeholder.com/400x400?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>

                        <div class="product-meta">
                            <h3>
                                <a href="product.php?id=<?php echo $product['product_id']; ?>" style="color:inherit;text-decoration:none;">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <p class="product-desc"><?php echo htmlspecialchars(!empty($product['description']) ? $product['description'] : 'Precision performance edition.'); ?></p>
                            <div class="product-footer">
                                <span class="product-price">R <?php echo number_format($product['price'], 2); ?></span>
                                <a href="cart.php?add=<?php echo $product['product_id']; ?>" class="add-bag-btn">+ ADD TO BAG</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-box">
                    <h3>NO MODELS FOUND IN CATALOG</h3>
                    <p>Use the Merchant Admin Control Panel to publish inventory to the storefront.</p>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin/index.php" class="btn-primary">OPEN ADMIN PANEL</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- LIMITED EDITION BANNER -->
    <?php if ($category === '' && $search === ''): ?>
    <section class="section-wrapper editorial-split reverse reveal">
        <div class="editorial-media dark-frame">
            <img src="https://images.unsplash.com/photo-1508296695146-257a814070b4?auto=format&fit=crop&w=900&q=80" alt="Limited Edition Hardware">
        </div>
        <div class="editorial-copy">
            <span class="eyebrow-tag">SMALL BATCH RELEASE</span>
            <h2>EDITION OF 200.<br>NUMBERED.<br>BUILT TO LAST.</h2>
            <p>Each seasonal run is individually serialized and inspected prior to dispatch. Once an allocation closes, the tooling is retired.</p>
            <a href="#core-models" class="btn-outline-light">SHOP ALLOCATION</a>
        </div>
    </section>

    <!-- ATHLETE STORIES -->
    <section class="section-wrapper reveal">
        <div class="section-header-row">
            <h2>ATHLETE STORIES.</h2>
            <span class="result-counter">FIELD REPORTS // SA</span>
        </div>

        <div class="stories-grid">
            <div class="story-card reveal">
                <img src="https://images.unsplash.com/photo-1530143311094-34d807799e8f?auto=format&fit=crop&w=800&q=80" alt="Trail Runner">
                <div class="story-body">
                    <span class="eyebrow-tag">ULTRA TRAIL</span>
                    <h4>THABO KHUMALO</h4>
                    <p>"Zero bounce and total clarity across 80km of Table Mountain ridge traverses."</p>
                </div>
            </div>

            <div class="story-card reveal">
                <img src="https://images.unsplash.com/photo-1541625602330-2277a4c46182?auto=format&fit=crop&w=800&q=80" alt="Cyclist">
                <div class="story-body">
                    <span class="eyebrow-tag">ROAD ENDURANCE</span>
                    <h4>ANNELIZE VAN WYK</h4>
                    <p>"Built for coastal headwinds and early-morning climbs without compromise."</p>
                </div>
            </div>

            <div class="story-card reveal">
                <img src="https://images.unsplash.com/photo-1502680390469-be75c86b636f?auto=format&fit=crop&w=800&q=80" alt="Ocean Athlete">
                <div class="story-body">
                    <span class="eyebrow-tag">OPEN WATER</span>
                    <h4>CALLUM REID</h4>
                    <p>"Salt-resistant hardware that performs identically from day one to season end."</p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- NEWSLETTER & FOOTER -->
    <footer class="site-footer reveal">
        <div class="newsletter-band">
            <div>
                <span class="eyebrow-tag">PRIVATE ACCESS</span>
                <h2>BE FIRST TO THE NEXT DROP.</h2>
            </div>
            <form class="newsletter-form" onsubmit="event.preventDefault(); alert('You are on the priority dispatch list.');">
                <input type="email" placeholder="ENTER YOUR EMAIL" required>
                <button type="submit">SUBSCRIBE</button>
            </form>
        </div>

        <div class="footer-grid">
            <div>
                <h4>NAVIGATION</h4>
                <a href="index.php">All Models</a>
                <a href="index.php?category=Electronics">Electronics</a>
                <a href="index.php?category=Fashion">Apparel</a>
                <a href="index.php?category=Sports %26 Outdoors">Endurance</a>
            </div>
            <div>
                <h4>ACCOUNT</h4>
                <a href="login.php">Member Login</a>
                <a href="register.php">Create Account</a>
                <a href="orders.php">My Orders</a>
                <a href="cart.php">Shopping Bag</a>
            </div>
            <div>
                <h4>SUPPORT</h4>
                <a href="#core-models">Shipping & Returns</a>
                <a href="#core-models">Warranty Registration</a>
                <a href="#core-models">Contact Dispatch</a>
            </div>
            <div>
                <h4>HEADQUARTERS</h4>
                <p>Cape Town, South Africa</p>
                <p>Built with PHP & MySQL</p>
            </div>
        </div>

        <div class="footer-watermark">MAKO</div>
    </footer>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll(".reveal").forEach((el) => observer.observe(el));
    });
    </script>

</body>
</html>