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

// Kontrola, zda máme data z checkout
if (!isset($_SESSION['custom_lightbox_order'])) {
    header('Location: custom_lightbox.php');
    exit;
}

// Zobrazení chybové hlášky, pokud existuje
$error_message = '';
if (isset($_SESSION['order_error'])) {
    $error_message = $_SESSION['order_error'];
    unset($_SESSION['order_error']);
    // Zajištění, že se chybová hláška zobrazí
    session_write_close();
}

// Zpracování formuláře z checkout_custom_lightbox.php
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
        'zasilkovna_name' => $_POST['zasilkovna_name'] ?? '',
        'zasilkovna_street' => $_POST['zasilkovna_street'] ?? '',
        'zasilkovna_city' => $_POST['zasilkovna_city'] ?? '',
        'zasilkovna_zip' => $_POST['zasilkovna_zip'] ?? '',
        'alzabox_code' => $_POST['alzabox_code'] ?? ''
    ];
    
    $paymentData = [
        'method' => $_POST['payment_method'] ?? 'bank_transfer'
    ];
    
    // Uložení do session pro další zpracování
    $_SESSION['custom_lightbox_order_data'] = [
        'customer' => $customerData,
        'delivery' => $deliveryData,
        'payment' => $paymentData
    ];
} else {
    // Pokud není POST, přesměruj na checkout
    header('Location: checkout_custom_lightbox.php');
    exit;
}

$orderData = $_SESSION['custom_lightbox_order'];

// Výpočet dopravy
$shippingCost = 0;
if ($orderData['total_price'] < 1000) {
    $shippingCost = 90;
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
    <title>Souhrn objednávky - Custom Lightbox - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="fonts/sf-pro.css">
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        body {
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .summary-header {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--kjd-earth-green);
        }
        
        .summary-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 2px solid var(--kjd-earth-green);
        }
        
        .summary-card h3 {
            color: var(--kjd-dark-green);
            font-weight: 700;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid var(--kjd-earth-green);
            padding-bottom: 0.75rem;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 12px;
            border: 2px solid var(--kjd-earth-green);
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="summary-header">
        <div class="container">
            <h1 style="color: var(--kjd-dark-green); font-weight: 800;">
                <i class="fas fa-check-circle me-2"></i>Souhrn objednávky
            </h1>
        </div>
    </div>
    
    <div class="container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger" style="background: #f8d7da; border: 2px solid #dc3545; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="summary-card">
                    <h3><i class="fas fa-image me-2"></i>Váš obrázek</h3>
                    <?php if (file_exists($orderData['image_path'])): ?>
                        <img src="<?= htmlspecialchars($orderData['image_path']) ?>" alt="Váš obrázek" class="image-preview">
                    <?php endif; ?>
                    
                    <h3 class="mt-4"><i class="fas fa-info-circle me-2"></i>Detaily objednávky</h3>
                    <p><strong>Velikost:</strong> 
                        <?php
                        $sizes = ['small' => 'Malé (15x15 cm)', 'medium' => 'Střední (20x20 cm)', 'large' => 'Velké (25x25 cm)'];
                        echo $sizes[$orderData['size']] ?? $orderData['size'];
                        ?>
                    </p>
                    <p><strong>Podstavec:</strong> <?= $orderData['has_stand'] ? 'Ano' : 'Ne' ?></p>
                    
                    <h3 class="mt-4"><i class="fas fa-user me-2"></i>Kontaktní údaje</h3>
                    <p><strong>Jméno:</strong> <?= htmlspecialchars($customerData['name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($customerData['email']) ?></p>
                    <p><strong>Telefon:</strong> <?= htmlspecialchars($customerData['phone']) ?></p>
                    
                    <h3 class="mt-4"><i class="fas fa-truck me-2"></i>Doprava</h3>
                    <p><strong>Způsob:</strong> <?= htmlspecialchars($deliveryData['method']) ?></p>
                    <?php if ($deliveryData['method'] === 'Zásilkovna'): ?>
                        <p><strong>Pobočka:</strong> <?= htmlspecialchars($deliveryData['zasilkovna_name']) ?></p>
                        <p><strong>Adresa:</strong> <?= htmlspecialchars($deliveryData['zasilkovna_street']) ?>, <?= htmlspecialchars($deliveryData['zasilkovna_zip']) ?> <?= htmlspecialchars($deliveryData['zasilkovna_city']) ?></p>
                    <?php elseif ($deliveryData['method'] === 'AlzaBox'): ?>
                        <p><strong>Kód:</strong> <?= htmlspecialchars($deliveryData['alzabox_code']) ?></p>
                    <?php endif; ?>
                    
                    <h3 class="mt-4"><i class="fas fa-credit-card me-2"></i>Platba</h3>
                    <p><strong>Způsob:</strong> <?= $paymentData['method'] === 'bank_transfer' ? 'Bankovní převod' : 'Revolut' ?></p>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="summary-card">
                    <h3><i class="fas fa-calculator me-2"></i>Cena</h3>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Custom Lightbox:</span>
                        <span><?= number_format($orderData['total_price'], 0, ',', ' ') ?> Kč</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Doprava:</span>
                        <span><?= $shippingCost > 0 ? number_format($shippingCost, 0, ',', ' ') . ' Kč' : 'Zdarma' ?></span>
                    </div>
                    <?php if ($walletUsed > 0): ?>
                    <div class="d-flex justify-content-between mb-2" style="color: var(--kjd-earth-green);">
                        <span><i class="fas fa-wallet me-1"></i>Využitý zůstatek:</span>
                        <span>-<?= number_format($walletUsed, 0, ',', ' ') ?> Kč</span>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between" style="font-size: 1.3rem; font-weight: 800; color: var(--kjd-dark-green);">
                        <span>Celkem:</span>
                        <span><?= number_format($finalTotal, 0, ',', ' ') ?> Kč</span>
                    </div>
                    
                    <form action="process_custom_lightbox.php" method="POST" class="mt-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-check me-2"></i>Potvrdit a zaplatit
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

