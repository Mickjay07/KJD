<?php
// Turn on error reporting at the top of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'config.php';
// functions.php is included via config.php

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Kontrola, zda je uživatel přihlášený jako admin - DOČASNĚ VYPNUTO PRO TESTOVÁNÍ
echo "<!-- Debug: Session check - admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? 'YES' : 'NO') . " -->";
echo "<!-- Debug: Session value: " . ($_SESSION['admin_logged_in'] ?? 'NULL') . " -->";

// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: admin_login.php');
//     exit;
// }

// Zajistíme, že tabulka orders má potřebné sloupce
try {
    // Zkontrolujeme a přidáme sloupce pokud neexistují
    $columnsToCheck = [
        'revolut_payment_link' => 'TEXT DEFAULT NULL',
        'invoice_file' => 'VARCHAR(255) DEFAULT NULL',
        'invoice_sent_at' => 'DATETIME DEFAULT NULL',
        'payment_confirmed_at' => 'DATETIME DEFAULT NULL'
    ];
    
    foreach ($columnsToCheck as $columnName => $columnDefinition) {
        $checkColumnQuery = "SHOW COLUMNS FROM orders LIKE '$columnName'";
        $stmt = $conn->prepare($checkColumnQuery);
        $stmt->execute();
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            $addColumnQuery = "ALTER TABLE orders ADD COLUMN $columnName $columnDefinition";
            $stmt = $conn->prepare($addColumnQuery);
            $stmt->execute();
        }
    }
} catch (Exception $e) {
    error_log("Chyba při kontrole sloupců: " . $e->getMessage());
}

// Funkce pro odeslání potvrzovacího e-mailu s fakturou
function sendPaymentConfirmationEmail($order, $invoice_path, $conn) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP konfigurace
        $mail->isSMTP();
        $mail->Host = 'mail.gigaserver.cz';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@kubajadesigns.eu';
        $mail->Password = '2007Mickey++';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Nastavení odesílatele a příjemce
        $mail->setFrom('info@kubajadesigns.eu', 'KJD');
        $mail->addAddress($order['email'], $order['name']);
        
        // Připojení faktury pokud existuje
        if ($invoice_path && file_exists($invoice_path)) {
            $mail->addAttachment($invoice_path, 'faktura_' . $order['order_id'] . '.pdf');
        }
        
        $mail->Subject = 'Potvrzení platby - objednávka #' . $order['order_id'];
        $mail->isHTML(true);
        
        // HTML šablona e-mailu
        $emailBody = "
        <!DOCTYPE html>
        <html lang='cs'>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <title>Potvrzení platby</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f7; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: #111; color: #fff; padding: 30px; text-align: center; }
                .logo { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .content { padding: 40px 30px; }
                h1 { color: #111; margin-top: 0; }
                .success-box { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .info-table td { padding: 10px; border-bottom: 1px solid #eee; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>KJD</div>
                    <div>Potvrzení platby</div>
                </div>
                <div class='content'>
                    <h1>Platba byla přijata! ✅</h1>
                    <p>Vážený/á <strong>" . htmlspecialchars($order['name']) . "</strong>,</p>
                    <p>s radostí vám potvrzujeme, že jsme přijali platbu za vaši objednávku.</p>
                    
                    <div class='success-box'>
                        <strong>✅ Platba potvrzena</strong><br>
                        Vaše objednávka bude nyní zpracována a připravena k odeslání.
                    </div>
                    
                    <table class='info-table'>
                        <tr><td><strong>Číslo objednávky:</strong></td><td>#" . htmlspecialchars($order['order_id']) . "</td></tr>
                        <tr><td><strong>Celková částka:</strong></td><td>" . number_format($order['total_price'], 0, ',', ' ') . " Kč</td></tr>
                        <tr><td><strong>Způsob doručení:</strong></td><td>" . htmlspecialchars($order['delivery_method']) . "</td></tr>
                    </table>
                    
                    <p>📄 <strong>Faktura je připojena k tomuto e-mailu.</strong></p>
                    <p>O dalším postupu vás budeme informovat e-mailem.</p>
                </div>
                <div class='footer'>
                    <p>Děkujeme za vaši důvěru!<br><strong>Tým KJD</strong></p>
                    <p>Máte otázky? Napište nám na info@kubajadesigns.eu</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->Body = $emailBody;
        $mail->send();
        
    } catch (Exception $e) {
        error_log('Chyba při odesílání potvrzovacího e-mailu: ' . $e->getMessage());
        throw new Exception('Nepodařilo se odeslat potvrzovací e-mail.');
    }
}

// Získání ID objednávky z POST nebo z URL
$order_id = $_POST['order_id'] ?? $_GET['id'] ?? $_GET['order_id'] ?? null;

// Debug informace
echo "<!-- Debug: order_id = " . htmlspecialchars($order_id ?? 'NULL') . " -->";
echo "<!-- Debug: GET = " . print_r($_GET, true) . " -->";
echo "<!-- Debug: POST = " . print_r($_POST, true) . " -->";
echo "<!-- Debug: Database connection: " . ($conn ? 'OK' : 'FAILED') . " -->";

$success_message = $_SESSION['admin_success'] ?? '';
$error_message = $_SESSION['admin_error'] ?? '';

// Vyčištění flash zpráv
unset($_SESSION['admin_success']);
unset($_SESSION['admin_error']);

// Pokud není order_id, nastavíme chybovou zprávu
if (!$order_id) {
    $error_message = "Nebyla zadána platná objednávka. Zadejte ID objednávky v URL.";
}

// Inicializace proměnných
$original_order = null;

// Inicializace proměnných pro produkty
$products_before_post = [];
$order_items_before_post = [];

// Načtení detailů objednávky (pouze pokud máme order_id)
$order = null;
$order_items = [];
$original_order = null;
$order_items_before_post = [];

if ($order_id) {
    echo "<!-- Debug: Reading order ID: " . htmlspecialchars($order_id) . " -->";
    
    // Nejdřív zkusíme jednoduchý dotaz
    try {
        // Kontrola a přidání sloupce final_design_path, pokud neexistuje
        try {
            $checkColumn = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'final_design_path'");
            if ($checkColumn->rowCount() == 0) {
                $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN final_design_path varchar(500) DEFAULT NULL");
            }
        } catch (PDOException $e) {
            error_log("Chyba při kontrole/přidání sloupce final_design_path: " . $e->getMessage());
        }
        
        // Zkusíme najít podle order_id (KJD-2025-...) nebo podle číselného id
        // Načteme i informace o Custom Lightbox, pokud existuje
        try {
            $simple_query = "
                SELECT o.*, 
                       clo.id as custom_lightbox_order_id_for_check,
                       clo.final_design_path,
                       clo.status as custom_status
                FROM orders o
                LEFT JOIN custom_lightbox_orders clo ON o.custom_lightbox_order_id = clo.id
                WHERE o.order_id = :order_id OR o.id = :order_id
            ";
            $stmt = $conn->prepare($simple_query);
            $stmt->bindParam(':order_id', $order_id);
            echo "<!-- Debug: Simple query: " . htmlspecialchars($simple_query) . " with param: " . htmlspecialchars($order_id) . " -->";
            $stmt->execute();
            echo "<!-- Debug: Simple query executed, rows: " . $stmt->rowCount() . " -->";
            
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Pokud selže kvůli chybějícímu sloupci, načteme bez něj
            $simple_query = "
                SELECT o.*, 
                       clo.id as custom_lightbox_order_id_for_check,
                       clo.status as custom_status
                FROM orders o
                LEFT JOIN custom_lightbox_orders clo ON o.custom_lightbox_order_id = clo.id
                WHERE o.order_id = :order_id OR o.id = :order_id
            ";
            $stmt = $conn->prepare($simple_query);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order) {
                $order['final_design_path'] = null;
            }
        }
        
        if ($order) {
            echo "<!-- Debug: Order found: " . htmlspecialchars($order['order_id'] ?? 'NO_ORDER_ID') . " -->";
            echo "<!-- Debug: Order ID from DB: " . htmlspecialchars($order['id'] ?? 'NO_ID') . " -->";
            echo "<!-- Debug: Order status: " . htmlspecialchars($order['status'] ?? 'NO_STATUS') . " -->";
            
            // Nastavíme $original_order pro kompatibilitu
            $original_order = $order;
            
            // Načtení Custom Lightbox dat, pokud je to Custom Lightbox objednávka
            $custom_lightbox_order = null;
            $custom_lightbox_id = $order['custom_lightbox_order_id'] ?? $order['custom_lightbox_order_id_for_check'] ?? null;
            if (($order['is_custom_lightbox'] ?? 0) && $custom_lightbox_id) {
                try {
                    $stmt = $conn->prepare("SELECT * FROM custom_lightbox_orders WHERE id = ?");
                    $stmt->execute([$custom_lightbox_id]);
                    $custom_lightbox_order = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Chyba při načítání Custom Lightbox objednávky: " . $e->getMessage());
                }
            }
            
            // Načteme produkty z products_json
            $products_before_post = json_decode($original_order['products_json'] ?? '[]', true);
            foreach ($products_before_post as $product) {
                $order_items_before_post[] = [
                    'id' => $product['id'] ?? 0,
                    'product_name' => $product['name'] ?? 'Neznámý produkt',
                    'quantity' => $product['quantity'] ?? 1,
                    'price' => $product['base_price'] ?? 0,
                    'final_price' => $product['final_price'] ?? 0,
                    'variant' => $product['variant'] ?? '',
                    'variants' => $product['variants'] ?? [],
                    'color' => $product['color'] ?? '',
                    'component_colors' => $product['component_colors'] ?? [],
                    'is_preorder' => $product['is_preorder'] ?? 0,
                    'release_date' => $product['release_date'] ?? null,
                ];
            }
        } else {
            echo "<!-- Debug: Order not found for ID: " . htmlspecialchars($order_id) . " -->";
            $error_message = "Objednávka nebyla nalezena.";
        }
    } catch(Exception $e) {
        echo "<!-- Debug: Exception: " . htmlspecialchars($e->getMessage()) . " -->";
        $error_message = "Chyba při načítání objednávky: " . $e->getMessage();
    }
}

    // Parse products from products_json field (for display on page AFTER update)
    if ($order) {
        $products = json_decode($order['products_json'] ?? '[]', true);
        
        foreach ($products as $product) {
            $order_items[] = [
                'id' => $product['id'] ?? 0,
                'product_name' => $product['name'] ?? 'Neznámý produkt',
                'quantity' => $product['quantity'] ?? 1,
                'price' => $product['base_price'] ?? 0,
                'final_price' => $product['final_price'] ?? 0,
                'variant' => $product['variant'] ?? '',
                'variants' => $product['variants'] ?? [],
                'color' => $product['selected_color'] ?? $product['color'] ?? '',
                'component_colors' => $product['component_colors'] ?? [],
                'is_preorder' => $product['is_preorder'] ?? 0,
                'release_date' => $product['release_date'] ?? null,
            ];
        }
        
        // Nastavíme $order_items_before_post pro kompatibilitu
        $order_items_before_post = $order_items;
    }

// Ověření, zda objednávka obsahuje předobjednávky
$hasPreorders = false;
$hasPreordersWithReleaseDate = false;

if ($order) {
    foreach ($order_items as $item) {
        if ($item['is_preorder'] == 1) {
            $hasPreorders = true;
            if (!empty($item['release_date']) && $item['release_date'] != '0000-00-00 00:00:00') {
                $hasPreordersWithReleaseDate = true;
            }
        }
    }
}

// --- NOVÉ: Parsování adresy z poznámky pro předvyplnění formuláře ---

// Nejprve předvyplňte z products_json->_delivery_info (pokud existuje)
$zasilkovna_name = '';
$zasilkovna_street = '';
$zasilkovna_city = '';
$zasilkovna_zip = '';
$alzabox_code = '';
$other_street = '';
$other_city = '';
$other_zip = '';

$__products_struct = json_decode($order['products_json'] ?? '[]', true);
if (is_array($__products_struct) && isset($__products_struct['_delivery_info']) && is_array($__products_struct['_delivery_info'])) {
    $di = $__products_struct['_delivery_info'];
    if (isset($di['zasilkovna']) && is_array($di['zasilkovna'])) {
        $z = $di['zasilkovna'];
        $zasilkovna_name = $z['name'] ?? $zasilkovna_name;
        // Podpora různých klíčů: 'street' i historické 'dress'
        $zasilkovna_street = $z['street'] ?? ($z['dress'] ?? $zasilkovna_street);
        $zasilkovna_city = $z['city'] ?? $zasilkovna_city;
        $zasilkovna_zip = $z['postal_code'] ?? $zasilkovna_zip;
    }
    if (isset($di['alzabox']) && is_array($di['alzabox'])) {
        $alzabox_code = $di['alzabox']['code'] ?? $alzabox_code;
    }
    if (isset($di['address']) && is_array($di['address'])) {
        $addr = $di['address'];
        $other_street = $addr['street'] ?? $other_street;
        $other_city = $addr['city'] ?? $other_city;
        $other_zip = $addr['postal_code'] ?? $other_zip;
    }
}

// FALLBACK: If not in JSON, try to read from database columns (for backward compatibility)
if (empty($zasilkovna_name) && !empty($order['zasilkovna_name'])) {
    $zasilkovna_name = $order['zasilkovna_name'];
}
// Parse address field for street, city, zip if zasilkovna selected
if (empty($zasilkovna_street) && empty($zasilkovna_city) && empty($zasilkovna_zip) && !empty($order['address'])) {
    // Try to parse: "Name, Street, ZIP City" format
    $addrParts = array_map('trim', explode(',', $order['address']));
    if (count($addrParts) >= 3) {
        // Skip first part (name, already got it)
        $zasilkovna_street = $addrParts[1] ?? '';
        // Last part might be "ZIP City"
        if (preg_match('/^(\d+)\s+(.+)$/', $addrParts[2] ?? '', $matches)) {
            $zasilkovna_zip = $matches[1];
            $zasilkovna_city = $matches[2];
        } else {
            $zasilkovna_city = $addrParts[2] ?? '';
        }
    }
}

$note = $order['note'] ?? '';

// Pokud nejsou data v JSON  ani v DB sloupcích, zkusíme doplnit z note (zpětná kompatibilita)
if ($zasilkovna_name === '' && $zasilkovna_street === '' && $zasilkovna_city === '' && $zasilkovna_zip === '') {
    if (preg_match('/^\[Zásilkovna\]\s*([^,]+),\s*([^,]+),\s*([^,]+),\s*([^\n\r]+)/', $note, $matches)) {
        $zasilkovna_name = trim($matches[1]);
        $zasilkovna_street = trim($matches[2]);
        $zasilkovna_city = trim($matches[3]);
        $zasilkovna_zip = trim($matches[4]);
    }
}
if ($alzabox_code === '') {
    if (preg_match('/^\[AlzaBox\]\s*([^\n\r]+)/', $note, $matches)) {
        $alzabox_code = trim($matches[1]);
    }
}
if ($other_street === '' && $other_city === '' && $other_zip === '') {
    if (preg_match('/^\[Adresa\]\s*([^,]+),\s*([^,]+),\s*([^\n\r]+)/', $note, $matches)) {
        $other_street = trim($matches[1]);
        $other_city = trim($matches[2]);
        $other_zip = trim($matches[3]);
    }
}
// --- KONEC NOVÉ ČÁSTI ---


// Zpracování formuláře pro aktualizaci objednávky
// Zpracování nahrání faktury a potvrzení platby
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'confirm_payment') {
        try {
            $invoice_file = null;
            
            // Zpracování nahrání faktury
            if (isset($_FILES['invoice']) && $_FILES['invoice']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/invoices/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['invoice']['name'], PATHINFO_EXTENSION);
                $invoice_file = $order_id . '_' . date('Y-m-d_H-i-s') . '.' . $file_extension;
                $upload_path = $upload_dir . $invoice_file;
                
                if (move_uploaded_file($_FILES['invoice']['tmp_name'], $upload_path)) {
                    // Aktualizace databáze s informacemi o faktuře a potvrzení platby
                    $stmt = $conn->prepare("UPDATE orders SET 
                        payment_status = 'paid', 
                        payment_confirmed_at = NOW(), 
                        invoice_file = ?, 
                        invoice_sent_at = NOW() 
                        WHERE id = ?");
                    $stmt->execute([$invoice_file, $order_id]);
                    
                    // Odeslání potvrzovacího e-mailu s fakturou
                    sendPaymentConfirmationEmail($order, $upload_path, $conn);
                    
                    $_SESSION['admin_success'] = 'Platba byla potvrzena a faktura odeslána zákazníkovi.';
                } else {
                    throw new Exception('Chyba při nahrávání faktury.');
                }
            } else {
                // Potvrzení platby bez faktury
                $stmt = $conn->prepare("UPDATE orders SET 
                    payment_status = 'paid', 
                    payment_confirmed_at = NOW() 
                    WHERE id = ?");
                $stmt->execute([$order_id]);
                
                $_SESSION['admin_success'] = 'Platba byla potvrzena.';
            }
            
            header("Location: admin_edit_order.php?id=$order_id");
            exit;
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Chyba při potvrzování platby: ' . $e->getMessage();
        }
    }
    // New: cancel order with reason and email notification
    if ($_POST['action'] === 'cancel_order') {
        try {
            $cancelReason = trim($_POST['cancel_reason'] ?? 'Bez udání důvodu');
            // Update status to cancelled and append reason to note
            $stmt = $conn->prepare("UPDATE orders SET status='cancelled', note = CONCAT(IFNULL(note,''), '\n\n--- Důvod zrušení (admin) ---\n', :reason) WHERE order_id = :order_id OR id = :order_id");
            $stmt->execute([':reason' => $cancelReason, ':order_id' => $order_id]);
            
            // Log status change to history
            try {
                // Get numeric ID first if we only have string ID
                $numericId = $order['id']; // Assuming $order is loaded before this block. If not, we might need to fetch it.
                // Actually $order is loaded at line 177, but this POST block is at line 400. 
                // We need to ensure we have the numeric ID.
                // The update query uses OR, so we can't be sure which one matched without fetching.
                // But wait, $order_id is from POST/GET.
                
                // Let's fetch the ID to be safe if we don't have it.
                $stmtId = $conn->prepare("SELECT id FROM orders WHERE order_id = ? OR id = ? LIMIT 1");
                $stmtId->execute([$order_id, $order_id]);
                $rowId = $stmtId->fetch(PDO::FETCH_ASSOC);
                
                if ($rowId) {
                    $stmtHistory = $conn->prepare("INSERT INTO order_status_history (order_id, status, created_at) VALUES (?, ?, NOW())");
                    $stmtHistory->execute([$rowId['id'], 'cancelled']);
                }
            } catch (Exception $e) {
                // Ignore history log error
            }

            // Reload order to get latest and payment status
            $reload = $conn->prepare("SELECT * FROM orders WHERE order_id = :order_id OR id = :order_id LIMIT 1");
            $reload->execute([':order_id' => $order_id]);
            $ord = $reload->fetch(PDO::FETCH_ASSOC) ?: $order;

            // Send cancellation email
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
            $mail->addAddress($ord['email'] ?? $order['email'], $ord['name'] ?? $order['name']);
            $mail->Subject = 'Zrušení objednávky #' . ($ord['order_id'] ?? $order['order_id']);

            $refundNote = '';
            $paymentStatus = $ord['payment_status'] ?? $order['payment_status'] ?? 'pending';
            if ($paymentStatus === 'paid') {
                $refundNote = '<div class="refund-box" style="background: #fff; padding: 25px; border-radius: 12px; margin: 20px 0; border: 2px solid #8A6240; box-shadow: 0 4px 15px rgba(138,98,64,0.2);">
                    <h3 style="margin-top: 0; color: #102820; font-size: 20px; font-weight: 700; margin-bottom: 15px; border-bottom: 2px solid #8A6240; padding-bottom: 8px;">Informace o vrácení peněz</h3>
                    <p style="color: #102820; font-size: 16px; margin: 0; font-weight: 600; line-height: 1.6;">Platba již proběhla. Peníze vám vrátíme do <strong style="color: #8A6240;">3 pracovních dnů</strong> na původní platební metodu.</p>
                </div>';
            }

            $body = "
            <html>
            <head>
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
                    .content h3 { 
                        color: #4D2D18; 
                        font-size: 20px; 
                        font-weight: 700; 
                        margin: 25px 0 15px 0;
                        border-bottom: 2px solid #CABA9C;
                        padding-bottom: 8px;
                    }
                    .order-info { 
                        background: rgba(202,186,156,0.1); 
                        padding: 20px; 
                        border-radius: 10px; 
                        border-left: 4px solid #8A6240;
                        margin: 20px 0;
                    }
                    .order-info p {
                        margin: 0;
                    }
                    .order-info strong {
                        color: #4D2D18;
                        font-weight: 700;
                    }
                    .reason-box { 
                        background: #fff; 
                        padding: 25px; 
                        border-radius: 12px; 
                        margin: 20px 0; 
                        border: 2px solid #8A6240;
                        box-shadow: 0 4px 15px rgba(138,98,64,0.2);
                    }
                    .reason-box h3 {
                        margin-top: 0;
                        color: #102820;
                        font-size: 20px;
                        font-weight: 700;
                        margin-bottom: 15px;
                        border-bottom: 2px solid #8A6240;
                        padding-bottom: 8px;
                    }
                    .reason-text {
                        color: #102820;
                        font-size: 16px;
                        line-height: 1.8;
                        font-weight: 500;
                    }
                    .footer { 
                        background: linear-gradient(135deg, #4D2D18, #8A6240); 
                        color: #fff; 
                        padding: 25px 20px; 
                        text-align: center; 
                        font-size: 14px;
                        font-weight: 500;
                    }
                    .footer p { 
                        margin: 5px 0;
                    }
                    @media (prefers-color-scheme: dark) {
                        body { 
                            background: #1a1a1a !important;
                        }
                        .email-container { 
                            background: #2d2d2d !important; 
                            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
                        }
                        .content h2,
                        .content h3,
                        .reason-text {
                            color: #e0e0e0 !important;
                        }
                        .reason-box {
                            background: #2d2d2d !important;
                            border-color: #8A6240 !important;
                        }
                        .order-info {
                            background: rgba(138,98,64,0.2) !important;
                        }
                        .order-info strong {
                            color: #CABA9C !important;
                        }
                        .refund-box {
                            background: #2d2d2d !important;
                            border-color: #8A6240 !important;
                        }
                        .refund-box h3 {
                            color: #e0e0e0 !important;
                            border-bottom-color: #8A6240 !important;
                        }
                        .refund-box p {
                            color: #e0e0e0 !important;
                        }
                        .refund-box strong {
                            color: #CABA9C !important;
                        }
                        p[style*='color: #102820'] {
                            color: #e0e0e0 !important;
                        }
                        p[style*='color: #4c6444'] {
                            color: #CABA9C !important;
                        }
                        a[style*='color: #4c6444'] {
                            color: #CABA9C !important;
                        }
                        h3[style*='color: #102820'] {
                            color: #e0e0e0 !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='header'>
                        <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                        <h1>Zrušení objednávky</h1>
                    </div>
                    
                    <div class='content'>
                        <h2>Dobrý den" . (!empty($ord['name'] ?? $order['name'] ?? '') ? ', ' . htmlspecialchars($ord['name'] ?? $order['name']) : '') . "!</h2>
                        
                        <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>Bohužel musíme informovat, že vaše objednávka byla zrušena.</p>
                        
                        <div class='order-info'>
                            <p><strong>Číslo objednávky:</strong> #" . htmlspecialchars($ord['order_id'] ?? $order['order_id']) . "</p>
                        </div>
                        
                        <h3>Důvod zrušení</h3>
                        
                        <div class='reason-box'>
                            <div class='reason-text'>" . nl2br(htmlspecialchars($cancelReason)) . "</div>
                        </div>
                        
                        " . $refundNote . "
                        
                        <p style='font-size: 16px; color: #4c6444; font-weight: 600; margin-top: 30px;'>
                            Pokud máte jakékoliv dotazy ohledně zrušení objednávky, neváhejte nás kontaktovat na <a href='mailto:info@kubajadesigns.eu' style='color: #4c6444; text-decoration: none; font-weight: 700;'>info@kubajadesigns.eu</a>.
                        </p>
                        
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
            </html>";
            
            $altBody = "Zrušení objednávky #" . ($ord['order_id'] ?? $order['order_id']) . "\n\n";
            $altBody .= "Dobrý den" . (!empty($ord['name'] ?? $order['name'] ?? '') ? ', ' . htmlspecialchars($ord['name'] ?? $order['name']) : '') . "!\n\n";
            $altBody .= "Vaše objednávka byla zrušena.\n\n";
            $altBody .= "Důvod zrušení:\n" . $cancelReason . "\n\n";
            if ($paymentStatus === 'paid') {
                $altBody .= "Platba již proběhla. Peníze vám vrátíme do 3 pracovních dnů na původní platební metodu.\n\n";
            }
            $altBody .= "Pokud máte dotazy, kontaktujte nás na info@kubajadesigns.eu.\n\n";
            $altBody .= "S pozdravem, Tým KJD";
            
            $mail->Body = $body;
            $mail->AltBody = $altBody;
            $mail->send();

            $_SESSION['admin_success'] = 'Objednávka byla zrušena a zákazník byl informován.';
            header("Location: admin_edit_order.php?id=$order_id");
            exit;
        } catch (Exception $e) {
            $_SESSION['admin_error'] = 'Zrušení se nezdařilo: ' . $e->getMessage();
            header("Location: admin_edit_order.php?id=$order_id");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        $status = $_POST['status'] ?? 'pending';
        $tracking_code = $_POST['tracking_code'] ?? '';
        $customer_note = $_POST['customer_note'] ?? '';
        $payment_status = $_POST['payment_status'] ?? $order['payment_status'] ?? 'pending';
        $revolut_payment_link = $_POST['revolut_payment_link'] ?? '';
        $send_revolut_link = isset($_POST['send_revolut_link']) && $_POST['send_revolut_link'] === '1';
        $edit_name = $_POST['edit_name'] ?? $order['name'];
        $edit_email = $_POST['edit_email'] ?? $order['email'];
        $edit_phone = $_POST['edit_phone'] ?? $order['phone'];
        $edit_delivery_method = $_POST['edit_delivery_method'] ?? $order['delivery_method'];

                // Zpracování adresy doručení
        $address_note = '';
        $original_note_without_address = $original_order['note'] ?? '';
        
        // Odstranění stávající adresy z poznámky, pokud existuje
        $original_note_without_address = preg_replace('/^\[(Zásilkovna|AlzaBox|Adresa)\].*?([\n\r]|$)/s', '', $original_note_without_address);
        $original_note_without_address = trim($original_note_without_address);

        if ($edit_delivery_method === 'Zásilkovna') {
            $zasilkovna_name = trim($_POST['zasilkovna_name'] ?? '');
            $zasilkovna_street = trim($_POST['zasilkovna_street'] ?? '');
            $zasilkovna_city = trim($_POST['zasilkovna_city'] ?? '');
            $zasilkovna_zip = trim($_POST['zasilkovna_zip'] ?? '');
            
            // Sestavení adresy Zásilkovny v konzistentním formátu
            if ($zasilkovna_name && ($zasilkovna_street || $zasilkovna_city || $zasilkovna_zip)) {
                $address_parts = [];
                if ($zasilkovna_name) $address_parts[] = $zasilkovna_name;
                if ($zasilkovna_street) $address_parts[] = $zasilkovna_street;
                if ($zasilkovna_city || $zasilkovna_zip) {
                    $address_parts[] = trim($zasilkovna_zip . ' ' . $zasilkovna_city);
                }
                $address_note = "[Zásilkovna] " . implode(", ", $address_parts);
            }
        } elseif ($edit_delivery_method === 'AlzaBox') {
            $alzabox_code = trim($_POST['alzabox_code'] ?? '');
            if ($alzabox_code) {
                $address_note = "[AlzaBox] $alzabox_code";
            }
        } elseif ($edit_delivery_method === 'Jiná doprava') {
            $other_street = trim($_POST['other_street'] ?? '');
            $other_city = trim($_POST['other_city'] ?? '');
            $other_zip = trim($_POST['other_zip'] ?? '');
             if ($other_street || $other_city || $other_zip) {
                $address_note = "[Adresa] $other_street, $other_city, $other_zip";
            }
        }

        $final_note = trim($original_note_without_address);
        if ($address_note) {
            $final_note = trim($address_note . "\n" . $final_note);
        }
        if ($customer_note) {
            $final_note .= "\n\n--- Admin poznámka: ---\n" . $customer_note;
        }
        // --- konec nové části ---

        // Kontrola, zda se jedná o zrušení objednávky
        $is_cancellation = isset($_POST['cancel_order']) && $_POST['cancel_order'] === 'yes';
        if ($is_cancellation) {
            $status = 'cancelled';
        }
        
        // Použít číslo objednávky jako kód pro sledování, pokud není zadán jiný
        if (empty($tracking_code)) {
            $tracking_code = $order['order_id'];
        }

        // Aktualizace JSON s doručovacími údaji
        $products_struct = json_decode($order['products_json'] ?? '[]', true);
        if (!is_array($products_struct)) { $products_struct = []; }
        if (!isset($products_struct['_delivery_info']) || !is_array($products_struct['_delivery_info'])) {
            $products_struct['_delivery_info'] = [];
        }
        if ($edit_delivery_method === 'Zásilkovna') {
            $products_struct['_delivery_info']['zasilkovna'] = [
                'name' => $zasilkovna_name,
                'street' => $zasilkovna_street,
                'city' => $zasilkovna_city,
                'postal_code' => $zasilkovna_zip
            ];
            // také vyčistíme ostatní režimy
            unset($products_struct['_delivery_info']['alzabox']);
            unset($products_struct['_delivery_info']['address']);
        } elseif ($edit_delivery_method === 'AlzaBox') {
            $products_struct['_delivery_info']['alzabox'] = [
                'code' => $alzabox_code
            ];
            unset($products_struct['_delivery_info']['zasilkovna']);
            unset($products_struct['_delivery_info']['address']);
        } elseif ($edit_delivery_method === 'Jiná doprava') {
            $products_struct['_delivery_info']['address'] = [
                'street' => $other_street,
                'city' => $other_city,
                'postal_code' => $other_zip
            ];
            unset($products_struct['_delivery_info']['zasilkovna']);
            unset($products_struct['_delivery_info']['alzabox']);
        }
        $products_json_updated = json_encode($products_struct, JSON_UNESCAPED_UNICODE);

        // Sestavit legacy textovou adresu pro sloupec orders.address (zpětná kompatibilita)
        $address_for_legacy = '';
        if ($edit_delivery_method === 'Zásilkovna') {
            $parts = [];
            if ($zasilkovna_name) $parts[] = $zasilkovna_name;
            if ($zasilkovna_street) $parts[] = $zasilkovna_street;
            if ($zasilkovna_city || $zasilkovna_zip) $parts[] = trim($zasilkovna_zip . ' ' . $zasilkovna_city);
            $address_for_legacy = implode(', ', $parts);
        } elseif ($edit_delivery_method === 'AlzaBox') {
            $address_for_legacy = $alzabox_code;
        } elseif ($edit_delivery_method === 'Jiná doprava') {
            $parts = [];
            if ($other_street) $parts[] = $other_street;
            if ($other_city) $parts[] = $other_city;
            if ($other_zip) $parts[] = $other_zip;
            $address_for_legacy = implode(', ', $parts);
        }

        // Aktualizace objednávky včetně nových údajů (vč. products_json)
        $update_query = "UPDATE orders SET 
                        status = :status, 
                        tracking_code = :tracking_code,
                        payment_status = :payment_status,
                        revolut_payment_link = :revolut_payment_link,
                        note = :final_note,
                        name = :edit_name,
                        email = :edit_email,
                        phone_number = :edit_phone,
                        delivery_method = :edit_delivery_method,
                        address = :address,
                        products_json = :products_json
                        WHERE order_id = :order_id";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':tracking_code', $tracking_code, PDO::PARAM_STR);
        $stmt->bindParam(':payment_status', $payment_status, PDO::PARAM_STR);
        $stmt->bindParam(':revolut_payment_link', $revolut_payment_link, PDO::PARAM_STR);
        $stmt->bindParam(':final_note', $final_note, PDO::PARAM_STR);
        $stmt->bindParam(':edit_name', $edit_name, PDO::PARAM_STR);
        $stmt->bindParam(':edit_email', $edit_email, PDO::PARAM_STR);
        $stmt->bindParam(':edit_phone', $edit_phone, PDO::PARAM_STR);
        $stmt->bindParam(':edit_delivery_method', $edit_delivery_method, PDO::PARAM_STR);
        $stmt->bindParam(':address', $address_for_legacy, PDO::PARAM_STR);
        $stmt->bindParam(':products_json', $products_json_updated, PDO::PARAM_STR);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_STR);
        $result = $stmt->execute();
        
        // Log status change to history if changed
        if ($result && isset($order['status']) && $order['status'] !== $status) {
            try {
                $stmtHistory = $conn->prepare("INSERT INTO order_status_history (order_id, status, created_at) VALUES (?, ?, NOW())");
                $stmtHistory->execute([$order['id'], $status]);
            } catch (\PDOException $e) {
                // If table doesn't exist, create it and retry
                if (strpos($e->getMessage(), '1146') !== false) {
                    try {
                        $conn->exec("CREATE TABLE IF NOT EXISTS order_status_history (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            order_id INT NOT NULL,
                            status VARCHAR(50) NOT NULL,
                            created_at DATETIME NOT NULL,
                            INDEX (order_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                        
                        // Retry insert
                        $stmtHistory = $conn->prepare("INSERT INTO order_status_history (order_id, status, created_at) VALUES (?, ?, NOW())");
                        $stmtHistory->execute([$order['id'], $status]);
                    } catch (Exception $ex) {
                        // Still failed, ignore
                    }
                }
            }
        }
        
        // Pokud se jedná o Custom Lightbox objednávku, aktualizujeme i custom_lightbox_orders
        if ($result && isset($order['is_custom_lightbox']) && $order['is_custom_lightbox'] && isset($order['custom_lightbox_order_id'])) {
            $customLightboxId = $order['custom_lightbox_order_id'];
            
            // Příprava adresy pro custom_lightbox_orders
            $customDeliveryAddress = '';
            $customPostalCode = '';
            
            if ($edit_delivery_method === 'Zásilkovna') {
                $parts = [];
                if ($zasilkovna_name) $parts[] = $zasilkovna_name;
                if ($zasilkovna_street) $parts[] = $zasilkovna_street;
                if ($zasilkovna_city || $zasilkovna_zip) {
                    $parts[] = trim($zasilkovna_zip . ' ' . $zasilkovna_city);
                }
                $customDeliveryAddress = implode(', ', $parts);
                $customPostalCode = $zasilkovna_zip;
            } elseif ($edit_delivery_method === 'AlzaBox') {
                $customDeliveryAddress = $alzabox_code;
            } elseif ($edit_delivery_method === 'Jiná doprava') {
                $parts = [];
                if ($other_street) $parts[] = $other_street;
                if ($other_city) $parts[] = $other_city;
                if ($other_zip) $parts[] = $other_zip;
                $customDeliveryAddress = implode(', ', $parts);
                $customPostalCode = $other_zip;
            }
            
            try {
                $updateCustomQuery = "UPDATE custom_lightbox_orders SET 
                    customer_name = ?,
                    customer_email = ?,
                    customer_phone = ?,
                    delivery_method = ?,
                    delivery_address = ?,
                    postal_code = ?,
                    updated_at = NOW()
                    WHERE id = ?";
                $stmtCustom = $conn->prepare($updateCustomQuery);
                $stmtCustom->execute([
                    $edit_name,
                    $edit_email,
                    $edit_phone,
                    $edit_delivery_method,
                    $customDeliveryAddress,
                    $customPostalCode,
                    $customLightboxId
                ]);
            } catch (PDOException $e) {
                error_log("Chyba při aktualizaci custom_lightbox_orders: " . $e->getMessage());
            }
        }
        
        if ($result) {
            // Porovnání změn
            $changes = [];
            $send_notification = isset($_POST['send_notification']) && $_POST['send_notification'] === '1';
            
            if ($status !== $original_order['status']) {
                $statusTexts = [
                    'pending' => 'Čeká na zpracování',
                    'processing' => 'Zpracovává se',
                    'preparing' => 'Příprava k odeslání',
                    'shipped' => 'Odesláno',
                    'delivered' => 'Doručeno',
                    'cancelled' => 'Zrušeno'
                ];
                $changes[] = "Stav objednávky: " . ($statusTexts[$original_order['status']] ?? $original_order['status']) . 
                            " → " . ($statusTexts[$status] ?? $status);
            }
            if ($payment_status !== ($original_order['payment_status'] ?? 'pending')) {
                $paymentStatusTexts = [
                    'pending' => 'Čeká na zaplacení',
                    'paid' => 'Zaplaceno',
                    'refunded' => 'Vráceno',
                    'cancelled' => 'Zrušeno'
                ];
                $oldPaymentStatus = $original_order['payment_status'] ?? 'pending';
                $changes[] = "Stav platby: " . ($paymentStatusTexts[$oldPaymentStatus] ?? $oldPaymentStatus) . 
                            " → " . ($paymentStatusTexts[$payment_status] ?? $payment_status);
            }
            if (!empty($tracking_code) && $tracking_code !== ($original_order['tracking_code'] ?? '')) {
                $changes[] = "Sledovací kód: " . htmlspecialchars($tracking_code);
            }
            if ($edit_name !== ($original_order['name'] ?? '')) {
                $oldName = $original_order['name'] ?? '';
                $changes[] = "Jméno: " . htmlspecialchars($oldName) . " → " . htmlspecialchars($edit_name);
            }
            if ($edit_email !== ($original_order['email'] ?? '')) {
                $oldEmail = $original_order['email'] ?? '';
                $changes[] = "E-mail: " . htmlspecialchars($oldEmail) . " → " . htmlspecialchars($edit_email);
            }
            if ($edit_phone !== ($original_order['phone'] ?? $original_order['phone_number'] ?? '')) {
                $oldPhone = $original_order['phone'] ?? $original_order['phone_number'] ?? '';
                $changes[] = "Telefon: " . htmlspecialchars($oldPhone) . " → " . htmlspecialchars($edit_phone);
            }
            if ($edit_delivery_method !== ($original_order['delivery_method'] ?? '')) {
                $oldMethod = $original_order['delivery_method'] ?? '';
                $changes[] = "Způsob doručení: " . htmlspecialchars($oldMethod) . " → " . htmlspecialchars($edit_delivery_method);
            }
            if (!empty($customer_note)) {
                $changes[] = "Přidána poznámka: " . htmlspecialchars($customer_note);
            }

            // Pokud je požadováno odeslání Revolut platebního odkazu a odkaz je vyplněn, odešli samostatný e-mail s odkazem
            $revolutEmailSent = false;
            if ($send_revolut_link && !empty($revolut_payment_link)) {
                try {
                    $revMail = new PHPMailer(true);
                    $revMail->isSMTP();
                    $revMail->Host = 'mail.gigaserver.cz';
                    $revMail->SMTPAuth = true;
                    $revMail->Username = 'info@kubajadesigns.eu';
                    $revMail->Password = '2007Mickey++';
                    $revMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $revMail->Port = 587;
                    $revMail->CharSet = 'UTF-8';
                    $revMail->isHTML(true);
                    $revMail->setFrom('info@kubajadesigns.eu', 'KJD');
                    $revMail->addAddress($edit_email, $edit_name);
                    $revMail->Subject = "Platební odkaz – objednávka #" . $order['order_id'];

                    $revolutBody = "
                    <html>
                    <head>
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
                            .content h3 { 
                                color: #4D2D18; 
                                font-size: 20px; 
                                font-weight: 700; 
                                margin: 25px 0 15px 0;
                                border-bottom: 2px solid #CABA9C;
                                padding-bottom: 8px;
                            }
                            .payment-card {
                                background: linear-gradient(135deg, #CABA9C, #f5f0e8);
                                padding: 25px;
                                border-radius: 12px;
                                margin: 20px 0;
                                border: 2px solid #4c6444;
                                box-shadow: 0 4px 15px rgba(76,100,68,0.2);
                                text-align: center;
                            }
                            .btn-pay {
                                background: linear-gradient(135deg, #4D2D18, #8A6240);
                                color: #fff;
                                padding: 15px 30px;
                                border-radius: 12px;
                                text-decoration: none;
                                font-weight: 700;
                                font-size: 16px;
                                display: inline-block;
                                margin: 20px 0;
                                box-shadow: 0 4px 15px rgba(77,45,24,0.3);
                                transition: all 0.3s ease;
                            }
                            .btn-pay:hover {
                                transform: translateY(-2px);
                                box-shadow: 0 6px 20px rgba(77,45,24,0.4);
                            }
                            .info-box {
                                background: #fff;
                                border: 2px solid #8A6240;
                                border-radius: 12px;
                                padding: 20px;
                                margin: 20px 0;
                                box-shadow: 0 4px 15px rgba(138,98,64,0.1);
                            }
                            .info-row {
                                display: flex;
                                justify-content: space-between;
                                margin: 12px 0;
                                padding: 8px 0;
                                border-bottom: 1px solid rgba(202,186,156,0.3);
                            }
                            .info-row:last-child {
                                border-bottom: none;
                            }
                            .info-label {
                                color: #4c6444;
                                font-weight: 600;
                            }
                            .info-value {
                                color: #102820;
                                font-weight: 700;
                            }
                            .note-box {
                                background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,193,7,0.05));
                                border: 2px solid #ffc107;
                                border-radius: 12px;
                                padding: 20px;
                                margin: 20px 0;
                            }
                            .note-box p {
                                color: #856404;
                                font-weight: 600;
                                margin: 0;
                                font-size: 15px;
                            }
                            .footer { 
                                background: linear-gradient(135deg, #4D2D18, #8A6240); 
                                color: #fff; 
                                padding: 25px 20px; 
                                text-align: center; 
                                font-size: 14px;
                                font-weight: 500;
                            }
                            .footer p { 
                                margin: 5px 0;
                            }
                            @media (prefers-color-scheme: dark) {
                                body { 
                                    background: #1a1a1a !important;
                                }
                                .email-container { 
                                    background: #2d2d2d !important; 
                                    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
                                }
                                .content h2,
                                .content h3 {
                                    color: #e0e0e0 !important;
                                }
                                .info-box {
                                    background: #2d2d2d !important;
                                    border-color: #8A6240 !important;
                                }
                                .info-value {
                                    color: #e0e0e0 !important;
                                }
                                .note-box {
                                    background: rgba(255,193,7,0.2) !important;
                                    border-color: #ffc107 !important;
                                }
                                .note-box p {
                                    color: #ffc107 !important;
                                }
                                p[style*='color: #102820'] {
                                    color: #e0e0e0 !important;
                                }
                                p[style*='color: #4c6444'] {
                                    color: #CABA9C !important;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                                <h1>Platební odkaz</h1>
                            </div>
                            
                            <div class='content'>
                                <h2>Dobrý den" . (!empty($edit_name) ? ', ' . htmlspecialchars($edit_name) : '') . "!</h2>
                                
                                <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>Děkujeme za vaši objednávku! Pro úhradu použijte bezpečný platební odkaz níže.</p>
                                
                                <div class='payment-card'>
                                    <h3 style='margin-top: 0; color: #102820;'>Zaplaťte svou objednávku</h3>
                                    <p style='color: #4c6444; font-weight: 600; margin: 15px 0;'>Platit můžete kartou, Apple Pay, Google Pay nebo přes aplikaci Revolut</p>
                                    <a href='" . htmlspecialchars($revolut_payment_link) . "' target='_blank' class='btn-pay'>
                                        💳 Zaplatit nyní
                                    </a>
                                </div>
                                
                                <div class='info-box'>
                                    <h3 style='margin-top: 0; color: #102820; font-size: 18px; font-weight: 700; margin-bottom: 15px; border-bottom: 2px solid #CABA9C; padding-bottom: 8px;'>Detaily objednávky</h3>
                                    <div class='info-row'>
                                        <span class='info-label'>Číslo objednávky:</span>
                                        <span class='info-value'>#" . htmlspecialchars($order['order_id']) . "</span>
                                    </div>
                                    <div class='info-row'>
                                        <span class='info-label'>Celková částka:</span>
                                        <span class='info-value'>" . number_format($original_order['total_price'], 0, ',', ' ') . " Kč</span>
                                    </div>
                                    <div class='info-row'>
                                        <span class='info-label'>Způsob platby:</span>
                                        <span class='info-value'>Revolut (online)</span>
                                    </div>
                                </div>
                                
                                <div class='note-box'>
                                    <p>⚠️ Prosíme, neposílejte platbu bankovním převodem. Použijte výhradně tento platební odkaz.</p>
                                </div>
                                
                                <p style='font-size: 16px; color: #4c6444; font-weight: 600; margin-top: 30px;'>
                                    Pokud by odkaz nefungoval, odpovězte prosím na tento e-mail a my vám zašleme nový.
                                </p>
                                
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
                    </html>";

                    $revAlt = "Děkujeme za objednávku. Pro úhradu použijte platební odkaz: " . $revolut_payment_link . "\n\nObjednávka #" . $order['order_id'] . "\nCelkem: " . number_format($original_order['total_price'], 0, ',', ' ') . " Kč\nZpůsob platby: Revolut (online)\n\nNeposílejte bankovní převod. V případě potíží nám prosím napište.";

                    $revMail->Body = $revolutBody;
                    $revMail->AltBody = $revAlt;
                    $revMail->send();
                    $revolutEmailSent = true;
                    $changes[] = "Zákazníkovi byl odeslán platební odkaz Revolut.";
                } catch (Exception $e) {
                    error_log("Chyba při odesílání Revolut odkazu: " . $e->getMessage());
                }
            }

            // Odeslání e-mailu zákazníkovi s rekapitulací změn - pouze pokud je zaškrtnuté "Odeslat notifikaci" a jsou nějaké změny
            if ($send_notification && !empty($changes)) {
            $mail = new PHPMailer(true);
            try {
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
                $mail->addAddress($edit_email, $edit_name);
                $mail->Subject = "Aktualizace objednávky #" . $order['order_id'];

                    // Moderní KJD styl e-mailu - stejný jako voucher
                    $emailBody = "
                    <html>
                    <head>
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
                            .content h3 { 
                                color: #4D2D18; 
                                font-size: 20px; 
                                font-weight: 700; 
                                margin: 25px 0 15px 0;
                                border-bottom: 2px solid #CABA9C;
                                padding-bottom: 8px;
                            }
                            .changes-box { 
                                background: #fff; 
                                padding: 25px; 
                                border-radius: 12px; 
                                margin: 20px 0; 
                                border: 2px solid #8A6240;
                                box-shadow: 0 4px 15px rgba(138,98,64,0.2);
                            }
                            .change-item { 
                                color: #102820; 
                                font-size: 16px; 
                                line-height: 1.8; 
                                margin-bottom: 12px; 
                                padding-left: 28px; 
                                position: relative; 
                                font-weight: 500;
                            }
                            .change-item:before { 
                                content: '✓'; 
                                position: absolute; 
                                left: 0; 
                                color: #4c6444; 
                                font-weight: 700; 
                                font-size: 18px;
                            }
                            .change-item:last-child { 
                                margin-bottom: 0; 
                            }
                            .footer { 
                                background: linear-gradient(135deg, #4D2D18, #8A6240); 
                                color: #fff; 
                                padding: 25px 20px; 
                                text-align: center; 
                                font-size: 14px;
                                font-weight: 500;
                            }
                            .footer p { 
                                margin: 5px 0;
                            }
                            @media (prefers-color-scheme: dark) {
                                body { 
                                    background: #1a1a1a !important;
                                }
                                .email-container { 
                                    background: #2d2d2d !important; 
                                    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
                                }
                                .content h2,
                                .content h3,
                                .change-item {
                                    color: #e0e0e0 !important;
                                }
                                .changes-box {
                                    background: #2d2d2d !important;
                                    border-color: #8A6240 !important;
                                }
                                .order-info {
                                    background: rgba(138,98,64,0.2) !important;
                                }
                                .order-info strong {
                                    color: #CABA9C !important;
                                }
                                .change-item:before {
                                    color: #CABA9C !important;
                                }
                                p[style*='color: #102820'] {
                                    color: #e0e0e0 !important;
                                }
                                p[style*='color: #4c6444'] {
                                    color: #CABA9C !important;
                                }
                                a[style*='color: #4c6444'] {
                                    color: #CABA9C !important;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class='email-container'>
                            <div class='header'>
                                <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                                <h1>Aktualizace objednávky</h1>
                            </div>
                            
                            <div class='content'>
                                <h2>Dobrý den" . (!empty($edit_name) ? ', ' . htmlspecialchars($edit_name) : '') . "!</h2>
                                
                                <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>Vaše objednávka byla aktualizována. Níže najdete přehled všech provedených změn:</p>
                                
                                <div class='order-info' style='background: rgba(202,186,156,0.1); padding: 20px; border-radius: 10px; border-left: 4px solid #8A6240; margin: 20px 0;'>
                                    <p style='margin: 0;'><strong style='color: #4D2D18; font-weight: 700;'>Číslo objednávky:</strong> #" . htmlspecialchars($order['order_id']) . "</p>
                                </div>
                                
                                <h3>Provedené změny</h3>
                                
                                <div class='changes-box'>";

                    foreach ($changes as $change) {
                        $emailBody .= "<div class='change-item'>" . htmlspecialchars($change) . "</div>";
                    }

                    $emailBody .= "
                                </div>
                                
                                <p style='font-size: 16px; color: #4c6444; font-weight: 600; margin-top: 30px;'>
                                    Pokud máte jakékoliv dotazy ohledně těchto změn, neváhejte nás kontaktovat na <a href='mailto:info@kubajadesigns.eu' style='color: #4c6444; text-decoration: none; font-weight: 700;'>info@kubajadesigns.eu</a>.
                                </p>
                                
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
                    </html>";

                    // Textová verze e-mailu
                    $altBody = "Aktualizace objednávky #" . $order['order_id'] . "\n\n";
                    $altBody .= "Vážený/á " . htmlspecialchars($edit_name) . ",\n\n";
                    $altBody .= "Vaše objednávka byla upravena. Provedené změny:\n\n";
                    foreach ($changes as $change) {
                        $altBody .= "• " . strip_tags($change) . "\n";
                    }
                    $altBody .= "\nPokud máte dotazy, kontaktujte nás na info@kubajadesigns.eu.\n\nS pozdravem, Tým KJD";

                $mail->Body = $emailBody;
                $mail->AltBody = $altBody;
        $mail->send();
            } catch (Exception $e) {
                error_log("Chyba při odesílání e-mailu: " . $e->getMessage());
                }
            }

            // Flash zpráva přes session, aby se zobrazila po přesměrování
            $successMsg = "Objednávka byla úspěšně aktualizována.";
            if ($send_notification && !empty($changes)) {
                $successMsg .= " Zákazník byl informován o změnách.";
            } elseif (!empty($changes) && !$send_notification) {
                $successMsg .= " Změny byly uloženy (notifikace nebyla odeslána).";
            }
            if ($revolutEmailSent) {
                $successMsg .= " Platební odkaz Revolut byl odeslán.";
            }
            $_SESSION['admin_success'] = $successMsg;
        } else {
            $error_message = "Nastala chyba při aktualizaci objednávky.";
            $_SESSION['admin_error'] = $error_message;
        }
        
        header("Location: admin_edit_order.php?id=$order_id");
        exit;
    } catch(Exception $e) {
        $_SESSION['admin_error'] = "Chyba při aktualizaci objednávky: " . $e->getMessage();
        header("Location: admin_edit_order.php?id=$order_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Úprava objednávky - KJD Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      
      /* Apple SF Pro Font */
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      body {
        background: #f8f9fa !important;
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      }
      
      main {
        background: none !important;
      }
      
      .cart-item {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 1px solid rgba(202,186,156,0.2);
        padding: 2rem;
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        width: 100%;
        box-sizing: border-box;
      }
      
      
      .cart-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      
      /* Ensure Bootstrap grid works correctly */
      .container-fluid .row,
      .container-fluid form > .row {
        display: flex !important;
        flex-wrap: wrap !important;
        margin-left: -15px !important;
        margin-right: -15px !important;
      }
      
      .container-fluid .row > [class*="col-"],
      .container-fluid form > .row > [class*="col-"] {
        padding-left: 15px !important;
        padding-right: 15px !important;
      }
      
      @media (min-width: 992px) {
        .container-fluid .row > .col-lg-8,
        .container-fluid form > .row > .col-lg-8 {
          flex: 0 0 66.66666667% !important;
          max-width: 66.66666667% !important;
        }
        
        .container-fluid .row > .col-lg-4,
        .container-fluid form > .row > .col-lg-4 {
          flex: 0 0 33.33333333% !important;
          max-width: 33.33333333% !important;
        }
      }
      
      @media (max-width: 991.98px) {
        .container-fluid .row > .col-lg-8,
        .container-fluid .row > .col-lg-4,
        .container-fluid form > .row > .col-lg-8,
        .container-fluid form > .row > .col-lg-4 {
          flex: 0 0 100% !important;
          max-width: 100% !important;
        }
      }
      
      .table-responsive {
        max-width: 100%;
        margin: 0 auto;
      }

      /* KJD enforced two-column layout */
      .kjd-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 420px;
        gap: 24px;
        align-items: start;
      }
      @media (max-width: 991.98px) {
        .kjd-layout {
          grid-template-columns: 1fr;
        }
      }
      
      .kjd-table {
        background: #fff;
        border: 2px solid #dee2e6;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 4px;
        overflow: hidden;
        width: 100%;
        margin: 0 auto;
      }
      
      .kjd-table th, .kjd-table td {
        border-right: 1px solid #dee2e6 !important;
        border-left: 1px solid #dee2e6 !important;
        padding: 0.75rem;
        font-size: 0.95rem;
      }
      
      .kjd-table th:first-child, .kjd-table td:first-child {
        border-left: none !important;
      }
      
      .kjd-table th:last-child, .kjd-table td:last-child {
        border-right: none !important;
      }
      
      .kjd-table th {
        background: #6c757d;
        color: #fff;
        font-weight: 600;
        border-bottom: 2px solid #5a6268;
        text-transform: none;
        font-size: 0.9rem;
        letter-spacing: 0;
        padding: 0.875rem 0.75rem;
      }
      
      .kjd-table tbody tr:nth-child(even) td {
        background: #f8f9fa;
      }
      
      .kjd-table tbody tr:hover td {
        background: #e9ecef;
      }
      
      .kjd-table tbody tr:not(:last-child) td {
        border-bottom: 1px solid #dee2e6 !important;
      }
      
      .kjd-table .table-light {
        background: #fff;
      }
      
      .kjd-table .table-light td {
        background: #fff !important;
        color: #333;
        font-weight: 700;
        font-size: 1rem;
        border-top: 2px solid #dee2e6 !important;
        border-bottom: none !important;
        padding: 1.25rem 0.75rem;
      }
      
      .kjd-table .table-light td:last-child {
        text-align: right;
      }
      
      .cart-item-title {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--kjd-dark-green);
        margin-bottom: 1.5rem;
        margin-top: 0;
        text-shadow: 1px 1px 2px rgba(16,40,32,0.1);
      }
      
      .kjd-form-label {
        font-weight: 600;
        color: var(--kjd-dark-green);
        margin-bottom: 0.5rem;
        font-size: 1rem;
      }
      
      .kjd-form-control, .kjd-form-select {
        border-radius: 8px;
        border: 2px solid var(--kjd-earth-green);
        font-size: 1rem;
        padding: 0.75rem;
        background: #fff;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
        font-weight: 500;
      }
      
      .kjd-form-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%234c6444' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        padding-right: 2.5rem;
      }
      
      .kjd-form-control:focus, .kjd-form-select:focus {
        border-color: var(--kjd-gold-brown);
        box-shadow: 0 0 0 0.2rem rgba(138,98,64,0.25);
      }
      
      .kjd-form-control.is-invalid, .kjd-form-select.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220,53,69,0.25);
      }
      
      .kjd-form-control.is-valid, .kjd-form-select.is-valid {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40,167,69,0.25);
      }
      
      .invalid-feedback {
        color: #dc3545;
        font-size: 0.875rem;
        font-weight: 600;
        margin-top: 0.25rem;
      }
      
      .valid-feedback {
        color: #28a745;
        font-size: 0.875rem;
        font-weight: 600;
        margin-top: 0.25rem;
      }
      
      .btn-kjd-primary {
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
        color: #fff;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(77,45,24,0.3);
      }
      
        .btn-kjd-primary:hover {
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(77,45,24,0.4);
      }
      
      .btn-kjd-primary:disabled {
        background: #ccc;
        color: #666;
        transform: none;
        box-shadow: none;
        cursor: not-allowed;
      }
      
      .btn-kjd-secondary {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        color: var(--kjd-dark-green);
        border: 2px solid var(--kjd-earth-green);
        padding: 0.75rem 2rem;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.3s ease;
      }
      
      .btn-kjd-secondary:hover {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76,100,68,0.3);
      }
      
      .kjd-card-header {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--kjd-dark-green);
        margin-bottom: 1rem;
        border-bottom: 2px solid var(--kjd-earth-green);
        padding-bottom: 0.75rem;
      }
      
      .alert {
        border-radius: 8px;
        font-size: 1rem;
        border: 2px solid;
      }
      
      .alert-success {
        background: rgba(40,167,69,0.1);
        border-color: #28a745;
        color: #155724;
      }
      
      .alert-danger {
        background: rgba(220,53,69,0.1);
        border-color: #dc3545;
        color: #721c24;
      }
      
      .alert-warning {
        background: rgba(255,193,7,0.1);
        border-color: #ffc107;
        color: #856404;
      }
      
      .badge {
        border-radius: 6px;
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
        font-weight: 600;
      }
      
      .badge.bg-warning {
        background: linear-gradient(135deg, #ffc107, #ff8c00) !important;
        color: #fff;
        font-weight: 600;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        margin-bottom: 0.5rem;
      }
      
      .cart-header p {
        color: var(--kjd-gold-brown);
        font-size: 1.1rem;
        font-weight: 500;
        opacity: 0.8;
        margin-bottom: 0;
      }
      
      .cart-item-title, .cart-product-name {
        color: var(--kjd-dark-green);
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1rem;
      }
      
      .color-indicator {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        margin-right: 8px;
        vertical-align: middle;
      }
      
      @media (max-width: 900px) {
        .cart-item, .kjd-table { padding: 1rem; }
        .kjd-header h1 { font-size: 2rem; }
        .kjd-header .d-flex { flex-direction: column; align-items: flex-start !important; }
        .kjd-header .btn { margin-top: 1rem; width: 100%; }
        .cart-item-title { font-size: 1.5rem; }
        .btn-kjd-primary, .btn-kjd-secondary { 
          width: 100%; 
          margin-bottom: 0.5rem; 
          padding: 1rem; 
          font-size: 1.1rem; 
        }
        .kjd-table th, .kjd-table td { padding: 0.75rem 0.5rem; font-size: 0.9rem; }
        .kjd-form-control, .kjd-form-select { font-size: 1rem; padding: 1rem; }
        .kjd-header p { font-size: 1rem; }
      }
      
      @media (max-width: 576px) {
        .kjd-header { padding: 1.5rem 0; }
        .kjd-header h1 { font-size: 1.75rem; }
        .cart-item { padding: 1rem; margin-bottom: 1rem; }
        .kjd-table { font-size: 0.85rem; }
        .kjd-table th, .kjd-table td { padding: 0.5rem; }
        .btn-kjd-primary, .btn-kjd-secondary { padding: 0.875rem; font-size: 1rem; }
      }
    </style>
</head>
<body>

    <!-- Navigation Menu -->
    <?php include 'admin_sidebar.php'; ?>
            
    <!-- Admin Header -->
    <div class="cart-header">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                                            <i class="fas fa-edit me-2"></i>Úprava objednávky <?php echo $order ? '#' . htmlspecialchars($order['order_id']) : '(žádná objednávka)'; ?>
                                        </h1>
                    <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);"><?php echo count($order_items_before_post ?? []) ?> produktů</p>
                                    </div>
                                    <a href="admin_orders.php" class="btn btn-kjd-secondary d-flex align-items-center">
                    <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                                        Zpět na seznam
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- Main Content -->
    <div class="container-fluid">
      <!-- Flash Messages -->
      <?php if ($success_message || $error_message): ?>
      <div class="row" style="display:grid;grid-template-columns:minmax(0,60%) minmax(0,40%);gap:20px;align-items:start;">
        <div class="col-12">
                <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show cart-item">
              <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show cart-item">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <strong>Chyba:</strong> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                </div>
      </div>
      <?php endif; ?>
                
                <?php if (!$order): ?>
      <div class="row" id="kjd-two-col" style="display:grid;grid-template-columns: 1fr 420px;gap:24px;align-items:start;">
        <div class="col-12">
          <div class="cart-item">
                        <div class="alert alert-warning">
                            <h4>Žádná objednávka k úpravě</h4>
                            <p>Pro úpravu objednávky zadejte ID objednávky v URL, například:</p>
                            <code>admin_edit_order.php?id=86</code>
            </div>
          </div>
                        </div>
                    </div>
                <?php else: ?>
      <form method="post" class="needs-validation" id="main-order-form" novalidate>
      <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
                <div class="cart-item">
                    <h2 class="cart-item-title">Zakoupené produkty</h2>
                    <div class="table-responsive">
                        <table class="table kjd-table">
                    <thead>
                        <tr>
                            <th>Název</th>
                                    <th>Varianty</th>
                            <th>Množství</th>
                            <th>Cena</th>
                                    <th>Celkem</th>
                        </tr>
                    </thead>
                    <tbody>
                                <?php foreach ($order_items_before_post as $item): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                            <?php if (!empty($item['is_preorder'])): ?>
                                                <span class="badge bg-warning">Předobjednávka</span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['color'])): ?>
                                                <div class="mt-1">
                                                    <span class="color-indicator" style="background-color: <?php echo htmlspecialchars($item['color']); ?>;"></span>
                                                    <?php 
                                                    if (function_exists('getColorName')) {
                                                        echo htmlspecialchars(getColorName($item['color']));
                                                    } else {
                                                        echo htmlspecialchars($item['color']);
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                </td>
                                        <td>
                                            <?php 
                                            $variantText = '';
                                            
                                            // Check if we have an old-style variant field
                                            if (!empty($item['variant'])) {
                                                $variantText = htmlspecialchars($item['variant']);
                                            } 
                                            // Check if we have a new-style variants array
                                            elseif (!empty($item['variants']) && is_array($item['variants'])) {
                                                $variantParts = [];
                                                foreach ($item['variants'] as $variantType => $variant) {
                                                    if (is_array($variant) && isset($variant['option'])) {
                                                        $variantParts[] = htmlspecialchars(ucfirst($variantType) . ': ' . $variant['option']);
                                                    } elseif (is_string($variant)) {
                                                        $variantParts[] = htmlspecialchars(ucfirst($variantType) . ': ' . $variant);
                                                    }
                                                }
                                                $variantText = implode("<br>", $variantParts);
                                            } else {
                                                $variantText = "Standardní";
                                            }
                                            
                                            echo $variantText;
                                            
                                            // Zobrazení barevných komponentů
                                            if (!empty($item['component_colors']) && is_array($item['component_colors'])) {
                                                echo "<br><small style='color: #666;'>";
                                                foreach ($item['component_colors'] as $componentName => $colorName) {
                                                    echo "<strong>" . htmlspecialchars($componentName) . ":</strong> " . htmlspecialchars($colorName) . "<br>";
                                                }
                                                echo "</small>";
                                            }
                                            ?>
                                </td>
                                        <td><?php echo (int)$item['quantity']; ?></td>
                                        <td><?php echo number_format($item['final_price'] ?? $item['price'], 0, ',', ' '); ?> Kč</td>
                                        <td><strong><?php echo number_format(($item['final_price'] ?? $item['price']) * $item['quantity'], 0, ',', ' '); ?> Kč</strong></td>
                            </tr>
                        <?php endforeach; ?>
                                <tr class="table-light">
                                    <td colspan="4" class="text-end"><strong>Celková cena:</strong></td>
                                    <td><strong><?php echo number_format($original_order['total_price'], 0, ',', ' '); ?> Kč</strong></td>
                                </tr>
                    </tbody>
                </table>
                    </div>
                </div>

                  <div class="cart-item">
                    <h2 class="cart-item-title">Stav objednávky a platby</h2>
                        <div class="mb-3">
                            <label for="status" class="kjd-form-label">Stav objednávky:</label>
                            <select class="form-select kjd-form-select" id="status" name="status">
                                <option value="pending" <?php echo ($order['status'] == 'pending') ? 'selected' : ''; ?>>Čeká na zpracování</option>
                                <option value="processing" <?php echo ($order['status'] == 'processing') ? 'selected' : ''; ?>>Zpracovává se</option>
                                <option value="preparing" <?php echo ($order['status'] == 'preparing') ? 'selected' : ''; ?>>Příprava k odeslání</option>
                                <option value="ready_for_pickup" <?php echo ($order['status'] == 'ready_for_pickup') ? 'selected' : ''; ?>>Připraveno k vyzvednutí</option>
                                <option value="shipped" <?php echo ($order['status'] == 'shipped') ? 'selected' : ''; ?>>Odesláno</option>
                                <option value="delivered" <?php echo ($order['status'] == 'delivered') ? 'selected' : ''; ?>>Doručeno</option>
                                <option value="cancelled" <?php echo ($order['status'] == 'cancelled') ? 'selected' : ''; ?>>Zrušeno</option>
                            </select>
                        </div>
                    <div class="mb-3">
                            <label for="payment_status" class="kjd-form-label">Stav platby:</label>
                            <select class="form-select kjd-form-select" id="payment_status" name="payment_status">
                                <option value="pending" <?php echo ($order['payment_status'] == 'pending') ? 'selected' : ''; ?>>Čeká na zaplacení</option>
                                <option value="paid" <?php echo ($order['payment_status'] == 'paid') ? 'selected' : ''; ?>>Zaplaceno</option>
                                <option value="refunded" <?php echo ($order['payment_status'] == 'refunded') ? 'selected' : ''; ?>>Vráceno</option>
                                <option value="cancelled" <?php echo ($order['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>Zrušeno</option>
                        </select>
                        </div>
                        <div class="mb-3">
                            <label for="tracking_code" class="kjd-form-label">Kód pro sledování zásilky:</label>
                            <input type="text" class="form-control kjd-form-control" id="tracking_code" name="tracking_code" value="<?php echo htmlspecialchars($order['tracking_code'] ?? ''); ?>">
                            <div class="form-text">Pokud není zadáno, bude použito ID objednávky.</div>
                        </div>
                        <?php if ($original_order['payment_method'] === 'revolut'): ?>
                        <div class="cart-item" style="padding:1.5rem; margin-bottom:0;">
                            <div class="kjd-card-header">Revolut platební odkaz</div>
                            <div>
                                <p>Platební metoda zákazníka: <strong>Revolut</strong></p>
                                <p>Zákazník bude moci zaplatit <strong>kartou, Apple Pay, Google Pay nebo přes Revolut aplikaci</strong>.</p>
                                <div class="mb-3">
                                    <label for="revolut_payment_link" class="kjd-form-label">Revolut platební odkaz:</label>
                                    <input type="text" class="form-control kjd-form-control" id="revolut_payment_link" name="revolut_payment_link" value="<?php echo htmlspecialchars($original_order['revolut_payment_link'] ?? ''); ?>" placeholder="https://pay.revolut.com/...">
                                </div>
                                <div class="d-grid gap-2 mb-3">
                                    <a href="https://business.revolut.com/merchant" class="btn btn-kjd-primary" target="_blank">
                                        <i class="fa fa-external-link me-2"></i>Vytvořit platební odkaz v Revolut
                                    </a>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="send_revolut_link" name="send_revolut_link">
                                    <label class="form-check-label" for="send_revolut_link">
                                        Odeslat platební odkaz zákazníkovi e-mailem
                                    </label>
                                </div>
                                <div class="alert alert-info" role="alert">
                                    <i class="fa fa-info-circle me-2"></i>
                                    Po vytvoření platebního odkazu v Revolutu jej vložte sem a zaškrtněte políčko pro odeslání e-mailu zákazníkovi.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($custom_lightbox_order) && $custom_lightbox_order): ?>
                    <div class="cart-item" style="background: #fff3cd; border: 2px solid #ffc107;">
                        <h2 class="cart-item-title" style="color: #856404;">
                            <i class="fas fa-lightbulb me-2"></i>Custom Lightbox
                        </h2>
                        <p style="color: #856404; font-weight: 600; margin-bottom: 1rem;">
                            Tato objednávka obsahuje Custom Lightbox. Pro správu návrhu a potvrzení použijte samostatnou stránku.
                        </p>
                        <a href="admin_custom_lightbox.php?id=<?= htmlspecialchars($custom_lightbox_order['id']) ?>" class="btn-kjd-primary">
                            <i class="fas fa-external-link-alt me-2"></i>
                            Spravovat Custom Lightbox
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="cart-item">
                        <h2 class="cart-item-title">Dodatečné informace</h2>
                    <div class="mb-3">
                            <label for="customer_note" class="kjd-form-label">Poznámka pro zákazníka</label>
                            <textarea name="customer_note" id="customer_note" class="form-control kjd-form-control" rows="3" placeholder="Tato poznámka bude přidána k objednávce a bude viditelná pro zákazníka"></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="send_notification" name="send_notification" value="1" checked>
                            <label class="form-check-label" for="send_notification">Odeslat notifikaci zákazníkovi</label>
                        </div>
                    </div>
                    <!-- Zrušení přes modal: původní blok odstraněn -->

                            </div>

                <!-- Right column -->
                <div class="col-lg-4">
                  <div class="cart-item">
                    <h2 class="cart-item-title">Údaje zákazníka</h2>
                        <div class="mb-3">
                            <label for="edit_name" class="kjd-form-label">Jméno a příjmení:</label>
                            <input type="text" class="form-control kjd-form-control" id="edit_name" name="edit_name" value="<?php echo htmlspecialchars($original_order['name'] ?? $original_order['first_name'] . ' ' . $original_order['last_name']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="kjd-form-label">E-mail:</label>
                            <input type="email" class="form-control kjd-form-control" id="edit_email" name="edit_email" value="<?php echo htmlspecialchars($original_order['email']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="kjd-form-label">Telefon:</label>
                            <input type="text" class="form-control kjd-form-control" id="edit_phone" name="edit_phone" value="<?php echo htmlspecialchars($original_order['phone'] ?? $original_order['phone_number']); ?>">
                        </div>
                    <div class="mb-3">
                            <label for="edit_delivery_method" class="kjd-form-label">Způsob doručení:</label>
                            <select class="form-select kjd-form-select" id="edit_delivery_method" name="edit_delivery_method" required>
                                <option value="Zásilkovna" <?php if ($original_order['delivery_method'] == 'Zásilkovna') echo 'selected'; ?>>Zásilkovna</option>
                                <option value="AlzaBox" <?php if ($original_order['delivery_method'] == 'AlzaBox') echo 'selected'; ?>>AlzaBox</option>
                                <option value="Jiná doprava" <?php if ($original_order['delivery_method'] == 'Jiná doprava') echo 'selected'; ?>>Jiná doprava</option>
                            </select>
                        </div>
                    </div>
                  
                  <div id="zasilkovna_fields" class="cart-item" style="display:none;">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i> Vyplňte prosím údaje o pobočce Zásilkovny. Všechna pole jsou povinná.
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="kjd-form-label">Název pobočky Zásilkovny:</label>
                                <input type="text" class="form-control kjd-form-control" name="zasilkovna_name" id="zasilkovna_name" value="<?php echo htmlspecialchars($zasilkovna_name ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="kjd-form-label">Ulice a číslo:</label>
                                <input type="text" class="form-control kjd-form-control" name="zasilkovna_street" id="zasilkovna_street" value="<?php echo htmlspecialchars($zasilkovna_street ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="kjd-form-label">Město:</label>
                                <input type="text" class="form-control kjd-form-control" name="zasilkovna_city" id="zasilkovna_city" value="<?php echo htmlspecialchars($zasilkovna_city ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="kjd-form-label">PSČ:</label>
                                <input type="text" class="form-control kjd-form-control" name="zasilkovna_zip" id="zasilkovna_zip" value="<?php echo htmlspecialchars($zasilkovna_zip ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                  <div id="alzabox_fields" class="cart-item" style="display:none;">
                    <div class="mb-3">
                            <label class="kjd-form-label">Kód/adresa AlzaBoxu:</label>
                            <input type="text" class="form-control kjd-form-control" name="alzabox_code" id="alzabox_code" value="<?php echo htmlspecialchars($alzabox_code ?? ''); ?>">
                        </div>
                    </div>
                  <div id="other_delivery_fields" class="cart-item" style="display:none;">
                        <div class="mb-3">
                            <label class="kjd-form-label">Ulice:</label>
                            <input type="text" class="form-control kjd-form-control" name="other_street" id="other_street" value="<?php echo htmlspecialchars($other_street ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="kjd-form-label">Město:</label>
                            <input type="text" class="form-control kjd-form-control" name="other_city" id="other_city" value="<?php echo htmlspecialchars($other_city ?? ''); ?>">
                        </div>
                    <div class="mb-3">
                            <label class="kjd-form-label">PSČ:</label>
                            <input type="text" class="form-control kjd-form-control" name="other_zip" id="other_zip" value="<?php echo htmlspecialchars($other_zip ?? ''); ?>">
                        </div>
                    </div>
                    <script>
                    // Funkce pro zobrazení/skrytí příslušných polí podle zvolené dopravy
                    function showDeliveryFields() {
                        var method = document.getElementById('edit_delivery_method').value;
                        document.getElementById('zasilkovna_fields').style.display = (method === 'Zásilkovna') ? 'block' : 'none';
                        document.getElementById('alzabox_fields').style.display = (method === 'AlzaBox') ? 'block' : 'none';
                        document.getElementById('other_delivery_fields').style.display = (method === 'Jiná doprava') ? 'block' : 'none';
                        
                        // Přidání třídy pro validaci
                        var requiredFields = [];
                        if (method === 'Zásilkovna') {
                            requiredFields = ['zasilkovna_name', 'zasilkovna_street', 'zasilkovna_city'];
                        } else if (method === 'AlzaBox') {
                            requiredFields = ['alzabox_code'];
                        } else if (method === 'Jiná doprava') {
                            requiredFields = ['other_street', 'other_city', 'other_zip'];
                        }
                        
                        // Reset všech required atributů
                        document.querySelectorAll('[name^="zasilkovna_"], [name^="alzabox_"], [name^="other_"]').forEach(function(field) {
                            field.required = false;
                            field.classList.remove('is-invalid');
                        });
                        
                        // Nastavení required pro aktuálně vybranou metodu
                        requiredFields.forEach(function(fieldId) {
                            var field = document.getElementsByName(fieldId)[0];
                            if (field) {
                                field.required = true;
                                // Přidání validace při změně
                                field.addEventListener('input', function() {
                                    this.classList.toggle('is-invalid', !this.value && this.required);
                                });
                            }
                        });
                    }
                    
                    // Inicializace událostí
                    document.addEventListener('DOMContentLoaded', function() {
                        // Inicializace zobrazení polí
                        showDeliveryFields();
                        
                        // Přidání změny způsobu doručení
                        document.getElementById('edit_delivery_method').addEventListener('change', showDeliveryFields);
                        
                        // Validace formuláře před odesláním - pouze pro hlavní formulář
                        const mainForm = document.getElementById('main-order-form');
                        if (mainForm) {
                            mainForm.addEventListener('submit', function(e) {
                                var isValid = true;
                                var method = document.getElementById('edit_delivery_method').value;
                                
                                // Validace Zásilkovna
                                if (method === 'Zásilkovna') {
                                    const requiredFields = ['zasilkovna_name', 'zasilkovna_street', 'zasilkovna_city'];
                                    requiredFields.forEach(function(fieldId) {
                                        const field = document.getElementById(fieldId);
                                        if (field && !field.value.trim()) {
                                            field.classList.add('is-invalid');
                                            isValid = false;
                                        }
                                    });
                                }
                                
                                // Validace AlzaBox
                                if (method === 'AlzaBox' && !document.getElementById('alzabox_code').value.trim()) {
                                    document.getElementById('alzabox_code').classList.add('is-invalid');
                                    isValid = false;
                                }
                                
                                // Validace Jiná doprava
                                if (method === 'Jiná doprava') {
                                    const requiredFields = ['other_street', 'other_city', 'other_zip'];
                                    requiredFields.forEach(function(fieldId) {
                                        const field = document.getElementById(fieldId);
                                        if (field && !field.value.trim()) {
                                            field.classList.add('is-invalid');
                                            isValid = false;
                                        }
                                    });
                                }
                                
                                if (!isValid) {
                                    e.preventDefault();
                                    alert('Vyplňte prosím všechna povinná pole pro zvolený způsob doručení.');
                                    return false;
                                }
                            });
                        }
                        
                        // Formulář pro upload finálního designu - bez validace ostatních polí
                        const uploadForm = document.getElementById('upload-final-design-form');
                        if (uploadForm) {
                            uploadForm.addEventListener('submit', function(e) {
                                // Validace pouze souboru, žádné další validace
                                const fileInput = document.getElementById('final_design');
                                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                                    e.preventDefault();
                                    alert('Prosím vyberte soubor k nahrání.');
                                    return false;
                                }
                                // Pokud je soubor vybrán, formulář se odešle bez dalších kontrol
                            });
                        }
                    });
                    </script>
                  
                  <div class="cart-item" style="text-align:center; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-kjd-primary w-100 mb-2">
                            <i class="fas fa-save me-2"></i> Uložit změny
                        </button>
                    <button type="button" class="btn btn-danger w-100 mb-2" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                      <i class="fas fa-times me-2"></i> Smazat objednávku
                    </button>
                    <button type="button" class="btn btn-kjd-secondary w-100 mb-2" onclick="window.history.back();">
                            <i class="fas fa-eye me-2"></i> Zpět na detail
                        </button>
                    <a href="admin_orders.php" class="btn btn-kjd-secondary w-100">
                            <i class="fas fa-list me-2"></i> Zpět na seznam
                        </a>
                  </div>
                </div>
                    </div>
                </form>
                <?php endif; ?>
      </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="cancelOrderModalLabel">Zrušit objednávku</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="cancel_reason" class="form-label">Důvod zrušení</label>
              <textarea id="cancel_reason" class="form-control" rows="4" placeholder="Uveďte důvod zrušení..." required></textarea>
            </div>
            <div class="alert alert-warning">Zákazník obdrží e-mailové oznámení o zrušení. Pokud byla platba uhrazena, přidáme informaci o vrácení peněz do 3 pracovních dnů.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavřít</button>
            <button type="button" class="btn btn-danger" id="confirmCancelBtn">Zrušit objednávku</button>
          </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Submit cancellation
        document.addEventListener('DOMContentLoaded', function() {
          const btn = document.getElementById('confirmCancelBtn');
          if (btn) {
            btn.addEventListener('click', function() {
              const reasonEl = document.getElementById('cancel_reason');
              const reason = reasonEl.value.trim();
              if (!reason) {
                reasonEl.focus();
                reasonEl.classList.add('is-invalid');
                return;
              }
              // Build and submit a hidden form
              const f = document.createElement('form');
              f.method = 'POST';
              f.action = 'admin_edit_order.php?id=<?php echo htmlspecialchars($order_id); ?>';
              const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='cancel_order'; f.appendChild(a);
              const r = document.createElement('input'); r.type='hidden'; r.name='cancel_reason'; r.value=reason; f.appendChild(r);
              const oid = document.createElement('input'); oid.type='hidden'; oid.name='order_id'; oid.value='<?php echo htmlspecialchars($order_id); ?>'; f.appendChild(oid);
              document.body.appendChild(f);
              f.submit();
            });
          }
        });
        // Loading states for buttons
        function setButtonLoading(button, isLoading) {
            if (isLoading) {
                button.disabled = true;
                button.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading...</span></div>Načítám...';
            } else {
                button.disabled = false;
                if (button.dataset.originalContent) {
                    button.innerHTML = button.dataset.originalContent;
                }
            }
        }

        // Store original button content on page load
        document.addEventListener('DOMContentLoaded', function() {
            const submitButton = document.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.dataset.originalContent = submitButton.innerHTML;
            }
            
            // Add loading state to form submission
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    if (submitButton) {
                        setButtonLoading(submitButton, true);
                    }
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const deliveryMethodSelect = document.getElementById('edit_delivery_method');
            const zasilkovnaFields = document.getElementById('zasilkovna_fields');
            const alzaboxFields = document.getElementById('alzabox_fields');
            const otherDeliveryFields = document.getElementById('other_delivery_fields');

            function toggleDeliveryFields() {
                const selectedMethod = deliveryMethodSelect.value;
                if (zasilkovnaFields) zasilkovnaFields.style.display = 'none';
                if (alzaboxFields) alzaboxFields.style.display = 'none';
                if (otherDeliveryFields) otherDeliveryFields.style.display = 'none';

                if (selectedMethod === 'Zásilkovna' && zasilkovnaFields) {
                    zasilkovnaFields.style.display = 'block';
                } else if (selectedMethod === 'AlzaBox' && alzaboxFields) {
                    alzaboxFields.style.display = 'block';
                } else if (selectedMethod === 'Jiná doprava' && otherDeliveryFields) {
                    otherDeliveryFields.style.display = 'block';
                }
            }

            if (deliveryMethodSelect) {
                deliveryMethodSelect.addEventListener('change', toggleDeliveryFields);
                // Initial call to set the correct fields on page load
                toggleDeliveryFields();
            }
        });
    </script>
</body>
</html> 