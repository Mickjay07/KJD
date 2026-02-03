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

$orderId = $_GET['order_id'] ?? null;
$token = $_GET['token'] ?? null;
$error = '';
$success = '';

// Načtení objednávky
$order = null;
if ($orderId) {
    try {
        $stmt = $conn->prepare("SELECT * FROM custom_lightbox_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ověření tokenu
        if ($order && $token !== md5($orderId . $order['customer_email'])) {
            $error = 'Neplatný odkaz pro potvrzení.';
            $order = null;
        }
    } catch (PDOException $e) {
        $error = 'Chyba při načítání objednávky: ' . $e->getMessage();
    }
}

// Zpracování potvrzení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'confirm') {
        // Potvrzení návrhu
        try {
            $stmt = $conn->prepare("
                UPDATE custom_lightbox_orders 
                SET status = 'confirmed', confirmed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            $success = 'Návrh byl potvrzen! Zahájíme výrobu a budeme vás informovat o průběhu.';
            
            // Odeslání potvrzovacího emailu
            sendConfirmationEmail($order);
            
            // Odeslání emailu adminovi
            sendAdminConfirmationNotification($order);
            
            // Aktualizace objednávky
            $order['status'] = 'confirmed';
            
        } catch (PDOException $e) {
            $error = 'Chyba při potvrzování: ' . $e->getMessage();
        }
    } elseif ($action === 'request_changes') {
        // Požadavek na změny
        $changeRequest = trim($_POST['change_request'] ?? '');
        
        if (empty($changeRequest)) {
            $error = 'Prosím, popište požadované změny.';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE custom_lightbox_orders 
                    SET status = 'changes_requested', change_request = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$changeRequest, $orderId]);
                
                $success = 'Váš požadavek na změny byl odeslán. Brzy vás budeme kontaktovat.';
                
                // Odeslání emailu adminovi
                sendChangeRequestEmail($order, $changeRequest);
                
                $order['status'] = 'changes_requested';
                
            } catch (PDOException $e) {
                $error = 'Chyba při odesílání požadavku: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení návrhu - Custom Lightbox - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        body {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 2rem 0;
        }
        
        .confirm-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .confirm-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(16,40,32,0.15);
            border: 3px solid var(--kjd-earth-green);
            overflow: hidden;
        }
        
        .confirm-header {
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        
        .confirm-body {
            padding: 2.5rem;
        }
        
        .design-preview {
            text-align: center;
            margin: 2rem 0;
        }
        
        .design-preview img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            border: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 15px rgba(16,40,32,0.2);
        }
        
        .order-details {
            background: var(--kjd-beige);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            border: 2px solid var(--kjd-earth-green);
        }
        
        .order-details h4 {
            color: var(--kjd-dark-green);
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: var(--kjd-dark-brown);
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76,100,68,0.4);
            color: #fff;
        }
        
        .btn-request-changes {
            background: transparent;
            color: var(--kjd-earth-green);
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-request-changes:hover {
            background: var(--kjd-earth-green);
            color: #fff;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 600;
        }
        
        .changes-form {
            display: none;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container confirm-container">
        <div class="confirm-card">
            <div class="confirm-header">
                <h1><i class="fas fa-check-circle me-2"></i>Potvrzení návrhu</h1>
                <p class="mb-0">Zkontrolujte a potvrďte svůj návrh</p>
            </div>
            
            <div class="confirm-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($order): ?>
                    <?php if ($order['status'] === 'confirmed'): ?>
                        <div class="alert alert-success">
                            <h4><i class="fas fa-check-circle me-2"></i>Návrh potvrzen!</h4>
                            <p>Vaše objednávka byla potvrzena a zahájíme výrobu. Budeme vás informovat o průběhu.</p>
                        </div>
                    <?php elseif ($order['status'] === 'changes_requested'): ?>
                        <div class="alert alert-info">
                            <h4><i class="fas fa-info-circle me-2"></i>Požadavek na změny odeslán</h4>
                            <p>Váš požadavek na změny byl odeslán. Brzy vás budeme kontaktovat.</p>
                        </div>
                    <?php else: ?>
                        <?php if ($order['status'] !== 'confirmed'): ?>
                        <?php if (!empty($order['final_design_path']) && file_exists($order['final_design_path'])): ?>
                            <div class="design-preview">
                                <h3 style="color: var(--kjd-dark-green); margin-bottom: 1rem;">Finální návrh vašeho Custom Lightbox</h3>
                                <img src="<?= htmlspecialchars($order['final_design_path']) ?>" alt="Finální návrh">
                            </div>
                            
                            <div class="alert alert-info" style="background: #e7f3ff; border: 2px solid #17a2b8; color: #0c5460;">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Zkontrolujte prosím návrh.</strong> Pokud vám vyhovuje, klikněte na "Potvrdit návrh". Pokud potřebujete změny, můžete požádat o úpravy.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning" style="background: #fff3cd; border: 2px solid #ffc107; color: #856404;">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Návrh se připravuje</strong>
                                <p style="margin-top: 0.5rem; margin-bottom: 0;">Finální návrh vašeho Custom Lightbox ještě není připraven. Jakmile bude hotový, obdržíte email s odkazem na potvrzení.</p>
                            </div>
                            
                            <div class="design-preview">
                                <h4 style="color: var(--kjd-dark-green); margin-bottom: 1rem;">Váš nahraný obrázek:</h4>
                                <?php if (file_exists($order['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($order['image_path']) ?>" alt="Váš obrázek">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="order-details">
                            <h4><i class="fas fa-info-circle me-2"></i>Detaily objednávky</h4>
                            <div class="detail-item">
                                <span>Zákazník:</span>
                                <span><strong><?= htmlspecialchars($order['customer_name']) ?></strong></span>
                            </div>
                            <div class="detail-item">
                                <span>Email:</span>
                                <span><?= htmlspecialchars($order['customer_email']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span>Velikost:</span>
                                <span>
                                    <?php
                                    $sizes = ['small' => 'Malé (15x15 cm)', 'medium' => 'Střední (20x20 cm)', 'large' => 'Velké (25x25 cm)'];
                                    echo $sizes[$order['size']] ?? $order['size'];
                                    ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span>Podstavec:</span>
                                <span><?= $order['has_stand'] ? 'Ano (+200 Kč)' : 'Ne' ?></span>
                            </div>
                            <div class="detail-item">
                                <span>Cena:</span>
                                <span><strong><?= number_format($order['total_price'], 0, ',', ' ') ?> Kč</strong></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['final_design_path']) && file_exists($order['final_design_path']) && $order['status'] === 'pending_approval'): ?>
                        <form method="POST" id="confirmForm">
                            <input type="hidden" name="action" value="confirm" id="actionInput">
                            
                            <button type="submit" class="btn-confirm" onclick="document.getElementById('actionInput').value='confirm'; return true;">
                                <i class="fas fa-check me-2"></i>Potvrdit návrh a zahájit výrobu
                            </button>
                        </form>
                        
                        <button type="button" class="btn-request-changes" onclick="toggleChangesForm()">
                            <i class="fas fa-edit me-2"></i>Požádat o změny
                        </button>
                        
                        <div class="changes-form" id="changesForm">
                            <form method="POST">
                                <input type="hidden" name="action" value="request_changes">
                                <div class="mb-3">
                                    <label for="change_request" class="form-label">Popište požadované změny:</label>
                                    <textarea class="form-control" id="change_request" name="change_request" rows="5" required style="border: 2px solid var(--kjd-beige); border-radius: 12px;"></textarea>
                                </div>
                                <button type="submit" class="btn-request-changes">
                                    <i class="fas fa-paper-plane me-2"></i>Odeslat požadavek
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">
                        Objednávka nebyla nalezena nebo je odkaz neplatný.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleChangesForm() {
            const form = document.getElementById('changesForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>

<?php
function sendConfirmationEmail($order) {
    $to = $order['customer_email'];
    $subject = "Potvrzení návrhu - Custom Lightbox - KJD";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 2px solid #4c6444; }
            .footer { background: #f5f0e8; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Návrh potvrzen!</h1>
            </div>
            <div class='content'>
                <p>Dobrý den <strong>" . htmlspecialchars($order['customer_name']) . "</strong>,</p>
                
                <p>Vaše objednávka Custom Lightbox byla potvrzena a zahájili jsme výrobu.</p>
                
                <p>Budeme vás informovat o průběhu výroby a dodání.</p>
                
                <p>S pozdravem,<br>
                <strong>Tým KJD</strong></p>
            </div>
            <div class='footer'>
                <p>KubaJaDesigns.eu</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: info@kubajadesigns.eu" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}

function sendChangeRequestEmail($order, $changeRequest) {
    $to = 'mickeyjarolim3@gmail.com'; // Admin email
    $subject = "Požadavek na změny - Custom Lightbox objednávka #" . $order['id'];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 20px; text-align: center; }
            .content { background: #fff; padding: 30px; border: 2px solid #4c6444; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Požadavek na změny</h1>
            </div>
            <div class='content'>
                <p><strong>Objednávka ID:</strong> " . $order['id'] . "</p>
                <p><strong>Zákazník:</strong> " . htmlspecialchars($order['customer_name']) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($order['customer_email']) . "</p>
                
                <h3>Požadované změny:</h3>
                <p>" . nl2br(htmlspecialchars($changeRequest)) . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: info@kubajadesigns.eu" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}

function sendAdminConfirmationNotification($order) {
    $to = 'mickeyjarolim3@gmail.com';
    $subject = "Návrh potvrzen - Custom Lightbox objednávka #" . $order['id'];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 20px; text-align: center; }
            .content { background: #fff; padding: 30px; border: 2px solid #4c6444; }
            .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Návrh potvrzen zákazníkem</h1>
            </div>
            <div class='content'>
                <p>Zákazník potvrdil finální návrh Custom Lightbox objednávky.</p>
                <div class='info-box'>
                    <p><strong>ID objednávky:</strong> " . $order['id'] . "</p>
                    <p><strong>Zákazník:</strong> " . htmlspecialchars($order['customer_name']) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($order['customer_email']) . "</p>
                    <p><strong>Status:</strong> confirmed</p>
                </div>
                <p>Můžete zahájit výrobu.</p>
                <p><a href='https://kubajadesigns.eu/admin/admin_custom_lightbox.php?id=" . $order['id'] . "' style='color: #4c6444; font-weight: 600;'>Zobrazit objednávku v admin panelu</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: info@kubajadesigns.eu" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>

