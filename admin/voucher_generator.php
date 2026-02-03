<?php
require_once 'config.php';
session_start();

// Kontrola, zda je uživatel přihlášený jako admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$messageType = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientType = $_POST['recipient_type'] ?? '';
    // Convert comma to dot for decimal numbers
    $amount = (float)str_replace(',', '.', $_POST['amount'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $orderId = $_POST['order_id'] ?? '';
    $note = trim($_POST['note'] ?? '');
    
    if ($amount <= 0) {
        $message = 'Částka musí být větší než 0.<br><small class="text-muted">Zadejte prosím platnou částku voucheru (můžete použít desetinná čísla, např. 99.50).</small>';
        $messageType = 'error';
    } elseif ($recipientType === 'order' && empty($orderId)) {
        $message = 'Vyberte prosím objednávku.<br><small class="text-muted">Nebo vyberte "Univerzální voucher" pro vytvoření bez emailu.</small>';
        $messageType = 'error';
    } elseif ($recipientType === 'manual' && empty($email)) {
        $message = 'Pro ručně zadaný email musíte vyplnit email adresu.<br><small class="text-muted">Nebo vyberte "Univerzální voucher" pro vytvoření bez emailu.</small>';
        $messageType = 'error';
    } else {
        // Generate voucher code
        $voucherCode = 'KJD-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Get recipient email (optional)
        $recipientEmail = '';
        $recipientName = '';
        
        if ($recipientType === 'universal') {
            $recipientEmail = null;
            $recipientName = 'Univerzální voucher';
        } elseif ($recipientType === 'manual') {
            $recipientEmail = !empty($email) ? $email : null;
            $recipientName = !empty($email) ? 'Zákazník' : 'Univerzální voucher';
        } else {
            // Get email from order
            try {
                $stmt = $conn->prepare("SELECT email, name FROM orders WHERE order_id = ? LIMIT 1");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order) {
                    $recipientEmail = $order['email'];
                    $recipientName = $order['name'];
                } else {
                    $message = 'Objednávka nebyla nalezena.<br><small class="text-muted">Zkuste vybrat jinou objednávku nebo použijte "Univerzální voucher".</small>';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Chyba při hledání objednávky.<br><small class="text-muted">Zkuste to prosím znovu nebo vyberte jinou objednávku.</small>';
                $messageType = 'error';
            }
        }
        
        // Save voucher to database
        try {
            $stmt = $conn->prepare("
                INSERT INTO vouchers (code, amount, recipient_email, order_id, note, created_by, created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active')
            ");
            $stmt->execute([
                $voucherCode,
                $amount,
                $recipientEmail ?: null,
                $recipientType === 'order' ? $orderId : null,
                $note,
                $_SESSION['admin_name'] ?? 'Admin'
            ]);
            
            // Send email only if recipient email is provided
            if (!empty($recipientEmail)) {
                if (sendVoucherEmail($recipientEmail, $recipientName, $voucherCode, $amount, $note)) {
                    $message = "Voucher byl úspěšně vytvořen a odeslán na email: $recipientEmail<br><strong>Kód: $voucherCode</strong><br><small class='text-muted'>Zákazník obdržel email s instrukcemi.</small>";
                    $messageType = 'success';
                } else {
                    $message = "Voucher byl vytvořen, ale email se nepodařilo odeslat.<br><strong>Kód: $voucherCode</strong><br><small class='text-muted'>Můžete ho předat zákazníkovi jiným způsobem.</small>";
                    $messageType = 'warning';
                }
            } else {
                $message = "Univerzální voucher byl úspěšně vytvořen!<br><strong>Kód: $voucherCode</strong><br><small class='text-muted'>Voucher můžete předat zákazníkovi osobně nebo jiným způsobem.</small>";
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Chyba při ukládání voucheru: ' . $e->getMessage() . '<br><small class="text-muted">Zkuste to prosím znovu nebo kontaktujte administrátora.</small>';
            $messageType = 'error';
        }
    }
}

// Get recent orders for dropdown
$recentOrders = [];
try {
    $stmt = $conn->prepare("
        SELECT order_id, email, name, total_price, created_at 
        FROM orders 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error silently
}

function sendVoucherEmail($email, $name, $voucherCode, $amount, $note) {
    $subject = "Dárkový voucher KJD - " . number_format($amount, 2, ',', ' ') . " Kč";
    
    $message = "
    <html>
    <head>
        <style>
            body { 
                font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                color: #102820; 
                margin: 0; 
                padding: 0; 
                background: #f8f9fa;
            }
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #fff; 
                border-radius: 16px; 
                overflow: hidden;
                box-shadow: 0 8px 32px rgba(16,40,32,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #102820, #4c6444); 
                color: #fff; 
                padding: 30px 20px; 
                text-align: center; 
                border-bottom: 3px solid #CABA9C;
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 800; 
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            }
            .header .logo { 
                font-size: 24px; 
                font-weight: 800; 
                margin-bottom: 10px;
            }
            .content { 
                padding: 30px 25px; 
                line-height: 1.6;
            }
            .content h2 { 
                color: #102820; 
                font-size: 24px; 
                font-weight: 700; 
                margin-bottom: 20px;
            }
            .content h3 { 
                color: #4D2D18; 
                font-size: 20px; 
                font-weight: 700; 
                margin: 25px 0 15px 0;
                border-bottom: 2px solid #CABA9C;
                padding-bottom: 8px;
            }
            .voucher-card { 
                background: linear-gradient(135deg, #CABA9C, #f5f0e8); 
                padding: 25px; 
                border-radius: 12px; 
                margin: 20px 0; 
                border: 2px solid #4c6444;
                box-shadow: 0 4px 15px rgba(76,100,68,0.2);
                text-align: center;
            }
            .voucher-code { 
                background: #8A6240; 
                color: #fff; 
                padding: 15px 25px; 
                border-radius: 8px; 
                font-size: 24px; 
                font-weight: 800; 
                letter-spacing: 2px;
                margin: 15px 0;
                display: inline-block;
                box-shadow: 0 4px 12px rgba(138,98,64,0.3);
            }
            .voucher-amount { 
                font-size: 32px; 
                font-weight: 900; 
                color: #102820; 
                margin: 10px 0;
            }
            .steps { 
                background: rgba(202,186,156,0.1); 
                padding: 20px; 
                border-radius: 10px; 
                border-left: 4px solid #8A6240;
                margin: 20px 0;
            }
            .step { 
                margin: 15px 0; 
                padding: 10px 0;
                border-bottom: 1px solid rgba(16,40,32,0.1);
            }
            .step:last-child { 
                border-bottom: none; 
            }
            .step-number { 
                background: #4c6444; 
                color: #fff; 
                width: 30px; 
                height: 30px; 
                border-radius: 50%; 
                display: inline-flex; 
                align-items: center; 
                justify-content: center; 
                font-weight: 700; 
                margin-right: 15px;
            }
            .btn-activate { 
                background: linear-gradient(135deg, #4D2D18, #8A6240); 
                color: #fff; 
                padding: 15px 30px; 
                border-radius: 12px; 
                text-decoration: none; 
                font-weight: 700; 
                font-size: 16px;
                display: inline-block;
                margin: 20px 0;
                box-shadow: 0 4px 15px rgba(77,45,24,0.3);
            }
            .footer { 
                background: linear-gradient(135deg, #4D2D18, #8A6240); 
                color: #fff; 
                padding: 25px 20px; 
                text-align: center; 
                font-size: 14px;
                font-weight: 500;
            }
            .footer p { 
                margin: 5px 0;
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                <h1>Dárkový voucher</h1>
            </div>
            
            <div class='content'>
                <h2>Dobrý den" . ($name !== 'Univerzální voucher' ? ', ' . htmlspecialchars($name) : '') . "!</h2>
                
                <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>" . ($name === 'Univerzální voucher' ? 'Máte k dispozici univerzální dárkový voucher!' : 'Máte pro vás připravený dárkový voucher!') . "</p>
                
                <div class='voucher-card'>
                    <h3 style='margin-top: 0; color: #102820;'>Váš dárkový voucher</h3>
                    <div class='voucher-amount'>" . number_format($amount, 2, ',', ' ') . " Kč</div>
                    <div class='voucher-code'>$voucherCode</div>
                    <p style='color: #4c6444; font-weight: 600; margin: 0;'>Kód pro aktivaci voucheru</p>
                </div>
                
                <h3>Jak aktivovat voucher?</h3>
                
                <div class='steps'>
                    <div class='step'>
                        <span class='step-number'>1</span>
                        <strong>Vytvořte si účet</strong> - Pokud ještě nemáte účet, zaregistrujte se na našem webu
                    </div>
                    <div class='step'>
                        <span class='step-number'>2</span>
                        <strong>Přihlaste se</strong> - Přihlaste se do svého účtu
                    </div>
                    <div class='step'>
                        <span class='step-number'>3</span>
                        <strong>Aktivujte voucher</strong> - V sekci \"Můj účet\" zadejte váš kód voucheru
                    </div>
                    <div class='step'>
                        <span class='step-number'>4</span>
                        <strong>Nakupujte</strong> - Částka se vám přičte na účet a můžete ji použít při platbě
                    </div>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://kubajadesigns.eu/02/login.php' class='btn-activate'>
                        Aktivovat voucher
                    </a>
                </div>
                
                " . (!empty($note) ? "<p style='font-size: 14px; color: #666; font-style: italic;'>Poznámka: " . htmlspecialchars($note) . "</p>" : "") . "
                
                <p style='font-size: 16px; color: #4c6444; font-weight: 600; margin-top: 30px;'>
                    " . ($name === 'Univerzální voucher' ? 'Tento voucher může použít kdokoliv - je ideální jako dárek!' : 'Voucher můžete použít kdykoliv při nákupu na našem webu.') . "
                </p>
                
                <p style='font-size: 16px; color: #102820; font-weight: 600; margin-top: 25px;'>
                    S pozdravem,<br><strong>Tým KJD</strong>
                </p>
            </div>
            
            <div class='footer'>
                <div class='logo' style='font-size: 20px; margin-bottom: 10px;'>KJ<span style='color: #CABA9C;'>D</span></div>
                <p><strong>Kubajadesigns.eu</strong></p>
                <p>Email: info@kubajadesigns.eu</p>
            </div>
        </div>
    </body>
    </html>";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: KJD <info@kubajadesigns.eu>',
        'Reply-To: info@kubajadesigns.eu',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($email, $subject, $message, implode("\r\n", $headers));
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generátor voucherů - KJD Admin</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/owl.theme.default.min.css">
    <link rel="stylesheet" href="css/jquery.fancybox.min.css">
    <link rel="stylesheet" href="fonts/icomoon/style.css">
    <link rel="stylesheet" href="fonts/flaticon/font/flaticon.css">
    <link rel="stylesheet" href="css/aos.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_clean_styles.css">
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        /* Apple SF Pro Font */
        body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        
        /* Cart page background */
        .cart-page { 
            background: #f8f9fa; 
            min-height: 100vh; 
        }
        
        /* Cart header */
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
        
        /* Cart items */
        .cart-item { 
            background: #fff;
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 2px solid var(--kjd-earth-green);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(16,40,32,0.12);
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
        
        /* KJD Buttons */
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
        
        /* Form controls */
        .form-control, .form-select {
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--kjd-dark-green);
            box-shadow: 0 0 0 0.2rem rgba(16, 40, 32, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--kjd-dark-green);
            margin-bottom: 0.5rem;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            font-weight: 600;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }
        
        .recipient-type-card {
            background: rgba(202,186,156,0.1);
            border: 2px solid var(--kjd-beige);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .recipient-type-card:hover {
            border-color: var(--kjd-earth-green);
            background: rgba(202,186,156,0.2);
        }
        
        .recipient-type-card.selected {
            border-color: var(--kjd-earth-green);
            background: rgba(76,100,68,0.1);
        }
        
        /* Preloader */
        .preloader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(248, 249, 250, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .preloader {
            width: 50px;
            height: 50px;
            border: 3px solid var(--kjd-beige);
            border-top: 3px solid var(--kjd-dark-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .preloader-wrapper.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .cart-header {
                padding: 2rem 0;
            }
            
            .cart-header h1 {
                font-size: 2rem;
            }
            
            .cart-header p {
                font-size: 1rem;
            }
            
            .cart-item {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .cart-product-name {
                font-size: 1.1rem;
            }
            
            .btn-kjd-primary, .btn-kjd-secondary {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .cart-header {
                padding: 1.5rem 0;
            }
            
            .cart-header h1 {
                font-size: 1.8rem;
            }
            
            .cart-item {
                padding: 1rem;
            }
            
            .btn-kjd-primary, .btn-kjd-secondary {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="cart-page">
    <?php include '../includes/icons.php'; ?>
    
    <!-- Preloader -->
    <div class="preloader-wrapper">
      <div class="preloader"></div>
    </div>

    <!-- Navigation Menu -->
    <?php include 'admin_sidebar.php'; ?>
    
    <!-- Admin Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-gift me-3"></i>Generátor voucherů</h1>
                    <p>Vytvořte a odešlete dárkové vouchery zákazníkům</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Flash zprávy -->
        <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'warning') ?> alert-dismissible fade show cart-item">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
                <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

                <!-- Form Section -->
                <div class="cart-item">
                    <h3 class="cart-product-name mb-3">
                        <i class="fas fa-gift me-2"></i>Nový voucher
                    </h3>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">Částka voucheru (Kč)</label>
                            <input type="text" class="form-control" name="amount" required 
                                   placeholder="Např. 500.50 nebo 500,50" value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                                   pattern="[0-9]+([,.][0-9]+)?">
                            <small class="text-muted">Můžete zadat desetinné číslo, např. 100.50 nebo 100,50 (použijte tečku nebo čárku pro desetinné číslo)</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">Poznámka (volitelné)</label>
                            <input type="text" class="form-control" name="note" 
                                   placeholder="Např. Děkujeme za nákup" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Způsob vytvoření voucheru</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="recipient-type-card" id="universal-card">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recipient_type" id="universal" value="universal" 
                                           <?= ($_POST['recipient_type'] ?? 'universal') === 'universal' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="universal">
                                        <strong><i class="fas fa-gift me-2"></i>Univerzální voucher</strong>
                                        <div class="mt-2">
                                            <small class="text-muted">Voucher použitelný kýmkoliv - ideální pro dárky</small>
                                            <br><small class="text-success"><i class="fas fa-check me-1"></i>Email není potřeba</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="recipient-type-card" id="manual-card">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recipient_type" id="manual" value="manual" 
                                           <?= ($_POST['recipient_type'] ?? '') === 'manual' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="manual">
                                        <strong><i class="fas fa-envelope me-2"></i>Ručně zadaný email</strong>
                                        <div class="mt-2">
                                            <small class="text-muted">Odešlete voucher na konkrétní email</small>
                                            <br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Email je povinný</small>
                                            <input type="email" class="form-control mt-2" name="email" 
                                                   placeholder="zákazník@email.cz" 
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                   <?= ($_POST['recipient_type'] ?? '') !== 'manual' ? 'disabled' : '' ?>
                                                   style="<?= ($_POST['recipient_type'] ?? 'universal') !== 'manual' ? 'display: none;' : '' ?>">
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="recipient-type-card" id="order-card">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recipient_type" id="order" value="order" 
                                           <?= ($_POST['recipient_type'] ?? '') === 'order' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="order">
                                        <strong><i class="fas fa-shopping-cart me-2"></i>Podle objednávky</strong>
                                        <div class="mt-2">
                                            <small class="text-muted">Email se vezme z objednávky</small>
                                            <br><small class="text-info"><i class="fas fa-info-circle me-1"></i>Automatický email</small>
                                            <select class="form-select mt-2" name="order_id" 
                                                    <?= ($_POST['recipient_type'] ?? '') !== 'order' ? 'disabled' : '' ?>
                                                    style="<?= ($_POST['recipient_type'] ?? 'universal') !== 'order' ? 'display: none;' : '' ?>">
                                                <option value="">Vyberte objednávku...</option>
                                                <?php foreach ($recentOrders as $order): ?>
                                                    <option value="<?= htmlspecialchars($order['order_id']) ?>" 
                                                            <?= ($_POST['order_id'] ?? '') === $order['order_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($order['order_id']) ?> - 
                                                        <?= htmlspecialchars($order['name']) ?> - 
                                                        <?= number_format($order['total_price'], 0, ',', ' ') ?> Kč
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn-kjd-primary">
                        <i class="fas fa-gift me-2"></i>Vytvořit voucher
                    </button>
                        <a href="admin.php" class="btn-kjd-secondary ms-3">
                        <i class="fas fa-arrow-left me-2"></i>Zpět na admin
                    </a>
                </div>
            </form>
        </div>

                <!-- Info Section -->
                <div class="cart-item">
                    <h3 class="cart-product-name mb-4">
                        <i class="fas fa-info-circle me-2"></i>Jak to funguje?
                    </h3>
            <div class="row">
                <div class="col-md-6">
                            <h5 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 20px;">
                                <i class="fas fa-user me-2"></i>Pro zákazníka:
                            </h5>
                    <ol style="color: #333; font-weight: 500; line-height: 1.8;">
                        <li><strong>Univerzální voucher:</strong> Získá kód voucheru (bez emailu)</li>
                        <li><strong>Email voucher:</strong> Obdrží email s kódem voucheru</li>
                        <li>Vytvoří si účet na webu</li>
                        <li>Aktivuje voucher v sekci "Můj účet"</li>
                        <li>Částka se přičte na jeho účet</li>
                        <li>Může ji použít při platbě</li>
                    </ol>
                </div>
                <div class="col-md-6">
                            <h5 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 20px;">
                                <i class="fas fa-cog me-2"></i>Technické detaily:
                            </h5>
                    <ul style="color: #333; font-weight: 500; line-height: 1.8;">
                        <li>Voucher kód: KJD-XXXXXXXX</li>
                        <li>Univerzální vouchery nevyžadují email</li>
                        <li>Email je stylizován podle webu</li>
                        <li>Voucher se ukládá do databáze</li>
                        <li>Integrace s košíkem</li>
                        <li>Historie všech voucherů</li>
                    </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hide preloader when page is loaded
        window.addEventListener('load', function() {
            const preloader = document.querySelector('.preloader-wrapper');
            if (preloader) {
                preloader.classList.add('hidden');
                setTimeout(() => {
                    preloader.style.display = 'none';
                }, 300);
            }
        });
    </script>
    <script>
        // Handle recipient type selection
        document.querySelectorAll('input[name="recipient_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const universalCard = document.getElementById('universal-card');
                const manualCard = document.getElementById('manual-card');
                const orderCard = document.getElementById('order-card');
                const emailInput = document.querySelector('input[name="email"]');
                const orderSelect = document.querySelector('select[name="order_id"]');
                
                // Remove all selected classes
                universalCard.classList.remove('selected');
                manualCard.classList.remove('selected');
                orderCard.classList.remove('selected');
                
                if (this.value === 'universal') {
                    universalCard.classList.add('selected');
                    emailInput.disabled = true;
                    emailInput.value = ''; // Clear email for universal vouchers
                    emailInput.style.display = 'none'; // Hide email field
                    orderSelect.disabled = true;
                    orderSelect.style.display = 'none'; // Hide order select
                } else if (this.value === 'manual') {
                    manualCard.classList.add('selected');
                    emailInput.disabled = false;
                    emailInput.style.display = 'block'; // Show email field
                    orderSelect.disabled = true;
                    orderSelect.style.display = 'none'; // Hide order select
                } else {
                    orderCard.classList.add('selected');
                    emailInput.disabled = true;
                    emailInput.value = ''; // Clear email for order vouchers
                    emailInput.style.display = 'none'; // Hide email field
                    orderSelect.disabled = false;
                    orderSelect.style.display = 'block'; // Show order select
                }
            });
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkedRadio = document.querySelector('input[name="recipient_type"]:checked');
            if (checkedRadio) {
                checkedRadio.dispatchEvent(new Event('change'));
            } else {
                // Default to universal voucher
                const universalRadio = document.getElementById('universal');
                if (universalRadio) {
                    universalRadio.checked = true;
                    universalRadio.dispatchEvent(new Event('change'));
                }
            }
        });
    </script>
</body>
</html>
