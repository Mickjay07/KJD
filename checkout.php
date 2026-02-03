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
    $discountPercent = (float)$_SESSION['applied_discount']['discount_percent'];
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

if ($hasFreeShippingCode) {
    // Free shipping code applied
    $shippingCost = 0;
    $shippingText = "Zdarma (slevový kód)";
    $shippingColor = "var(--kjd-earth-green)";
} elseif ($total < 1000) {
    $shippingCost = 100;
    $shippingText = number_format($shippingCost, 0, ',', ' ') . " Kč";
    $shippingColor = "#666";
}

// Wallet balance calculation
$walletBalance = 0;
$useWallet = isset($_SESSION['use_wallet']) && $_SESSION['use_wallet'];
$walletAmount = isset($_SESSION['wallet_amount']) ? (float)$_SESSION['wallet_amount'] : 0;

if (isset($_SESSION['user_id'])) {
    try {
        $walletQuery = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
        $walletQuery->execute([$_SESSION['user_id']]);
        $walletResult = $walletQuery->fetch(PDO::FETCH_ASSOC);
        $walletBalance = $walletResult ? (float)$walletResult['balance'] : 0;
    } catch (PDOException $e) {
        error_log("Wallet query error: " . $e->getMessage());
        $walletBalance = 0;
    }
}

// Calculate final total with wallet usage
$subtotal = $total - $discount + $shippingCost;
$walletUsed = $useWallet ? min($walletAmount, $subtotal) : 0;
$finalTotal = $subtotal - $walletUsed;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Dokončení objednávky - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    
    <!-- Packeta Widget v6 -->
    <script src="https://widget.packeta.com/v6/www/js/library.js"></script>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        
        .checkout-page { 
            background: #f8f9fa; 
            min-height: 100vh; 
        }
        
        .checkout-header { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            padding: 3rem 0; 
            margin-bottom: 2rem; 
            border-bottom: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
        
        .checkout-header h1 { 
            font-size: 2.5rem; 
            font-weight: 800; 
            text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
            margin-bottom: 0.5rem;
        }
        
        .checkout-header p { 
            font-size: 1.1rem; 
            font-weight: 500;
            opacity: 0.8;
        }
        
        .checkout-card { 
            background: #fff; 
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 1px solid rgba(202,186,156,0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .checkout-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16,40,32,0.12);
        }
        
        .checkout-card h3 { 
            color: var(--kjd-dark-green); 
            font-weight: 700; 
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid var(--kjd-earth-green);
            padding-bottom: 0.75rem;
        }
        
        .form-group label {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--kjd-dark-green);
        }
        
        .form-control {
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1.1rem;
            border: 2px solid #e0e0e0;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--kjd-earth-green);
            box-shadow: 0 0 0 3px rgba(76,100,68,0.1);
            outline: none;
        }
        
        .btn-checkout-primary { 
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
        
        .btn-checkout-primary:hover { 
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.4);
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
        
        .order-summary { 
            background: var(--kjd-beige); 
            border-radius: 12px; 
            padding: 1.5rem; 
            border: 2px solid var(--kjd-earth-green);
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
        
        .delivery-option, .payment-option {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .delivery-option:hover, .payment-option:hover {
            border-color: var(--kjd-earth-green);
            box-shadow: 0 4px 15px rgba(76,100,68,0.1);
        }
        
        .delivery-option.active, .payment-option.active {
            border-color: var(--kjd-earth-green);
            background: rgba(76,100,68,0.05);
        }
        
        .option-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--kjd-beige);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--kjd-earth-green);
            margin-right: 1rem;
        }
        
        .option-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--kjd-dark-green);
            margin-bottom: 0.25rem;
        }
        
        .option-desc {
            color: #666;
            font-size: 0.95rem;
        }

        /* Mobile optimizations */
        @media (max-width: 576px) {
            .checkout-header { padding: 1.25rem 0; margin-bottom: 1rem; }
            .checkout-header h1 { font-size: 1.6rem; }
            .checkout-header p { font-size: 0.95rem; }
            .checkout-card { padding: 1rem; border-radius: 12px; }
            .checkout-card h3 { font-size: 1.1rem; margin-bottom: 1rem; }
            .form-control { padding: 0.75rem 0.9rem; font-size: 1rem; border-radius: 10px; }
            .option-icon { width: 40px; height: 40px; font-size: 1.2rem; margin-right: 0.75rem; }
            .option-title { font-size: 1.05rem; }
            .option-desc { font-size: 0.9rem; }
            .order-summary { padding: 1rem; }
            .summary-row { padding: 0.6rem 0.75rem; font-size: 0.95rem; }
            .summary-total { padding: 1rem; font-size: 1.2rem; }
            .btn-checkout-primary, .btn-checkout-secondary { padding: 0.9rem 1.25rem; border-radius: 10px; }
            /* Sticky mobile bar */
            .mobile-checkout-bar { position: fixed; left: 0; right: 0; bottom: 0; background:#fff; border-top: 2px solid var(--kjd-beige); box-shadow: 0 -6px 20px rgba(16,40,32,0.15); padding: 0.75rem 1rem; z-index: 10000; }
            body.checkout-page { padding-bottom: 90px; }
        }
    </style>
</head>
<body class="checkout-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
        <div class="preloader"></div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Checkout Header -->
    <div class="checkout-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                                <i class="fas fa-credit-card me-2"></i>Dokončení objednávky
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Vyplňte údaje pro dokončení objednávky</p>
                        </div>
                        <a href="cart.php" class="btn btn-checkout-secondary d-flex align-items-center">
                            <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                            Zpět do košíku
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="container-fluid">
        <form id="checkout-form" method="POST" action="order_summary.php">
            <div class="row">
                <!-- Zákaznické údaje -->
                <div class="col-lg-8 mb-4">
                    <div class="checkout-card">
                        <h3><i class="fas fa-user me-2"></i>Zákaznické údaje</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Jméno a příjmení *</label>
                                    <input type="text" name="name" id="name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">E-mail *</label>
                                    <input type="email" name="email" id="email" class="form-control" required>
                                    <div class="alert alert-warning mt-2 mb-0" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 0.75rem; font-size: 0.85rem;">
                                        <i class="fas fa-exclamation-triangle me-1" style="color: #856404;"></i>
                                        <strong style="color: #856404;">Pozor:</strong> <span style="color: #856404;">Naše emaily často končí ve spamu. Zkontrolujte prosím složku Spam/Promo!</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone_number">Telefonní číslo *</label>
                                    <input type="tel" name="phone_number" id="phone_number" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="note">Poznámka k objednávce</label>
                            <textarea name="note" id="note" class="form-control" rows="3" placeholder="Volitelné poznámky..."></textarea>
                        </div>
                    </div>

                    <!-- Způsob dopravy -->
                    <div class="checkout-card">
                        <h3><i class="fas fa-truck me-2"></i>Způsob dopravy</h3>
                        
                        <div class="delivery-option active" onclick="selectDelivery('Zásilkovna')">
                            <div class="d-flex align-items-center">
                                <div class="option-icon">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <div>
                                    <div class="option-title">Zásilkovna</div>
                                    <div class="option-desc">Doručení na výdejní místo Zásilkovny</div>
                                </div>
                            </div>
                            <input type="radio" name="delivery_method" value="Zásilkovna" checked style="display: none;">
                        </div>
                        
                        <div class="delivery-option" onclick="selectDelivery('AlzaBox')">
                            <div class="d-flex align-items-center">
                                <div class="option-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div>
                                    <div class="option-title">AlzaBox</div>
                                    <div class="option-desc">Doručení do AlzaBoxu</div>
                                </div>
                            </div>
                            <input type="radio" name="delivery_method" value="AlzaBox" style="display: none;">
                        </div>
                        
                        <div class="delivery-option" onclick="selectDelivery('Jiná doprava')">
                            <div class="d-flex align-items-center">
                                <div class="option-icon">
                                    <i class="fas fa-shipping-fast"></i>
                                </div>
                                <div>
                                    <div class="option-title">Jiná doprava</div>
                                    <div class="option-desc">Individuální domluva</div>
                                </div>
                            </div>
                            <input type="radio" name="delivery_method" value="Jiná doprava" style="display: none;">
                        </div>
                        
                        <!-- Detaily pro Zásilkovnu -->
                        <div id="zasilkovna-details" class="mt-3">
                            <button type="button" id="packetaWidgetBtn" class="btn mb-3" style="width: 100%; background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); color: white; border: none; padding: 1rem 1.5rem; font-weight: 600; font-size: 1.05rem; border-radius: 12px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(76,100,68,0.3);">
                                <i class="fas fa-map-marker-alt me-2"></i>Vybrat výdejní místo Zásilkovny
                            </button>
                            
                            <!-- Selected branch display -->
                            <div id="selectedBranchDisplay" style="display: none; background: rgba(76,100,68,0.1); border: 2px solid var(--kjd-earth-green); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                                <h6 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">
                                    <i class="fas fa-check-circle me-2" style="color: var(--kjd-earth-green);"></i>Vybrané výdejní místo:
                                </h6>
                                <div id="selectedBranchInfo"></div>
                            </div>
                            
                            <!-- Hidden fields for branch data -->
                            <input type="hidden" name="packeta_branch_id" id="packeta_branch_id">
                            <input type="hidden" name="zasilkovna_name" id="zasilkovna_name">
                            <input type="hidden" name="zasilkovna_street" id="zasilkovna_street">
                            <input type="hidden" name="zasilkovna_city" id="zasilkovna_city">
                            <input type="hidden" name="zasilkovna_zip" id="zasilkovna_zip">
                        </div>
                        
                        <!-- Detaily pro AlzaBox -->
                        <div id="alzabox-details" class="mt-3" style="display: none;">
                            <div class="form-group">
                                <label for="alzabox_code">Kód nebo adresa AlzaBoxu *</label>
                                <input type="text" name="alzabox_code" id="alzabox_code" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Detaily pro Jinou dopravu -->
                        <div id="jina-doprava-details" class="mt-3" style="display: none;">
                            <div class="alert alert-info" style="background: linear-gradient(135deg, rgba(76,100,68,0.1), rgba(202,186,156,0.1)); border: 2px solid var(--kjd-earth-green); border-radius: 12px; padding: 1.5rem;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle me-3" style="font-size: 1.5rem; color: var(--kjd-earth-green);"></i>
                                    <div>
                                        <h6 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">Budeme vás kontaktovat</h6>
                                        <p style="margin-bottom: 0; color: #666; font-weight: 500;">
                                            V případě volby "Jiná doprava" vás budeme kontaktovat ohledně podrobností a ceny dopravy. 
                                            Uveďte prosím do poznámky k objednávce, jaký způsob dopravy preferujete.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Způsob platby -->
                    <div class="checkout-card">
                        <h3><i class="fas fa-credit-card me-2"></i>Způsob platby</h3>
                        
                        <div class="payment-option active" onclick="selectPayment('bank_transfer')">
                            <div class="d-flex align-items-center">
                                <div class="option-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div>
                                    <div class="option-title">Bankovní převod</div>
                                    <div class="option-desc">Standardní bankovní převod z vašeho účtu</div>
                                </div>
                            </div>
                            <input type="radio" name="payment_method" value="bank_transfer" checked style="display: none;">
                        </div>
                        
                        <div class="payment-option" onclick="selectPayment('revolut')">
                            <div class="d-flex align-items-center">
                                <div class="option-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div>
                                    <div class="option-title">Revolut</div>
                                    <div class="option-desc">Rychlá platba kartou, Apple Pay, Google Pay</div>
                                </div>
                            </div>
                            <input type="radio" name="payment_method" value="revolut" style="display: none;">
                        </div>
                        
                        
                        <div class="payment-option" onclick="selectPayment('gopay')">
                            <div class="d-flex align-items-center">
                                <div class="option-icon" style="background: #00a8e1;">
                                    <i class="fas fa-credit-card" style="color: #fff;"></i>
                                </div>
                                <div>
                                    <div class="option-title">GoPay - Platební brána</div>
                                    <div class="option-desc">Platba kartou, bankovní převod, Apple Pay, Google Pay</div>
                                </div>
                            </div>
                            <input type="radio" name="payment_method" value="gopay" style="display: none;">
                        </div>

                    </div>
                </div>

                <!-- Souhrn objednávky -->
                <div class="col-lg-4 mb-4">
                    <div class="checkout-card">
                        <h3><i class="fas fa-shopping-bag me-2"></i>Souhrn objednávky</h3>
                        
                        <div class="order-summary">
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
                            
                            <?php if ($walletUsed > 0): ?>
                            <div class="summary-row" style="background: rgba(202,186,156,0.2); border: 2px solid var(--kjd-beige);">
                                <span><i class="fas fa-wallet me-2"></i>Využitý zůstatek:</span>
                                <span style="color: var(--kjd-earth-green); font-weight: 700;">-<?= number_format($walletUsed, 0, ',', ' ') ?> Kč</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="summary-row summary-total">
                                <span>Celkem k úhradě:</span>
                                <span><?= number_format($finalTotal, 0, ',', ' ') ?> Kč</span>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-checkout-primary w-100">
                                <i class="fas fa-check me-2"></i>Pokračovat k přehledu
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Mobile sticky proceed bar -->
    <div class="mobile-checkout-bar d-sm-none d-block">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div style="font-weight:800; color: var(--kjd-dark-green);">Celkem: <?= number_format($finalTotal, 0, ',', ' ') ?> Kč</div>
                <?php if ($walletUsed > 0): ?>
                <div style="font-size: 0.8rem; color: var(--kjd-earth-green); font-weight: 600;">
                    <i class="fas fa-wallet me-1"></i>Využit zůstatek: <?= number_format($walletUsed, 0, ',', ' ') ?> Kč
                </div>
                <?php endif; ?>
            </div>
            <button type="submit" form="checkout-form" class="btn btn-checkout-primary">Pokračovat</button>
        </div>
        </form>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        console.log('=== CHECKOUT SCRIPT STARTED ===');
        
        // Packeta widget v6 - Initialize FIRST before anything else
        console.log('=== INITIALIZING PACKETA WIDGET ===');
        
        window.addEventListener('load', function() {
            console.log('Page loaded, setting up Packeta widget...');
            
            const packetaBtn = document.getElementById('packetaWidgetBtn');
            console.log('Packeta button:', packetaBtn);
            
            if (packetaBtn) {
                packetaBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Packeta button clicked!');
                    
                    if (typeof Packeta === 'undefined' || typeof Packeta.Widget === 'undefined') {
                        console.error('Packeta Widget not loaded!');
                        alert('Packeta widget se nepodařilo načíst. Zkuste obnovit stránku.');
                        return;
                    }
                    
                    console.log('Opening Packeta widget...');
                    
                    Packeta.Widget.pick('fc9e38cb95f48f3f', function(point) {
                        console.log('Selected point:', point);
                        
                        if (point && point.id) {
                            document.getElementById('packeta_branch_id').value = point.id;
                            document.getElementById('zasilkovna_name').value = point.name || '';
                            document.getElementById('zasilkovna_street').value = (point.street || '') + ' ' + (point.houseNumber || '');
                            document.getElementById('zasilkovna_city').value = point.city || '';
                            document.getElementById('zasilkovna_zip').value = point.zip || '';
                            
                            const display = document.getElementById('selectedBranchDisplay');
                            const info = document.getElementById('selectedBranchInfo');
                            
                            info.innerHTML = '<strong>' + point.name + '</strong><br>' + 
                                           point.street + ' ' + point.houseNumber + ', ' + point.zip + ' ' + point.city;
                            
                            display.style.display = 'block';
                            console.log('Branch saved:', point.name);
                        }
                    }, {
                        country: 'cz',
                        language: 'cs',
                        appIdentity: 'kubajadesigns'
                    });
                });
                
                console.log('Packeta event listener attached!');
            } else {
                console.error('Packeta button NOT FOUND!');
            }
        });
        
        function selectDelivery(method) {
            // Remove active class from all delivery options
            document.querySelectorAll('.delivery-option').forEach(option => {
                option.classList.remove('active');
                option.querySelector('input[type="radio"]').checked = false;
            });
            
            // Add active class to selected option
            event.currentTarget.classList.add('active');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
            
            // Show/hide details
            const zasilkovnaDetails = document.getElementById('zasilkovna-details');
            const alzaboxDetails = document.getElementById('alzabox-details');
            const jinaDopravaDetails = document.getElementById('jina-doprava-details');
            
            // Hide all details first
            zasilkovnaDetails.style.display = 'none';
            alzaboxDetails.style.display = 'none';
            jinaDopravaDetails.style.display = 'none';
            
            // Make all fields not required first
            document.getElementById('packeta_branch_id').required = false;
            document.getElementById('zasilkovna_name').required = false;
            document.getElementById('zasilkovna_street').required = false;
            document.getElementById('zasilkovna_city').required = false;
            document.getElementById('zasilkovna_zip').required = false;
            document.getElementById('alzabox_code').required = false;
            
            // Payment section handling
            const paymentSection = document.querySelector('.checkout-card:last-of-type'); // Assuming payment is the last card in the first column
            const paymentOptions = document.querySelectorAll('.payment-option');
            
            // Reset payment section visibility
            paymentOptions.forEach(opt => opt.style.display = 'block');
            
            // Remove any existing individual payment message
            const existingMsg = document.getElementById('individual-payment-msg');
            if (existingMsg) existingMsg.remove();
            
            // Remove hidden individual payment input if exists
            const existingInput = document.getElementById('individual-payment-input');
            if (existingInput) existingInput.remove();

            if (method === 'Zásilkovna') {
                zasilkovnaDetails.style.display = 'block';
                // Make Zásilkovna fields required
                document.getElementById('packeta_branch_id').required = true;
                document.getElementById('zasilkovna_name').required = true;
                document.getElementById('zasilkovna_street').required = true;
                document.getElementById('zasilkovna_city').required = true;
                document.getElementById('zasilkovna_zip').required = true;
            } else if (method === 'AlzaBox') {
                alzaboxDetails.style.display = 'block';
                // Make AlzaBox field required
                document.getElementById('alzabox_code').required = true;
            } else if (method === 'Jiná doprava') {
                jinaDopravaDetails.style.display = 'block';
                
                // Hide standard payment options
                paymentOptions.forEach(opt => opt.style.display = 'none');
                
                // Add individual payment message
                const msgDiv = document.createElement('div');
                msgDiv.id = 'individual-payment-msg';
                msgDiv.className = 'alert alert-info mt-3';
                msgDiv.style.background = 'linear-gradient(135deg, rgba(76,100,68,0.1), rgba(202,186,156,0.1))';
                msgDiv.style.border = '2px solid var(--kjd-earth-green)';
                msgDiv.style.borderRadius = '12px';
                msgDiv.innerHTML = '<i class="fas fa-info-circle me-2" style="color: var(--kjd-earth-green);"></i><strong>Platba:</strong> Bude vyřešena individuálně na základě domluvy.';
                paymentSection.appendChild(msgDiv);
                
                // Add hidden input for individual payment
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'payment_method';
                input.value = 'individual';
                input.id = 'individual-payment-input';
                paymentSection.appendChild(input);
                
                // Uncheck other payment methods
                paymentOptions.forEach(opt => {
                    opt.classList.remove('active');
                    opt.querySelector('input[type="radio"]').checked = false;
                });
            }
        }
        
        function selectPayment(method) {
            // If individual payment is active (hidden input exists), don't allow selecting other methods
            if (document.getElementById('individual-payment-input')) return;

            // Remove active class from all payment options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('active');
                option.querySelector('input[type="radio"]').checked = false;
            });
            
            // Add active class to selected option
            event.currentTarget.classList.add('active');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        // Form validation - wait for page to load
        window.addEventListener('load', function() {
            const checkoutForm = document.getElementById('checkout-form');
            console.log('Checkout form element:', checkoutForm);
            
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    console.log('=== FORM SUBMIT EVENT FIRED ===');
                    
                    const selectedDeliveryEl = document.querySelector('input[name="delivery_method"]:checked');
                    console.log('Selected delivery element:', selectedDeliveryEl);
                    
                    if (!selectedDeliveryEl) {
                        console.error('No delivery method selected!');
                        e.preventDefault();
                        alert('Prosím vyberte způsob dopravy.');
                        return;
                    }
                    
                    const selectedDelivery = selectedDeliveryEl.value;
                    console.log('Selected delivery:', selectedDelivery);
                    
                    if (selectedDelivery === 'Zásilkovna') {
                        console.log('Validating Zásilkovna...');
                        
                        // Check if branch was selected
                        const branchId = document.getElementById('packeta_branch_id');
                        console.log('Branch ID value:', branchId ? branchId.value : 'ELEMENT NOT FOUND');
                        
                        if (!branchId || !branchId.value.trim()) {
                            console.log('Branch ID is empty - blocking submit');
                            e.preventDefault();
                            alert('Prosím vyberte výdejní místo Zásilkovny kliknutím na zelené tlačítko.');
                            return;
                        }
                        
                        // Also check other fields
                        const requiredFields = ['zasilkovna_name', 'zasilkovna_street', 'zasilkovna_city', 'zasilkovna_zip'];
                        for (const fieldId of requiredFields) {
                            const field = document.getElementById(fieldId);
                            console.log(`Checking field ${fieldId}:`, field ? field.value : 'NOT FOUND');
                            
                            if (!field || !field.value.trim()) {
                                console.log(`Field ${fieldId} is empty - blocking submit`);
                                e.preventDefault();
                                alert('Chyba: Nebyly načteny údaje o pobočce. Zkuste vybrat výdejní místo znovu.');
                                return;
                            }
                        }
                        
                        console.log('Zásilkovna validation PASSED');
                    } else if (selectedDelivery === 'AlzaBox') {
                        console.log('Validating AlzaBox...');
                        const alzaboxCode = document.getElementById('alzabox_code');
                        if (!alzaboxCode || !alzaboxCode.value.trim()) {
                            console.log('AlzaBox code is empty - blocking submit');
                            e.preventDefault();
                            alert('Prosím zadejte kód nebo adresu AlzaBoxu.');
                            if (alzaboxCode) alzaboxCode.focus();
                            return;
                        }
                        console.log('AlzaBox validation PASSED');
                    }
                    
                    console.log('=== ALL VALIDATION PASSED - SUBMITTING FORM ===');
                    // Jiná doprava nevyžaduje žádné dodatečné pole
                });
                
                console.log('Form submit event listener attached');
            } else {
                console.error('Checkout form NOT FOUND!');
            }
        });
        
        // Smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.checkout-card');
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
        
        // Packeta widget v6 integration
        console.log('Initializing Packeta widget v6...');
        
        const packetaBtn = document.getElementById('packetaWidgetBtn');
        console.log('Packeta button element:', packetaBtn);
        
        if (packetaBtn) {
            packetaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Packeta button clicked!');
                
                if (typeof Packeta === 'undefined' || typeof Packeta.Widget === 'undefined') {
                    console.error('Packeta Widget not loaded!');
                    alert('Packeta widget se nepodařilo načíst. Zkuste obnovit stránku.');
                    return;
                }
                
                console.log('Opening Packeta widget...');
                
                // Initialize widget v6
                Packeta.Widget.pick('fc9e38cb95f48f3f', function(point) {
                    console.log('Selected point:', point);
                    
                    if (point && point.id) {
                        // Store branch data in hidden fields
                        document.getElementById('packeta_branch_id').value = point.id;
                        document.getElementById('zasilkovna_name').value = point.name || '';
                        document.getElementById('zasilkovna_street').value = (point.street || '') + ' ' + (point.houseNumber || '');
                        document.getElementById('zasilkovna_city').value = point.city || '';
                        document.getElementById('zasilkovna_zip').value = point.zip || '';
                        
                        // Display selected branch
                        const display = document.getElementById('selectedBranchDisplay');
                        const info = document.getElementById('selectedBranchInfo');
                        
                        info.innerHTML = `
                            <strong>${point.name}</strong><br>
                            ${point.street} ${point.houseNumber}, ${point.zip} ${point.city}
                        `;
                        
                        display.style.display = 'block';
                        console.log('Branch saved:', point.name);
                    }
                }, {
                    country: 'cz',
                    language: 'cs',
                    appIdentity: 'kubajadesigns'
                });
            });
            
            console.log('Packeta widget event listener attached');
        } else {
            console.error('Packeta button element NOT found! Check if zasilkovna-details div is visible.');
        }
    </script>
</body>
</html>
