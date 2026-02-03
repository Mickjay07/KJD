<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'config.php';

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$success_message = $_SESSION['admin_success'] ?? '';
$error_message = $_SESSION['admin_error'] ?? '';

// Vyčištění flash zpráv
unset($_SESSION['admin_success']);
unset($_SESSION['admin_error']);

// Kontrola a přidání sloupce final_design_path, pokud neexistuje
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'final_design_path'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN final_design_path varchar(500) DEFAULT NULL");
    }
} catch (PDOException $e) {
    error_log("Chyba při kontrole/přidání sloupce final_design_path: " . $e->getMessage());
}

// Zpracování nahrání finálního designu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_final_design') {
    $customOrderId = $_POST['custom_order_id'] ?? null;
    
    if ($customOrderId && isset($_FILES['final_design']) && $_FILES['final_design']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/custom_lightbox/final/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['final_design']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'final_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['final_design']['tmp_name'], $filePath)) {
                $relativePath = 'uploads/custom_lightbox/final/' . $fileName;
                
                try {
                    // Aktualizace custom_lightbox_orders
                    $stmt = $conn->prepare("
                        UPDATE custom_lightbox_orders 
                        SET final_design_path = ?, status = 'pending_approval', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$relativePath, $customOrderId]);
                    
                    // Odeslání emailu zákazníkovi s odkazem na potvrzení pomocí PHPMailer
                    $stmt = $conn->prepare("SELECT * FROM custom_lightbox_orders WHERE id = ?");
                    $stmt->execute([$customOrderId]);
                    $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($customOrder) {
                        try {
                            $confirmUrl = 'https://kubajadesigns.eu/confirm_custom_design.php?order_id=' . $customOrderId . '&token=' . md5($customOrderId . $customOrder['customer_email']);
                            
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = 'mail.gigaserver.cz';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'info@kubajadesigns.eu';
                            $mail->Password = '2007Mickey++';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;
                            $mail->CharSet = 'UTF-8';
                            $mail->isHTML(true);
                            
                            $mail->setFrom('info@kubajadesigns.eu', 'KJD');
                            $mail->addAddress($customOrder['customer_email'], $customOrder['customer_name']);
                            $mail->Subject = "Finální návrh vašeho Custom Lightbox je připraven - KJD";
                            
                            $emailBody = "
                            <html>
                            <head>
                                <style>
                                    body { font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #102820; margin: 0; padding: 0; background: #f8f9fa; }
                                    .email-container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 32px rgba(16,40,32,0.1); }
                                    .header { background: linear-gradient(135deg, #102820, #4c6444); color: #fff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C; }
                                    .header h1 { margin: 0; font-size: 28px; font-weight: 800; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
                                    .header .logo { font-size: 24px; font-weight: 800; margin-bottom: 10px; }
                                    .content { padding: 30px 25px; line-height: 1.6; }
                                    .content h2 { color: #102820; font-size: 24px; font-weight: 700; margin-bottom: 20px; }
                                    .button { display: inline-block; background: linear-gradient(135deg, #4D2D18, #8A6240); color: #fff; padding: 15px 30px; text-decoration: none; border-radius: 12px; margin: 20px 0; font-weight: 700; box-shadow: 0 4px 15px rgba(77,45,24,0.3); }
                                    .button:hover { background: linear-gradient(135deg, #8A6240, #4D2D18); }
                                    .footer { background: linear-gradient(135deg, #4D2D18, #8A6240); color: #fff; padding: 25px 20px; text-align: center; font-size: 14px; font-weight: 500; }
                                </style>
                            </head>
                            <body>
                                <div class='email-container'>
                                    <div class='header'>
                                        <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                                        <h1>Finální návrh je připraven!</h1>
                                    </div>
                                    <div class='content'>
                                        <h2>Dobrý den, " . htmlspecialchars($customOrder['customer_name']) . "!</h2>
                                        <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>Finální návrh vašeho Custom Lightbox je připraven k potvrzení.</p>
                                        <p>Prosím, zkontrolujte návrh a potvrďte ho, nebo požádejte o změny.</p>
                                        <div style='text-align: center;'>
                                            <a href='" . htmlspecialchars($confirmUrl) . "' class='button'>Zobrazit a potvrdit návrh</a>
                                        </div>
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
                            </html>
                            ";
                            
                            $mail->Body = $emailBody;
                            $mail->AltBody = "Dobrý den " . $customOrder['customer_name'] . ",\n\nFinální návrh vašeho Custom Lightbox je připraven k potvrzení.\n\nZobrazit a potvrdit návrh: " . $confirmUrl . "\n\nS pozdravem,\nTým KJD";
                            
                            $mail->send();
                            error_log("Email úspěšně odeslán zákazníkovi: " . $customOrder['customer_email']);
                            
                            // Odeslání emailu adminovi
                            try {
                                $adminMail = new PHPMailer(true);
                                $adminMail->isSMTP();
                                $adminMail->Host = 'mail.gigaserver.cz';
                                $adminMail->SMTPAuth = true;
                                $adminMail->Username = 'info@kubajadesigns.eu';
                                $adminMail->Password = '2007Mickey++';
                                $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $adminMail->Port = 587;
                                $adminMail->CharSet = 'UTF-8';
                                $adminMail->isHTML(true);
                                
                                $adminMail->setFrom('info@kubajadesigns.eu', 'KJD');
                                $adminMail->addAddress('mickeyjarolim3@gmail.com', 'Admin');
                                $adminMail->Subject = "Finální design nahrán - Custom Lightbox #" . $customOrder['id'];
                                
                                $adminEmailBody = "
                                <html>
                                <head>
                                    <style>
                                        body { font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #102820; margin: 0; padding: 0; background: #f8f9fa; }
                                        .email-container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 32px rgba(16,40,32,0.1); }
                                        .header { background: linear-gradient(135deg, #102820, #4c6444); color: #fff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C; }
                                        .header h1 { margin: 0; font-size: 28px; font-weight: 800; }
                                        .content { padding: 30px 25px; }
                                        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4c6444; }
                                        .footer { background: linear-gradient(135deg, #4D2D18, #8A6240); color: #fff; padding: 25px 20px; text-align: center; }
                                    </style>
                                </head>
                                <body>
                                    <div class='email-container'>
                                        <div class='header'>
                                            <h1>Finální design nahrán</h1>
                                        </div>
                                        <div class='content'>
                                            <p>Finální design pro Custom Lightbox objednávku byl nahrán.</p>
                                            <div class='info-box'>
                                                <p><strong>ID objednávky:</strong> " . htmlspecialchars($customOrder['id']) . "</p>
                                                <p><strong>Zákazník:</strong> " . htmlspecialchars($customOrder['customer_name']) . "</p>
                                                <p><strong>Email:</strong> " . htmlspecialchars($customOrder['customer_email']) . "</p>
                                                <p><strong>Status:</strong> pending_approval</p>
                                            </div>
                                            <p>Zákazník byl informován a čeká na potvrzení návrhu.</p>
                                            <p><a href='https://kubajadesigns.eu/admin/admin_custom_lightbox.php?id=" . htmlspecialchars($customOrder['id']) . "' style='color: #4c6444; font-weight: 600;'>Zobrazit objednávku v admin panelu</a></p>
                                        </div>
                                        <div class='footer'>
                                            <p>KJD Admin</p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                                ";
                                
                                $adminMail->Body = $adminEmailBody;
                                $adminMail->AltBody = "Finální design pro Custom Lightbox objednávku #" . $customOrder['id'] . " byl nahrán. Zákazník: " . $customOrder['customer_name'] . " (" . $customOrder['customer_email'] . ")";
                                
                                $adminMail->send();
                                error_log("Email úspěšně odeslán adminovi o nahrání finálního designu");
                            } catch (Exception $e) {
                                error_log("Chyba při odesílání emailu adminovi: " . $e->getMessage());
                            }
                            
                            $_SESSION['admin_success'] = 'Finální design byl úspěšně nahrán a zákazník byl informován.';
                        } catch (Exception $e) {
                            error_log("Chyba při odesílání emailu zákazníkovi: " . $e->getMessage());
                            $_SESSION['admin_error'] = 'Design byl nahrán, ale email se nepodařilo odeslat: ' . $e->getMessage();
                        }
                    }
                    
                    header("Location: admin_custom_lightbox.php?id=" . $customOrderId);
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['admin_error'] = 'Chyba při ukládání designu: ' . $e->getMessage();
                }
            } else {
                $_SESSION['admin_error'] = 'Chyba při nahrávání souboru.';
            }
        } else {
            $_SESSION['admin_error'] = 'Neplatný formát souboru. Povolené formáty: ' . implode(', ', $allowedExtensions);
        }
    }
}

// Získání ID objednávky z URL
$custom_lightbox_id = $_GET['id'] ?? null;

// Načtení seznamu všech custom lightbox objednávek nebo detailu jedné
$custom_lightbox_orders = [];
$current_order = null;

if ($custom_lightbox_id) {
    // Načtení detailu jedné objednávky
    try {
        $stmt = $conn->prepare("SELECT * FROM custom_lightbox_orders WHERE id = ?");
        $stmt->execute([$custom_lightbox_id]);
        $current_order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Chyba při načítání objednávky: " . $e->getMessage();
    }
} else {
    // Načtení seznamu všech objednávek
    try {
        $stmt = $conn->query("SELECT * FROM custom_lightbox_orders ORDER BY created_at DESC");
        $custom_lightbox_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Chyba při načítání objednávek: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Lightbox - KJD Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      body {
        background: #f8f9fa !important;
      }
      
      .cart-item {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 1px solid rgba(202,186,156,0.2);
        padding: 2rem;
        margin-bottom: 1.5rem;
      }
      
      .cart-header {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        padding: 3rem 0;
        margin-bottom: 2rem;
        border-bottom: 3px solid var(--kjd-earth-green);
        box-shadow: 0 4px 20px rgba(16,40,32,0.1);
      }
      
      .cart-header h1 {
        color: var(--kjd-dark-green);
        font-size: 2.5rem;
        font-weight: 800;
      }
      
      .btn-kjd-primary {
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
        color: #fff;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 8px;
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
      
      .kjd-form-control {
        border-radius: 8px;
        border: 2px solid var(--kjd-earth-green);
        padding: 0.75rem;
      }
      
      .kjd-form-control:focus {
        border-color: var(--kjd-gold-brown);
        box-shadow: 0 0 0 0.2rem rgba(138,98,64,0.25);
      }
      
      .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        display: inline-block;
      }
      
      .status-pending_payment { background: #ffc107; color: #000; }
      .status-paid { background: #28a745; color: #fff; }
      .status-pending_approval { background: #17a2b8; color: #fff; }
      .status-confirmed { background: #28a745; color: #fff; }
      .status-changes_requested { background: #ffc107; color: #000; }
      .status-in_production { background: #007bff; color: #fff; }
      .status-completed { background: #28a745; color: #fff; }
      .status-cancelled { background: #dc3545; color: #fff; }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-0">
                                <i class="fas fa-lightbulb me-2"></i>Custom Lightbox
                            </h1>
                            <?php if ($current_order): ?>
                                <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Objednávka #<?= htmlspecialchars($current_order['id']) ?></p>
                            <?php else: ?>
                                <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);"><?= count($custom_lightbox_orders) ?> objednávek</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($current_order): ?>
                            <a href="admin_custom_lightbox.php" class="btn btn-kjd-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Zpět na seznam
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($success_message || $error_message): ?>
            <div class="row">
                <div class="col-12">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($current_order): ?>
            <!-- Detail objednávky -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="cart-item">
                        <h2 class="mb-4" style="color: var(--kjd-dark-green); font-weight: 800;">Informace o objednávce</h2>
                        
                        <div class="mb-3">
                            <strong>Zákazník:</strong> <?= htmlspecialchars($current_order['customer_name']) ?><br>
                            <strong>Email:</strong> <?= htmlspecialchars($current_order['customer_email']) ?><br>
                            <strong>Telefon:</strong> <?= htmlspecialchars($current_order['customer_phone'] ?? '-') ?><br>
                            <strong>Status:</strong> <span class="status-badge status-<?= htmlspecialchars($current_order['status']) ?>"><?= htmlspecialchars($current_order['status']) ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Velikost:</strong> <?= htmlspecialchars($current_order['size']) ?><br>
                            <strong>Stojánek:</strong> <?= $current_order['has_stand'] ? 'Ano' : 'Ne' ?><br>
                            <strong>Cena:</strong> <?= number_format($current_order['total_price'], 0, ',', ' ') ?> Kč
                        </div>
                        
                        <?php if ($current_order['delivery_method']): ?>
                            <div class="mb-3">
                                <strong>Způsob doručení:</strong> <?= htmlspecialchars($current_order['delivery_method']) ?><br>
                                <?php if ($current_order['delivery_address']): ?>
                                    <strong>Adresa:</strong> <?= htmlspecialchars($current_order['delivery_address']) ?><br>
                                <?php endif; ?>
                                <?php if ($current_order['postal_code']): ?>
                                    <strong>PSČ:</strong> <?= htmlspecialchars($current_order['postal_code']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="cart-item" style="background: #fff3cd; border: 2px solid #ffc107;">
                        <h2 class="mb-4" style="color: #856404; font-weight: 800;">
                            <i class="fas fa-lightbulb me-2"></i>Finální návrh
                        </h2>
                        
                        <?php if (!empty($current_order['final_design_path']) && file_exists('../' . $current_order['final_design_path'])): ?>
                            <div class="mb-3">
                                <p style="color: #856404; font-weight: 600; margin-bottom: 0.5rem;"><strong>Aktuální finální design:</strong></p>
                                <img src="../<?= htmlspecialchars($current_order['final_design_path']) ?>" 
                                     alt="Finální design" 
                                     style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 2px solid #ffc107; margin-bottom: 1rem;">
                                <p style="color: #856404; font-size: 0.9rem; margin-bottom: 0.5rem;">
                                    Status: <strong><?= htmlspecialchars($current_order['status'] ?? 'paid') ?></strong>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-3" style="background: rgba(255,193,7,0.2); border: 2px solid #ffc107; color: #856404;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Finální design ještě nebyl nahrán. Nahrajte finální návrh, který bude zákazníkovi zobrazen k potvrzení.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_final_design">
                            <input type="hidden" name="custom_order_id" value="<?= htmlspecialchars($current_order['id']) ?>">
                            
                            <div class="mb-3">
                                <label for="final_design" class="form-label" style="color: #856404; font-weight: 600;">
                                    <?= !empty($current_order['final_design_path']) ? 'Nahradit finální design' : 'Nahrát finální design' ?>
                                </label>
                                <input type="file" 
                                       name="final_design" 
                                       id="final_design" 
                                       accept="image/*,.pdf" 
                                       class="form-control kjd-form-control" 
                                       required>
                                <div class="form-text" style="color: #856404;">
                                    Povolené formáty: JPG, PNG, GIF, PDF. Po nahrání bude zákazníkovi automaticky odeslán email s odkazem na potvrzení.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-kjd-primary">
                                <i class="fas fa-upload me-2"></i>
                                <?= !empty($current_order['final_design_path']) ? 'Nahradit design' : 'Nahrát finální design' ?>
                            </button>
                        </form>
                        
                        <?php if (!empty($current_order['image_path'])): ?>
                            <div class="mt-4 pt-3" style="border-top: 2px solid #ffc107;">
                                <p style="color: #856404; font-weight: 600; margin-bottom: 0.5rem;"><strong>Původní nahraný obrázek zákazníka:</strong></p>
                                <img src="../<?= htmlspecialchars($current_order['image_path']) ?>" 
                                     alt="Původní obrázek" 
                                     style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #ffc107;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="cart-item">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700;">Rychlé akce</h3>
                        <a href="admin_edit_order.php?id=<?= htmlspecialchars($current_order['id']) ?>" class="btn btn-kjd-secondary w-100 mb-2">
                            <i class="fas fa-edit me-2"></i>Upravit objednávku
                        </a>
                        <a href="admin_orders.php" class="btn btn-kjd-secondary w-100">
                            <i class="fas fa-list me-2"></i>Všechny objednávky
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Seznam objednávek -->
            <div class="row">
                <div class="col-12">
                    <div class="cart-item">
                        <h2 class="mb-4" style="color: var(--kjd-dark-green); font-weight: 800;">Seznam Custom Lightbox objednávek</h2>
                        
                        <?php if (empty($custom_lightbox_orders)): ?>
                            <p>Žádné Custom Lightbox objednávky.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Zákazník</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Cena</th>
                                            <th>Datum</th>
                                            <th>Akce</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($custom_lightbox_orders as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['id']) ?></td>
                                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($order['customer_email']) ?></td>
                                                <td><span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                                <td><?= number_format($order['total_price'], 0, ',', ' ') ?> Kč</td>
                                                <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                                <td>
                                                    <a href="admin_custom_lightbox.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-kjd-primary">
                                                        <i class="fas fa-eye"></i> Zobrazit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

