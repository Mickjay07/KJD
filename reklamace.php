<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$pageTitle = 'Reklamace zboží | KJD';
$pageDescription = 'Formulář pro podání reklamace zboží.';

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
                    $complaintType = trim($_POST['complaint_type']);
                    $description = trim($_POST['description']);
                    $products = $_POST['products'] ?? [];
                    
                    // Validate required fields
                    if (empty($name) || empty($email) || empty($address) || empty($complaintType) || empty($description) || empty($products)) {
                        $error = 'Všechna pole jsou povinná.';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Zadejte platnou e-mailovou adresu.';
                    } else {
                        // Save complaint to database
                        $stmt = $conn->prepare("
                            INSERT INTO complaints 
                            (order_number, name, email, phone, address, complaint_type, description, products, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
                        ")->execute([
                            $orderNumber,
                            $name,
                            $email,
                            $phone,
                            $address,
                            $complaintType,
                            $description,
                            json_encode($products)
                        ]);
                        
                        // Get the complaint ID
                        $complaintId = $conn->lastInsertId();
                        
                        // Handle file uploads
                        $uploadedFiles = [];
                        if (!empty($_FILES['photos']['name'][0])) {
                            $uploadDir = 'uploads/complaints/' . $complaintId . '/';
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            // Process each uploaded file
                            $fileCount = count($_FILES['photos']['name']);
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                                    $fileName = uniqid() . '_' . basename($_FILES['photos']['name'][$i]);
                                    $targetPath = $uploadDir . $fileName;
                                    
                                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $targetPath)) {
                                        $uploadedFiles[] = $fileName;
                                    }
                                }
                            }
                            
                            // Update complaint with file paths
                            if (!empty($uploadedFiles)) {
                                $stmt = $conn->prepare("UPDATE complaints SET photos = ? WHERE id = ?");
                                $stmt->execute([json_encode($uploadedFiles), $complaintId]);
                            }
                        }
                        
                        // Send confirmation email
                        $to = $email;
                        $subject = 'Potvrzení přijetí reklamace #' . $complaintId;
                        $message = "
                            <h2>Dobrý den $name,</h2>
                            <p>Děkujeme za vaši reklamaci č. $complaintId.</p>
                            <p>Vaše reklamace byla přijata a bude vyřízena v nejkratším možném čase. O dalším postupu vás budeme informovat e-mailem.</p>
                            <p>Detaily reklamace:</p>
                            <ul>
                                <li><strong>Číslo reklamace:</strong> $complaintId</li>
                                <li><strong>Typ reklamace:</strong> $complaintType</li>
                                <li><strong>Popis problému:</strong> $description</li>
                            </ul>
                            <p>S pozdravem,<br>Tým KubaJa Designs</p>
                        ";
                        
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "Content-type: text/html; charset=utf-8\r\n";
                        $headers .= "From: KubaJa Designs <info@kubajadesigns.eu>\r\n";
                        
                        mail($to, $subject, $message, $headers);
                        
                        // Also notify admin
                        $adminEmail = 'mickeyjarolim3@gmail.com';
                        $adminSubject = 'Nová reklamace #' . $complaintId;
                        $adminMessage = "
                            <h2>Nová reklamace #$complaintId</h2>
                            <p><strong>Zákazník:</strong> $name</p>
                            <p><strong>E-mail:</strong> $email</p>
                            <p><strong>Telefon:</strong> " . ($phone ?: 'neuveden') . "</p>
                            <p><strong>Adresa:</strong> $address</p>
                            <p><strong>Typ reklamace:</strong> $complaintType</p>
                            <p><strong>Popis problému:</strong></p>
                            <p>$description</p>
                        ";
                        
                        if (!empty($uploadedFiles)) {
                            $adminMessage .= "<p><strong>Připojené fotografie:</strong> " . count($uploadedFiles) . "</p>";
                        }
                        
                        $adminMessage .= "<p>Přihlaste se do administrace pro zobrazení podrobností a zpracování reklamace.</p>";
                        
                        $adminHeaders = "MIME-Version: 1.0\r\n";
                        $adminHeaders .= "Content-type: text/html; charset=utf-8\r\n";
                        $adminHeaders .= "From: KubaJa Designs <reklamace@kubajadesigns.eu>\r\n";
                        
                        mail($adminEmail, $adminSubject, $adminMessage, $adminHeaders);
                        
                        $success = 'Vaše reklamace byla úspěšně odeslána. O jejím vyřízení vás budeme informovat na e-mail ' . $email . '.';
                    }
                }
            } else {
                // Order found, show form with order details
                if (isset($_POST['submit_complaint'])) {
                    // Process complaint with order details
                    $complaintType = trim($_POST['complaint_type']);
                    $description = trim($_POST['description']);
                    $products = $_POST['products'] ?? [];
                    
                    if (empty($complaintType) || empty($description) || empty($products)) {
                        $error = 'Prosím vyplňte všechny požadované údaje a vyberte zboží k reklamaci.';
                    } else {
                        // Save complaint to database
                        $stmt = $conn->prepare("
                            INSERT INTO complaints 
                            (order_id, order_number, name, email, phone, address, complaint_type, description, products, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', NOW())
                        ")->execute([
                            $order['id'] ?? null,
                            $order['order_id'] ?? $orderNumber,
                            ($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''),
                            $order['email'] ?? '',
                            $order['phone'] ?? '',
                            $order['address'],
                            $complaintType,
                            $description,
                            json_encode($products)
                        ]);
                        
                        // Get the complaint ID
                        $complaintId = $conn->lastInsertId();
                        
                        // Handle file uploads
                        $uploadedFiles = [];
                        if (!empty($_FILES['photos']['name'][0])) {
                            $uploadDir = 'uploads/complaints/' . $complaintId . '/';
                            
                            // Create directory if it doesn't exist
                            if (!file_exists($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            
                            // Process each uploaded file
                            $fileCount = count($_FILES['photos']['name']);
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                                    $fileName = uniqid() . '_' . basename($_FILES['photos']['name'][$i]);
                                    $targetPath = $uploadDir . $fileName;
                                    
                                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $targetPath)) {
                                        $uploadedFiles[] = $fileName;
                                    }
                                }
                            }
                            
                            // Update complaint with file paths
                            if (!empty($uploadedFiles)) {
                                $stmt = $conn->prepare("UPDATE complaints SET photos = ? WHERE id = ?");
                                $stmt->execute([json_encode($uploadedFiles), $complaintId]);
                            }
                        }
                        
                        // Send confirmation email
                        $to = $order['email'] ?? '';
                        $name = ($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '');
                        $orderId = $order['order_id'] ?? $orderNumber;
                        $subject = 'Potvrzení přijetí reklamace #' . $complaintId;
                        
                        $photoInfo = !empty($uploadedFiles) ? "<p style=\"margin: 0 0 15px 0;\"><strong>Připojené fotografie:</strong> " . count($uploadedFiles) . "</p>" : '';
                        
                        $message = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                            <meta name=\"x-apple-disable-message-reformatting\">
                            <meta name=\"color-scheme\" content=\"light dark\">
                            <meta name=\"supported-color-schemes\" content=\"light dark\">
                            <title>Potvrzení reklamace</title>
                        </head>
                        <body style=\"margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;\">
                            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f8f9fa;\">
                                <tr>
                                    <td align=\"center\" style=\"padding: 20px 10px;\">
                                        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden;\">
                                            <tr>
                                                <td style=\"background: linear-gradient(135deg, #102820, #4c6444); background-color: #102820; color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid #CABA9C;\">
                                                    <div style=\"font-size: 24px; font-weight: 800; margin-bottom: 10px;\">KJ<span style=\"color: #CABA9C;\">D</span></div>
                                                    <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Reklamace zboží</h1>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                                    <h2 style=\"margin: 0 0 20px 0; color: #102820; font-size: 24px; font-weight: 700;\">Dobrý den $name,</h2>
                                                    <p style=\"margin: 0 0 20px 0; font-size: 16px; color: #4c6444; font-weight: 600;\">Děkujeme za vaši reklamaci.</p>
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                        <tr>
                                                            <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Číslo reklamace:</strong> $complaintId</p>
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Číslo objednávky:</strong> $orderId</p>
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Typ reklamace:</strong> $complaintType</p>
                                                                <p style=\"margin: 0 0 15px 0;\"><strong>Popis problému:</strong> $description</p>
                                                                $photoInfo
                                                                <p style=\"margin: 0;\">Vaše reklamace byla přijata a bude vyřízena v nejkratším možném čase. O dalším postupu vás budeme informovat e-mailem.</p>
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
                                                    <p style=\"margin: 15px 0 5px 0; font-size: 12px; color: rgba(255,255,255,0.9);\">Tento e-mail jste obdrželi v souvislosti s vaší reklamací.</p>
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
                        $headers .= "From: KubaJa Designs <reklamace@kubajadesigns.eu>\r\n";
                        
                        mail($to, $subject, $message, $headers);
                        
                        // Also notify admin
                        $adminEmail = 'mickeyjarolim3@gmail.com';
                        $adminSubject = 'Nová reklamace #' . $complaintId . ' k objednávce #' . $orderId;
                        
                        $adminPhotoInfo = !empty($uploadedFiles) ? "<p style=\"margin: 0 0 10px 0;\"><strong>Připojené fotografie:</strong> " . count($uploadedFiles) . "</p>" : '';
                        
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
                                                    <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">Nová reklamace</h1>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                                    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                        <tr>
                                                            <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Číslo reklamace:</strong> $complaintId</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Číslo objednávky:</strong> $orderId</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Zákazník:</strong> $name</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>E-mail:</strong> " . ($order['email'] ?? 'N/A') . "</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Telefon:</strong> " . ($order['phone'] ?? 'neuveden') . "</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Adresa:</strong> " . ($order['address'] ?? 'N/A') . "</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Typ reklamace:</strong> $complaintType</p>
                                                                <p style=\"margin: 0 0 10px 0;\"><strong>Popis problému:</strong></p>
                                                                <p style=\"margin: 0 0 10px 0; padding: 10px; background: rgba(255,255,255,0.5); border-radius: 8px;\">$description</p>
                                                                $adminPhotoInfo
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
                        $adminHeaders .= "From: KubaJa Designs <reklamace@kubajadesigns.eu>\r\n";
                        
                        mail($adminEmail, $adminSubject, $adminMessage, $adminHeaders);
                        
                        $success = 'Vaše reklamace byla úspěšně odeslána. O jejím vyřízení vás budeme informovat na e-mail ' . $order['email'] . '.';
                        $order = null; // Reset order to show success message
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Došlo k chybě při zpracování vaší reklamace: ' . $e->getMessage();
            error_log('Complaint submission error: ' . $e->getMessage());
        } catch (Exception $e) {
            $error = 'Došlo k chybě: ' . $e->getMessage();
            error_log('Complaint submission error: ' . $e->getMessage());
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
      
      .complaint-hero {
            text-align: center;
        }
        
        .complaint-steps {
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
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: var(--kjd-beige);
            background: #fffaf0;
        }
        
        .file-upload i {
            font-size: 48px;
            color: var(--kjd-beige);
            margin-bottom: 15px;
            display: block;
        }
        
        .file-upload p {
            margin-bottom: 0;
            color: #666;
        }
        
        .file-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .file-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #eee;
        }
        
        .file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-preview-item .remove-file {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 24px;
            height: 24px;
            background: rgba(0,0,0,0.6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        
        .complaint-type-card {
            border: 2px solid #eee;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .complaint-type-card:hover {
            border-color: var(--kjd-beige);
            background: #fffaf0;
        }
        
        .complaint-type-card input[type="radio"] {
            margin-right: 10px;
        }
        
        .complaint-type-card h5 {
            margin-bottom: 5px;
        }
        
        .complaint-type-card p {
            margin-bottom: 0;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="complaint-page">

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
                <i class="fas fa-exclamation-triangle me-2"></i>Reklamace zboží
            </h1>
            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Máte problém s objednávkou? Vyplňte prosím náš reklamační formulář</p>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="complaint-steps">
                        <h3 class="mb-4">Jak postupovat při reklamaci</h3>
                        
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
                                <h5>Popište problém</h5>
                                <p class="mb-0 text-muted">Uveďte podrobný popis závady</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <span class="step-number">3</span>
                            <div class="step-content">
                                <h5>Přiložte fotografie</h5>
                                <p class="mb-0 text-muted">Přidejte fotografie poškozeného zboží</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <span class="step-number">4</span>
                            <div class="step-content">
                                <h5>Odeslání</h5>
                                <p class="mb-0 text-muted">Potvrďte odeslání reklamace</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h5>Důležité informace</h5>
                        <ul class="mb-0">
                            <li>Reklamační lhůta je 24 měsíců od převzetí zboží</li>
                            <li>Uveďte co nejpřesnější popis závady</li>
                            <li>Přiložte fotografie poškozeného zboží</li>
                            <li>O vyřízení reklamace vás budeme informovat e-mailem</li>
                        </ul>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Potřebujete pomoc?</h5>
                            <p class="card-text">Máte dotaz ohledně reklamace? Kontaktujte nás na e-mailu <a href="mailto:reklamace@kubajadesigns.eu">info@kubajadesigns.eu</a> nebo na telefonním čísle <a href="tel:+420123456789">+420 722 341 256</a>.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        </div>
                        
                        <div class="text-center mt-5">
                            <a href="index.php" class="btn btn-primary">Zpět na úvodní stránku</a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$order && !isset($_POST['submit_manual'])): ?>
                            <div class="form-container">
                                <h2 class="mb-4">Zadejte číslo objednávky</h2>
                                
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
                                    <button type="button" onclick="showManualForm()" class="btn btn-outline-secondary">Pokračovat bez čísla objednávky</button>
                                </div>
                            </div>
                            
                            <!-- Manual Form (hidden by default) -->
                            <div class="form-container mt-4 manual-form" id="manualForm" style="display: none;">
                                <h2 class="mb-4">Vyplňte prosím vaše údaje</h2>
                                
                                <form method="POST" id="manualComplaintForm" enctype="multipart/form-data">
                                    <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($_POST['order_number'] ?? 'NENALEZENO'); ?>">
                                    
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
                                    
                                    <h4 class="mb-3 mt-4">Detaily reklamace</h4>
                                    
                                    <div class="mb-4">
                                        <label class="form-label d-block mb-3">Typ reklamace *</label>
                                        
                                        <div class="complaint-type-options">
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Vadný výrobek" required>
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Vadný výrobek</h5>
                                                    <p>Zboží je poškozené, rozbité nebo nefunguje tak, jak má</p>
                                                </div>
                                            </label>
                                            
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Nesprávná dodávka" required>
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Nesprávná dodávka</h5>
                                                    <p>Obdrželi jste jiné zboží, než jste objednali</p>
                                                </div>
                                            </label>
                                            
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Poškozené balení" required>
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Poškozené balení</h5>
                                                    <p>Zboží je poškozené v důsledku nevhodného balení</p>
                                                </div>
                                            </label>
                                            
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Jiný důvod" required>
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Jiný důvod</h5>
                                                    <p>Jiný důvod reklamace, který specifikujete v popisu</p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="description" class="form-label">Popis problému *</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required placeholder="Popište co nejpodrobněji, co je předmětem vaší reklamace. Uveďte například, kdy jste si všimli závady, zda se jedná o mechanické poškození, závadu na funkci apod."></textarea>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label d-block mb-3">Přiložte fotografie *</label>
                                        
                                        <div class="file-upload" id="fileUploadArea">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <h5>Přetáhněte sem soubory nebo klikněte pro výběr</h5>
                                            <p>Maximální velikost souboru: 5 MB<br>Povolené formáty: JPG, PNG, PDF</p>
                                            <input type="file" id="photos" name="photos[]" multiple accept="image/*,.pdf" style="display: none;">
                                        </div>
                                        
                                        <div class="file-preview" id="filePreview"></div>
                                        <div class="form-text">Přidejte fotografie dokumentující závadu (max. 5 souborů)</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label d-block mb-3">Reklamované zboží *</label>
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
                                    
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            Souhlasím s <a href="obchodni-podminky" target="_blank">obchodními podmínkami</a> a <a href="ochrana-osobnich-udaju" target="_blank">zásadami ochrany osobních údajů</a> *
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex">
                                        <button type="submit" name="submit_manual" class="btn btn-primary btn-lg me-md-2">Odeslat reklamaci</button>
                                        <button type="button" onclick="history.back()" class="btn btn-outline-secondary">Zpět</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Form with order details -->
                            <div class="form-container">
                                <h2 class="mb-4">Reklamace zboží</h2>
                                
                                <div class="alert alert-info mb-4">
                                    <h5>Objednávka č. <?php echo htmlspecialchars($order['order_id'] ?? $_POST['order_number'] ?? 'N/A'); ?></h5>
                                    <?php if (isset($order['created_at'])): ?>
                                        <p class="mb-0">Datum objednání: <?php echo date('d.m.Y', strtotime($order['created_at'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" id="complaintForm" enctype="multipart/form-data">
                                    <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($order['order_id'] ?? $_POST['order_number'] ?? ''); ?>">
                                    
                                    <h4 class="mb-3">Typ reklamace *</h4>
                                    
                                    <div class="mb-4">
                                        <div class="complaint-type-options">
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Vadný výrobek" required>
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Vadný výrobek</h5>
                                                    <p>Zboží je poškozené, rozbité nebo nefunguje tak, jak má</p>
                                                </div>
                                            </label>
                                            
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Nesprávná dodávka">
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Nesprávná dodávka</h5>
                                                    <p>Obdrželi jste jiné zboží, než jste objednali</p>
                                                </div>
                                            </label>
                                            
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Poškozené balení">
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Poškozené balení</h5>
                                                    <p>Zboží je poškozené v důsledku nevhodného balení</p>
                                                </div>
                                            </label>
                                            
                                            <label class="complaint-type-card d-block">
                                                <input type="radio" name="complaint_type" value="Jiný důvod">
                                                <div class="d-inline-block">
                                                    <h5 class="d-inline">Jiný důvod</h5>
                                                    <p>Jiný důvod reklamace, který specifikujete v popisu</p>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="description" class="form-label">Popis problému *</label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required placeholder="Popište co nejpodrobněji, co je předmětem vaší reklamace. Uveďte například, kdy jste si všimli závady, zda se jedná o mechanické poškození, závadu na funkci apod."></textarea>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label d-block mb-3">Přiložte fotografie *</label>
                                        
                                        <div class="file-upload" id="fileUploadArea">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <h5>Přetáhněte sem soubory nebo klikněte pro výběr</h5>
                                            <p>Maximální velikost souboru: 5 MB<br>Povolené formáty: JPG, PNG, PDF</p>
                                            <input type="file" id="photos" name="photos[]" multiple accept="image/*,.pdf" style="display: none;">
                                        </div>
                                        
                                        <div class="file-preview" id="filePreview"></div>
                                        <div class="form-text">Přidejte fotografie dokumentující závadu (max. 5 souborů)</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label d-block mb-3">Vyberte zboží k reklamaci *</label>
                                        
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
                                    
                                    <div class="alert alert-warning">
                                        <h5><i class="fas fa-info-circle me-2"></i> Důležité informace</h5>
                                        <ul class="mb-0">
                                            <li>Zboží musí být vráceno v nepoškozeném stavu v originálním obalu</li>
                                            <li>Reklamační řízení může trvat až 30 dní</li>
                                            <li>V případě uznání reklamace vám bude vrácena částka za vrácené zboží</li>
                                            <li>V případě dotazů nás neváhejte kontaktovat</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="form-check mb-4">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            Souhlasím s <a href="obchodni-podminky" target="_blank">obchodními podmínkami</a> a <a href="ochrana-osobnich-udaju" target="_blank">zásadami ochrany osobních údajů</a> *
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex">
                                        <button type="submit" name="submit_complaint" class="btn btn-primary btn-lg me-md-2">Odeslat reklamaci</button>
                                        <button type="button" onclick="history.back()" class="btn btn-outline-secondary">Zpět</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
        // Show/hide manual form
        function showManualForm() {
            document.getElementById('orderLookupForm').closest('.form-container').style.display = 'none';
            document.getElementById('manualForm').style.display = 'block';
        }
        
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
        
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const fileUpload = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('photos');
            const filePreview = document.getElementById('filePreview');
            
            // Handle click on upload area
            fileUpload.addEventListener('click', function() {
                fileInput.click();
            });
            
            // Handle drag and drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileUpload.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                fileUpload.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileUpload.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                fileUpload.classList.add('bg-light');
                fileUpload.style.borderColor = 'var(--kjd-beige)';
            }
            
            function unhighlight() {
                fileUpload.classList.remove('bg-light');
                fileUpload.style.borderColor = '';
            }
            
            // Handle dropped files
            fileUpload.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }
            
            // Handle selected files
            fileInput.addEventListener('change', function() {
                handleFiles(this.files);
            });
            
            function handleFiles(files) {
                const maxFiles = 5;
                const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                // Check number of files
                const currentFileCount = filePreview.children.length;
                const newFileCount = Math.min(files.length, maxFiles - currentFileCount);
                
                if (currentFileCount + newFileCount > maxFiles) {
                    alert(`Můžete nahrát maximálně ${maxFiles} souborů.`);
                    return;
                }
                
                // Process each file
                for (let i = 0; i < newFileCount; i++) {
                    const file = files[i];
                    
                    // Check file type
                    if (!allowedTypes.includes(file.type)) {
                        alert(`Soubor ${file.name} má nepodporovaný formát. Povolené formáty: JPG, PNG, PDF`);
                        continue;
                    }
                    
                    // Check file size
                    if (file.size > maxSize) {
                        alert(`Soubor ${file.name} je příliš velký. Maximální velikost je 5 MB.`);
                        continue;
                    }
                    
                    // Create preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'file-preview-item';
                        
                        if (file.type.startsWith('image/')) {
                            previewItem.innerHTML = `
                                <img src="${e.target.result}" alt="${file.name}">
                                <div class="remove-file" onclick="removeFilePreview(this)" title="Odebrat">×</div>
                            `;
                        } else {
                            previewItem.innerHTML = `
                                <div style="width:100%;height:100%;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                                    <div class="text-center p-2">
                                        <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                        <div style="font-size:10px;word-break:break-all;">${file.name}</div>
                                    </div>
                                </div>
                                <div class="remove-file" onclick="removeFilePreview(this)" title="Odebrat">×</div>
                            `;
                        }
                        
                        // Store file data for form submission
                        previewItem.dataset.fileName = file.name;
                        
                        filePreview.appendChild(previewItem);
                    };
                    
                    if (file.type.startsWith('image/')) {
                        reader.readAsDataURL(file);
                    } else {
                        reader.readAsText(file);
                    }
                }
                
                // Update file input to keep track of selected files
                updateFileInput();
            }
            
            // Update the file input with selected files
            function updateFileInput() {
                // In a real implementation, you would handle the file upload here
                // For this example, we're just showing a preview
                console.log('Files selected:', fileInput.files);
            }
            
            // Form validation
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Check if at least one product is selected
                    if (form.id === 'complaintForm' || form.id === 'manualComplaintForm') {
                        const checkboxes = form.querySelectorAll('input[type="checkbox"][name^="products"]:checked');
                        if (checkboxes.length === 0) {
                            e.preventDefault();
                            alert('Prosím vyberte alespoň jeden produkt k reklamaci.');
                            return false;
                        }
                        
                        // Check if complaint type is selected
                        const complaintType = form.querySelector('input[name="complaint_type"]:checked');
                        if (!complaintType) {
                            e.preventDefault();
                            alert('Prosím vyberte typ reklamace.');
                            return false;
                        }
                        
                        // Check if description is provided
                        const description = form.querySelector('textarea[name="description"]');
                        if (!description.value.trim()) {
                            e.preventDefault();
                            alert('Prosím vyplňte popis problému.');
                            description.focus();
                            return false;
                        }
                        
                        // Check if at least one file is uploaded
                        if (filePreview.children.length === 0) {
                            e.preventDefault();
                            alert('Prosím přidejte alespoň jednu fotografii dokumentující závadu.');
                            return false;
                        }
                    }
                    
                    // Check terms and conditions
                    const terms = form.querySelector('input[name="terms"]');
                    if (terms && !terms.checked) {
                        e.preventDefault();
                        alert('Pro odeslání reklamace musíte souhlasit s obchodními podmínkami.');
                        terms.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                    
                    return true;
                });
            });
        });
        
        // Remove file preview
        function removeFilePreview(element) {
            element.closest('.file-preview-item').remove();
            
            // In a real implementation, you would also need to remove the file from the file input
            // This is a simplified version that just removes the preview
        }
    </script>
</body>
</html>
