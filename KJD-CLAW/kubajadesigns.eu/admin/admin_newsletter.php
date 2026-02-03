<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Načtení PHPMailer tříd – nejprve stejná cesta jako v admin_edit_order.php
$phpmailer_loaded = false;
$fast_exception = __DIR__ . '/../vendor/phpmailer/phpmailer/src/exception.php';
$fast_phpmailer = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
$fast_smtp = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
if (file_exists($fast_exception) && file_exists($fast_phpmailer) && file_exists($fast_smtp)) {
    require_once $fast_exception;
    require_once $fast_phpmailer;
    require_once $fast_smtp;
    $phpmailer_loaded = true;
}

// 1) Zkusíme composer autoload
if (!$phpmailer_loaded) {
    $autoload_paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        '/www/kubajadesigns.eu/vendor/autoload.php',
        '/www/kubajadesigns.eu/kubajadesigns.eu/vendor/autoload.php'
    ];
    foreach ($autoload_paths as $ap) {
        if (file_exists($ap)) { require_once $ap; $phpmailer_loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer'); break; }
    }
}

// 2) Ruční require – podporuj i různé velikosti písmen
if (!$phpmailer_loaded) {
    $phpmailer_paths = [
        __DIR__ . '/../../vendor/phpmailer/phpmailer/src/',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
        dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/',
        '/www/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/',
        '/www/kubajadesigns.eu/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/'
    ];
    foreach ($phpmailer_paths as $path) {
        $exception_files = [$path . 'Exception.php', $path . 'exception.php'];
        $phpmailer_file = $path . 'PHPMailer.php';
        $smtp_file = $path . 'SMTP.php';
        foreach ($exception_files as $exception_file) {
            if (file_exists($exception_file) && file_exists($phpmailer_file) && file_exists($smtp_file)) {
                require_once $exception_file;
                require_once $phpmailer_file;
                require_once $smtp_file;
                $phpmailer_loaded = true;
                break 2;
            }
        }
    }
}
if (!$phpmailer_loaded) { 
    // Zkusíme ještě jednou stejnou cestu jako v admin_edit_order.php
    $alt_exception = __DIR__ . '/../vendor/phpmailer/phpmailer/src/exception.php';
    $alt_phpmailer = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    $alt_smtp = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    if (file_exists($alt_exception) && file_exists($alt_phpmailer) && file_exists($alt_smtp)) {
        require_once $alt_exception;
        require_once $alt_phpmailer;
        require_once $alt_smtp;
        $phpmailer_loaded = true;
    }
}
if (!$phpmailer_loaded) { 
    error_log('PHPMailer not found in admin_newsletter.php');
    // Pokračujeme dál, možná se použije jiný způsob odesílání
}

// Použití jmenných prostorů pro PHPMailer (pokud je načten)
if ($phpmailer_loaded || class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    // Použijeme plně kvalifikované názvy místo use statements
}

require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

// Načtení statistik newsletteru
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM newsletter");
    $subscriberCount = $stmt->fetchColumn();
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM newsletter WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $newSubscriberCount = $stmt->fetchColumn();
    
    // Načtení všech odběratelů newsletteru
    $stmt = $conn->query("SELECT * FROM newsletter ORDER BY created_at DESC");
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Načtení historie newsletterů s kontrolou existence tabulky
    try {
        $stmt = $conn->query("SELECT * FROM newsletter_history ORDER BY sent_at DESC");
        $newsletterHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Pokud tabulka neexistuje, vytvoříme ji
        if ($e->getCode() == '42S02') {
            $conn->exec("CREATE TABLE IF NOT EXISTS newsletter_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                recipient_count INT DEFAULT 0,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $newsletterHistory = [];
        } else {
            throw $e;
        }
    }
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání statistik newsletteru: " . $e->getMessage();
}

// Načtení aktuálních nastavení newsletteru
try {
    $stmt = $conn->query("SELECT newsletter_enabled, newsletter_popup_delay, newsletter_popup_frequency, newsletter_always_show FROM settings WHERE id = 1");
    $newsletterSettings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání nastavení newsletteru: " . $e->getMessage();
}

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_newsletter':
                $stmt = $conn->prepare("UPDATE settings SET 
                    newsletter_enabled = ?, 
                    newsletter_popup_delay = ?, 
                    newsletter_popup_frequency = ?,
                    newsletter_always_show = ?
                    WHERE id = 1");
                
                $stmt->execute([
                    isset($_POST['newsletter_enabled']) ? 1 : 0,
                    $_POST['newsletter_popup_delay'],
                    $_POST['newsletter_popup_frequency'],
                    isset($_POST['newsletter_always_show']) ? 1 : 0
                ]);
                
                $successMessage = "Nastavení newsletteru byla úspěšně aktualizována.";
                break;
                
            case 'export_subscribers':
                // Nastavení hlaviček pro stahování CSV souboru
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=odberatele_newsletteru_' . date('Y-m-d') . '.csv');
                
                // Otevření výstupního streamu
                $output = fopen('php://output', 'w');
                
                // Přidání BOM (Byte Order Mark) pro správné zobrazení českých znaků v Excel
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Hlavička CSV souboru
                fputcsv($output, ['Email', 'Datum přihlášení']);
                
                // Získání všech odběratelů
                $stmt = $conn->query("SELECT email, created_at FROM newsletter ORDER BY created_at DESC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [$row['email'], $row['created_at']]);
                }
                
                fclose($output);
                exit;
                
            case 'clear_subscribers':
                // Kontrolní kód pro vymazání všech odběratelů (použít s opatrností)
                if (isset($_POST['confirm_clear']) && $_POST['confirm_clear'] === 'yes') {
                    $conn->exec("TRUNCATE TABLE newsletter");
                    $successMessage = "Všichni odběratelé byli úspěšně odstraněni.";
                }
                break;
                
            case 'delete_subscriber':
                // Smazání konkrétního odběratele
                if (isset($_POST['subscriber_id']) && is_numeric($_POST['subscriber_id'])) {
                    $stmt = $conn->prepare("DELETE FROM newsletter WHERE id = ?");
                    $stmt->execute([$_POST['subscriber_id']]);
                    $successMessage = "Odběratel byl úspěšně odstraněn.";
                }
                break;
                
            case 'send_newsletter':
                // Načtení odběratelů pro hromadný e-mail
                $emailList = [];
                
                // Podle výběru buď všem odběratelům, nebo vlastní adrese
                $recipient = $_POST['recipient'] ?? 'all';
                
                if ($recipient === 'all') {
                    $stmt = $conn->query("SELECT email FROM newsletter");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $emailList[] = $row['email'];
                    }
                } else if ($recipient === 'custom' && !empty($_POST['custom_email'])) {
                    $emailList[] = $_POST['custom_email'];
                }
                
                if (empty($emailList)) {
                    throw new Exception("Žádní příjemci nebyli vybráni.");
                }
                
                // Základní validace formuláře
                $subject = $_POST['subject'] ?? '';
                $message = $_POST['message'] ?? '';
                
                if (empty($subject)) {
                    throw new Exception("Předmět e-mailu je povinný.");
                }
                
                // Zpracování nahraného obrázku
                $imageHtml = '';
                $imageAttachment = '';
                $imageUrl = '';
                $imageCid = '';
                $imageEmbeddedPath = '';
                
                if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
                    $tmpName = $_FILES['image']['tmp_name'];
                    $originalFileName = $_FILES['image']['name'];
                    if (file_exists($tmpName) && is_readable($tmpName)) {
                        // Absolutní cesta v /admin/newsletter_images
                        $uploadDirAbs = __DIR__ . '/newsletter_images/';
                        if (!is_dir($uploadDirAbs)) { mkdir($uploadDirAbs, 0755, true); }
                        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $originalFileName);
                        $uploadPathAbs = $uploadDirAbs . $fileName;
                        if (move_uploaded_file($tmpName, $uploadPathAbs)) {
                            // Veřejná relativní URL (pokud bychom chtěli externí zobrazování)
                            $publicRel = 'admin/newsletter_images/' . $fileName;
                            $imageUrl = 'https://kubajadesigns.eu/' . $publicRel;
                            $imageAttachment = $uploadPathAbs; // pro případné přiložení
                            // Preferujme vložený obrázek přes CID (spolehlivější v klientech)
                            $imageCid = 'img' . uniqid();
                            $imageEmbeddedPath = $uploadPathAbs;
                            $imageHtml = '
                            <div style="text-align: center; margin: 10px 0; max-width: 600px;">
                                <img src="cid:' . $imageCid . '" alt="Newsletter obrázek" width="100%" style="max-width: 600px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0;">
                            </div>';
                        }
                    }
                }
                
                // Vytvoříme HTML obsah emailu – KJD brand styl (kompatibilní s Gmailem)
                $htmlBody = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
                    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                    <meta name=\"x-apple-disable-message-reformatting\">
                    <!--[if !mso]><!--><meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\"><!--<![endif]-->
                    <title>KJD Newsletter</title>
                    <!--[if mso]>
                    <style type=\"text/css\">
                        body, table, td {font-family: Arial, sans-serif !important;}
                    </style>
                    <![endif]-->
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
                                            <h1 style=\"margin: 0; font-size: 28px; font-weight: 800; color: #ffffff;\">KJD Newsletter</h1>
                                        </td>
                                    </tr>
                                    <!-- Content -->
                                    <tr>
                                        <td style=\"padding: 30px 25px; line-height: 1.6;\">
                                            <h2 style=\"margin: 0 0 20px 0; color: #102820; font-size: 24px; font-weight: 700;\">Dobrý den!</h2>
                                            
                                            <p style=\"margin: 0 0 20px 0; font-size: 16px; color: #4c6444; font-weight: 600;\">Máme pro vás novinky!</p>
                                            
                                            " . (!empty($imageHtml) ? "<div style=\"text-align: center; margin: 20px 0;\">" . $imageHtml . "</div>" : "") . "
                                            
                                            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background: linear-gradient(135deg, #CABA9C, #f5f0e8); background-color: #CABA9C; border: 2px solid #4c6444; border-radius: 12px; margin: 20px 0;\">
                                                <tr>
                                                    <td style=\"padding: 25px; color: #102820; font-size: 16px; line-height: 1.8;\">
                                                        " . nl2br(htmlspecialchars($message)) . "
                                                    </td>
                                                </tr>
                                            </table>
                                            
                                            <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
                                                <tr>
                                                    <td align=\"center\" style=\"padding: 20px 0;\">
                                                        <a href=\"https://kubajadesigns.eu\" style=\"background: linear-gradient(135deg, #4D2D18, #8A6240); background-color: #4D2D18; color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 12px; font-weight: 700; font-size: 16px; display: inline-block;\">Navštívit web</a>
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
                                            <p style=\"margin: 15px 0 5px 0; font-size: 12px; color: rgba(255,255,255,0.9);\">Tento e-mail jste obdrželi, protože jste přihlášeni k odběru KJD.</p>
                                            <p style=\"margin: 5px 0;\"><a href=\"https://kubajadesigns.eu/unsubscribe.php\" style=\"color: #CABA9C; text-decoration: none;\">Odhlásit odběr</a></p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>";
                
                // Nastavení emailu a odeslání
                if (!$phpmailer_loaded && !class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    throw new Exception("PHPMailer není k dispozici.");
                }
                $total = count($emailList);
                $sent = 0;
                $failed = 0;
                foreach ($emailList as $recipientEmail) {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'mail.gigaserver.cz';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'info@kubajadesigns.eu';
                        $mail->Password = '2007Mickey++';
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->CharSet = 'UTF-8';

                        $mail->setFrom('info@kubajadesigns.eu', 'KJD');
                        // Set envelope sender (Return-Path) to reduce bounces marking as spam
                        $mail->Sender = 'bounce@kubajadesigns.eu'; // vytvořte mailbox nebo alias
                        $mail->addAddress($recipientEmail);

                        // List-Unsubscribe headers (URL + mailto)
                        $unsubUrl = 'https://kubajadesigns.eu/ajax/unsubscribe_one_click.php?email=' . urlencode($recipientEmail);
                        $mail->addCustomHeader('List-Unsubscribe', '<mailto:info@kubajadesigns.eu?subject=unsubscribe>, <' . $unsubUrl . '>' );
                        $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                        $mail->addCustomHeader('Precedence', 'bulk');
                        $mail->addCustomHeader('Feedback-ID', 'kjd-newsletter:kubajadesigns.eu');

                        // Optional DKIM (enable after DNS + private key available)
                        // if (file_exists(__DIR__ . '/dkim/private.key')) {
                        //     $mail->DKIM_domain = 'kubajadesigns.eu';
                        //     $mail->DKIM_selector = 'gigaserver'; // upravte dle administrace
                        //     $mail->DKIM_private = __DIR__ . '/dkim/private.key';
                        //     $mail->DKIM_identity = 'info@kubajadesigns.eu';
                        // }

                        $mail->Subject = $subject;
                        $mail->isHTML(true);

                        if (!empty($imageEmbeddedPath) && file_exists($imageEmbeddedPath) && $imageCid) {
                            $mail->addEmbeddedImage($imageEmbeddedPath, $imageCid, basename($imageEmbeddedPath));
                        }
                        $mail->Body = $htmlBody;
                        $mail->AltBody = strip_tags($message);

                        if (!empty($imageAttachment) && file_exists($imageAttachment)) {
                            $mail->addAttachment($imageAttachment, basename($originalFileName));
                        }

                        $mail->send();
                        $sent++;
                        // throttle: ~2 emaily/s
                        usleep(500000);
                    } catch (Exception $e) {
                        error_log('Newsletter send failed for ' . $recipientEmail . ': ' . $mail->ErrorInfo);
                        $failed++;
                    }
                }

                // Záznam o odeslání do historie (počet zamýšlených příjemců)
                $stmt = $conn->prepare("INSERT INTO newsletter_history (subject, content, sent_at, recipient_count) VALUES (?, ?, NOW(), ?)");
                $stmt->execute([$subject, $message, $total]);

                $successMessage = "Newsletter odeslán. Úspěšně: {$sent}, neúspěšně: {$failed}.";
                
                break;
        }
        
        // Znovu načteme nastavení po aktualizaci
        if ($action !== 'export_subscribers') {
            $stmt = $conn->query("SELECT * FROM settings WHERE id = 1");
            $newsletterSettings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Aktualizace seznamu odběratelů po provedení akce
            $stmt = $conn->query("SELECT * FROM newsletter ORDER BY created_at DESC");
            $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $conn->query("SELECT * FROM newsletter_history ORDER BY sent_at DESC");
            $newsletterHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch(Exception $e) {
        $errorMessage = "Chyba: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa newsletteru - Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="admin_clean_styles.css">
    <style>
        :root { 
          --kjd-dark-green:#102820; 
          --kjd-earth-green:#4c6444; 
          --kjd-gold-brown:#8A6240; 
          --kjd-dark-brown:#4D2D18; 
          --kjd-beige:#CABA9C; 
        }
        body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
          font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        .cart-page { 
          background: #f8f9fa; 
          min-height: 100vh; 
        }
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
        .subscriber-table {
            margin-top: 20px;
        }
        .stats-card {
            background: linear-gradient(135deg, rgba(202,186,156,0.18), #fff);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid var(--kjd-earth-green);
            box-shadow: 0 4px 12px rgba(16,40,32,0.08);
        }
        .stats-card h3 {
            margin-bottom: 5px;
            font-size: 24px;
            font-weight: bold;
            color: var(--kjd-dark-green);
        }
        .stats-card p {
            margin-bottom: 0;
            color: #666;
        }
        .nav-tabs .nav-link {
            color: var(--kjd-dark-brown);
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            color: var(--kjd-dark-brown);
            border-bottom-color: var(--kjd-dark-brown);
        }
        .tab-content {
            padding: 20px 0;
        }
        .message-box {
            min-height: 150px;
        }
        .hidden {
            display: none;
        }
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
    </style>
</head>
<body class="cart-page">
    <?php 
    if (file_exists(__DIR__ . '/../includes/icons.php')) {
        include __DIR__ . '/../includes/icons.php';
    }
    ?>
    <div class="preloader-wrapper">
      <div class="preloader"></div>
    </div>
    <?php 
    if (file_exists(__DIR__ . '/admin_sidebar.php')) {
        include __DIR__ . '/admin_sidebar.php';
    }
    ?>
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-envelope me-3"></i>Správa newsletteru</h1>
                    <p>Odeslání newsletteru, odběratelé a nastavení</p>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>

                <!-- Přehled a statistiky -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <h3><?php echo $subscriberCount; ?></h3>
                            <p>Celkový počet odběratelů</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <h3><?php echo $newSubscriberCount; ?></h3>
                            <p>Nových odběratelů za posledních 30 dní</p>
                        </div>
                    </div>
                </div>

                <!-- Záložky pro různé sekce -->
                <ul class="nav nav-tabs" id="newsletterTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="send-tab" data-bs-toggle="tab" data-bs-target="#send" type="button" role="tab" aria-controls="send" aria-selected="true">
                            Odeslat newsletter
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="subscribers-tab" data-bs-toggle="tab" data-bs-target="#subscribers" type="button" role="tab" aria-controls="subscribers" aria-selected="false">
                            Odběratelé (<?php echo $subscriberCount; ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                            Historie odesílání
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">
                            Nastavení
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="newsletterTabsContent">
                    <!-- Záložka pro odeslání newsletteru -->
                    <div class="tab-pane fade show active" id="send" role="tabpanel" aria-labelledby="send-tab">
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-paper-plane me-2"></i>Odeslat hromadný e-mail
                            </h3>
                                
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="send_newsletter">
                                    
                                    <div class="mb-3">
                                        <label for="recipient" class="form-label">Komu odeslat:</label>
                                        <select name="recipient" id="recipient" class="form-select" required>
                                            <option value="all">Všem odběratelům (<?php echo $subscriberCount; ?>)</option>
                                            <option value="custom">Zadat vlastní e-mail</option>
                                        </select>
                                    </div>
                                    
                                    <div id="customEmailField" class="mb-3 hidden">
                                        <label for="custom_email" class="form-label">E-mailová adresa:</label>
                                        <input type="email" class="form-control" id="custom_email" name="custom_email" placeholder="např. info@example.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Předmět:</label>
                                        <input type="text" class="form-control" id="subject" name="subject" placeholder="Zadejte předmět e-mailu" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Obsah:</label>
                                        <textarea class="form-control message-box" id="message" name="message" rows="6" placeholder="Zde napište obsah e-mailu..."></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Přiložit obrázek (volitelné):</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <div class="form-text">Maximální velikost: 5MB. Povolené formáty: JPEG, PNG, GIF.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-kjd-primary">
                                        <i class="fas fa-paper-plane me-1"></i> Odeslat newsletter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Záložka pro odběratele -->
                    <div class="tab-pane fade" id="subscribers" role="tabpanel" aria-labelledby="subscribers-tab">
                        <div class="cart-item">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="cart-product-name mb-0">
                                    <i class="fas fa-users me-2"></i>Seznam odběratelů
                                </h3>
                                <div class="d-flex gap-2">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="export_subscribers">
                                        <button type="submit" class="btn btn-kjd-primary btn-sm">
                                            <i class="fas fa-download me-1"></i> Exportovat do CSV
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearSubscribersModal">
                                        <i class="fas fa-trash me-1"></i> Vymazat všechny
                                    </button>
                                </div>
                            </div>
                                <?php if (!empty($subscribers)): ?>
                                    <div class="table-responsive subscriber-table">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>E-mail</th>
                                                    <th>Datum přihlášení</th>
                                                    <th>Akce</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($subscribers as $subscriber): ?>
                                                    <tr>
                                                        <td><?php echo $subscriber['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $date = new DateTime($subscriber['created_at']);
                                                            echo $date->format('d.m.Y H:i'); 
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <form method="POST" onsubmit="return confirm('Opravdu chcete smazat tohoto odběratele?');" class="d-inline">
                                                                <input type="hidden" name="action" value="delete_subscriber">
                                                                <input type="hidden" name="subscriber_id" value="<?php echo $subscriber['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i> Smazat
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> Nejsou k dispozici žádní odběratelé.
                                    </div>
                                <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Záložka pro historii -->
                    <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-history me-2"></i>Historie odeslaných newsletterů
                            </h3>
                                <?php if (!empty($newsletterHistory)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Předmět</th>
                                                    <th>Datum odeslání</th>
                                                    <th>Počet příjemců</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($newsletterHistory as $history): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($history['subject']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $date = new DateTime($history['sent_at']);
                                                            echo $date->format('d.m.Y H:i'); 
                                                            ?>
                                                        </td>
                                                        <td><?php echo $history['recipient_count']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i> Zatím nebyl odeslán žádný newsletter.
                                    </div>
                                <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Záložka pro nastavení -->
                    <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-cog me-2"></i>Nastavení newsletteru
                            </h3>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_newsletter">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input type="checkbox" class="form-check-input" id="newsletter_enabled" name="newsletter_enabled" <?php echo ($newsletterSettings['newsletter_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="newsletter_enabled">Povolit newsletter</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input type="checkbox" class="form-check-input" id="newsletter_always_show" name="newsletter_always_show" <?php echo ($newsletterSettings['newsletter_always_show'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="newsletter_always_show">Vždy zobrazit popup</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="newsletter_popup_delay" class="form-label">Zpoždění popup okna (sekundy)</label>
                                            <input type="number" class="form-control" id="newsletter_popup_delay" name="newsletter_popup_delay" value="<?php echo htmlspecialchars($newsletterSettings['newsletter_popup_delay'] ?? '5'); ?>" min="0" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="newsletter_popup_frequency" class="form-label">Frekvence zobrazení (dny)</label>
                                            <input type="number" class="form-control" id="newsletter_popup_frequency" name="newsletter_popup_frequency" value="<?php echo htmlspecialchars($newsletterSettings['newsletter_popup_frequency'] ?? '7'); ?>" min="0" required>
                                            <small class="form-text text-muted">0 = zobrazit při každé návštěvě</small>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-kjd-primary">Uložit nastavení</button>
                                </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal pro potvrzení vymazání všech odběratelů -->
    <div class="modal fade" id="clearSubscribersModal" tabindex="-1" aria-labelledby="clearSubscribersModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clearSubscribersModalLabel">Potvrzení smazání</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Varování! Tato akce smaže <strong>všechny</strong> odběratele newsletteru. Tuto akci nelze vrátit zpět.
                    </div>
                    <p>Jste si jistí, že chcete pokračovat?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <form method="POST">
                        <input type="hidden" name="action" value="clear_subscribers">
                        <input type="hidden" name="confirm_clear" value="yes">
                        <button type="submit" class="btn btn-danger">Ano, smazat všechny odběratele</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ovládání zobrazení pole pro vlastní e-mail
        const recipientSelect = document.getElementById('recipient');
        const customEmailField = document.getElementById('customEmailField');
        
        if (recipientSelect && customEmailField) {
            recipientSelect.addEventListener('change', function() {
                customEmailField.classList.toggle('hidden', this.value !== 'custom');
                
                // Pokud je vybrán vlastní e-mail, nastavíme pole jako povinné
                const customEmailInput = document.getElementById('custom_email');
                if (customEmailInput) {
                    customEmailInput.required = (this.value === 'custom');
                }
            });
        }
        
        // Aktivace záložky podle URL hash
        const hash = window.location.hash;
        if (hash) {
            const tab = document.querySelector(`#newsletterTabs a[href="${hash}"]`);
            if (tab) {
                const bsTab = new bootstrap.Tab(tab);
                bsTab.show();
            }
        }
        
        // Aktualizace URL při změně záložky
        const tabElms = document.querySelectorAll('#newsletterTabs button[data-bs-toggle="tab"]');
        tabElms.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                const targetId = event.target.getAttribute('data-bs-target');
                window.location.hash = targetId;
            });
        });
    });
    </script>
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
</body>
</html> 
