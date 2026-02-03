<?php
session_start();

// DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";

try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES utf8");
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Helper function for product images
function getProductImageSrc(array $product): string {
    $images = [];
    if (!empty($product['image_url'])) {
        $images = explode(',', $product['image_url']);
    }
    $firstImage = trim($images[0] ?? '');
    if ($firstImage === '') {
        return 'images/product-thumb-11.jpg';
    }
    
    // Absolute URL already
    if (preg_match('~^https?://~i', $firstImage)) {
        return $firstImage;
    }
    
    // Simple path resolution - try the most common patterns
    $normalized = ltrim($firstImage, '/');
    return '../' . $normalized;
}

// Get user's favorites
$favorites = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("
            SELECT p.* FROM product p 
            JOIN user_favorites uf ON p.id = uf.product_id 
            WHERE uf.user_id = ? 
            ORDER BY uf.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $favorites = [];
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oblíbené - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">
    <style>
        :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
        
        body {
            background: #f8f9fa;
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        
        /* Apple SF Pro Font */
        body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        
        /* Fix for header icons - make them properly round */
        .rounded-circle {
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 48px !important;
            height: 48px !important;
        }
        
        /* Ensure SVG icons are visible */
        svg {
            width: 24px !important;
            height: 24px !important;
            fill: currentColor !important;
        }
        
        .favorites-header {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
        
        .favorites-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
            margin-bottom: 0.5rem;
        }
        
        .product-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 2px solid rgba(202,186,156,0.2);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(16,40,32,0.12);
            border-color: var(--kjd-earth-green);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid var(--kjd-beige);
            transition: all 0.3s ease;
        }
        
        .product-image:hover {
            border-color: var(--kjd-earth-green);
            transform: scale(1.02);
        }
        
        .product-name {
            color: var(--kjd-dark-green);
            font-weight: 700;
            font-size: 1.2rem;
            margin: 1rem 0 0.5rem;
        }
        
        .product-price {
            color: var(--kjd-gold-brown);
            font-weight: 800;
            font-size: 1.3rem;
            margin: 0.5rem 0;
        }
        
        .btn-kjd-primary {
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(77,45,24,0.3);
        }
        
        .btn-kjd-primary:hover {
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.4);
        }
        
        .btn-remove-favorite {
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .btn-remove-favorite:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .empty-favorites {
            text-align: center;
            padding: 6rem 2rem;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(16,40,32,0.1);
        }
        
        .empty-favorites h3 {
            color: var(--kjd-dark-green);
            margin-bottom: 1.5rem;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .empty-favorites p {
            color: #666;
            margin-bottom: 2.5rem;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>

    <?php include 'includes/icons.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="favorites-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1><i class="fas fa-heart me-3"></i>Moje oblíbené</h1>
                    <p class="fs-5">Produkty, které máte rádi</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($favorites)): ?>
            <div class="empty-favorites">
                <h3><i class="fas fa-heart-broken me-3"></i>Žádné oblíbené produkty</h3>
                <p>Zatím nemáte žádné oblíbené produkty. Prohlédněte si naše produkty a přidejte si je do oblíbených!</p>
                <a href="index.php" class="btn btn-kjd-primary">
                    <i class="fas fa-shopping-bag me-2"></i>Prohlédnout produkty
                </a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                <?php foreach ($favorites as $product): ?>
                    <?php
                    $img = htmlspecialchars(getProductImageSrc($product));
                    $name = htmlspecialchars($product['name'] ?? 'Product');
                    $price = isset($product['price']) ? (float)$product['price'] : 0;
                    $isPreorder = !empty($product['is_preorder']) && (int)$product['is_preorder'] === 1;
                    $saleActive = false; $salePrice = 0;
                    if (!empty($product['sale_enabled']) && (int)$product['sale_enabled'] === 1) {
                        $sp = isset($product['sale_price']) ? (float)$product['sale_price'] : 0;
                        if ($sp > 0 && $sp < $price) {
                            date_default_timezone_set('Europe/Prague');
                            $now = time();
                            $startTs = !empty($product['sale_start']) ? strtotime($product['sale_start']) : 0;
                            $endTs = !empty($product['sale_end']) ? strtotime($product['sale_end']) : 0;
                            
                            $isUpcoming = ($startTs > 0 && $now < $startTs);
                            
                            if (!$isUpcoming) {
                                if ($endTs > 0) {
                                    if ($now < $endTs) {
                                        $saleActive = true; $salePrice = $sp;
                                    }
                                } else {
                                    $saleActive = true; $salePrice = $sp;
                                }
                            }
                        }
                    }
                    ?>
                    <div class="col">
                        <div class="product-card">
                            <div class="position-relative">
                                <?php if ($saleActive): ?>
                                    <span class="badge bg-success position-absolute top-0 start-0 m-2">-<?= max(1, (int)round((1 - ($salePrice / max(0.01, $price))) * 100)) ?>%</span>
                                <?php elseif ($isPreorder): ?>
                                    <span class="badge bg-warning position-absolute top-0 start-0 m-2">Preorder</span>
                                <?php endif; ?>
                                
                                <button class="btn btn-remove-favorite position-absolute top-0 end-0 m-2" 
                                        onclick="removeFromFavorites(<?= (int)$product['id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <a href="product.php?id=<?= (int)$product['id'] ?>" title="<?= $name ?>">
                                <img src="<?= $img ?>" class="product-image" alt="<?= $name ?>">
                            </a>
                            
                            <h3 class="product-name"><?= $name ?></h3>
                            
                            <div class="product-price">
                                <?php if ($saleActive): ?>
                                    <span style="text-decoration:line-through;color:#888;margin-right:6px;"><?= number_format($price, 0, ',', ' ') ?> Kč</span>
                                    <span style="color:#c62828;font-weight:700;"><?= number_format($salePrice, 0, ',', ' ') ?> Kč</span>
                                <?php else: ?>
                                    <?= number_format($price, 0, ',', ' ') ?> Kč
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-center mt-3">
                                <a href="product.php?id=<?= (int)$product['id'] ?>" class="btn btn-kjd-primary">Zobrazit produkt</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php include 'includes/footer.php'; ?>
    <script>
        function removeFromFavorites(productId) {
            if (confirm('Opravdu chcete odebrat tento produkt z oblíbených?')) {
                fetch('ajax/toggle_favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Chyba při odebírání z oblíbených: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Chyba při odebírání z oblíbených');
                });
            }
        }
    </script>
</body>
</html>
