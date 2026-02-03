<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_GET['preview'])) {
        die('Access denied');
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get POST data
$recipient = $_POST['recipient_email'] ?? '';
$subject = $_POST['subject'] ?? '';
$content = $_POST['email_content'] ?? '';
$isPreview = isset($_GET['preview']);

if (!$isPreview && (empty($recipient) || empty($subject) || empty($content))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Construct the HTML email
$htmlContent = "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { 
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            color: #102820; 
            margin: 0; 
            padding: 0; 
            background-color: #f8f9fa;
            -webkit-font-smoothing: antialiased;
        }
        .email-wrapper {
            background-color: #f8f9fa;
            padding: 20px 0;
            width: 100%;
        }
        .email-container { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff; 
            border-radius: 12px; 
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .header { 
            background: linear-gradient(135deg, #102820, #2c4c3b); 
            color: #ffffff; 
            padding: 30px 20px; 
            text-align: center; 
        }
        .header .logo { 
            font-size: 32px; 
            font-weight: 800; 
            margin-bottom: 0;
            letter-spacing: -1px;
        }
        .content { 
            padding: 40px 30px; 
            line-height: 1.6;
            font-size: 16px;
            color: #333;
        }
        .content h1, .content h2, .content h3 {
            color: #102820;
            margin-top: 0;
        }
        .footer { 
            background-color: #f1f1f1; 
            color: #666; 
            padding: 25px 20px; 
            text-align: center; 
            font-size: 13px;
            border-top: 1px solid #e0e0e0;
        }
        .footer p { 
            margin: 5px 0;
        }
        .social-links { 
            margin: 15px 0;
        }
        .social-links a { 
            color: #8A6240; 
            text-decoration: none; 
            margin: 0 10px;
            font-weight: 600;
        }
        a { color: #8A6240; text-decoration: underline; }
        blockquote {
            border-left: 4px solid #8A6240;
            margin: 0;
            padding-left: 15px;
            color: #555;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class='email-wrapper'>
        <div class='email-container'>
            <div class='header'>
                <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
            </div>
            
            <div class='content'>
                $content
            </div>
            
            <div class='footer'>
                <p><strong>KubaJa Designs</strong></p>
                <p>Mezilesí 2078, 193 00 Praha 20</p>
                <p>Email: info@kubajadesigns.eu | Tel: +420 722 341 256</p>
                <div class='social-links'>
                    <a href='https://www.instagram.com/kubajadesigns/'>Instagram</a> | 
                    <a href='https://www.facebook.com/kubajadesigns/'>Facebook</a>
                </div>
                <p style='font-size: 11px; color: #999; margin-top: 15px;'>
                    Tento e-mail byl odeslán z webu kubajadesigns.eu
                </p>
            </div>
        </div>
    </div>
</body>
</html>
";

// If preview, just output the HTML
if ($isPreview) {
    echo $htmlContent;
    exit;
}

// Send email
$headers = [
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: KubaJa Designs <info@kubajadesigns.eu>',
    'Reply-To: info@kubajadesigns.eu',
    'X-Mailer: PHP/' . phpversion()
];

if (mail($recipient, $subject, $htmlContent, implode("\r\n", $headers))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to send email via mail() function']);
}
