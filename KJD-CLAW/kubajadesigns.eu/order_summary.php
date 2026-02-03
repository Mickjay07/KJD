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

// Kontrola, zda je košík prázdný
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

// Helper function pro obrázky (stejná jako v cart.php)
// Helper function to follow URL redirects (for shorturl.at, bit.ly, etc.)
function followRedirect($url, $maxRedirects = 5) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, $maxRedirects);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    return $finalUrl ?: $url;
}

function getProductImageSrc(array $product): string {
    $images = [];
    if (!empty($product['image_url'])) {
        $images = explode(',', $product['image_url']);
    }
    
    $first = '';
    if (!empty($images) && isset($images[0])) {
        $first = trim($images[0]);
    }
    
    if ($first === '') {
        return 'images/product-thumb-11.jpg';
    }
    
    // External URLs
    if (preg_match('~^https?://~i', $first)) {
        // Handle URL shorteners (shorturl.at, bit.ly, etc.) - follow redirect to get actual URL
        if (preg_match('/(shorturl\.at|bit\.ly|tinyurl\.com|goo\.gl)/', $first)) {
            $actualUrl = followRedirect($first);
            if ($actualUrl && $actualUrl !== $first) {
                $first = $actualUrl;
            }
        }
        
        // Convert Google Drive URL if needed - use direct image URL
        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $first, $matches)) {
            return 'https://lh3.googleusercontent.com/d/' . $matches[1];
        }
        // Also handle /uc format and convert it
        if (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $first, $matches)) {
            return 'https://lh3.googleusercontent.com/d/' . $matches[1];
        }
        return $first;
    }
    
    // Normalize path - remove leading slashes and dots
    $normalized = ltrim($first, './');
    $normalized = ltrim($normalized, '/');
    
    // If path starts with 'admin/', it's already correct from root
    if (strpos($normalized, 'admin/') === 0) {
        return $normalized;
    }
    
    // If path starts with 'uploads/', it could be in admin/ or root
    // Check if file exists in admin/uploads/ first (newer structure)
    // If not, use direct uploads/ path (older structure)
    if (strpos($normalized, 'uploads/') === 0) {
        $adminPath = 'admin/' . $normalized;
        $directPath = $normalized;
        
        // Check if file exists in admin/uploads/ directory
        if (file_exists(__DIR__ . '/' . $adminPath)) {
            return $adminPath;
        }
        // If not in admin/, try direct path (for older uploads)
        if (file_exists(__DIR__ . '/' . $directPath)) {
            return $directPath;
        }
        // Default to admin path (for new uploads)
        return $adminPath;
    }
    
    // Fallback: try relative path
    return 'uploads/products/' . $normalized;
}

// Zpracování formuláře z checkout.php
$customerData = [];
$deliveryData = [];
$paymentData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerData = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone_number'] ?? '',
        'note' => $_POST['note'] ?? ''
    ];
    
    $deliveryData = [
        'method' => $_POST['delivery_method'] ?? 'Zásilkovna',
        'packeta_branch_id' => $_POST['packeta_branch_id'] ?? '',
        'zasilkovna_name' => $_POST['zasilkovna_name'] ?? '',
        'zasilkovna_street' => $_POST['zasilkovna_street'] ?? '',
        'zasilkovna_city' => $_POST['zasilkovna_city'] ?? '',
        'zasilkovna_zip' => $_POST['zasilkovna_zip'] ?? '',
        'alzabox_code' => $_POST['alzabox_code'] ?? ''
    ];
    
    // DEBUG: Log received data
    file_put_contents('debug_order_post.log', date('Y-m-d H:i:s') . " POST DATA: " . print_r($_POST, true) . "\n", FILE_APPEND);
    
    $paymentData = [
        'method' => $_POST['payment_method'] ?? 'bank_transfer'
    ];
    
    // Uložení do session pro další zpracování
    $_SESSION['order_data'] = [
        'customer' => $customerData,
        'delivery' => $deliveryData,
        'payment' => $paymentData
    ];
} elseif (isset($_SESSION['order_data'])) {
    // Pokud máme data v session (např. při návratu z chyby), načteme je
    $customerData = $_SESSION['order_data']['customer'];
    $deliveryData = $_SESSION['order_data']['delivery'];
    $paymentData = $_SESSION['order_data']['payment'];
} else {
    // Pokud není POST ani Session, přesměruj na cart
    header('Location: cart.php');
    exit;
}

// Výpočet cen
$cartItems = [];
$total = 0;
$discount = 0;
$discountCode = '';

foreach ($_SESSION['cart'] as $cartKey => $productData) {
    $quantity = (int)($productData['quantity'] ?? 0);
    $price = (float)($productData['final_price'] ?? $productData['price'] ?? 0);
    
    $cartItems[] = [
        'product' => $productData,
        'quantity' => $quantity,
        'price' => $price,
        'subtotal' => $price * $quantity,
        'cart_key' => $cartKey
    ];
    
    $total += $price * $quantity;
}

// Aplikace slevy
if (isset($_SESSION['applied_discount'])) {
    $discountPercent = (int)$_SESSION['applied_discount']['discount_percent'];
    $discount = ($total * $discountPercent) / 100;
    $discountCode = $_SESSION['applied_discount']['code'];
}

// Výpočet dopravy
$shippingCost = 0;
$shippingText = "Zdarma";
$shippingColor = "var(--kjd-earth-green)";

// Check if free shipping discount code is applied
$hasFreeShippingCode = false;
if (isset($_SESSION['applied_discount'])) {
    $appliedCode = strtoupper(trim($_SESSION['applied_discount']['code']));
    // Codes that grant free shipping
    $freeShippingCodes = ['DOPRAVAZDARMA', 'FREESHIP', 'SHIPFREE'];
    if (in_array($appliedCode, $freeShippingCodes)) {
        $hasFreeShippingCode = true;
    }
}

// Shipping cost logic
if ($hasFreeShippingCode) {
    // Free shipping code applied - override all other logic
    $shippingCost = 0;
    $shippingText = "Zdarma (slevový kód)";
    $shippingColor = "var(--kjd-earth-green)";
} elseif (isset($_SESSION['order_data']['delivery']['method'])) {
    $deliveryMethod = $_SESSION['order_data']['delivery']['method'];
    
    // Jiná doprava is always FREE
    if ($deliveryMethod === 'Jiná doprava') {
        $shippingCost = 0;
        $shippingText = "Zdarma";
        $shippingColor = "var(--kjd-earth-green)";
    } 
    // Zásilkovna and AlzaBox: 100 Kč under 1000 Kč, free over 1000 Kč
    else if ($deliveryMethod === 'Zásilkovna' || $deliveryMethod === 'AlzaBox') {
        if ($total < 1000) {
            $shippingCost = 100;
            $shippingText = number_format($shippingCost, 0, ',', ' ') . " Kč";
            $shippingColor = "#666";
        } else {
            $shippingCost = 0;
            $shippingText = "Zdarma";
            $shippingColor = "var(--kjd-earth-green)";
        }
    }
}

$finalTotal = $total - $discount + $shippingCost;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Přehled objednávky - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">

    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        /* Cart page base styles (to mirror cart.php) */
        .cart-page { background: #f8f9fa; min-height: 100vh; }
        .cart-header { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            padding: 3rem 0; 
            margin-bottom: 2rem; 
            border-bottom: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
        .cart-header h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
            margin-bottom: 0.5rem;
            color: var(--kjd-dark-green);
        }
        .cart-header p { 
            font-size: 1.1rem; 
            font-weight: 500;
            opacity: 0.8;
            color: var(--kjd-gold-brown);
        }

        /* Shared button styles from cart.php */
        .btn-kjd-primary { 
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
            color: #fff; 
            border: none; 
            padding: 1rem 2.5rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(77,45,24,0.3);
        }
        .btn-kjd-primary:hover { 
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.4);
        }
        .btn-kjd-secondary { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            color: var(--kjd-dark-green); 
            border: 2px solid var(--kjd-earth-green); 
            padding: 1rem 2.5rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-kjd-secondary:hover { 
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76,100,68,0.3);
        }
        
        .order-summary-page { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            min-height: 100vh; 
            padding: 2rem 0;
        }
        
        .summary-header { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            padding: 3rem 0; 
            margin-bottom: 2rem; 
            border-bottom: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
        
        .summary-header h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
            margin-bottom: 0.5rem;
            color: var(--kjd-dark-green);
        }
        
        .summary-header p { 
            font-size: 1.1rem; 
            font-weight: 500;
            opacity: 0.8;
            color: var(--kjd-gold-brown);
        }
        
        .summary-card { 
            background: #fff; 
            border-radius: 20px; 
            padding: 2.5rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 8px 32px rgba(16,40,32,0.1);
            border: 2px solid rgba(202,186,156,0.2);
        }
        
        .summary-card h3 { 
            color: var(--kjd-dark-green); 
            font-weight: 700; 
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid var(--kjd-earth-green);
            padding-bottom: 0.75rem;
        }
        
        .order-item { 
            background: rgba(202,186,156,0.1); 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 1rem; 
            border: 1px solid rgba(202,186,156,0.3);
        }
        
        .order-item-image { 
            width: 80px; 
            height: 80px; 
            object-fit: cover; 
            border-radius: 8px;
            border: 2px solid var(--kjd-beige);
        }
        
        .order-item-name { 
            color: var(--kjd-dark-green); 
            font-weight: 600; 
            font-size: 1.1rem;
            margin-bottom: 0.25rem; 
        }
        
        .order-item-price { 
            color: var(--kjd-gold-brown); 
            font-weight: 700; 
            font-size: 1.1rem;
        }
        
        .price-breakdown { 
            background: var(--kjd-beige); 
            border-radius: 12px; 
            padding: 1.5rem; 
            border: 2px solid var(--kjd-earth-green);
        }
        
        .price-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(202,186,156,0.1);
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            color: var(--kjd-dark-green);
        }
        
        .price-total { 
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
            color: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            font-weight: 800;
            font-size: 1.4rem;
            box-shadow: 0 4px 15px rgba(76,100,68,0.3);
            border: none;
        }
        
        .price-total span:first-child {
            color: rgba(255,255,255,0.9);
        }
        
        .price-total span:last-child {
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .btn-confirm { 
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
            color: #fff; 
            border: none; 
            padding: 1.2rem 3rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(77,45,24,0.3);
        }
        
        .btn-confirm:hover { 
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(77,45,24,0.4);
        }
        
        .btn-checkout-secondary { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            color: var(--kjd-dark-green); 
            border: 2px solid var(--kjd-earth-green); 
            padding: 1rem 2.5rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-checkout-secondary:hover { 
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76,100,68,0.3);
        }
        
        .info-item { 
            background: rgba(202,186,156,0.1); 
            border-radius: 8px; 
            padding: 1rem; 
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--kjd-earth-green);
        }
        
        .info-label { 
            font-weight: 700; 
            color: var(--kjd-dark-green);
            margin-bottom: 0.25rem;
        }
        
        .info-value { 
            color: var(--kjd-gold-brown); 
            font-weight: 600;
        }

        /* Order summary specific compact list rows */
        .info-list {
            padding: 0.5rem 0;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 0.25rem;
            border-bottom: 1px solid rgba(202,186,156,0.5);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-row .info-label {
            margin: 0;
            min-width: 180px;
        }
        .info-row .info-value {
            margin: 0;
            color: var(--kjd-dark-green);
            font-weight: 700;
        }
        
        .success-icon { 
            color: var(--kjd-earth-green); 
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .cart-header {
                padding: 2rem 0;
            }
            
            .cart-header h1 {
                font-size: 1.8rem;
            }
            
            .cart-header p {
                font-size: 1rem;
            }
            
            .summary-card {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .summary-card h3 {
                font-size: 1.3rem;
                margin-bottom: 1rem;
            }
            
            .order-item {
                padding: 1rem;
            }
            
            .order-item-image {
                width: 60px;
                height: 60px;
            }
            
            .order-item-name {
                font-size: 1rem;
            }
            
            .order-item-price {
                font-size: 1rem;
            }
            
            .price-breakdown {
                padding: 1rem;
            }
            
            .price-row {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
            
            .price-total {
                padding: 1rem;
                font-size: 1.2rem;
            }
            
            .btn-confirm {
                padding: 1rem 2rem;
                font-size: 1.1rem;
                width: 100%;
            }
            
            .btn-checkout-secondary {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                width: 100%;
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
                padding: 0.5rem 0;
            }
            
            .info-row .info-label {
                min-width: auto;
                font-size: 0.9rem;
            }
            
            .info-row .info-value {
                font-size: 0.9rem;
                word-break: break-word;
            }
            
            .d-flex.gap-3 {
                flex-direction: column;
                gap: 1rem !important;
            }
            
            .d-flex.gap-3 .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .cart-header {
                padding: 1.5rem 0;
            }
            
            .cart-header h1 {
                font-size: 1.5rem;
            }
            
            .summary-card {
                padding: 1rem;
                border-radius: 12px;
            }
            
            .summary-card h3 {
                font-size: 1.2rem;
            }
            
            .order-item {
                padding: 0.75rem;
            }
            
            .order-item-image {
                width: 50px;
                height: 50px;
            }
            
            .price-breakdown {
                padding: 0.75rem;
            }
            
            .price-row {
                padding: 0.4rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .price-total {
                padding: 0.75rem;
                font-size: 1.1rem;
            }
            
            .btn-confirm {
                padding: 0.875rem 1.5rem;
                font-size: 1rem;
            }
            
            .btn-checkout-secondary {
                padding: 0.625rem 1.25rem;
                font-size: 0.9rem;
            }
            
            .success-icon {
                font-size: 1.5rem;
            }
            
            .info-row .info-label {
                font-size: 0.85rem;
            }
            
            .info-row .info-value {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body class="cart-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
        <div class="preloader"></div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Header (mirrors cart.php) -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
                        <div class="mb-3 mb-md-0">
                            <h1 class="h2 mb-0">
                                <i class="fas fa-check-circle me-2"></i>Přehled objednávky
                            </h1>
                            <p class="mb-0 mt-2">Zkontrolujte si údaje před dokončením objednávky</p>
                        </div>
                        <a href="checkout.php" class="btn btn-kjd-secondary d-flex align-items-center">
                            <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                            <span class="d-none d-sm-inline">Zpět k úpravám</span>
                            <span class="d-sm-none">Zpět</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($_SESSION['order_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 12px; box-shadow: 0 4px 15px rgba(220,53,69,0.1);">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Chyba:</strong> <?= htmlspecialchars($_SESSION['order_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['order_error']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- Zákaznické údaje -->
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="summary-card">
                    <h3><i class="fas fa-user me-2"></i>Zákaznické údaje</h3>
                    <div class="info-list">
                        <div class="info-row">
                            <div class="info-label">Jméno a příjmení</div>
                            <div class="info-value"><?= htmlspecialchars($customerData['name']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">E-mail</div>
                            <div class="info-value"><?= htmlspecialchars($customerData['email']) ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Telefon</div>
                            <div class="info-value"><?= htmlspecialchars($customerData['phone']) ?></div>
                        </div>
                        <?php if (!empty($customerData['note'])): ?>
                        <div class="info-row">
                            <div class="info-label">Poznámka</div>
                            <div class="info-value"><?= htmlspecialchars($customerData['note']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Doprava a platba -->
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="summary-card">
                    <h3><i class="fas fa-truck me-2"></i>Doprava a platba</h3>
                    <div class="info-list">
                        <div class="info-row">
                            <div class="info-label">Způsob dopravy</div>
                            <div class="info-value"><?= htmlspecialchars($deliveryData['method']) ?></div>
                        </div>
                        <?php if ($deliveryData['method'] === 'Zásilkovna'): ?>
                        <div class="info-row">
                            <div class="info-label">Adresa Zásilkovny</div>
                            <div class="info-value">
                                <?= htmlspecialchars($deliveryData['zasilkovna_name']) ?>,
                                <?= htmlspecialchars($deliveryData['zasilkovna_street']) ?>,
                                <?= htmlspecialchars($deliveryData['zasilkovna_zip']) ?> <?= htmlspecialchars($deliveryData['zasilkovna_city']) ?>
                            </div>
                        </div>
                        <?php elseif ($deliveryData['method'] === 'AlzaBox'): ?>
                        <div class="info-row">
                            <div class="info-label">AlzaBox</div>
                            <div class="info-value"><?= htmlspecialchars($deliveryData['alzabox_code']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <div class="info-label">Způsob platby</div>
                            <div class="info-value">
                                <?php 
                                    if ($paymentData['method'] === 'bank_transfer') {
                                        echo 'Bankovní převod';
                                    } elseif ($paymentData['method'] === 'revolut') {
                                        echo 'Revolut';
                                    } elseif ($paymentData['method'] === 'gopay') {
                                        echo 'GoPay - Platební brána';
                                    } else {
                                        echo 'Individuální domluva';
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Produkty -->
            <div class="col-lg-8 col-md-12 mb-4">
                <div class="summary-card">
                    <h3><i class="fas fa-shopping-bag me-2"></i>Produkty v objednávce</h3>
                    <?php foreach ($cartItems as $item): ?>
                        <div class="order-item">
                            <div class="row align-items-center">
                                <div class="col-3 col-md-2">
                                    <img src="<?= htmlspecialchars(getProductImageSrc($item['product'])) ?>" 
                                         class="order-item-image" 
                                         alt="<?= htmlspecialchars($item['product']['name']) ?>"
                                         referrerpolicy="no-referrer"
                                         onerror="this.src='images/product-thumb-11.jpg';">
                                </div>
                                <div class="col-6 col-md-6">
                                    <div class="order-item-name"><?= htmlspecialchars($item['product']['name']) ?></div>
                                    <div class="text-muted">Množství: <?= $item['quantity'] ?></div>
                                </div>
                                <div class="col-3 col-md-4 text-end">
                                    <div class="order-item-price">
                                        <?= number_format($item['subtotal'], 0, ',', ' ') ?> Kč
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Souhrn ceny -->
            <div class="col-lg-4 col-md-12 mb-4">
                <div class="summary-card">
                    <h3><i class="fas fa-calculator me-2"></i>Souhrn objednávky</h3>
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Mezisoučet:</span>
                            <span><?= number_format($total, 0, ',', ' ') ?> Kč</span>
                        </div>
                        
                        <?php if ($discount > 0): ?>
                        <div class="price-row" style="color: var(--kjd-earth-green);">
                            <span>Sleva (<?= htmlspecialchars($discountCode) ?>):</span>
                            <span>-<?= number_format($discount, 0, ',', ' ') ?> Kč</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="price-row">
                            <span>Doprava:</span>
                            <span style="color: <?= $shippingColor ?>; font-weight: 600;"><?= $shippingText ?></span>
                        </div>
                        
                        <div class="price-row price-total">
                            <span>Celkem:</span>
                            <span><?= number_format($finalTotal, 0, ',', ' ') ?> Kč</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Akce -->
        <div class="row">
            <div class="col-12 text-center">
                <div class="summary-card">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 style="color: var(--kjd-earth-green); margin-bottom: 2rem;">
                        Všechny údaje jsou správně?
                    </h3>
                    <p class="mb-1" style="font-size: 1.1rem; color: #666;">
                        Po potvrzení objednávky obdržíte e-mail s detaily a platebními údaji.
                    </p>
                    <p class="mb-4" style="font-size: 0.95rem; color: #888;">
                        Pokud e-mail nevidíte do pár minut, zkontrolujte prosím složku Spam/Promo.
                    </p>
                    
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="process_order.php" class="btn btn-kjd-primary">
                            <i class="fas fa-check me-2"></i>
                            <span class="d-none d-sm-inline">Potvrdit objednávku zavazující k platbě</span>
                            <span class="d-sm-none">Potvrdit objednávku</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        // Smooth scroll pro lepší UX
        document.addEventListener('DOMContentLoaded', function() {
            // Animace pro karty
            const cards = document.querySelectorAll('.summary-card');
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
