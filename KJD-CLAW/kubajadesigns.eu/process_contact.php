<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $gdpr = isset($_POST['gdpr']);
    
    if (empty($name) || empty($email) || empty($message)) {
        $response['message'] = 'Prosím vyplňte všechna povinná pole.';
        echo json_encode($response);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Zadejte platnou e-mailovou adresu.';
        echo json_encode($response);
        exit;
    }
    
    if (!$gdpr) {
        $response['message'] = 'Musíte souhlasit se zpracováním osobních údajů.';
        echo json_encode($response);
        exit;
    }
    
    // Send email to customer
    $customerSubject = 'Potvrzení přijetí zprávy - KJD';
    $customerMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <meta name=\"color-scheme\" content=\"light dark\">
        <meta name=\"supported-color-schemes\" content=\"light dark\">
    </head>
    <body style=\"margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;\">
        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f8f9fa;\">
            <tr>
                <td align=\"center\" style=\"padding: 20px 10px;\">
                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;\">
                        <tr>
                            <td style=\"background: linear-gradient(135deg, #102820, #4c6444); background-color: #102820; color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C;\">
                                <div style=\"font-size: 24px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Děkujeme za zprávu</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                <h2 style=\"margin: 0 0 20px 0; color: #102820; font-size: 24px; font-weight: 700;\">Dobrý den " . htmlspecialchars($name) . ",</h2>
                                <p style=\"margin: 0 0 20px 0; font-size: 16px; color: #4c6444; font-weight: 600;\">Děkujeme za vaši zprávu. Ozveme se vám co nejdříve.</p>
                                <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                    <tr>
                                        <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                            <p style=\"margin: 0 0 15px 0;\"><strong>Vaše zpráva:</strong></p>
                                            <p style=\"margin: 0;\">" . nl2br(htmlspecialchars($message)) . "</p>
                                        </td>
                                    </tr>
                                </table>
                                <p style=\"margin: 25px 0 0 0; font-size: 16px; color: #102820; font-weight: 600;\">
                                    S pozdravem,<br><strong>Tým KJD</strong>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style=\"background: linear-gradient(135deg, #4D2D18, #8A6240); background-color: #4D2D18; color: #ffffff; padding: 25px 20px; text-align: center; font-size: 14px; font-weight: 500;\">
                                <div style=\"font-size: 20px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                <p style=\"margin: 5px 0; color: #ffffff;\"><strong>Kubajadesigns.eu</strong></p>
                                <p style=\"margin: 5px 0; color: #ffffff;\">Email: info@kubajadesigns.eu</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";
    
    $customerHeaders = "MIME-Version: 1.0\r\n";
    $customerHeaders .= "Content-type: text/html; charset=utf-8\r\n";
    $customerHeaders .= "From: KubaJa Designs <info@kubajadesigns.eu>\r\n";
    
    mail($email, $customerSubject, $customerMessage, $customerHeaders);
    
    // Send email to admin
    $adminEmail = 'mickeyjarolim3@gmail.com';
    $adminSubject = 'Nová zpráva z kontaktního formuláře - ' . $subject;
    $adminMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
        <meta name=\"color-scheme\" content=\"light dark\">
        <meta name=\"supported-color-schemes\" content=\"light dark\">
    </head>
    <body style=\"margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;\">
        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f8f9fa;\">
            <tr>
                <td align=\"center\" style=\"padding: 20px 10px;\">
                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;\">
                        <tr>
                            <td style=\"background: linear-gradient(135deg, #102820, #4c6444); background-color: #102820; color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C;\">
                                <div style=\"font-size: 24px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Nová zpráva</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                    <tr>
                                        <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                            <p style=\"margin: 0 0 10px 0;\"><strong>Jméno:</strong> " . htmlspecialchars($name) . "</p>
                                            <p style=\"margin: 0 0 10px 0;\"><strong>E-mail:</strong> " . htmlspecialchars($email) . "</p>
                                            <p style=\"margin: 0 0 10px 0;\"><strong>Telefon:</strong> " . htmlspecialchars($phone) . "</p>
                                            <p style=\"margin: 0 0 10px 0;\"><strong>Předmět:</strong> " . htmlspecialchars($subject) . "</p>
                                            <p style=\"margin: 15px 0 5px 0;\"><strong>Zpráva:</strong></p>
                                            <p style=\"margin: 0; padding: 10px; background: rgba(255,255,255,0.5); border-radius: 8px;\">" . nl2br(htmlspecialchars($message)) . "</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style=\"background: linear-gradient(135deg, #4D2D18, #8A6240); background-color: #4D2D18; color: #ffffff; padding: 25px 20px; text-align: center; font-size: 14px; font-weight: 500;\">
                                <div style=\"font-size: 20px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                <p style=\"margin: 5px 0; color: #ffffff;\"><strong>Kubajadesigns.eu</strong></p>
                                <p style=\"margin: 5px 0; color: #ffffff;\">Admin notifikace</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";
    
    $adminHeaders = "MIME-Version: 1.0\r\n";
    $adminHeaders .= "Content-type: text/html; charset=utf-8\r\n";
    $adminHeaders .= "From: KubaJa Designs <info@kubajadesigns.eu>\r\n";
    $adminHeaders .= "Reply-To: " . $email . "\r\n";
    
    mail($adminEmail, $adminSubject, $adminMessage, $adminHeaders);
    
    $response['success'] = true;
    $response['message'] = 'Děkujeme za vaši zprávu! Ozveme se vám co nejdříve.';
}

echo json_encode($response);
