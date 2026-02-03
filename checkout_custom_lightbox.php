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

// Kontrola, zda máme data z custom_lightbox
if (!isset($_SESSION['custom_lightbox_order'])) {
    header('Location: custom_lightbox.php');
    exit;
}

$orderData = $_SESSION['custom_lightbox_order'];

// Výpočet dopravy - 100 Kč
$shippingCost = 100;
$shippingText = number_format($shippingCost, 0, ',', ' ') . " Kč";
$shippingColor = "#666";

// Pokud je objednávka nad 1000 Kč, doprava zůstává 100 Kč (ne zdarma)
if ($orderData['total_price'] >= 1000) {
    // Doprava zůstává 100 Kč
    $shippingCost = 100;
    $shippingText = number_format($shippingCost, 0, ',', ' ') . " Kč";
    $shippingColor = "var(--kjd-earth-green)";
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
$subtotal = $orderData['total_price'] + $shippingCost;
$walletUsed = $useWallet ? min($walletAmount, $subtotal) : 0;
$finalTotal = $subtotal - $walletUsed;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Dokončení objednávky - Custom Lightbox - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
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
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid var(--kjd-earth-green);
        }
        
        /* Custom checkbox styling */
        #custom_product_agreement {
            min-width: 24px;
            min-height: 24px;
            cursor: pointer;
        }
        
        #custom_product_agreement:checked {
            background-color: var(--kjd-earth-green);
            border-color: var(--kjd-dark-green);
        }
        
        #custom_product_agreement:focus {
            outline: 3px solid rgba(76,100,68,0.3);
            outline-offset: 2px;
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
            body.checkout-page { padding-bottom: 90px; }
            #custom_product_agreement { 
                width: 22px !important; 
                height: 22px !important; 
                min-width: 22px !important;
                min-height: 22px !important;
            }
            .form-check-label { font-size: 0.95rem !important; }
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
                                <i class="fas fa-credit-card me-2"></i>Dokončení objednávky - Custom Lightbox
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Vyplňte údaje pro dokončení objednávky</p>
                        </div>
                        <a href="custom_lightbox.php" class="btn btn-checkout-secondary d-flex align-items-center">
                            <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                            Zpět
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <form action="order_summary_custom_lightbox.php" method="POST" id="checkout-form">
            <div class="row">
                <!-- Zákaznické údaje -->
                <div class="col-lg-8 mb-4">
                    <div class="checkout-card">
                        <h3><i class="fas fa-user me-2"></i>Kontaktní údaje</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Jméno a příjmení *</label>
                                    <input type="text" name="name" id="name" class="form-control" required 
                                           value="<?= htmlspecialchars($orderData['customer_name']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">E-mail *</label>
                                    <input type="email" name="email" id="email" class="form-control" required 
                                           value="<?= htmlspecialchars($orderData['customer_email']) ?>">
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
                                    <input type="tel" name="phone_number" id="phone_number" class="form-control" required 
                                           value="<?= htmlspecialchars($orderData['customer_phone'] ?? '') ?>">
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
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="zasilkovna_name">Název pobočky *</label>
                                        <input type="text" name="zasilkovna_name" id="zasilkovna_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="zasilkovna_street">Ulice a číslo *</label>
                                        <input type="text" name="zasilkovna_street" id="zasilkovna_street" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="zasilkovna_city">Město *</label>
                                        <input type="text" name="zasilkovna_city" id="zasilkovna_city" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="zasilkovna_zip">PSČ *</label>
                                        <input type="text" name="zasilkovna_zip" id="zasilkovna_zip" class="form-control" required>
                                    </div>
                                </div>
                            </div>
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
                    </div>
                </div>

                <!-- Souhrn objednávky -->
                <div class="col-lg-4 mb-4">
                    <div class="checkout-card">
                        <h3><i class="fas fa-shopping-bag me-2"></i>Souhrn objednávky</h3>
                        
                        <?php if (file_exists($orderData['image_path'])): ?>
                            <img src="<?= htmlspecialchars($orderData['image_path']) ?>" alt="Váš obrázek" class="image-preview">
                        <?php endif; ?>
                        
                        <div class="order-summary">
                            <div class="summary-row">
                                <span>Custom Lightbox:</span>
                                <span>
                                    <?php
                                    $sizes = ['small' => 'Malé', 'medium' => 'Střední', 'large' => 'Velké'];
                                    $quantity = isset($orderData['quantity']) ? (int)$orderData['quantity'] : 1;
                                    echo ($sizes[$orderData['size']] ?? $orderData['size']) . ($quantity > 1 ? ' × ' . $quantity : '');
                                    ?>
                                </span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Cena světla (ks):</span>
                                <span><?= number_format($orderData['base_price'], 0, ',', ' ') ?> Kč</span>
                            </div>
                            
                            <?php if ($orderData['stand_price'] > 0): ?>
                            <div class="summary-row">
                                <span>Podstavec (ks):</span>
                                <span><?= number_format($orderData['stand_price'], 0, ',', ' ') ?> Kč</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($quantity > 1): ?>
                            <div class="summary-row">
                                <span>Množství:</span>
                                <span><?= $quantity ?>x</span>
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
                            <div class="form-check" style="background: #fff3cd; border: 3px solid #ffc107; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(255,193,7,0.3);">
                                <div class="d-flex align-items-start">
                                    <input class="form-check-input" type="checkbox" id="custom_product_agreement" name="custom_product_agreement" required 
                                           style="width: 24px; height: 24px; margin-top: 0.1rem; margin-right: 0.75rem; cursor: pointer; 
                                                  border: 2px solid #856404; border-radius: 4px; 
                                                  accent-color: var(--kjd-earth-green); 
                                                  flex-shrink: 0;">
                                    <label class="form-check-label" for="custom_product_agreement" 
                                           style="color: #856404; font-weight: 700; font-size: 1rem; line-height: 1.5; cursor: pointer; flex: 1;">
                                        <i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>
                                        Beru na vědomí, že se jedná o výrobek upravený na míru a nelze odstoupit od smlouvy.
                                    </label>
                                </div>
                            </div>
                            
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
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        function selectDelivery(method) {
            document.querySelectorAll('.delivery-option').forEach(option => {
                option.classList.remove('active');
                option.querySelector('input[type="radio"]').checked = false;
            });
            
            event.currentTarget.classList.add('active');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
            
            const zasilkovnaDetails = document.getElementById('zasilkovna-details');
            const alzaboxDetails = document.getElementById('alzabox-details');
            const jinaDopravaDetails = document.getElementById('jina-doprava-details');
            
            zasilkovnaDetails.style.display = 'none';
            alzaboxDetails.style.display = 'none';
            jinaDopravaDetails.style.display = 'none';
            
            document.getElementById('zasilkovna_name').required = false;
            document.getElementById('zasilkovna_street').required = false;
            document.getElementById('zasilkovna_city').required = false;
            document.getElementById('zasilkovna_zip').required = false;
            document.getElementById('alzabox_code').required = false;
            
            if (method === 'Zásilkovna') {
                zasilkovnaDetails.style.display = 'block';
                document.getElementById('zasilkovna_name').required = true;
                document.getElementById('zasilkovna_street').required = true;
                document.getElementById('zasilkovna_city').required = true;
                document.getElementById('zasilkovna_zip').required = true;
            } else if (method === 'AlzaBox') {
                alzaboxDetails.style.display = 'block';
                document.getElementById('alzabox_code').required = true;
            } else if (method === 'Jiná doprava') {
                jinaDopravaDetails.style.display = 'block';
            }
        }
        
        function selectPayment(method) {
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('active');
                option.querySelector('input[type="radio"]').checked = false;
            });
            
            event.currentTarget.classList.add('active');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            // Kontrola souhlasu s podmínkami
            const agreementCheckbox = document.getElementById('custom_product_agreement');
            if (!agreementCheckbox.checked) {
                e.preventDefault();
                alert('Prosím, potvrďte, že berete na vědomí, že se jedná o výrobek upravený na míru a nelze odstoupit od smlouvy.');
                agreementCheckbox.focus();
                return;
            }
            
            const selectedDelivery = document.querySelector('input[name="delivery_method"]:checked').value;
            
            if (selectedDelivery === 'Zásilkovna') {
                const requiredFields = ['zasilkovna_name', 'zasilkovna_street', 'zasilkovna_city', 'zasilkovna_zip'];
                for (const fieldId of requiredFields) {
                    const field = document.getElementById(fieldId);
                    if (!field.value.trim()) {
                        e.preventDefault();
                        alert('Prosím vyplňte všechny údaje pro Zásilkovnu.');
                        field.focus();
                        return;
                    }
                }
            } else if (selectedDelivery === 'AlzaBox') {
                const alzaboxCode = document.getElementById('alzabox_code');
                if (!alzaboxCode.value.trim()) {
                    e.preventDefault();
                    alert('Prosím zadejte kód nebo adresu AlzaBoxu.');
                    alzaboxCode.focus();
                    return;
                }
            }
        });
    </script>
</body>
</html>

