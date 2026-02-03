<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";
$dbUser = $username;
$dbPassword = $password;

try {
    $conn = new PDO($dsn, $dbUser, $dbPassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES utf8");
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

$order = null;
$searchCode = '';
$searchError = '';

// Zpracování vyhledávání
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['code'])) {
    $searchCode = $_POST['tracking_code'] ?? $_GET['code'] ?? '';
    
    if (!empty($searchCode)) {
        try {
            $stmt = $conn->prepare("
                SELECT * FROM orders 
                WHERE tracking_code = ? OR order_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$searchCode, $searchCode]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                $searchError = "Objednávka s tímto kódem nebyla nalezena.";
            } else {
                // Fetch status history
                try {
                    $stmtHistory = $conn->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC");
                    $stmtHistory->execute([$order['id']]);
                    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $history = [];
                }
            }
        } catch (PDOException $e) {
            $searchError = "Chyba při vyhledávání objednávky.";
        }
    } else {
        $searchError = "Zadejte prosím sledovací kód nebo číslo objednávky.";
    }
}

// Funkce pro získání statusu
function getStatusInfo($status) {
    $statuses = [
        'pending' => ['icon' => 'fas fa-check-circle', 'color' => 'success', 'text' => 'Objednávka byla přijata'],
        'processing' => ['icon' => 'fas fa-cogs', 'color' => 'warning', 'text' => 'Objednávka se zpracovává'],
        'preparing' => ['icon' => 'fas fa-box-open', 'color' => 'info', 'text' => 'Příprava k odeslání'],
        'shipped' => ['icon' => 'fas fa-shipping-fast', 'color' => 'primary', 'text' => 'Objednávka byla odeslána'],
        'delivered' => ['icon' => 'fas fa-check-double', 'color' => 'success', 'text' => 'Objednávka byla doručena'],
        'cancelled' => ['icon' => 'fas fa-times-circle', 'color' => 'danger', 'text' => 'Objednávka byla zrušena']
    ];
    
    return $statuses[$status] ?? ['icon' => 'fas fa-question-circle', 'color' => 'secondary', 'text' => $status];
}

// Funkce pro dekódování produktů
function getOrderProducts($productsJson) {
    if (empty($productsJson)) return [];
    
    $products = json_decode($productsJson, true);
    if (!$products) return [];
    
    $result = [];
    foreach ($products as $cartKey => $productData) {
        $result[] = [
            'name' => $productData['name'] ?? 'Neznámý produkt',
            'quantity' => $productData['quantity'] ?? 1,
            'price' => $productData['final_price'] ?? $productData['price'] ?? 0,
            'subtotal' => ($productData['final_price'] ?? $productData['price'] ?? 0) * ($productData['quantity'] ?? 1)
        ];
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Sledování objednávky - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">
    
    <style>
        :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
        
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
        
        /* Page specific styles matching cart.php */
        .tracking-page { background: #f8f9fa; min-height: 100vh; }
        
        .tracking-header { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            padding: 3rem 0; 
            margin-bottom: 2rem; 
            border-bottom: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
        .tracking-header h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
            margin-bottom: 0.5rem;
            color: var(--kjd-dark-green);
        }
        .tracking-header p { 
            font-size: 1.1rem; 
            font-weight: 500;
            opacity: 0.8;
            color: var(--kjd-dark-brown);
        }
        
        .content-card { 
            background: #fff; 
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 1px solid rgba(202,186,156,0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16,40,32,0.12);
        }
        
        .content-card h3 {
            color: var(--kjd-dark-green); 
            font-weight: 700; 
            font-size: 1.3rem;
            margin-bottom: 1.5rem; 
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(202,186,156,0.3);
        }
        
        .order-detail {
            margin-bottom: 15px;
        }
        
        .order-detail-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .order-detail-value {
            font-weight: 700;
            color: var(--kjd-dark-green);
            font-size: 1.05rem;
        }
        
        .btn-kjd-primary { 
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
            color: #fff; 
            border: none; 
            padding: 0.8rem 2rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(77,45,24,0.3);
        }
        .btn-kjd-primary:hover { 
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.4);
        }
        
        .form-control {
            border: 2px solid var(--kjd-earth-green); 
            border-radius: 8px; 
            padding: 0.75rem; 
            font-weight: 600;
        }
        .form-control:focus {
            border-color: var(--kjd-gold-brown);
            box-shadow: 0 0 0 0.2rem rgba(138,98,64,0.25);
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -27px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #e9ecef;
            z-index: 1;
        }
        
        .timeline-item.completed::after {
            background: var(--kjd-earth-green);
            border-color: var(--kjd-earth-green);
        }
        
        .timeline-item.current::after {
            background: #fff;
            border-color: var(--kjd-gold-brown);
        }
        
        .timeline-item.pending::after {
            background: #fff;
            border-color: #e9ecef;
        }
        
        .timeline-content {
            padding-left: 10px;
        }
        
        .timeline-title {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--kjd-dark-green);
        }
        
        .timeline-item.completed .timeline-title {
            color: var(--kjd-earth-green);
        }
        
        .timeline-item.current .timeline-title {
            color: var(--kjd-gold-brown);
        }
        
        .timeline-item.pending .timeline-title {
            color: #999;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #888;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .status-success { background-color: #d4edda; color: #155724; }
        .status-warning { background-color: #fff3cd; color: #856404; }
        .status-info { background-color: #d1ecf1; color: #0c5460; }
        .status-danger { background-color: #f8d7da; color: #721c24; }
        .status-secondary { background-color: #e2e3e5; color: #383d41; }
        .status-primary { background-color: #cce5ff; color: #004085; }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            font-weight: 700;
            color: var(--kjd-dark-green);
            margin-bottom: 5px;
        }
        
        .product-price {
            font-weight: 700;
            color: var(--kjd-gold-brown);
        }
    </style>
</head>
<body class="tracking-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
        <div class="preloader"></div>
    </div>

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <div class="tracking-header">
        <div class="container">
            <h1>Sledování objednávky</h1>
            <p>Zadejte sledovací kód nebo číslo objednávky pro zobrazení detailů</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="content-card">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <input type="text" 
                                       name="tracking_code" 
                                       class="form-control" 
                                       placeholder="Zadejte sledovací kód (např. TRK-ABC12345) nebo číslo objednávky"
                                       value="<?= htmlspecialchars($searchCode) ?>"
                                       required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-kjd-primary w-100">
                                    <i class="fas fa-search me-2"></i>Vyhledat
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if ($searchError): ?>
                        <div class="alert alert-danger mt-3 mb-0" role="alert" style="border-radius: 12px;">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($searchError) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($order): ?>
        <!-- Order Details -->
        <div class="row">
            <!-- Order Info -->
            <div class="col-lg-4 mb-4">
                <div class="content-card">
                    <h3><i class="fas fa-receipt me-2"></i>Detaily objednávky</h3>
                    
                    <?php 
                    $statusInfo = getStatusInfo($order['status']);
                    ?>
                    <div class="status-badge status-<?= $statusInfo['color'] ?>">
                        <i class="<?= $statusInfo['icon'] ?> me-2"></i>
                        <?= htmlspecialchars($statusInfo['text']) ?>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Číslo objednávky</div>
                        <div class="order-detail-value"><?= htmlspecialchars($order['order_id']) ?></div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Sledovací kód</div>
                        <div class="order-detail-value" style="font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: 1px;">
                            <?= htmlspecialchars($order['tracking_code']) ?>
                            <?php if (!empty($order['packeta_tracking_url'])): ?>
                                <a href="<?= htmlspecialchars($order['packeta_tracking_url']) ?>" target="_blank" class="btn btn-sm btn-outline-success ms-2" style="font-family: 'Montserrat', sans-serif; letter-spacing: 0;">
                                    <i class="fas fa-truck me-1"></i>Sledovat zásilku
                                </a>
                            <?php elseif (!empty($order['packeta_barcode'])): ?>
                                <a href="https://tracking.packeta.com/cs/?id=<?= htmlspecialchars($order['packeta_barcode']) ?>" target="_blank" class="btn btn-sm btn-outline-success ms-2" style="font-family: 'Montserrat', sans-serif; letter-spacing: 0;">
                                    <i class="fas fa-truck me-1"></i>Sledovat zásilku
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Datum objednávky</div>
                        <div class="order-detail-value"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                    </div>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="col-lg-4 mb-4">
                <div class="content-card">
                    <h3><i class="fas fa-user me-2"></i>Zákazník</h3>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Jméno</div>
                        <div class="order-detail-value"><?= htmlspecialchars($order['name']) ?></div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Email</div>
                        <div class="order-detail-value"><?= htmlspecialchars($order['email']) ?></div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Telefon</div>
                        <div class="order-detail-value"><?= htmlspecialchars($order['phone_number'] ?? 'Není uveden') ?></div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Způsob dopravy</div>
                        <div class="order-detail-value"><?= htmlspecialchars($order['delivery_method']) ?></div>
                    </div>
                    
                    <?php if (!empty($order['address'])): ?>
                    <div class="order-detail">
                        <div class="order-detail-label">Adresa doručení</div>
                        <div class="order-detail-value"><?= htmlspecialchars($order['address']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Info -->
            <div class="col-lg-4 mb-4">
                <div class="content-card">
                    <h3><i class="fas fa-credit-card me-2"></i>Platba</h3>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Způsob platby</div>
                        <div class="order-detail-value">
                            <?= 
                                $order['payment_method'] === 'bank_transfer' ? 'Bankovní převod' : 
                                ($order['payment_method'] === 'gopay' ? 'GoPay - Platební brána' : 
                                 ($order['payment_method'] === 'revolut' ? 'Revolut' : htmlspecialchars($order['payment_method'])))
                            ?>
                        </div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Status platby</div>
                        <div class="order-detail-value">
                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'danger' ?>" style="font-size: 0.9rem; padding: 0.5rem 1rem; font-weight: 700;">
                                <?= $order['payment_status'] === 'paid' ? 'Zaplaceno' : 'Čeká na platbu' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="order-detail">
                        <div class="order-detail-label">Celkem k úhradě</div>
                        <div class="order-detail-value" style="font-size: 1.2rem; font-weight: 800; color: var(--kjd-earth-green);">
                            <?= number_format($order['total_price'], 0, ',', ' ') ?> Kč
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products and Timeline -->
        <div class="row">
            <!-- Products -->
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <h3><i class="fas fa-shopping-bag me-2"></i>Produkty v objednávce</h3>
                    
                    <?php 
                    $products = getOrderProducts($order['products_json']);
                    $total = 0;
                    foreach ($products as $product): 
                        $total += $product['subtotal'];
                    ?>
                    <div class="product-item">
                        <div>
                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-quantity">Množství: <?= $product['quantity'] ?></div>
                        </div>
                        <div class="product-price"><?= number_format($product['subtotal'], 0, ',', ' ') ?> Kč</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Timeline -->
            <div class="col-lg-6 mb-4">
                <div class="content-card">
                    <h3><i class="fas fa-route me-2"></i>Stav objednávky</h3>
                    
                    <div class="timeline">
                        <?php if (!empty($history)): ?>
                            <?php foreach ($history as $index => $event): 
                                $eventInfo = getStatusInfo($event['status']);
                                $isLatest = ($index === 0);
                            ?>
                            <div class="timeline-item <?= $isLatest ? 'current' : 'completed' ?>">
                                <div class="timeline-content">
                                    <div class="timeline-title" style="<?= $isLatest ? 'color: var(--kjd-gold-brown);' : '' ?>">
                                        <?= htmlspecialchars($eventInfo['text']) ?>
                                    </div>
                                    <div class="timeline-date">
                                        <?= date('d.m.Y H:i', strtotime($event['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Show "Order Created" as the last item if not in history -->
                            <?php 
                            $hasCreated = false;
                            foreach ($history as $h) { if ($h['status'] === 'pending' || $h['status'] === 'Přijato') $hasCreated = true; }
                            if (!$hasCreated):
                            ?>
                            <div class="timeline-item completed">
                                <div class="timeline-content">
                                    <div class="timeline-title">Objednávka vytvořena</div>
                                    <div class="timeline-date"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <!-- Fallback for orders without history -->
                            <div class="timeline-item completed">
                                <div class="timeline-content">
                                    <div class="timeline-title">Objednávka přijata</div>
                                    <div class="timeline-date"><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                                </div>
                            </div>
                            
                            <?php 
                            $isProcessing = $order['status'] === 'processing';
                            $isPreparing = $order['status'] === 'preparing';
                            $isShipped = $order['status'] === 'shipped';
                            $isDelivered = $order['status'] === 'delivered';
                            $isCancelled = $order['status'] === 'cancelled';
                            
                            $processingClass = ($isProcessing) ? 'current' : (($isPreparing || $isShipped || $isDelivered) ? 'completed' : 'pending');
                            $preparingClass = ($isPreparing) ? 'current' : (($isShipped || $isDelivered) ? 'completed' : 'pending');
                            $shippedClass = ($isShipped) ? 'current' : (($isDelivered) ? 'completed' : 'pending');
                            $deliveredClass = ($isDelivered) ? 'completed' : 'pending';
                            
                            if ($isCancelled) {
                                $processingClass = 'pending';
                                $preparingClass = 'pending';
                                $shippedClass = 'pending';
                                $deliveredClass = 'pending';
                            }
                            ?>
                            
                            <div class="timeline-item <?= $processingClass ?>">
                                <div class="timeline-content">
                                    <div class="timeline-title">Zpracovává se</div>
                                    <div class="timeline-date">Objednávka se připravuje</div>
                                </div>
                            </div>
                            
                            <div class="timeline-item <?= $preparingClass ?>">
                                <div class="timeline-content">
                                    <div class="timeline-title">Příprava k odeslání</div>
                                    <div class="timeline-date">Balíme vaši objednávku</div>
                                </div>
                            </div>
                            
                            <div class="timeline-item <?= $shippedClass ?>">
                                <div class="timeline-content">
                                    <div class="timeline-title">Objednávka odeslána</div>
                                    <div class="timeline-date">Na cestě k vám</div>
                                </div>
                            </div>
                            
                            <div class="timeline-item <?= $deliveredClass ?>">
                                <div class="timeline-content">
                                    <div class="timeline-title">Objednávka doručena</div>
                                    <div class="timeline-date">Úspěšně doručeno</div>
                                </div>
                            </div>
                            
                            <?php if ($isCancelled): ?>
                            <div class="timeline-item current" style="color: #dc3545;">
                                <div class="timeline-content">
                                    <div class="timeline-title" style="color: #dc3545;">Objednávka zrušena</div>
                                    <div class="timeline-date">Objednávka byla stornována</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="py-5 mt-5" style="background: var(--kjd-dark-green); color: #fff;">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-menu">
                        <span style="font-size: 1.5rem; font-weight: 700; color: #fff;">KJ</span><span style="font-size: 1.5rem; font-weight: 700; color: var(--kjd-beige);">D</span>
                        <div class="social-links mt-5">
                            <ul class="d-flex list-unstyled gap-2">
                                <li>
                                    <a href="#" class="btn btn-outline-light">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M15.12 5.32H17V2.14A26.11 26.11 0 0 0 14.26 2c-2.72 0-4.58 1.66-4.58 4.7v2.62H6.61v3.56h3.07V22h3.68v-9.12h3.06l.46-3.56h-3.52V7.05c0-1.05.28-1.73 1.76-1.73Z"/></svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="btn btn-outline-light">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M22.991 3.95a1 1 0 0 0-1.51-.86a7.48 7.48 0 0 1-1.874.794a5.152 5.152 0 0 0-3.374-1.242a5.232 5.232 0 0 0-5.223 5.063a11.032 11.032 0 0 1-6.814-3.924a1.012 1.012 0 0 0-.857-.365a.999.999 0 0 0-.785.5a5.276 5.276 0 0 0-.242 4.769l-.002.001a1.041 1.041 0 0 0-.496.89a3.042 3.042 0 0 0 .027.439a5.185 5.185 0 0 0 1.568 3.312a.998.998 0 0 0-.066.77a5.204 5.204 0 0 0 2.362 2.922a7.465 7.465 0 0 1-3.59.448A1 1 0 0 0 1.45 19.3a12.942 12.942 0 0 0 7.01 2.061a12.788 12.788 0 0 0 12.465-9.363a12.822 12.822 0 0 0 .535-3.646l-.001-.2a5.77 5.77 0 0 0 1.532-4.202Zm-3.306 3.212a.995.995 0 0 0-.234.702c.01.165.009.331.009.488a10.824 10.824 0 0 1-.454 3.08a10.685 10.685 0 0 1-10.546 7.93a10.938 10.938 0 0 1-2.55-.301a9.48 9.48 0 0 0 2.942-1.564a1 1 0 0 0-.602-1.786a3.208 3.208 0 0 1-2.214-.935q.224-.042.445-.105a1 1 0 0 0-.08-1.943a3.198 3.198 0 0 1-2.25-1.726a5.3 5.3 0 0 0 .545.046a1 1 0 0 0 .984-.696a1 1 0 0 0-.4-1.137a3.196 3.196 0 0 1-1.425-2.673c0-.066.002-.133.006-.198a13.014 13.014 0 0 0 8.21 3.48a1.02 1.02 0 0 0 .817-.36a1 1 0 0 0 .206-.867a3.157 3.157 0 0 1-.087-.729a3.23 3.23 0 0 1 3.226-3.226a3.184 3.184 0 0 1 2.345 1.02a.993.993 0 0 0 .921.298a9.27 9.27 0 0 0 1.212-.322a6.681 6.681 0 0 1-1.026 1.524Z"/></svg>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <div id="footer-bottom" style="background: var(--kjd-dark-brown); color: #fff;">
        <div class="container">
            <div class="row">
                <div class="col-md-6 copyright">
                    <p>© 2023 KJD. All rights reserved.</p>
                </div>
                <div class="col-md-6 credit-link text-start text-md-end">
                    <p>Kubajadesigns.eu</p>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        // Smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animace pro karty
            const cards = document.querySelectorAll('.content-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>
