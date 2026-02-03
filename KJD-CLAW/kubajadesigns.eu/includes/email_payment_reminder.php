<?php
/**
 * Send payment reminder email for canceled/unpaid GoPay orders
 */
function sendPaymentReminderEmail($orderId, $orderEmail, $orderName, $totalPrice, $gopayPaymentId = null) {
    $to = $orderEmail;
    $subject = "⚠️ Platba nebyla dokončena - Objednávka #$orderId - KJD";
    
    // Create new payment link if gopay_payment_id exists
    $newPaymentLink = '';
    if ($gopayPaymentId) {
        $newPaymentLink = "https://gate.gopay.cz/gw/v3/3100000099/#{$gopayPaymentId}";
    }
    
    // HTML email body
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; color: #102820; margin: 0; padding: 0; background: #f8f9fa; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px; line-height: 1.6; }
            .warning-badge { 
                background: linear-gradient(135deg, #ffa500, #ff8c00); 
                color: #fff; 
                padding: 20px; 
                border-radius: 12px; 
                text-align: center; 
                font-size: 18px; 
                font-weight: bold; 
                margin: 20px 0;
                box-shadow: 0 4px 15px rgba(255,165,0,0.3);
            }
            .info-box { 
                background: #fff3cd; 
                padding: 20px; 
                border-left: 4px solid #ffc107; 
                margin: 20px 0; 
                border-radius: 8px;
            }
            .info-box p { margin: 8px 0; }
            .payment-options { 
                background: #f5f0e8; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0;
            }
            .btn { 
                display: inline-block; 
                background: linear-gradient(135deg, #4c6444, #102820); 
                color: #fff; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                font-weight: bold; 
                margin: 10px 5px;
            }
            .footer { 
                background: #4D2D18; 
                color: #fff; 
                padding: 20px; 
                text-align: center; 
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>⚠️ Platba nebyla dokončena</h1>
            </div>
            
            <div class='content'>
                <div class='warning-badge'>
                    Vaše platba nebyla dokončena
                </div>
                
                <h2>Dobrý den, " . htmlspecialchars($orderName) . "!</h2>
                
                <p>Obdrželi jsme vaši objednávku, ale <strong>platba nebyla dokončena</strong>.</p>
                
                <div class='info-box'>
                    <p><strong>Číslo objednávky:</strong> {$orderId}</p>
                    <p><strong>Celková částka:</strong> " . number_format($totalPrice, 0, ',', ' ') . " Kč</p>
                    <p><strong>Status platby:</strong> <span style='color: #ff8c00;'>⚠️ NEUHRAZENO</span></p>
                </div>
                
                <h3 style='color: #4D2D18;'>💳 Možnosti doplacení:</h3>
                
                <div class='payment-options'>
                    <p><strong>1. Online platba kartou (GoPay):</strong></p>
                    <p>Klikněte na tlačítko níže a dokončete platbu online.</p>
                    <a href='{$newPaymentLink}' class='btn'>💳 Zaplatit online</a>
                    
                    <hr style='margin: 20px 0; border: 0; border-top: 1px solid #ddd;'>
                    
                    <p><strong>2. Bankovní převod:</strong></p>
                    <p>Číslo účtu: <strong>296614297/0300</strong></p>
                    <p>Variabilní symbol: <strong>{$orderId}</strong></p>
                    <p>Částka: <strong>" . number_format($totalPrice, 0, ',', ' ') . " Kč</strong></p>
                </div>
                
                <p style='color: #ff6b6b; font-weight: 600;'>⏰ Prosím dokončete platbu do 7 dnů, jinak bude objednávka automaticky zrušena.</p>
                
                <p style='margin-top: 30px;'>Máte-li jakékoliv dotazy, neváhejte nás kontaktovat.</p>
                
                <p style='font-weight: 600;'>Děkujeme za pochopení!</p>
                <p><strong>Tým KJD Designs</strong></p>
            </div>
            
            <div class='footer'>
                <p style='margin: 5px 0;'><strong>Kubajadesigns.eu</strong></p>
                <p style='margin: 5px 0;'>Email: info@kubajadesigns.eu</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: KJD Designs <info@kubajadesigns.eu>',
        'Reply-To: info@kubajadesigns.eu'
    ];
    
    $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    
    if ($result) {
        error_log("Payment reminder email sent for order $orderId to $to");
    } else {
        error_log("Failed to send payment reminder email for order $orderId");
    }
    
    return $result;
}
?>
