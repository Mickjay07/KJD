<?php
session_start();
header('Content-Type: application/json');

// DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";

try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES utf8");
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Chyba databáze']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Neplatný email']);
    exit;
}

try {
    // Create table if not exists (with correct structure matching your database)
    $createTable = "CREATE TABLE IF NOT EXISTS newsletter (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($createTable);
    
    // Check if email already exists in newsletter table
    $stmt = $conn->prepare("SELECT id FROM newsletter WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email je již registrován']);
        exit;
    }
    
    // Generate unique discount code
    $discountCode = 'NEWS' . strtoupper(substr(md5($email . time()), 0, 6));
    
    // Check if code is unique
    $stmt = $conn->prepare("SELECT id FROM discount_codes WHERE code = ?");
    $stmt->execute([$discountCode]);
    while ($stmt->fetch()) {
        $discountCode = 'NEWS' . strtoupper(substr(md5($email . time() . rand()), 0, 6));
        $stmt->execute([$discountCode]);
    }
    
    // Insert newsletter subscriber into newsletter table
    $stmt = $conn->prepare("INSERT INTO newsletter (email, created_at) VALUES (?, NOW())");
    $stmt->execute([$email]);
    
    error_log("Newsletter subscriber inserted: $email into newsletter table");
    
    // Insert discount code
    $validFrom = date('Y-m-d');
    $validTo = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("
        INSERT INTO discount_codes (code, discount_percent, valid_from, valid_to, usage_limit, times_used, active) 
        VALUES (?, 10, ?, ?, 1, 0, 1)
    ");
    $stmt->execute([$discountCode, $validFrom, $validTo]);
    
    error_log("Discount code inserted: $discountCode valid until $validTo");
    
    // Send email with KJD branded styling (consistent with voucher email)
    $subject = "Vítejte v KJD Newsletteru – 10% sleva!";
    $validToFormatted = date('d.m.Y', strtotime($validTo));
    
    $message = "
    <html>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
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
            .highlight-card { 
                background: linear-gradient(135deg, #CABA9C, #f5f0e8); 
                padding: 25px; 
                border-radius: 12px; 
                margin: 20px 0; 
                border: 2px solid #4c6444;
                box-shadow: 0 4px 15px rgba(76,100,68,0.2);
                text-align: center;
            }
            .discount-code { 
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
            .btn-primary { 
                background: linear-gradient(135deg, #4D2D18, #8A6240); 
                color: #fff !important; 
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
            .footer p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                <h1>Vítejte v KJD Newsletteru</h1>
            </div>
            <div class='content'>
                <h2>Děkujeme za registraci!</h2>
                <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>Jako poděkování máte od nás 10% slevu.</p>
                <div class='highlight-card'>
                    <div style='color:#102820; font-weight:700;'>Váš slevový kód</div>
                    <div class='discount-code'>{$discountCode}</div>
                    <div style='color:#4c6444; font-weight:600;'>Platnost do: {$validToFormatted}</div>
                </div>
                <div style='text-align: center;'>
                    <a href='https://kubajadesigns.eu' class='btn-primary'>Navštívit web</a>
                </div>
                <p style='font-size: 16px; color: #102820; font-weight: 600; margin-top: 25px;'>S pozdravem,<br><strong>Tým KJD</strong></p>
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
    
    $emailSent = false;
    // Prefer PHPMailer SMTP if available (loaded via config.php)
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = 'mail.gigaserver.cz';
            $mailer->SMTPAuth = true;
            $mailer->Username = 'info@kubajadesigns.eu';
            $mailer->Password = '2007Mickey++';
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = 587;
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom('info@kubajadesigns.eu', 'KJD');
            $mailer->Sender = 'bounce@kubajadesigns.eu'; // ensure mailbox/alias exists
            $mailer->addAddress($email);

            // List-Unsubscribe headers (with One-Click)
            $unsubUrl = 'https://kubajadesigns.eu/ajax/unsubscribe_one_click.php?email=' . urlencode($email);
            $mailer->addCustomHeader('List-Unsubscribe', '<mailto:info@kubajadesigns.eu?subject=unsubscribe>, <' . $unsubUrl . '>');
            $mailer->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            $mailer->addCustomHeader('List-Id', 'KJD Newsletter <newsletter.kubajadesigns.eu>');
            $mailer->addCustomHeader('List-Help', '<https://kubajadesigns.eu/unsubscribe.php>');
            $mailer->addCustomHeader('Feedback-ID', 'kjd-newsletter:kubajadesigns.eu');

            $mailer->Subject = $subject;
            $mailer->isHTML(true);
            $mailer->Body = $message;
            $mailer->AltBody = 'Děkujeme za registraci do KJD Newsletteru. Váš slevový kód: ' . $discountCode . "\nPlatnost do: " . $validToFormatted . "\n\nNavštívit web: https://kubajadesigns.eu\n\nOdhlášení: https://kubajadesigns.eu/unsubscribe.php?email=" . urlencode($email);

            // Optional DKIM – pokud hosting podepisuje, není nutné
            // if (file_exists(__DIR__ . '/../admin/dkim/private.key')) {
            //     $mailer->DKIM_domain = 'kubajadesigns.eu';
            //     $mailer->DKIM_selector = 'mailgs'; // dle DNS selectoru
            //     $mailer->DKIM_private = __DIR__ . '/../admin/dkim/private.key';
            //     $mailer->DKIM_identity = 'info@kubajadesigns.eu';
            // }

            $emailSent = $mailer->send();
        } catch (\Exception $ex) {
            error_log('Signup email send via SMTP failed: ' . $ex->getMessage());
            $emailSent = false;
        }
    }
    if (!$emailSent) {
        // Fallback to mail()
        $headersArray = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: KJD <info@kubajadesigns.eu>',
            'Reply-To: info@kubajadesigns.eu',
            'List-Unsubscribe: <mailto:info@kubajadesigns.eu?subject=unsubscribe>, <https://kubajadesigns.eu/ajax/unsubscribe_one_click.php?email=' . urlencode($email) . '>',
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            'List-Id: KJD Newsletter <newsletter.kubajadesigns.eu>',
            'List-Help: <https://kubajadesigns.eu/unsubscribe.php>',
            'X-Mailer: PHP/' . phpversion()
        ];
        $emailSent = mail($email, $subject, $message, implode("\r\n", $headersArray));
    }
    // Log email sending result
    error_log("Newsletter email sent to {$email}: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
    
    echo json_encode([
        'success' => true, 
        'message' => 'Úspěšně jste se zaregistrovali! Slevový kód byl odeslán na váš email.',
        'discount_code' => $discountCode
    ]);
    
} catch (PDOException $e) {
    error_log("Newsletter signup error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Chyba při registraci: ' . $e->getMessage()]);
}
?>
