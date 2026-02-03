<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Check for payment status from GoPay callback
$paymentStatus = $_GET['payment'] ?? 'success'; // Default to success for non-GoPay orders
$paymentFailed = ($paymentStatus === 'failed');

// Kontrola, zda máme data z process_order.php
if (!isset($_SESSION['order_confirmation'])) {
    header('Location: index.php');
    exit;
}

$orderData = $_SESSION['order_confirmation'];
$orderId = $orderData['order_id'];
$trackingCode = $orderData['tracking_code'];
$total = $orderData['total'];
$walletDeduction = $orderData['wallet_deduction'] ?? 0;
$amountToPay = $orderData['amount_to_pay'] ?? $total;
$email = $orderData['email'];
$name = $orderData['name'];

// Vyčištění session po zobrazení
unset($_SESSION['order_confirmation']);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Potvrzení objednávky - KJD</title>
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
        
        .confirmation-page { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            min-height: 100vh; 
            padding: 2rem 0;
        }
        
        .confirmation-header { 
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
            color: #fff;
            padding: 4rem 0; 
            margin-bottom: 3rem; 
            box-shadow: 0 8px 32px rgba(16,40,32,0.2);
        }
        
        .confirmation-header h1 { 
            font-size: 3rem; 
            font-weight: 800; 
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 1rem;
        }
        
        .confirmation-header p { 
            font-size: 1.3rem; 
            font-weight: 500;
            opacity: 0.9;
        }
        
        .success-icon {
            font-size: 4rem;
            color: var(--kjd-earth-green);
            margin-bottom: 2rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .confirmation-card { 
            background: #fff; 
            border-radius: 20px; 
            padding: 3rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 8px 32px rgba(16,40,32,0.1);
            border: 2px solid rgba(202,186,156,0.2);
            text-align: center;
        }
        
        .order-details { 
            background: var(--kjd-beige); 
            border-radius: 12px; 
            padding: 2rem; 
            border: 2px solid var(--kjd-earth-green);
            margin: 2rem 0;
        }
        
        .detail-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: rgba(202,186,156,0.1);
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            color: var(--kjd-dark-green);
        }
        
        .detail-total { 
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
        
        .detail-total span:first-child {
            color: rgba(255,255,255,0.9);
        }
        
        .detail-total span:last-child {
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .btn-primary-action { 
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
            color: #fff; 
            border: none; 
            padding: 1.2rem 3rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(77,45,24,0.3);
            margin: 0.5rem;
        }
        
        .btn-primary-action:hover { 
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
            color: #fff;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(77,45,24,0.4);
        }
        
        .btn-secondary-action { 
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
            color: var(--kjd-dark-green); 
            border: 2px solid var(--kjd-earth-green); 
            padding: 1rem 2.5rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }
        
        .btn-secondary-action:hover { 
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76,100,68,0.3);
        }
        
        .info-box {
            background: rgba(76,100,68,0.1);
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .info-box h4 {
            color: var(--kjd-dark-green);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .info-box p {
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .tracking-code {
            background: var(--kjd-dark-green);
            color: #fff;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 1rem 0;
            display: inline-block;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .confirmation-header {
                padding: 2.5rem 0;
                margin-bottom: 2rem;
            }
            
            .confirmation-header h1 {
                font-size: 2.2rem;
            }
            
            .confirmation-header p {
                font-size: 1.1rem;
            }
            
            .success-icon {
                font-size: 3rem;
                margin-bottom: 1.5rem;
            }
            
            .confirmation-card {
                padding: 2rem;
                margin-bottom: 1.5rem;
            }
            
            .confirmation-card h2 {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .order-details {
                padding: 1.5rem;
                margin: 1.5rem 0;
            }
            
            .detail-row {
                padding: 0.6rem 0.8rem;
                font-size: 0.95rem;
                margin-bottom: 0.8rem;
            }
            
            .detail-total {
                padding: 1.2rem;
                font-size: 1.2rem;
            }
            
            .btn-primary-action {
                padding: 1rem 2rem;
                font-size: 1.1rem;
                width: 100%;
                margin: 0.3rem 0;
            }
            
            .btn-secondary-action {
                padding: 0.875rem 1.8rem;
                font-size: 1rem;
                width: 100%;
                margin: 0.3rem 0;
            }
            
            .info-box {
                padding: 1.2rem;
                margin: 1.5rem 0;
            }
            
            .info-box h4 {
                font-size: 1.1rem;
                margin-bottom: 0.8rem;
            }
            
            .info-box p {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }
            
            .tracking-code {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
                letter-spacing: 1px;
            }
            
            .d-flex.flex-wrap {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .d-flex.flex-wrap .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .confirmation-header {
                padding: 2rem 0;
                margin-bottom: 1.5rem;
            }
            
            .confirmation-header h1 {
                font-size: 1.8rem;
            }
            
            .confirmation-header p {
                font-size: 1rem;
            }
            
            .success-icon {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }
            
            .confirmation-card {
                padding: 1.5rem;
                border-radius: 16px;
            }
            
            .confirmation-card h2 {
                font-size: 1.3rem;
                margin-bottom: 1rem;
            }
            
            .order-details {
                padding: 1rem;
                margin: 1rem 0;
            }
            
            .detail-row {
                padding: 0.5rem 0.6rem;
                font-size: 0.9rem;
                margin-bottom: 0.6rem;
            }
            
            .detail-total {
                padding: 1rem;
                font-size: 1.1rem;
            }
            
            .btn-primary-action {
                padding: 0.875rem 1.5rem;
                font-size: 1rem;
            }
            
            .btn-secondary-action {
                padding: 0.75rem 1.5rem;
                font-size: 0.95rem;
            }
            
            .info-box {
                padding: 1rem;
                margin: 1rem 0;
            }
            
            .info-box h4 {
                font-size: 1rem;
                margin-bottom: 0.6rem;
            }
            
            .info-box p {
                font-size: 0.85rem;
                margin-bottom: 0.3rem;
            }
            
            .tracking-code {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
                letter-spacing: 0.5px;
            }
            
            .footer {
                padding: 2rem 0 !important;
            }
            
            .footer .col-lg-3 {
                text-align: center;
            }
            
            .social-links {
                justify-content: center;
            }
        }
    </style>
</head>
<body class="confirmation-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
        <div class="preloader"></div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Header (mirrors cart.php style) -->
    <div class="cart-header" style="<?= $paymentFailed ? 'background: linear-gradient(135deg, #ff6b6b, #ee5a6f);' : '' ?>">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
                        <div class="mb-3 mb-md-0">
                            <h1 class="h2 mb-0">
                                <?php if ($paymentFailed): ?>
                                    <i class="fas fa-exclamation-triangle me-2"></i>Platba nebyla dokončena
                                <?php else: ?>
                                    <i class="fas fa-check-circle me-2"></i>Objednávka přijata
                                <?php endif; ?>
                            </h1>
                            <p class="mb-0 mt-2">
                                <?php if ($paymentFailed): ?>
                                    Vaše objednávka byla vytvořena, ale platba nebyla dokončena.
                                <?php else: ?>
                                    Děkujeme za vaši objednávku, <?= htmlspecialchars($name) ?>!
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="index.php" class="btn btn-kjd-secondary d-flex align-items-center">
                            <i class="fas fa-home me-2"></i>
                            <span class="d-none d-sm-inline">Zpět na úvod</span>
                            <span class="d-sm-none">Domů</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="confirmation-card">
                    <h2 style="color: var(--kjd-dark-green); margin-bottom: 2rem;">
                        <i class="fas fa-receipt me-2"></i>Detaily objednávky
                    </h2>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <span>Číslo objednávky:</span>
                            <span style="font-weight: 800; color: var(--kjd-earth-green);"><?= htmlspecialchars($orderId) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span>Sledovací kód:</span>
                            <span class="tracking-code"><?= htmlspecialchars($trackingCode) ?></span>
                        </div>
                        
                        <div class="detail-row">
                            <span>Email:</span>
                            <span><?= htmlspecialchars($email) ?></span>
                        </div>
                        
                        <?php if ($walletDeduction > 0): ?>
                        <div class="detail-row" style="background: rgba(202,186,156,0.2); border: 2px solid var(--kjd-beige);">
                            <span><i class="fas fa-wallet me-2"></i>Zůstatek na účtu:</span>
                            <span style="color: var(--kjd-earth-green); font-weight: 700;">-<?= number_format($walletDeduction, 0, ',', ' ') ?> Kč</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-row detail-total">
                            <span><?= $walletDeduction > 0 ? 'K úhradě:' : 'Celková částka:' ?></span>
                            <span><?= number_format($amountToPay, 0, ',', ' ') ?> Kč</span>
                        </div>
                    </div>
                    
                    <?php if ($paymentFailed): ?>
                    <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                        <h4 style="color: #ff6b6b;"><i class="fas fa-exclamation-circle me-2"></i>Platba nebyla dokončena!</h4>
                        <p style="font-weight: 600; color: #856404;">Vaše objednávka čeká na uhrazení. Brzy vám přijde email s pokyny k doplacení.</p>
                        <p><i class="fas fa-envelope text-warning me-2"></i> Zkontrolujte svou emailovou schránku (i spam)</p>
                        <p><i class="fas fa-credit-card text-primary me-2"></i> Email obsahuje odkaz na dokončení platby</p>
                        <p><i class="fas fa-university text-info me-2"></i> Nebo údaje pro bankovní převod</p>
                        <p style="color: #ff6b6b; font-weight: 700;"><i class="fas fa-clock me-2"></i> Prosím dokončete platbu do 7 dnů</p>
                    </div>
                    <?php else: ?>
                    <div class="info-box">
                        <h4><i class="fas fa-envelope me-2"></i>Co se děje dál?</h4>
                        <p><i class="fas fa-check text-success me-2"></i> Potvrzení objednávky jsme vám poslali na email</p>
                        <p><i class="fas fa-credit-card text-primary me-2"></i> Zkontrolujte si platební údaje v emailu</p>
                        <p><i class="fas fa-truck text-warning me-2"></i> Po připsání platby začneme s přípravou objednávky</p>
                        <p><i class="fas fa-shipping-fast text-info me-2"></i> O odeslání vás budeme informovat emailem</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                        <a href="index.php" class="btn btn-primary-action">
                            <i class="fas fa-home me-2"></i>
                            <span class="d-none d-sm-inline">Zpět na hlavní stránku</span>
                            <span class="d-sm-none">Hlavní stránka</span>
                        </a>
                        <a href="track_order.php?code=<?= urlencode($trackingCode) ?>" class="btn btn-secondary-action">
                            <i class="fas fa-search me-2"></i>
                            <span class="d-none d-sm-inline">Sledovat objednávku</span>
                            <span class="d-sm-none">Sledovat</span>
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
        // Smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animace pro kartu
            const card = document.querySelector('.confirmation-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 300);
            
            // Animace pro detaily
            const details = document.querySelectorAll('.detail-row');
            details.forEach((detail, index) => {
                detail.style.opacity = '0';
                detail.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    detail.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    detail.style.opacity = '1';
                    detail.style.transform = 'translateX(0)';
                }, 500 + (index * 100));
            });
        });
    </script>
</body>
</html>
