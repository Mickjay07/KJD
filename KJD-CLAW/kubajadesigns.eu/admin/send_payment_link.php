<?php
// Disable error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Set JSON header first
header('Content-Type: application/json');

// Suppress all errors and warnings
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

try {
    require_once 'config.php';
    require_once '../functions.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Chyba při načítání konfigurace: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Chyba při načítání konfigurace: ' . $e->getMessage()]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Neautorizovaný přístup']);
    exit;
}

// Check if database connection is available
if (!isset($conn) || $conn === null) {
    echo json_encode(['success' => false, 'message' => 'Databázové připojení není dostupné']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id']) || !isset($input['revolut_link']) || $input['action'] !== 'send_payment_link') {
    echo json_encode(['success' => false, 'message' => 'Neplatné parametry']);
    exit;
}

$order_id = intval($input['order_id']);
$revolut_link = trim($input['revolut_link']);

if (empty($revolut_link)) {
    echo json_encode(['success' => false, 'message' => 'Revolut odkaz je povinný']);
    exit;
}

try {
    // Get order details
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Objednávka nebyla nalezena']);
        exit;
    }
    
    // Update order with Revolut link
    $stmt = $conn->prepare("UPDATE orders SET revolut_payment_link = ? WHERE id = ?");
    $stmt->execute([$revolut_link, $order_id]);
    
    // Send payment email
    $email_sent = sendPaymentEmail($order, $revolut_link);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Platební odkaz byl uložen' . ($email_sent ? ' a email byl odeslán' : ' (email se nepodařilo odeslat)')
    ]);
    
} catch (Exception $e) {
    error_log("Error sending payment link: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při odesílání platebního odkazu']);
}

function sendPaymentEmail($order, $revolut_link) {
    try {
        require_once '../../vendor/phpmailer/phpmailer/src/Exception.php';
        require_once '../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once '../../vendor/phpmailer/phpmailer/src/SMTP.php';
        
        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\SMTP;
        use PHPMailer\PHPMailer\Exception;
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kubajadesigns@gmail.com';
        $mail->Password = 'your_app_password'; // Replace with actual app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom('kubajadesigns@gmail.com', 'KJD Designs');
        $mail->addAddress($order['email'], $order['name'] ?? $order['first_name'] . ' ' . $order['last_name']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Platební odkaz pro objednávku č. ' . $order['id'] . ' - KJD Designs';
        
        // Get KJD styled email template
        $email_body = getPaymentEmailTemplate($order, $revolut_link);
        $mail->Body = $email_body;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

function getPaymentEmailTemplate($order, $revolut_link) {
    $customer_name = $order['name'] ?? $order['first_name'] . ' ' . $order['last_name'];
    $order_total = number_format($order['total_price'], 0, ',', ' ');
    
    return '
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Platební odkaz - KJD Designs</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f8f9fa;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #2c5530, #4a7c59);
                color: white;
                padding: 2rem;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 1.8rem;
                font-weight: 700;
            }
            .content {
                padding: 2rem;
            }
            .payment-alert {
                background: linear-gradient(135deg, #d4af37, #f4e4bc);
                color: #2c5530;
                padding: 1.5rem;
                border-radius: 10px;
                margin-bottom: 2rem;
                text-align: center;
            }
            .payment-alert h2 {
                margin: 0 0 0.5rem 0;
                font-size: 1.3rem;
            }
            .payment-button {
                display: inline-block;
                background: linear-gradient(135deg, #2c5530, #4a7c59);
                color: white;
                padding: 1rem 2rem;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                font-size: 1.1rem;
                margin: 1rem 0;
                transition: transform 0.2s;
            }
            .payment-button:hover {
                transform: translateY(-2px);
                color: white;
                text-decoration: none;
            }
            .order-details {
                background: #f8f9fa;
                padding: 1.5rem;
                border-radius: 10px;
                margin: 1.5rem 0;
            }
            .order-details h3 {
                color: #2c5530;
                margin-top: 0;
                font-size: 1.2rem;
            }
            .detail-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                padding: 0.5rem 0;
                border-bottom: 1px solid #e9ecef;
            }
            .detail-row:last-child {
                border-bottom: none;
                font-weight: bold;
                font-size: 1.1rem;
                color: #2c5530;
            }
            .footer {
                background: #2c5530;
                color: white;
                padding: 1.5rem;
                text-align: center;
            }
            .footer p {
                margin: 0;
                font-size: 0.9rem;
            }
            .info-box {
                background: linear-gradient(135deg, #e3f2fd, #bbdefb);
                color: #1565c0;
                padding: 1rem;
                border-radius: 10px;
                margin: 1.5rem 0;
                border-left: 4px solid #2196f3;
            }
            .info-box h4 {
                margin: 0 0 0.5rem 0;
                font-size: 1.1rem;
            }
            .info-box p {
                margin: 0;
                font-size: 0.95rem;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>KJD Designs</h1>
                <p>Ruční výroba lamp a designových předmětů</p>
            </div>
            
            <div class="content">
                <div class="payment-alert">
                    <h2>💳 Platební odkaz je připraven</h2>
                    <p>Vaše objednávka je připravena k zaplacení přes Revolut.</p>
                </div>
                
                <p>Vážený/á <strong>' . htmlspecialchars($customer_name) . '</strong>,</p>
                
                <p>děkujeme za vaši objednávku! Připravili jsme pro vás platební odkaz pro rychlé a bezpečné zaplacení.</p>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <a href="' . htmlspecialchars($revolut_link) . '" class="payment-button">
                        💳 ZAPLATIT OBJEDNÁVKU
                    </a>
                </div>
                
                <div class="order-details">
                    <h3>📋 Detaily objednávky</h3>
                    <div class="detail-row">
                        <span>Číslo objednávky:</span>
                        <span>' . $order['id'] . '</span>
                    </div>
                    <div class="detail-row">
                        <span>Datum objednávky:</span>
                        <span>' . date('d.m.Y H:i', strtotime($order['created_at'])) . '</span>
                    </div>
                    <div class="detail-row">
                        <span>Celková cena:</span>
                        <span>' . $order_total . ' Kč</span>
                    </div>
                </div>
                
                <div class="info-box">
                    <h4>📄 Co se stane po zaplacení?</h4>
                    <p>Po úspěšném zaplacení vám automaticky zašleme:</p>
                    <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                        <li>Potvrzení o platbě</li>
                        <li>Fakturu v PDF formátu</li>
                        <li>Informace o dalším postupu</li>
                    </ul>
                </div>
                
                <div class="info-box">
                    <h4>⏰ Důležité upozornění</h4>
                    <p>Platební odkaz je platný 7 dní. Po uplynutí této doby bude nutné objednávku znovu potvrdit.</p>
                </div>
                
                <p>Pokud máte jakékoliv otázky nebo potřebujete pomoc s platbou, neváhejte nás kontaktovat.</p>
                
                <p>S pozdravem,<br>
                <strong>Tým KJD Designs</strong></p>
            </div>
            
            <div class="footer">
                <p>© 2024 KJD Designs. Všechna práva vyhrazena.</p>
                <p>Email: kubajadesigns@gmail.com | Web: kubajadesigns.eu</p>
            </div>
        </div>
    </body>
    </html>';
}
?>
