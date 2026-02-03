<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Track abandoned cart for logged-in users
if (isset($_SESSION['user_id']) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    include 'track_abandoned_cart.php';
}

// DB connection (copied from root index.php)
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

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}


// Helper to get product image path for this subdirectory (same as index.php)
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

// Helper: compute effective item price with active sale
function computeEffectivePrice(array $p): float {
    $base = (float)($p['final_price'] ?? $p['price'] ?? 0);
    // If final_price is explicitly provided in session, trust it
    if (isset($p['final_price'])) {
        return (float)$p['final_price'];
    }
    // Recompute sale if product data available
    $saleActive = false; $salePrice = 0;
    if (!empty($p['sale_enabled']) && (int)$p['sale_enabled'] === 1) {
        $sp = isset($p['sale_price']) ? (float)$p['sale_price'] : 0;
        if ($sp > 0 && $sp < $base) {
            if (!empty($p['sale_end'])) {
                $endTs = strtotime((string)$p['sale_end']);
                if ($endTs && time() < $endTs) { $saleActive = true; $salePrice = $sp; }
            } else { $saleActive = true; $salePrice = $sp; }
        }
    }
    return $saleActive ? $salePrice : $base;
}

$cartItems = [];
$total = 0;
$discount = 0;
$discountCode = '';
$discountMessage = '';

// Remove discount if requested
if (isset($_GET['remove_discount']) && $_GET['remove_discount'] == '1') {
    unset($_SESSION['applied_discount']);
    header('Location: cart.php');
    exit;
}

// Check for discount code
if (isset($_POST['discount_code']) && !empty($_POST['discount_code'])) {
    $discountCode = trim($_POST['discount_code']);
    
    // Debug: Log submitted code
    error_log("Discount code submitted: " . $discountCode);
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM discount_codes 
            WHERE code = ? AND active = 1 
            AND valid_from <= CURDATE() 
            AND valid_to >= CURDATE() 
            AND (usage_limit IS NULL OR times_used < usage_limit)
        ");
        $stmt->execute([$discountCode]);
        $discountData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log fetched data
        error_log("Fetched discount data: " . print_r($discountData, true));
        
        if ($discountData) {
            // Valid discount code found
            $_SESSION['applied_discount'] = $discountData;
            $discountMessage = "Slevový kód '{$discountCode}' byl aplikován!";
        } else {
            // Debug: Check what's wrong
            $stmt2 = $conn->prepare("SELECT * FROM discount_codes WHERE code = ?");
            $stmt2->execute([$discountCode]);
            $allData = $stmt2->fetch(PDO::FETCH_ASSOC);
            error_log("All discount data for code: " . print_r($allData, true));
            
            $discountMessage = "Neplatný nebo vypršelý slevový kód.";
        }
    } catch (PDOException $e) {
        error_log("Discount code error: " . $e->getMessage());
        $discountMessage = "Chyba při ověřování slevového kódu.";
    }
}

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cartKey => $productData) {
        // Session already contains all product data
        $quantity = (int)($productData['quantity'] ?? 0);
        $price = computeEffectivePrice($productData);
        
        $cartItems[] = [
            'product' => $productData,
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $price * $quantity,
            'cart_key' => $cartKey
        ];
        
        $total += $price * $quantity;
    }
}

// Apply discount if valid
if (isset($_SESSION['applied_discount'])) {
    $discountPercent = (float)$_SESSION['applied_discount']['discount_percent'];
    $discount = ($total * $discountPercent) / 100;
    $discountCode = $_SESSION['applied_discount']['code'];
}

// Calculate shipping cost
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

// Respect selected delivery method stored during checkout step
$selectedDeliveryMethod = $_SESSION['order_data']['delivery']['method'] ?? null;
if ($selectedDeliveryMethod && $selectedDeliveryMethod !== 'Zásilkovna') {
    // Non-Zásilkovna methods are always free
    $shippingCost = 0;
    $shippingText = "Zdarma";
    $shippingColor = "var(--kjd-earth-green)";
} elseif ($hasFreeShippingCode) {
    // Free shipping code applied
    $shippingCost = 0;
    $shippingText = "Zdarma (slevový kód)";
    $shippingColor = "var(--kjd-earth-green)";
} else {
    // Default/Zásilkovna rule: free over 1000, otherwise 100 Kč
    if ($total < 1000) {
        $shippingCost = 100; // Cena dopravy pod 1000 Kč
        $shippingText = number_format($shippingCost, 0, ',', ' ') . " Kč";
        $shippingColor = "#666";
    }
}

// Get user wallet balance if logged in
$walletBalance = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT balance FROM user_wallet WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $walletBalance = $wallet ? (float)$wallet['balance'] : 0;
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Calculate final total after discount and shipping
$finalTotal = $total - $discount + $shippingCost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Košík - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="author" content="">
    <meta name="keywords" content="">
    <meta name="description" content="">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">

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
      
      /* Cart specific styles */
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
      }
      .cart-header p { 
        font-size: 1.1rem; 
        font-weight: 500;
        opacity: 0.8;
      }
      .cart-item { 
        background: #fff; 
        border-radius: 16px; 
        padding: 2rem; 
        margin-bottom: 1.5rem; 
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 1px solid rgba(202,186,156,0.2);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      .cart-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      .cart-product-image { 
        width: 140px; 
        height: 140px; 
        object-fit: cover; 
        border-radius: 12px;
        border: 3px solid var(--kjd-beige);
        box-shadow: 0 4px 15px rgba(16,40,32,0.1);
      }
      .cart-product-name { 
        color: var(--kjd-dark-green); 
        font-weight: 700; 
        font-size: 1.3rem;
        margin-bottom: 0.5rem; 
      }
      .cart-product-price { 
        color: var(--kjd-gold-brown); 
        font-weight: 800; 
        font-size: 1.2rem;
      }
      .quantity-controls { 
        display: flex; 
        align-items: center; 
        gap: 0.75rem; 
        background: var(--kjd-beige);
        padding: 0.5rem;
        border-radius: 12px;
        border: 2px solid var(--kjd-earth-green);
      }
      .quantity-btn { 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        border: 2px solid var(--kjd-earth-green); 
        background: #fff; 
        color: var(--kjd-earth-green); 
        display: flex; 
        align-items: center; 
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        transition: all 0.2s ease;
      }
      .quantity-btn:hover { 
        background: var(--kjd-earth-green); 
        color: #fff;
        transform: scale(1.1);
      }
      .quantity-input { 
            width: 80px;
        text-align: center; 
        border: 2px solid var(--kjd-earth-green); 
        border-radius: 8px; 
        padding: 0.75rem; 
        font-weight: 700;
        font-size: 1.1rem;
        background: #fff;
      }
      .remove-btn { 
        background: linear-gradient(135deg, #dc3545, #c82333); 
        color: #fff; 
        border: none; 
        border-radius: 8px; 
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
      }
      .remove-btn:hover { 
        background: linear-gradient(135deg, #c82333, #a71e2a);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(220,53,69,0.3);
      }
      
      .cart-summary { 
        background: var(--kjd-beige); 
        border-radius: 12px; 
        padding: 1.5rem; 
        border: 2px solid var(--kjd-earth-green);
      }
      .summary-title { 
        color: var(--kjd-dark-green); 
        font-weight: 800; 
        font-size: 1.5rem;
        margin-bottom: 2rem;
        text-align: center;
        border-bottom: 3px solid var(--kjd-earth-green);
        padding-bottom: 1rem;
      }
      .summary-row { 
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
      .summary-total { 
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
      
      .summary-total span:first-child {
        color: rgba(255,255,255,0.9);
      }
      
      .summary-total span:last-child {
        color: #fff;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
      }
      
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
      
      .empty-cart { 
        text-align: center; 
        padding: 6rem 2rem;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(16,40,32,0.1);
      }
      .empty-cart h3 { 
        color: var(--kjd-dark-green); 
        margin-bottom: 1.5rem;
        font-size: 2rem;
        font-weight: 700;
      }
      .empty-cart p { 
        color: #666; 
        margin-bottom: 2.5rem;
        font-size: 1.2rem;
      }
      
      .shipping-banner { 
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown)); 
        color: #fff; 
        padding: 1.5rem; 
        border-radius: 12px; 
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px rgba(76,100,68,0.3);
        border: 2px solid rgba(255,255,255,0.2);
      }
      .shipping-banner i { 
        margin-right: 0.75rem;
        font-size: 1.2rem;
      }
      
      .discount-card {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        border: 2px solid var(--kjd-earth-green);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
      
      .wallet-control-section {
        transition: all 0.3s ease;
      }
      
      .wallet-control-section:hover {
        border-color: var(--kjd-earth-green) !important;
        background: rgba(202,186,156,0.15) !important;
      }
      
      .form-check-input:checked {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
      }
      
      .form-check-input:focus {
        border-color: var(--kjd-earth-green);
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .btn-outline-primary {
        border-color: var(--kjd-earth-green);
        color: var(--kjd-earth-green);
      }
      
      .btn-outline-primary:hover {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
        color: #fff;
      }
      
      .btn-outline-secondary {
        border-color: var(--kjd-gold-brown);
        color: var(--kjd-gold-brown);
      }
      
      .btn-outline-secondary:hover {
        background-color: var(--kjd-gold-brown);
        border-color: var(--kjd-gold-brown);
        color: #fff;
      }
      
      /* Modal checkbox styling */
      #modalUseWallet {
        width: 20px !important;
        height: 20px !important;
        border: 2px solid var(--kjd-earth-green) !important;
        border-radius: 4px !important;
        background-color: #fff !important;
      }
      
      #modalUseWallet:checked {
        background-color: var(--kjd-earth-green) !important;
        border-color: var(--kjd-earth-green) !important;
      }
      
      #modalUseWallet:focus {
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25) !important;
        border-color: var(--kjd-earth-green) !important;
      }
    </style>
</head>
  <body class="cart-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
      <div class="preloader"></div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Cart Header -->
    <div class="cart-header">
      <div class="container-fluid">
        <div class="row">
            <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                  <i class="fas fa-shopping-cart me-2"></i>Váš košík
                </h1>
                <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);"><?= count($cartItems) ?> produktů</p>
              </div>
              <a href="index.php" class="btn btn-kjd-secondary d-flex align-items-center">
                <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                Pokračovat v nákupu
              </a>
            </div>
          </div>
        </div>
      </div>
                </div>
                
                
    <!-- Main Content -->
    <div class="container-fluid">
      <div class="row">
                <?php if (empty($cartItems)): ?>
          <div class="col-12">
            <div class="empty-cart">
              <svg width="80" height="80" class="mb-4" style="color: var(--kjd-beige);">
                <use xlink:href="#cart"></use>
              </svg>
                        <h3>Váš košík je prázdný</h3>
              <p>Přidejte nějaké produkty do košíku a začněte nakupovat</p>
              <a href="index.php" class="btn btn-kjd-primary">Prohlédnout produkty</a>
            </div>
                    </div>
                <?php else: ?>
                        <div class="col-lg-8">
            <!-- Shipping Banner respecting delivery method -->
            <?php 
              $selectedDeliveryMethod = $_SESSION['order_data']['delivery']['method'] ?? null;
              $isNonZasilkovna = $selectedDeliveryMethod && $selectedDeliveryMethod !== 'Zásilkovna';
            ?>
            <?php if ($isNonZasilkovna): ?>
            <div class="shipping-banner">
              <i class="fas fa-truck"></i>
              <strong>Doprava zdarma pro vybraný způsob doručení.</strong>
            </div>
            <?php else: ?>
              <?php if ($total >= 1000): ?>
              <div class="shipping-banner">
                <i class="fas fa-truck"></i>
                <strong>Výhoda! Při nákupu nad 1000 Kč je doprava zdarma.</strong>
              </div>
              <?php else: ?>
              <div class="shipping-banner" style="background: linear-gradient(135deg, #ffc107, #ff8c00);">
                <i class="fas fa-info-circle"></i>
                <strong>Přidejte ještě za <?= number_format(1000 - $total, 0, ',', ' ') ?> Kč a doprava bude zdarma!</strong>
              </div>
              <?php endif; ?>
            <?php endif; ?>

            <!-- Discount Code Section -->
            <div class="card mb-3 discount-card">
              <div class="card-body p-4">
                <h5 class="card-title mb-3" style="color: var(--kjd-dark-green); font-weight: 700;">
                  <i class="fas fa-percentage me-2"></i>Slevový kód
                </h5>
                <?php if (!empty($discountMessage)): ?>
                  <div class="alert <?= strpos($discountMessage, 'aplikován') !== false ? 'alert-success' : 'alert-danger' ?> alert-sm mb-3" style="border-radius: 8px; font-weight: 600;">
                    <?= htmlspecialchars($discountMessage) ?>
                  </div>
                <?php endif; ?>
                <form method="POST" class="d-flex gap-3">
                  <input type="text" 
                         name="discount_code" 
                         class="form-control" 
                         placeholder="Zadejte slevový kód"
                         value="<?= htmlspecialchars($discountCode) ?>"
                         style="border: 2px solid var(--kjd-earth-green); border-radius: 8px; padding: 0.75rem; font-weight: 600;">
                  <button type="submit" class="btn btn-kjd-primary">
                    <i class="fas fa-check me-2"></i>Použít kód
                  </button>
                </form>
                <?php if (!empty($discountCode)): ?>
                  <div class="mt-3 p-3" style="background: rgba(76,100,68,0.1); border-radius: 8px;">
                    <small style="color: var(--kjd-dark-green); font-weight: 600;">
                      <i class="fas fa-tag me-2"></i>Aplikovaný kód: <strong><?= htmlspecialchars($discountCode) ?></strong>
                      <a href="?remove_discount=1" class="text-danger ms-3" style="text-decoration: none;">
                        <i class="fas fa-times me-1"></i>Odstranit
                      </a>
                    </small>
                  </div>
                <?php endif; ?>
              </div>
            </div>


            <!-- Cart Items -->
                            <?php foreach ($cartItems as $item): ?>
                                <div class="cart-item" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= $item['cart_key'] ?>">
                                    <div class="row align-items-center">
                  <div class="col-md-2">
                    <img src="<?= htmlspecialchars(getProductImageSrc($item['product'])) ?>" 
                         class="cart-product-image" 
                         alt="<?= htmlspecialchars($item['product']['name']) ?>"
                         referrerpolicy="no-referrer"
                         onerror="this.src='images/product-thumb-11.jpg'; console.log('Image failed to load: <?= htmlspecialchars($item['product']['image_url'] ?? '') ?>');">
                                        </div>
                  <div class="col-md-4">
                    <h5 class="cart-product-name"><?= htmlspecialchars($item['product']['name']) ?></h5>
                    <?php if (!empty($item['product']['selected_color'])): ?>
                      <p class="mb-1" style="font-size: 0.9rem; color: #666;">
                        <strong>Barva:</strong> 
                        <span style="display: inline-block; width: 16px; height: 16px; border-radius: 50%; background-color: <?= htmlspecialchars($item['product']['selected_color']) ?>; border: 1px solid #ddd; vertical-align: middle; margin: 0 4px;"></span>
                      </p>
                    <?php endif; ?>
                    <?php if (!empty($item['product']['variants']) && is_array($item['product']['variants'])): ?>
                      <p class="mb-1" style="font-size: 0.9rem; color: #666;">
                        <?php foreach ($item['product']['variants'] as $variantName => $variantValue): ?>
                          <strong><?= htmlspecialchars($variantName) ?>:</strong> <?= htmlspecialchars($variantValue) ?><br>
                        <?php endforeach; ?>
                      </p>
                    <?php endif; ?>
                    <?php if (!empty($item['product']['component_colors']) && is_array($item['product']['component_colors'])): ?>
                      <p class="mb-1" style="font-size: 0.9rem; color: #666;">
                        <?php foreach ($item['product']['component_colors'] as $componentName => $colorName): ?>
                          <strong><?= htmlspecialchars($componentName) ?>:</strong> <?= htmlspecialchars($colorName) ?><br>
                        <?php endforeach; ?>
                      </p>
                    <?php endif; ?>
                    <?php if (!empty($item['product']['catalog_selection']) && is_array($item['product']['catalog_selection'])): ?>
                      <p class="mb-1" style="font-size: 0.9rem; color: #666;">
                        <strong>Výběr z katalogu:</strong><br>
                        <?php 
                        $cat = $item['product']['catalog_selection'];
                        if(!empty($cat['base_code'])) echo 'Podstavec: ' . htmlspecialchars($cat['base_code']) . '<br>';
                        if(!empty($cat['base_color'])) echo 'Barva podstavce: ' . htmlspecialchars($cat['base_color']) . '<br>';
                        if(!empty($cat['shade_code'])) echo 'Stínidlo: ' . htmlspecialchars($cat['shade_code']) . '<br>';
                        if(!empty($cat['shade_color'])) echo 'Barva stínidla: ' . htmlspecialchars($cat['shade_color']) . '<br>';
                        ?>
                      </p>
                    <?php endif; ?>
                  <p class="cart-product-price mb-0">
                    <?php
                      $baseP = (float)($item['product']['price'] ?? $item['price']);
                      $effP = (float)$item['price'];
                      if ($effP < $baseP) {
                        echo '<span style="text-decoration:line-through;color:#888; margin-right:6px;">' . number_format($baseP, 0, ',', ' ') . ' Kč</span>';
                      }
                      echo number_format($effP, 0, ',', ' ') . ' Kč';
                    ?>
                  </p>
                                        </div>
                  <div class="col-md-3">
                    <div class="quantity-controls">
                      <button class="quantity-btn" onclick="updateQuantity(<?= $item['product']['id'] ?>, -1)">
                        <svg width="16" height="16"><use xlink:href="#minus"></use></svg>
                      </button>
                      <input type="text" class="quantity-input" value="<?= $item['quantity'] ?>" data-current-quantity="<?= $item['quantity'] ?>" readonly>
                      <button class="quantity-btn" onclick="updateQuantity(<?= $item['product']['id'] ?>, 1)">
                        <svg width="16" height="16"><use xlink:href="#plus"></use></svg>
                      </button>
                                            </div>
                                        </div>
                  <div class="col-md-2 text-end">
                    <strong style="color: var(--kjd-dark-green); font-size: 1.1rem;">
                      <?= number_format($item['subtotal'], 0, ',', ' ') ?> Kč
                    </strong>
                                        </div>
                  <div class="col-md-1 text-end">
                    <button class="remove-btn" onclick="removeItem(<?= $item['product']['id'] ?>)">
                      <svg width="16" height="16"><use xlink:href="#trash"></use></svg>
                    </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
          <!-- Cart Summary -->
                        <div class="col-lg-4">
            <div class="cart-summary">
              <h4 class="summary-title">Souhrn objednávky</h4>
              
              <div class="summary-row">
                                        <span>Mezisoučet:</span>
                                        <span><?= number_format($total, 0, ',', ' ') ?> Kč</span>
                                    </div>
              <?php if ($discount > 0): ?>
              <div class="summary-row" style="color: var(--kjd-earth-green);">
                <span>Sleva (<?= htmlspecialchars($discountCode) ?>):</span>
                <span>-<?= number_format($discount, 0, ',', ' ') ?> Kč</span>
              </div>
              <?php endif; ?>
              <div class="summary-row">
                <span>Doprava:</span>
                <span style="color: <?= $shippingColor ?>; font-weight: 600;"><?= $shippingText ?></span>
              </div>
              
              <?php if ($walletBalance > 0): ?>
              <div class="summary-row" style="background: rgba(202,186,156,0.2); border: 2px solid var(--kjd-beige);">
                <span><i class="fas fa-wallet me-2"></i>Zůstatek na účtu:</span>
                <span style="color: var(--kjd-earth-green); font-weight: 700;"><?= number_format($walletBalance, 0, ',', ' ') ?> Kč</span>
              </div>
              
              <!-- Wallet Usage Button -->
              <div class="text-center mb-3">
                <button type="button" class="btn" onclick="openWalletModal()" style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); color: #fff; border: none; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.95rem;">
                  <i class="fas fa-wallet me-2"></i>Využít zůstatek z účtu
                </button>
              </div>
              <?php endif; ?>
              
              <div class="summary-row summary-total">
                <span>Celkem:</span>
                <span><?= number_format($finalTotal, 0, ',', ' ') ?> Kč</span>
              </div>
              
              <?php 
              // Calculate wallet deduction based on user selection
              $walletDeduction = 0;
              if ($walletBalance > 0 && isset($_SESSION['use_wallet']) && $_SESSION['use_wallet']) {
                  $walletDeduction = min($_SESSION['wallet_amount'] ?? 0, $walletBalance, $finalTotal);
              }
              $amountToPay = max(0, $finalTotal - $walletDeduction);
              ?>
              
              <?php if ($walletBalance > 0 && $finalTotal > 0): ?>
              <div class="summary-row" style="background: rgba(76,100,68,0.1); border: 2px solid var(--kjd-earth-green);">
                <span><i class="fas fa-credit-card me-2"></i>K úhradě:</span>
                <span style="color: var(--kjd-dark-green); font-weight: 700;" id="amountToPay">
                  <?= number_format($amountToPay, 0, ',', ' ') ?> Kč
                </span>
              </div>
              <?php endif; ?>
              
              <a href="checkout.php" class="btn btn-kjd-primary w-100 mb-3" style="padding: 1rem; text-decoration: none; display: block; text-align: center; font-weight: 700; font-size: 1.1rem;">
                <i class="fas fa-credit-card me-2"></i>Pokračovat k pokladně
              </a>
              <a href="index.php" class="btn btn-kjd-secondary w-100">
                Pokračovat v nákupu
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
                                    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Wallet Usage Modal -->
    <div class="modal fade" id="walletModal" tabindex="-1" aria-labelledby="walletModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: 3px solid var(--kjd-earth-green);">
          <div class="modal-header" style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); color: #fff; border-radius: 17px 17px 0 0;">
            <h5 class="modal-title" id="walletModalLabel" style="font-weight: 700;">
              <i class="fas fa-wallet me-2"></i>Využít zůstatek z účtu?
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-4">
            <div class="mb-4">
              <div class="alert alert-info" style="background: var(--kjd-beige); border: 2px solid var(--kjd-earth-green); color: var(--kjd-dark-green); border-radius: 12px;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Dostupný zůstatek:</strong> <?= number_format($walletBalance, 0, ',', ' ') ?> Kč
              </div>
            </div>
            
            <!-- Debug info -->
            <div class="mb-3" style="background: #f8f9fa; padding: 0.5rem; border-radius: 4px; font-size: 0.8rem; color: #666;">
              <strong>Debug:</strong> 
              <span id="debugInfo">Modal loaded</span>
              <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="testFunction()">Test</button>
            </div>
            
            <div class="form-check mb-4" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 2px solid var(--kjd-beige);">
              <input class="form-check-input" type="checkbox" id="modalUseWallet" 
                     <?= isset($_SESSION['use_wallet']) && $_SESSION['use_wallet'] ? 'checked' : '' ?>
                     onchange="toggleModalWalletUsage()"
                     style="transform: scale(1.5); margin-right: 1rem; accent-color: var(--kjd-earth-green);">
              <label class="form-check-label" for="modalUseWallet" style="font-weight: 700; color: var(--kjd-dark-green); font-size: 1.1rem; cursor: pointer;">
                <i class="fas fa-check-circle me-2" style="color: var(--kjd-earth-green);"></i>Ano, chci využít zůstatek z účtu
              </label>
            </div>
            
            <div id="modalWalletAmountSection" style="<?= isset($_SESSION['use_wallet']) && $_SESSION['use_wallet'] ? '' : 'display: none;' ?>">
              <label for="modalWalletAmount" class="form-label" style="color: var(--kjd-dark-green); font-weight: 600; font-size: 1rem; margin-bottom: 0.75rem;">
                Kolik chcete použít ze zůstatku?
              </label>
              <div class="input-group mb-3">
                <input type="number" 
                       class="form-control" 
                       id="modalWalletAmount" 
                       min="0" 
                       max="<?= $walletBalance ?>" 
                       step="1"
                       value="<?= isset($_SESSION['wallet_amount']) ? $_SESSION['wallet_amount'] : min($walletBalance, $finalTotal) ?>"
                       onchange="updateModalWalletAmount()"
                       style="border: 2px solid var(--kjd-earth-green); border-radius: 8px; font-weight: 600; padding: 0.75rem; text-align: center;">
                <span class="input-group-text" style="background: var(--kjd-earth-green); border: 2px solid var(--kjd-earth-green); font-weight: 600; color: #fff;">Kč</span>
              </div>
              
              <div class="wallet-amount-buttons mb-3 d-flex gap-2">
                <button type="button" class="btn flex-fill" onclick="setModalWalletAmount(<?= min($walletBalance, $finalTotal) ?>)" style="background: var(--kjd-earth-green); color: #fff; border: none; border-radius: 8px; font-weight: 600;">
                  Použít vše
                </button>
                <button type="button" class="btn flex-fill" onclick="setModalWalletAmount(0)" style="background: #fff; color: var(--kjd-dark-green); border: 2px solid var(--kjd-beige); border-radius: 8px; font-weight: 600;">
                  Nepoužít nic
                </button>
              </div>
              
              <div class="wallet-preview" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid var(--kjd-beige);">
                <div class="row text-center">
                  <div class="col-6">
                    <small style="color: #666;">Celkem k úhradě:</small>
                    <div style="font-weight: 700; color: var(--kjd-dark-green); font-size: 1.1rem;" id="modalTotalAmount">
                      <?= number_format($finalTotal, 0, ',', ' ') ?> Kč
                    </div>
                  </div>
                  <div class="col-6">
                    <small style="color: #666;">K úhradě:</small>
                    <div style="font-weight: 700; color: var(--kjd-earth-green); font-size: 1.1rem;" id="modalAmountToPay">
                      <?= number_format($amountToPay, 0, ',', ' ') ?> Kč
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 2px solid var(--kjd-beige);">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: var(--kjd-beige); color: var(--kjd-dark-green); border: 2px solid var(--kjd-earth-green); border-radius: 8px; font-weight: 600;">
              Zrušit
            </button>
            <button type="button" class="btn btn-primary" onclick="testSave()" id="saveWalletBtn" style="background: var(--kjd-earth-green); color: #fff; border: none; border-radius: 8px; font-weight: 600;">
              <i class="fas fa-check me-2"></i>Uložit
            </button>
          </div>
        </div>
      </div>
    </div>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    <script>
        // Loading states for buttons
        function setButtonLoading(button, isLoading) {
            if (isLoading) {
                button.disabled = true;
                button.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Načítám...';
            } else {
                button.disabled = false;
                // Restore original content - we'll need to store it
                if (button.dataset.originalContent) {
                    button.innerHTML = button.dataset.originalContent;
                }
            }
        }

        // Store original button content on page load
        document.addEventListener('DOMContentLoaded', function() {
            const quantityButtons = document.querySelectorAll('.quantity-btn');
            const removeButtons = document.querySelectorAll('.remove-btn');
            
            quantityButtons.forEach(button => {
                button.dataset.originalContent = button.innerHTML;
            });
            
            removeButtons.forEach(button => {
                button.dataset.originalContent = button.innerHTML;
            });
        });

        function updateQuantity(productId, change) {
            const button = event.target.closest('.quantity-btn');
            const quantityInput = button.parentElement.querySelector('.quantity-input');
            const currentQuantity = parseInt(quantityInput.value);
            const newQuantity = currentQuantity + change;
            
            // Prevent negative quantities
            if (newQuantity < 1) {
                alert('Množství nemůže být menší než 1. Pokud chcete produkt odstranit, použijte tlačítko pro smazání.');
                return;
            }
            
            // Set loading state
            setButtonLoading(button, true);
            
            // Disable all quantity buttons during update
            const allQuantityButtons = document.querySelectorAll('.quantity-btn');
            allQuantityButtons.forEach(btn => btn.disabled = true);
            
            fetch('update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&change=${change}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showMessage('Množství bylo aktualizováno', 'success');
                    
                    // Simple reload after short delay
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                } else {
                    throw new Error(data.message || 'Neznámá chyba při aktualizaci množství');
                }
            })
            .catch(error => {
                console.error('Error updating quantity:', error);
                showMessage('Chyba při aktualizaci množství: ' + error.message, 'error');
                
                // Restore original quantity
                quantityInput.value = currentQuantity;
            })
            .finally(() => {
                // Restore button states
                allQuantityButtons.forEach(btn => {
                    setButtonLoading(btn, false);
                });
            });
        }
        
        function removeItem(productId) {
            if (!confirm('Opravdu chcete odstranit tento produkt z košíku?')) {
                return;
            }
            
            const button = event.target.closest('.remove-btn');
            const cartItem = button.closest('.cart-item');
            
            // Set loading state
            setButtonLoading(button, true);
            
            // Disable all buttons during removal
            const allButtons = document.querySelectorAll('.quantity-btn, .remove-btn');
            allButtons.forEach(btn => btn.disabled = true);
            
            // Add fade out effect
            cartItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            cartItem.style.opacity = '0.5';
            cartItem.style.transform = 'scale(0.95)';
            
                fetch('update_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&remove=1`
                })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Complete fade out animation
                    cartItem.style.opacity = '0';
                    cartItem.style.transform = 'scale(0.8)';
                    
                    showMessage('Produkt byl odstraněn z košíku', 'success');
                    
                    // Remove from DOM after animation
                    setTimeout(() => {
                        cartItem.remove();
                        
                        // Check if cart is now empty
                        const remainingItems = document.querySelectorAll('.cart-item');
                        if (remainingItems.length === 0) {
                            location.reload(); // Reload to show empty cart
                        } else {
                            location.reload(); // Reload to update totals
                        }
                    }, 300);
                } else {
                    throw new Error(data.message || 'Neznámá chyba při odstraňování produktu');
                }
            })
            .catch(error => {
                console.error('Error removing item:', error);
                showMessage('Chyba při odstraňování produktu: ' + error.message, 'error');
                
                // Restore item appearance
                cartItem.style.opacity = '1';
                cartItem.style.transform = 'scale(1)';
            })
            .finally(() => {
                // Restore button states
                allButtons.forEach(btn => {
                    setButtonLoading(btn, false);
                });
            });
        }

        // Message display function
        function showMessage(message, type = 'info') {
            // Remove existing messages
            const existingMessages = document.querySelectorAll('.cart-message');
            existingMessages.forEach(msg => msg.remove());
            
            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} cart-message`;
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border-radius: 8px;
                font-weight: 600;
            `;
            messageDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
            `;
            
            document.body.appendChild(messageDiv);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.remove();
                        }
                    }, 300);
                }
            }, 3000);
        }

        // Test function
        function testFunction() {
            console.log('Test function called');
            document.getElementById('debugInfo').textContent = 'Test function works!';
            alert('Test function works!');
        }
        
        // Test save function
        function testSave() {
            console.log('testSave called');
            
            const checkbox = document.getElementById('modalUseWallet');
            const input = document.getElementById('modalWalletAmount');
            const saveBtn = document.getElementById('saveWalletBtn');
            
            if (!checkbox || !input) {
                alert('Chyba: Chybí checkbox nebo input');
                return;
            }
            
            const useWallet = checkbox.checked;
            const walletAmount = useWallet ? (parseFloat(input.value) || 0) : 0;
            
            console.log('Settings:', { useWallet, walletAmount });
            
            // Set loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Ukládám...';
            
            // Use fetch with proper headers
            fetch('update_wallet_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `use_wallet=${useWallet ? 1 : 0}&wallet_amount=${walletAmount}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Close modal immediately
                    const modalElement = document.getElementById('walletModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        console.log('Closing modal');
                        modal.hide();
                    } else {
                        console.log('Modal instance not found, trying to create new one');
                        const newModal = new bootstrap.Modal(modalElement);
                        newModal.hide();
                    }
                    
                    // Show success message
                    showSuccessMessage();
                    
                    // Reload page to update totals
                    setTimeout(() => {
                        console.log('Reloading page');
                        location.reload();
                    }, 1000);
                } else {
                    alert('Chyba při ukládání: ' + (data.message || 'Neznámá chyba'));
                    // Restore button
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Chyba při ukládání: ' + error.message);
                // Restore button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
            });
        }
        
        // AJAX wallet settings save function
        function saveWalletSettings() {
            console.log('saveWalletSettings called');
            
            const checkbox = document.getElementById('modalUseWallet');
            const input = document.getElementById('modalWalletAmount');
            const saveBtn = document.getElementById('saveWalletBtn');
            
            if (!checkbox || !input || !saveBtn) {
                alert('Chyba: Chybí potřebné elementy');
                return;
            }
            
            const useWallet = checkbox.checked;
            const walletAmount = useWallet ? (parseFloat(input.value) || 0) : 0;
            
            console.log('Settings:', { useWallet, walletAmount });
            
            // Set loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Ukládám...';
            
            // Use fetch with proper headers
            fetch('update_wallet_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `use_wallet=${useWallet ? 1 : 0}&wallet_amount=${walletAmount}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Close modal
                    const modalElement = document.getElementById('walletModal');
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Show success message
                    showSuccessMessage();
                    
                    // Reload page to update totals
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Chyba při ukládání: ' + (data.message || 'Neznámá chyba'));
                    // Restore button
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Chyba při ukládání: ' + error.message);
                // Restore button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
            });
        }
        
        // Show success message
        function showSuccessMessage() {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'alert alert-success';
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                background: var(--kjd-beige);
                border: 2px solid var(--kjd-earth-green);
                color: var(--kjd-dark-green);
                border-radius: 12px;
                font-weight: 600;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            `;
            messageDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-2"></i>
                    Nastavení zůstatku bylo uloženo!
                </div>
            `;
            
            document.body.appendChild(messageDiv);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.remove();
                        }
                    }, 300);
                }
            }, 3000);
        }
        
        // Modal wallet management functions
        function openWalletModal() {
            console.log('Opening wallet modal');
            const modal = new bootstrap.Modal(document.getElementById('walletModal'));
            modal.show();
        }
        
        function toggleModalWalletUsage() {
            const checkbox = document.getElementById('modalUseWallet');
            const section = document.getElementById('modalWalletAmountSection');
            
            if (checkbox.checked) {
                section.style.display = 'block';
                updateModalWalletAmount();
            } else {
                section.style.display = 'none';
            }
        }
        
        function setModalWalletAmount(amount) {
            const input = document.getElementById('modalWalletAmount');
            input.value = amount;
            updateModalWalletAmount();
        }
        
        function updateModalWalletAmount() {
            const checkbox = document.getElementById('modalUseWallet');
            const input = document.getElementById('modalWalletAmount');
            
            if (!checkbox.checked) {
                return;
            }
            
            const walletAmount = parseFloat(input.value) || 0;
            const walletBalance = parseFloat(input.max) || 0;
            const finalTotal = <?= $finalTotal ?>;
            
            // Validate amount
            if (walletAmount < 0) {
                input.value = 0;
                return;
            }
            if (walletAmount > walletBalance) {
                input.value = walletBalance;
                return;
            }
            if (walletAmount > finalTotal) {
                input.value = finalTotal;
                return;
            }
            
            // Calculate amount to pay
            const amountToPay = Math.max(0, finalTotal - walletAmount);
            
            // Update preview
            document.getElementById('modalAmountToPay').textContent = new Intl.NumberFormat('cs-CZ').format(amountToPay) + ' Kč';
        }
        
        function saveWalletSettings() {
            console.log('saveWalletSettings called');
            
            const checkbox = document.getElementById('modalUseWallet');
            const input = document.getElementById('modalWalletAmount');
            const saveBtn = document.getElementById('saveWalletBtn');
            
            if (!checkbox || !input || !saveBtn) {
                console.error('Missing elements:', { checkbox, input, saveBtn });
                alert('Chyba: Chybí potřebné elementy');
                return;
            }
            
            const useWallet = checkbox.checked;
            const walletAmount = useWallet ? (parseFloat(input.value) || 0) : 0;
            
            console.log('Saving wallet settings:', { useWallet, walletAmount });
            
            // Set loading state
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Ukládám...';
            
            // Try with XMLHttpRequest instead of fetch for better compatibility
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_wallet_settings.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                console.log('XHR readyState:', xhr.readyState, 'status:', xhr.status);
                
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            console.log('Response data:', data);
                            
                            if (data.success) {
                                // Close modal
                                const modal = bootstrap.Modal.getInstance(document.getElementById('walletModal'));
                                if (modal) {
                                    modal.hide();
                                }
                                // Reload page to update totals
                                setTimeout(() => {
                                    location.reload();
                                }, 300);
                            } else {
                                alert('Chyba při ukládání nastavení: ' + (data.message || 'Neznámá chyba'));
                                // Restore button
                                saveBtn.disabled = false;
                                saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
                            }
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.log('Raw response:', xhr.responseText);
                            alert('Chyba při zpracování odpovědi: ' + e.message);
                            // Restore button
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
                        }
                    } else {
                        console.error('HTTP error:', xhr.status, xhr.statusText);
                        alert('Chyba při komunikaci se serverem: ' + xhr.status);
                        // Restore button
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
                    }
                }
            };
            
            xhr.onerror = function() {
                console.error('XHR error');
                alert('Chyba při komunikaci se serverem');
                // Restore button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-check me-2"></i>Uložit';
            };
            
            const body = `use_wallet=${useWallet ? 1 : 0}&wallet_amount=${walletAmount}`;
            console.log('Sending body:', body);
            xhr.send(body);
        }
        
        function setWalletAmount(amount) {
            const input = document.getElementById('walletAmount');
            input.value = amount;
            updateWalletAmount();
        }
        
        function updateWalletAmount() {
            const checkbox = document.getElementById('useWallet');
            const input = document.getElementById('walletAmount');
            const amountToPayElement = document.getElementById('amountToPay');
            
            if (!checkbox.checked) {
                return;
            }
            
            const walletAmount = parseFloat(input.value) || 0;
            const walletBalance = parseFloat(input.max) || 0;
            const finalTotal = <?= $finalTotal ?>;
            
            // Validate amount
            if (walletAmount < 0) {
                input.value = 0;
                return;
            }
            if (walletAmount > walletBalance) {
                input.value = walletBalance;
                return;
            }
            if (walletAmount > finalTotal) {
                input.value = finalTotal;
                return;
            }
            
            // Calculate amount to pay
            const amountToPay = Math.max(0, finalTotal - walletAmount);
            
            // Update display
            if (amountToPayElement) {
                amountToPayElement.textContent = new Intl.NumberFormat('cs-CZ').format(amountToPay) + ' Kč';
            }
            
            // Save to session
            saveWalletSettings(true, walletAmount);
        }
        
        function saveWalletSettings(useWallet, amount) {
            fetch('update_wallet_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `use_wallet=${useWallet ? 1 : 0}&wallet_amount=${amount}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error saving wallet settings:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving wallet settings:', error);
            });
        }

        // Add input validation for quantity
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            quantityInputs.forEach(input => {
                input.addEventListener('blur', function() {
                    const value = parseInt(this.value);
                    if (isNaN(value) || value < 1) {
                        this.value = 1;
                        showMessage('Množství musí být alespoň 1', 'error');
                    }
                });
                
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const value = parseInt(this.value);
                        if (!isNaN(value) && value > 0) {
                            const productId = this.closest('.cart-item').dataset.productId;
                            if (productId) {
                                const currentQuantity = parseInt(this.dataset.currentQuantity || 1);
                                const change = value - currentQuantity;
                                if (change !== 0) {
                                    updateQuantity(productId, change);
                                }
                            }
                        }
                    }
                });
            });
            
            // Initialize wallet amount display
            const walletAmountInput = document.getElementById('walletAmount');
            if (walletAmountInput) {
                updateWalletAmount();
            }
        });
    </script>
</body>
</html>
