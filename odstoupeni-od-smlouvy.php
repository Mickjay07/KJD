<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$pageTitle = 'Odstoupení od smlouvy | KJD';
$pageDescription = 'Formulář pro odstoupení od smlouvy a vrácení zboží.';

// Include necessary files
require_once 'config.php';

// Calculate cart count and total
$cart_count = 0;
$cart_total = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int)($item['quantity'] ?? 0);
        $cart_total += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0);
    }
}

// Initialize variables
$order = null;
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = trim($_POST['order_number'] ?? '');
    
    if (empty($orderNumber)) {
        $error = 'Prosím zadejte číslo objednávky.';
    } else {
        try {
            // Check if order exists
            $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
            $stmt->execute([$orderNumber]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                // If order not found, check if we're submitting the manual form
                if (isset($_POST['submit_manual'])) {
                    // Process manual form submission
                    $name = trim($_POST['name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    $address = trim($_POST['address']);
                    $reason = trim($_POST['reason']);
                    $products = $_POST['products'] ?? [];
                    
                    // Validate required fields
                    if (empty($name) || empty($email) || empty($address) || empty($reason) || empty($products)) {
                        $error = 'Všechna pole jsou povinná.';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Zadejte platnou e-mailovou adresu.';
                    } else {
                        // Save cancellation request to database
                        $stmt = $conn->prepare("
                            INSERT INTO order_cancellations 
                            (order_number, name, email, phone, address, reason, products, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                        ")->execute([
                            $orderNumber,
                            $name,
                            $email,
                            $phone,
                            $address,
                            $reason,
                            json_encode($products)
                        ]);
                        
                        // Send confirmation email to customer
                        $to = $email;
                        $subject = 'Potvrzení žádosti o odstoupení od smlouvy #' . $orderNumber;
                        $message = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                            <meta name=\"x-apple-disable-message-reformatting\">
                            <meta name=\"color-scheme\" content=\"light dark\">
                            <meta name=\"supported-color-schemes\" content=\"light dark\">
                            <title>Potvrzení žádosti o odstoupení</title>
                        </head>
                        <body style=\"margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;\">
                            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f8f9fa;\">
                                <tr>
                                    <td align=\"center\" style=\"padding: 20px 10px;\">
                                        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;\">
                                            <!-- Header -->
                                            <tr>
                                                <td style=\"background: linear-gradient(135deg, #102820, #4c6444); background-color: #102820; color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C;\">
                                                    <div style=\"font-size: 24px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                                    <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Odstoupení od smlouvy</h1>
                                                </td>
                                            </tr>
                                            <!-- Content -->
                                            <tr>
                                                <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                                    <h2 style=\"margin: 0 0 20px 0; color: #102820; font-size: 24px; font-weight: 700;\">Dobrý den $name,</h2>
                                                    
                                                    <p style=\"margin: 0 0 20px 0; font-size: 16px; color: #4c6444; font-weight: 600;\">Děkujeme za vaši žádost o odstoupení od smlouvy.</p>
                                                    
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                        <tr>
                                                            <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Číslo objednávky:</strong> $orderNumber</p>
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Důvod vrácení:</strong> $reason</p>
                                                                <p style=\"margin: 0;\">Vaše žádost byla přijata a bude zpracována v nejkratším možném čase. O dalším postupu vás budeme informovat e-mailem.</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    
                                                    <p style=\"margin: 25px 0 0 0; font-size: 16px; color: #102820; font-weight: 600;\">
                                                        S pozdravem,<br><strong>Tým KJD</strong>
                                                    </p>
                                                </td>
                                            </tr>
                                            <!-- Footer -->
                                            <tr>
                                                <td style=\"background: linear-gradient(135deg, #4D2D18, #8A6240); background-color: #4D2D18; color: #ffffff; padding: 25px 20px; text-align: center; font-size: 14px; font-weight: 500;\">
                                                    <div style=\"font-size: 20px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                                    <p style=\"margin: 5px 0; color: #ffffff;\"><strong>Kubajadesigns.eu</strong></p>
                                                    <p style=\"margin: 5px 0; color: #ffffff;\">Email: info@kubajadesigns.eu</p>
                                                    <p style=\"margin: 15px 0 5px 0; font-size: 12px; color: rgba(255,255,255,0.9);\">Tento e-mail jste obdrželi v souvislosti s vaší objednávkou.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </body>
                        </html>";
                        
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "Content-type: text/html; charset=utf-8\r\n";
                        $headers .= "From: KubaJa Designs <info@kubajadesigns.eu>\r\n";
                        
                        mail($to, $subject, $message, $headers);
                        
                        // Send notification email to admin
                        $adminEmail = 'mickeyjarolim3@gmail.com';
                        $adminSubject = 'Nová žádost o odstoupení od smlouvy #' . $orderNumber;
                        
                        $productsListHtml = '';
                        foreach ($products as $product) {
                            $productsListHtml .= "<li style=\"margin: 5px 0; color: #102820;\">$product</li>";
                        }
                        
                        $adminMessage = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                            <meta name=\"color-scheme\" content=\"light dark\">
                            <meta name=\"supported-color-schemes\" content=\"light dark\">
                            <title>Nová žádost o odstoupení</title>
                        </head>
                        <body style=\"margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;\">
                            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f8f9fa;\">
                                <tr>
                                    <td align=\"center\" style=\"padding: 20px 10px;\">
                                        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;\">
                                            <tr>
                                                <td style=\"background: linear-gradient(135deg, #102820, #4c6444); background-color: #102820; color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C;\">
                                                    <div style=\"font-size: 24px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                                    <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Nová žádost o odstoupení</h1>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                        <tr>
                                                            <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Číslo objednávky:</strong> $orderNumber</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Zákazník:</strong> $name</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>E-mail:</strong> $email</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Telefon:</strong> $phone</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Adresa:</strong> $address</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Důvod vrácení:</strong> $reason</p>
                                                                <p style=\"margin: 15px 0 5px 0;\"><strong>Produkty k vrácení:</strong></p>
                                                                <ul style=\"margin: 0; padding-left: 20px;\">$productsListHtml</ul>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
                                                        <tr>
                                                            <td align=\"center\" style=\"padding: 20px 0;\">
                                                                <a href=\"https://kubajadesigns.eu/admin\" style=\"background: linear-gradient(135deg, #4D2D18, #8A6240); background-color: #4D2D18; color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 12px; font-weight: 700; font-size: 16px; display: inline-block;\">Přejít do administrace</a>
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
                        
                        mail($adminEmail, $adminSubject, $adminMessage, $adminHeaders);
                        
                        $success = 'Vaše žádost o odstoupení od smlouvy byla úspěšně odeslána. O dalším postupu vás budeme informovat e-mailem.';
                    }
                }
            } else {
                // Order found, show form with order details
                if (isset($_POST['submit_cancellation'])) {
                    // Process cancellation with order details
                    $reason = trim($_POST['reason']);
                    $products = $_POST['products'] ?? [];
                    
                    if (empty($reason) || empty($products)) {
                        $error = 'Prosím vyplňte důvod vrácení a vyberte zboží k vrácení.';
                    } else {
                        // Save cancellation request to database
                        $stmt = $conn->prepare("
                            INSERT INTO order_cancellations 
                            (order_id, order_number, name, email, phone, address, reason, products, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                        ")->execute([
                            $order['id'] ?? null,
                            $order['order_id'] ?? $orderNumber,
                            ($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''),
                            $order['email'] ?? '',
                            $order['phone'] ?? '',
                            $order['address'] ?? '',
                            $reason,
                            json_encode($products)
                        ]);
                        
                        // Send confirmation email to customer
                        $to = $order['email'] ?? '';
                        $name = ($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '');
                        $subject = 'Potvrzení žádosti o odstoupení od smlouvy #' . $orderNumber;
                        $message = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                            <meta name=\"x-apple-disable-message-reformatting\">
                            <meta name=\"color-scheme\" content=\"light dark\">
                            <meta name=\"supported-color-schemes\" content=\"light dark\">
                            <title>Potvrzení žádosti o odstoupení</title>
                        </head>
                        <body style=\"margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;\">
                            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f8f9fa;\">
                                <tr>
                                    <td align=\"center\" style=\"padding: 20px 10px;\">
                                        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;\">
                                            <tr>
                                                <td style=\"background: linear-gradient(135deg, #102820, #4c6444); background-color: #102820; color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C;\">
                                                    <div style=\"font-size: 24px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                                    <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Odstoupení od smlouvy</h1>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                                    <h2 style=\"margin: 0 0 20px 0; color: #102820; font-size: 24px; font-weight: 700;\">Dobrý den $name,</h2>
                                                    
                                                    <p style=\"margin: 0 0 20px 0; font-size: 16px; color: #4c6444; font-weight: 600;\">Děkujeme za vaši žádost o odstoupení od smlouvy.</p>
                                                    
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                        <tr>
                                                            <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Číslo objednávky:</strong> $orderNumber</p>
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Důvod vrácení:</strong> $reason</p>
                                                                <p style=\"margin: 0;\">Vaše žádost byla přijata a bude zpracována v nejkratším možném čase. O dalším postupu vás budeme informovat e-mailem.</p>
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
                                                    <p style=\"margin: 15px 0 5px 0; font-size: 12px; color: rgba(255,255,255,0.9);\">Tento e-mail jste obdrželi v souvislosti s vaší objednávkou.</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </body>
                        </html>";
                        
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "Content-type: text/html; charset=utf-8\r\n";
                        $headers .= "From: KubaJa Designs <info@kubajadesigns.eu>\r\n";
                        
                        mail($to, $subject, $message, $headers);
                        
                        // Send notification email to admin
                        $adminEmail = 'mickeyjarolim3@gmail.com';
                        $adminSubject = 'Nová žádost o odstoupení od smlouvy #' . $orderNumber;
                        
                        $productsListHtml = '';
                        foreach ($products as $productJson) {
                            $product = json_decode($productJson, true);
                            if ($product && isset($product['name'])) {
                                $productsListHtml .= "<li style=\"margin: 5px 0; color: #102820;\">" . $product['name'] . " (Množství: " . ($product['quantity'] ?? 1) . ", Cena: " . ($product['price'] ?? 'N/A') . ")</li>";
                            }
                        }
                        
                        // Use same template as manual form
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
                                                    <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Nová žádost o odstoupení</h1>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                        <tr>
                                                            <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Číslo objednávky:</strong> $orderNumber</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Zákazník:</strong> $name</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>E-mail:</strong> $to</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Telefon:</strong> " . ($order['phone'] ?? 'N/A') . "</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Adresa:</strong> " . ($order['address'] ?? 'N/A') . "</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Důvod vrácení:</strong> $reason</p>
                                                                <p style=\"margin: 15px 0 5px 0;\"><strong>Produkty k vrácení:</strong></p>
                                                                <ul style=\"margin: 0; padding-left: 20px;\">$productsListHtml</ul>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
                                                        <tr>
                                                            <td align=\"center\" style=\"padding: 20px 0;\">
                                                                <a href=\"https://kubajadesigns.eu/admin\" style=\"background: linear-gradient(135deg, #4D2D18, #8A6240); background-color: #4D2D18; color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 12px; font-weight: 700; font-size: 16px; display: inline-block;\">Přejít do administrace</a>
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
                        
                        mail($adminEmail, $adminSubject, $adminMessage, $adminHeaders);
                        
                        $success = 'Vaše žádost o odstoupení od smlouvy byla úspěšně odeslána. O dalším postupu vás budeme informovat e-mailem.';
                        $order = null; // Reset order to show success message
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Došlo k chybě při zpracování vaší žádosti: ' . $e->getMessage();
            error_log('Order cancellation error: ' . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Došlo k chybě: ' . $e->getMessage();
            error_log('Order cancellation error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title><?php echo $pageTitle; ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      
      /* Apple SF Pro Font */
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      }
      
      body {
        background-color: #f8f9fa;
        color: #333;
        line-height: 1.6;
      }
      
      /* KJD Custom Preloader */
      .preloader-wrapper {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8) !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
      }
      
      .preloader-wrapper .preloader {
        margin: 0;
        transform: none;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5rem;
        background: transparent;
        border: none;
        border-radius: 0;
      }
      
      .preloader:before,
      .preloader:after {
        display: none !important;
      }
      
      .preloader-text {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--kjd-dark-green);
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        margin-bottom: 0.5rem;
        animation: textFadeIn 1s ease-out;
      }
      
      .preloader-progress {
        width: 300px;
        height: 6px;
        background: rgba(202,186,156,0.3);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        margin-bottom: 1rem;
      }
      
      .preloader-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--kjd-earth-green), var(--kjd-dark-green));
        border-radius: 10px;
        width: 0;
        transition: width 0.3s ease;
        box-shadow: 0 2px 8px rgba(76,100,68,0.3);
      }
      
      .preloader-percentage {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--kjd-gold-brown);
      }
      
      @keyframes textFadeIn {
        0% { opacity: 0; transform: translateY(-20px); }
        100% { opacity: 1; transform: translateY(0); }
      }
      
      @keyframes zoomOut {
        0% { transform: scale(1); opacity: 1; }
        100% { transform: scale(1.2); opacity: 0; }
      }
      
      .preloader-wrapper.fade-out {
        animation: zoomOut 0.8s ease-in-out forwards;
      }
        
        .return-steps {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--kjd-dark-brown);
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .step:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .step-content {
            flex: 1;
        }
        
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .product-checkbox {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .product-checkbox:hover {
            border-color: var(--kjd-beige);
            background: #fffaf0;
        }
        
        .product-checkbox input[type="checkbox"] {
            margin-right: 15px;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            margin-right: 15px;
        }
        
        .btn-primary {
            background-color: var(--kjd-dark-brown);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--kjd-gold-brown);
        }
        
        .manual-form {
            display: none;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body class="return-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
      <div class="preloader">
        <div class="preloader-text">kubajadesigns.eu</div>
        <div class="preloader-progress">
          <div class="preloader-progress-bar"></div>
        </div>
        <div class="preloader-percentage">0%</div>
      </div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Page Title -->
    <section class="py-4" style="background: var(--kjd-beige); border-bottom: 2px solid var(--kjd-earth-green);">
        <div class="container">
            <h1 class="mb-0" style="color: var(--kjd-dark-green); font-weight: 700; font-size: 2rem;">
                <i class="fas fa-undo me-2"></i>Odstoupení od smlouvy
            </h1>
            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Máte právo odstoupit od smlouvy do 14 dnů od převzetí zboží</p>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="return-steps">
                        <h3 class="mb-4">Jak postupovat při vrácení zboží</h3>
                        
                        <div class="step">
                            <span class="step-number">1</span>
                            <div class="step-content">
                                <h5>Vyplňte formulář</h5>
                                <p class="mb-0 text-muted">Zadejte číslo objednávky a vyplňte požadované údaje</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <span class="step-number">2</span>
                            <div class="step-content">
                                <h5>Označte zboží k vrácení</h5>
                                <p class="mb-0 text-muted">Vyberte produkty, které chcete vrátit</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <span class="step-number">3</span>
                            <div class="step-content">
                                <h5>Zaslání zboží</h5>
                                <p class="mb-0 text-muted">Obdržíte e-mail s pokyny k vrácení zboží</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <span class="step-number">4</span>
                            <div class="step-content">
                                <h5>Vrácení peněz</h5>
                                <p class="mb-0 text-muted">Po převzetí zboží vám vrátíme peníze</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h5>Důležité informace</h5>
                        <ul class="mb-0">
                            <li>Lhůta pro odstoupení je 14 dní od převzetí zboží</li>
                            <li>Zboží musí být nepoškozené a v původním obalu</li>
                            <li>Náklady na vrácení hradí zákazník</li>
                            <li>Vrátíme vám peníze do 14 dnů od převzetí zboží</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-container">
                        <h2 class="mb-4">Žádost o odstoupení od smlouvy</h2>
                        
                        <?php if (!$order && !isset($_POST['submit_manual'])): ?>
                            <form method="POST" id="orderLookupForm">
                                <div class="mb-4">
                                    <label for="order_number" class="form-label">Číslo objednávky *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">#</span>
                                        <input type="text" class="form-control" id="order_number" name="order_number" required>
                                    </div>
                                    <div class="form-text">Číslo objednávky najdete v e-mailu s potvrzením objednávky.</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Pokračovat</button>
                                </div>
                            </form>
                            
                            <div class="mt-4 pt-4 border-top">
                                <p class="mb-3">Nemáte číslo objednávky nebo máte jiný dotaz?</p>
                                <a href="kontakty.php" class="btn btn-outline-secondary">Kontaktujte nás</a>
                            </div>
                        <?php elseif (isset($_POST['submit_manual']) || $error): ?>
                            <!-- Manual form when order not found -->
                            <form method="POST" id="manualCancellationForm">
                                <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($_POST['order_number'] ?? ''); ?>">
                                
                                <h4 class="mb-3">Osobní údaje</h4>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Jméno a příjmení *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">E-mail *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Telefon *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="address" class="form-label">Doručovací adresa *</label>
                                        <input type="text" class="form-control" id="address" name="address" required>
                                    </div>
                                </div>
                                
                                <h4 class="mb-3 mt-4">Detaily vrácení</h4>
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Důvod vrácení *</label>
                                    <select class="form-select" id="reason" name="reason" required>
                                        <option value="">Vyberte důvod vrácení</option>
                                        <option value="Změna názoru">Změna názoru</option>
                                        <option value="Nevyhovující barva/velikost">Nevyhovující barva/velikost</option>
                                        <option value="Vadný výrobek">Vadný výrobek</option>
                                        <option value="Doručeno pozdě">Doručeno pozdě</option>
                                        <option value="Jiný důvod">Jiný důvod</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Vrácené zboží *</label>
                                    <div id="manualProductsContainer">
                                        <div class="product-item mb-3">
                                            <div class="row align-items-center">
                                                <div class="col-md-6 mb-2">
                                                    <input type="text" class="form-control" name="products[0][name]" placeholder="Název produktu" required>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <input type="number" class="form-control" name="products[0][quantity]" placeholder="Množství" min="1" value="1" required>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <input type="text" class="form-control" name="products[0][price]" placeholder="Cena" required>
                                                </div>
                                                <div class="col-md-1 mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-product" onclick="removeProductItem(this)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addProductItem()">
                                        <i class="fas fa-plus me-1"></i> Přidat další produkt
                                    </button>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="additional_info" class="form-label">Dodatečné informace</label>
                                    <textarea class="form-control" id="additional_info" name="additional_info" rows="3" placeholder="Zde můžete uvést další podrobnosti k vaší žádosti o vrácení zboží."></textarea>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        Souhlasím s <a href="obchodni-podminky" target="_blank">obchodními podmínkami</a> a <a href="ochrana-osobnich-udaju" target="_blank">zásadami ochrany osobních údajů</a> *
                                    </label>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" name="submit_manual" class="btn btn-primary btn-lg me-md-2">Odeslat žádost</button>
                                    <button type="button" onclick="history.back()" class="btn btn-outline-secondary">Zpět</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Form with order details -->
                            <form method="POST" id="cancellationForm">
                                <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($order['order_id'] ?? ''); ?>">
                                
                                <div class="alert alert-info">
                                    <h5>Objednávka č. <?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></h5>
                                    <p class="mb-0">Datum objednání: <?php echo isset($order['created_at']) ? date('d.m.Y', strtotime($order['created_at'])) : 'N/A'; ?></p>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="reason" class="form-label">Důvod vrácení *</label>
                                    <select class="form-select" id="reason" name="reason" required>
                                        <option value="">Vyberte důvod vrácení</option>
                                        <option value="Změna názoru">Změna názoru</option>
                                        <option value="Nevyhovující barva/velikost">Nevyhovující barva/velikost</option>
                                        <option value="Vadný výrobek">Vadný výrobek</option>
                                        <option value="Doručeno pozdě">Doručeno pozdě</option>
                                        <option value="Jiný důvod">Jiný důvod</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label d-block mb-3">Vyberte zboží k vrácení *</label>
                                    
                                    <?php 
                                    // Decode products from JSON
                                    $orderItems = [];
                                    if (isset($order['products_json']) && !empty($order['products_json'])) {
                                        $productsData = json_decode($order['products_json'], true);
                                        if (is_array($productsData)) {
                                            foreach ($productsData as $key => $item) {
                                                // Skip delivery info
                                                if ($key === '_delivery_info') continue;
                                                
                                                if (is_array($item) && isset($item['name'])) {
                                                    $orderItems[] = [
                                                        'id' => $item['id'] ?? $key,
                                                        'name' => $item['name'] ?? 'Produkt',
                                                        'price' => number_format($item['final_price'] ?? $item['price'] ?? 0, 0, ',', ' ') . ' Kč',
                                                        'image' => $item['image_url'] ?? 'images/product-thumb-11.jpg',
                                                        'quantity' => $item['quantity'] ?? 1,
                                                        'color' => $item['color'] ?? null
                                                    ];
                                                }
                                            }
                                        }
                                    }
                                    
                                    if (empty($orderItems)) {
                                        echo '<div class="alert alert-warning">Nepodařilo se načíst produkty z objednávky.</div>';
                                    } else {
                                        foreach ($orderItems as $item): 
                                    ?>
                                    <div class="product-checkbox">
                                        <input type="checkbox" name="products[]" id="product-<?php echo $item['id']; ?>" value="<?php echo htmlspecialchars(json_encode($item)); ?>" checked>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image" onerror="this.src='images/product-thumb-11.jpg'">
                                        <div class="product-info">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                                <?php if ($item['color']): ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($item['color']); ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Množství: <?php echo $item['quantity']; ?></span>
                                                <span class="fw-bold"><?php echo $item['price']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                        endforeach;
                                    }
                                    ?>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="additional_info" class="form-label">Dodatečné informace</label>
                                    <textarea class="form-control" id="additional_info" name="additional_info" rows="3" placeholder="Zde můžete uvést další podrobnosti k vaší žádosti o vrácení zboží."></textarea>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        Souhlasím s <a href="obchodni-podminky" target="_blank">obchodními podmínkami</a> a <a href="ochrana-osobnich-udaju" target="_blank">zásadami ochrany osobních údajů</a> *
                                    </label>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-info-circle me-2"></i> Důležité informace</h5>
                                    <ul class="mb-0">
                                        <li>Zboží musí být vráceno v nepoškozeném stavu v originálním obalu</li>
                                        <li>Náklady na vrácení zboží hradí zákazník</li>
                                        <li>Vrácení peněz proběhne do 14 dnů od převzetí zboží</li>
                                        <li>V případě vrácení nekompletního nebo poškozeného zboží si vyhrazujeme právo na částečnou úhradu</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" name="submit_cancellation" class="btn btn-primary btn-lg me-md-2">Odeslat žádost o vrácení</button>
                                    <button type="button" onclick="history.back()" class="btn btn-outline-secondary">Zpět</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-light mt-4">
                        <h5>Potřebujete pomoc?</h5>
                        <p class="mb-0">Máte dotaz ohledně vrácení zboží? Kontaktujte nás na e-mailu <a href="mailto:info@kubajadesigns.eu">info@kubajadesigns.eu</a> nebo na telefonním čísle +420 123 456 789.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        // Custom Preloader Script
        document.addEventListener('DOMContentLoaded', function() {
          const preloaderWrapper = document.querySelector('.preloader-wrapper');
          const progressBar = document.querySelector('.preloader-progress-bar');
          const percentageElement = document.querySelector('.preloader-percentage');
          let progress = 0;
          
          const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 100) progress = 100;
            
            progressBar.style.width = progress + '%';
            percentageElement.textContent = Math.round(progress) + '%';
            
            if (progress >= 100) {
              clearInterval(progressInterval);
              
              setTimeout(() => {
                preloaderWrapper.classList.add('fade-out');
                setTimeout(() => {
                  preloaderWrapper.style.display = 'none';
                }, 800);
              }, 500);
            }
          }, 150);
          
          // Fallback
          setTimeout(() => {
            if (preloaderWrapper.style.display !== 'none') {
              clearInterval(progressInterval);
              percentageElement.textContent = '100%';
              preloaderWrapper.classList.add('fade-out');
              setTimeout(() => {
                preloaderWrapper.style.display = 'none';
              }, 800);
            }
          }, 4000);
        });
    </script>
    
    <script>
        // Add product item to manual form
        function addProductItem() {
            const container = document.getElementById('manualProductsContainer');
            const index = container.children.length;
            
            const item = document.createElement('div');
            item.className = 'product-item mb-3';
            item.innerHTML = `
                <div class="row align-items-center">
                    <div class="col-md-6 mb-2">
                        <input type="text" class="form-control" name="products[${index}][name]" placeholder="Název produktu" required>
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="number" class="form-control" name="products[${index}][quantity]" placeholder="Množství" min="1" value="1" required>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" class="form-control" name="products[${index}][price]" placeholder="Cena" required>
                    </div>
                    <div class="col-md-1 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-product" onclick="removeProductItem(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(item);
        }
        
        // Remove product item from manual form
        function removeProductItem(button) {
            const container = document.getElementById('manualProductsContainer');
            if (container.children.length > 1) {
                button.closest('.product-item').remove();
                
                // Reindex the remaining items
                const items = container.querySelectorAll('.product-item');
                items.forEach((item, index) => {
                    const inputs = item.querySelectorAll('input');
                    inputs.forEach(input => {
                        input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
                    });
                });
            } else {
                alert('Musí zůstat alespoň jeden produkt.');
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Check if at least one product is selected
                    if (form.id === 'cancellationForm') {
                        const checkboxes = form.querySelectorAll('input[type="checkbox"][name^="products"]:checked');
                        if (checkboxes.length === 0) {
                            e.preventDefault();
                            alert('Prosím vyberte alespoň jeden produkt k vrácení.');
                            return false;
                        }
                    }
                    
                    // Check terms and conditions
                    const terms = form.querySelector('input[name="terms"]');
                    if (terms && !terms.checked) {
                        e.preventDefault();
                        alert('Pro dokončení objednávky musíte souhlasit s obchodními podmínkami.');
                        terms.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                    
                    return true;
                });
            });
        });
        
        // Show/hide manual form
        function showManualForm() {
            document.getElementById('orderLookupForm').style.display = 'none';
            document.getElementById('manualForm').style.display = 'block';
        }
    </script>
</body>
</html>
