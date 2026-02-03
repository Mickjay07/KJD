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

// Kontrola a přidání sloupců do tabulky orders, pokud neexistují
try {
    $checkColumn1 = $conn->query("SHOW COLUMNS FROM orders LIKE 'is_custom_lightbox'");
    if ($checkColumn1->rowCount() == 0) {
        $conn->exec("ALTER TABLE orders ADD COLUMN is_custom_lightbox tinyint(1) DEFAULT 0");
    }
    
    $checkColumn2 = $conn->query("SHOW COLUMNS FROM orders LIKE 'custom_lightbox_order_id'");
    if ($checkColumn2->rowCount() == 0) {
        $conn->exec("ALTER TABLE orders ADD COLUMN custom_lightbox_order_id int(11) DEFAULT NULL");
    }
} catch (PDOException $e) {
    error_log("Chyba při kontrole/přidání sloupců do orders: " . $e->getMessage());
}

// Kontrola a přidání sloupců do tabulky custom_lightbox_orders, pokud neexistují
try {
    $columnsToAdd = [
        'delivery_method' => "ALTER TABLE custom_lightbox_orders ADD COLUMN delivery_method varchar(50) DEFAULT NULL",
        'delivery_address' => "ALTER TABLE custom_lightbox_orders ADD COLUMN delivery_address text DEFAULT NULL",
        'postal_code' => "ALTER TABLE custom_lightbox_orders ADD COLUMN postal_code varchar(20) DEFAULT NULL",
        'payment_method' => "ALTER TABLE custom_lightbox_orders ADD COLUMN payment_method varchar(50) DEFAULT NULL",
        'shipping_cost' => "ALTER TABLE custom_lightbox_orders ADD COLUMN shipping_cost decimal(10,2) DEFAULT 0.00",
        'wallet_used' => "ALTER TABLE custom_lightbox_orders ADD COLUMN wallet_used tinyint(1) DEFAULT 0",
        'wallet_amount' => "ALTER TABLE custom_lightbox_orders ADD COLUMN wallet_amount decimal(10,2) DEFAULT 0.00",
        'amount_to_pay' => "ALTER TABLE custom_lightbox_orders ADD COLUMN amount_to_pay decimal(10,2) DEFAULT NULL"
    ];
    
    foreach ($columnsToAdd as $columnName => $alterQuery) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE '$columnName'");
        if ($checkColumn->rowCount() == 0) {
            try {
                $conn->exec($alterQuery);
            } catch (PDOException $e) {
                error_log("Chyba při přidávání sloupce $columnName: " . $e->getMessage());
            }
        }
    }
} catch (PDOException $e) {
    error_log("Chyba při kontrole/přidání sloupců do custom_lightbox_orders: " . $e->getMessage());
}

// Kontrola, zda je to POST požadavek
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Pokud není POST, přesměruj na souhrn
    header('Location: order_summary_custom_lightbox.php');
    exit;
}

// Kontrola, zda máme data z custom_lightbox
if (!isset($_SESSION['custom_lightbox_order']) || !isset($_SESSION['custom_lightbox_order_data'])) {
    // Dočasně zobrazíme debug informace
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Debug</title></head><body>";
    echo "<h1>Chybějící data v session</h1>";
    echo "<p>custom_lightbox_order: " . (isset($_SESSION['custom_lightbox_order']) ? 'ANO' : 'NE') . "</p>";
    echo "<p>custom_lightbox_order_data: " . (isset($_SESSION['custom_lightbox_order_data']) ? 'ANO' : 'NE') . "</p>";
    echo "<p>REQUEST_METHOD: " . htmlspecialchars($_SERVER['REQUEST_METHOD']) . "</p>";
    echo "<p><a href='order_summary_custom_lightbox.php'>Zpět na souhrn</a></p>";
    echo "</body></html>";
    exit;
}

// Načtení dat
$orderData = $_SESSION['custom_lightbox_order'];
$orderDataFull = $_SESSION['custom_lightbox_order_data'];
$customerData = $orderDataFull['customer'];
$deliveryData = $orderDataFull['delivery'];
$paymentData = $orderDataFull['payment'];

$orderId = $orderData['order_id'];

// Výpočet dopravy
$shippingCost = 0;
if ($orderData['total_price'] < 1000) {
    $shippingCost = 90;
}

// Wallet balance calculation
$walletBalance = 0;
$walletDeduction = 0;
$amountToPay = $orderData['total_price'] + $shippingCost;

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $walletBalance = $wallet ? (float)$wallet['balance'] : 0;

        if (isset($_SESSION['use_wallet']) && $_SESSION['use_wallet'] && $walletBalance > 0) {
            $requestedAmount = (float)($_SESSION['wallet_amount'] ?? 0);
            $walletDeduction = min($requestedAmount, $walletBalance, $amountToPay);
            $amountToPay = $amountToPay - $walletDeduction;
        }
    } catch (PDOException $e) {
        error_log("Wallet balance error: " . $e->getMessage());
    }
}

// Příprava adresy
$address = '';
if ($deliveryData['method'] === 'Zásilkovna') {
    $address = $deliveryData['zasilkovna_name'] . ', ' . 
               $deliveryData['zasilkovna_street'] . ', ' . 
               $deliveryData['zasilkovna_zip'] . ' ' . $deliveryData['zasilkovna_city'];
} elseif ($deliveryData['method'] === 'AlzaBox') {
    $address = $deliveryData['alzabox_code'];
}

// Generování order_id pro hlavní tabulku orders
$mainOrderId = 'KJD-CUSTOM-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
$trackingCode = 'TRK-' . strtoupper(substr(md5(uniqid()), 0, 8));

// Zpracování platby - stejná logika jako process_order.php
try {
    // Kontrola existence nových sloupců
    $hasLogoShape = false;
    $hasAspectRatio = false;
    $hasBoxColor = false;
    $hasQuantity = false;
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'logo_shape'");
        $hasLogoShape = $checkCol->rowCount() > 0;
    } catch (PDOException $e) {}
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'aspect_ratio'");
        $hasAspectRatio = $checkCol->rowCount() > 0;
    } catch (PDOException $e) {}
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'box_color'");
        $hasBoxColor = $checkCol->rowCount() > 0;
    } catch (PDOException $e) {}
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'quantity'");
        $hasQuantity = $checkCol->rowCount() > 0;
    } catch (PDOException $e) {}
    
    // Aktualizace custom_lightbox_orders
    $updateFields = [
        "customer_name = ?",
        "customer_email = ?",
        "customer_phone = ?",
        "delivery_method = ?",
        "delivery_address = ?",
        "postal_code = ?",
        "payment_method = ?",
        "shipping_cost = ?",
        "wallet_used = ?",
        "wallet_amount = ?",
        "amount_to_pay = ?",
        "total_price = ?",
        "status = 'paid'",
        "payment_date = NOW()",
        "updated_at = NOW()"
    ];
    
    $updateValues = [
        $customerData['name'],
        $customerData['email'],
        $customerData['phone'],
        $deliveryData['method'],
        $address,
        $deliveryData['method'] === 'Zásilkovna' ? ($deliveryData['zasilkovna_zip'] ?? '') : '',
        $paymentData['method'],
        $shippingCost,
        $walletDeduction > 0 ? 1 : 0,
        $walletDeduction,
        $amountToPay,
        $orderData['total_price'] + $shippingCost
    ];
    
    if ($hasLogoShape && isset($orderData['logo_shape'])) {
        $updateFields[] = "logo_shape = ?";
        $updateValues[] = $orderData['logo_shape'];
    }
    if ($hasAspectRatio && isset($orderData['aspect_ratio'])) {
        $updateFields[] = "aspect_ratio = ?";
        $updateValues[] = $orderData['aspect_ratio'];
    }
    if ($hasBoxColor && isset($orderData['box_color'])) {
        $updateFields[] = "box_color = ?";
        $updateValues[] = $orderData['box_color'];
    }
    if ($hasQuantity && isset($orderData['quantity'])) {
        $updateFields[] = "quantity = ?";
        $updateValues[] = $orderData['quantity'];
    }
    
    $updateValues[] = $orderId;
    
    $sql = "UPDATE custom_lightbox_orders SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($updateValues);
        
        // Vložení do hlavní tabulky orders pro zobrazení v admin_orders.php
        $quantity = isset($orderData['quantity']) ? (int)$orderData['quantity'] : 1;
        $productData = [
            'name' => 'Custom Lightbox - ' . ($orderData['size'] === 'small' ? 'Malé' : ($orderData['size'] === 'medium' ? 'Střední' : 'Velké')) . ($quantity > 1 ? ' × ' . $quantity : ''),
            'quantity' => $quantity,
            'price' => $orderData['total_price'],
            'image_path' => $orderData['image_path'],
            'size' => $orderData['size'],
            'has_stand' => $orderData['has_stand'],
            'is_custom_lightbox' => true,
            'custom_lightbox_order_id' => $orderId
        ];
        
        if (isset($orderData['logo_shape'])) {
            $productData['logo_shape'] = $orderData['logo_shape'];
        }
        if (isset($orderData['aspect_ratio'])) {
            $productData['aspect_ratio'] = $orderData['aspect_ratio'];
        }
        if (isset($orderData['box_color'])) {
            $productData['box_color'] = $orderData['box_color'];
        }
        if (isset($orderData['quantity'])) {
            $productData['order_quantity'] = $orderData['quantity'];
        }
        
        $productsJson = json_encode([$productData], JSON_UNESCAPED_UNICODE);
        
        // Zkontrolujeme, zda sloupce existují, pokud ne, použijeme dotaz bez nich
        $hasCustomColumns = true;
        try {
            $checkCol1 = $conn->query("SHOW COLUMNS FROM orders LIKE 'is_custom_lightbox'");
            $checkCol2 = $conn->query("SHOW COLUMNS FROM orders LIKE 'custom_lightbox_order_id'");
            if ($checkCol1->rowCount() == 0 || $checkCol2->rowCount() == 0) {
                $hasCustomColumns = false;
            }
        } catch (PDOException $e) {
            $hasCustomColumns = false;
        }
        
        $zasilkovnaName = $deliveryData['method'] === 'Zásilkovna' ? ($deliveryData['zasilkovna_name'] ?? '') : '';
        
        if ($hasCustomColumns) {
            $stmt = $conn->prepare("
                INSERT INTO orders (
                    user_id, order_id, email, phone_number, name, delivery_method,
                    zasilkovna_name, address, postal_code, total_price, status,
                    tracking_code, note, products_json, payment_method, payment_status,
                    shipping_cost, wallet_used, wallet_amount, amount_to_pay, is_custom_lightbox, custom_lightbox_order_id, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, 1, ?, NOW()
                )
            ");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $mainOrderId,
                $customerData['email'],
                $customerData['phone'],
                $customerData['name'],
                $deliveryData['method'],
                $zasilkovnaName,
                $address,
                $postalCode,
                $orderData['total_price'] + $shippingCost,
                'Přijato',
                $trackingCode,
                $customerData['note'] ?? '',
                $productsJson,
                $paymentData['method'],
                'pending',
                $shippingCost,
                $walletDeduction > 0 ? 1 : 0,
                $walletDeduction,
                $amountToPay,
                $orderId
            ]);
        } else {
            // Fallback - bez custom_lightbox_order_id
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $mainOrderId,
                $customerData['email'],
                $customerData['phone'],
                $customerData['name'],
                $deliveryData['method'],
                $zasilkovnaName,
                $address,
                $postalCode,
                $orderData['total_price'] + $shippingCost,
                'Přijato',
                $trackingCode,
                $customerData['note'] ?? '',
                $productsJson,
                $paymentData['method'],
                'pending',
                $shippingCost,
                $walletDeduction > 0 ? 1 : 0,
                $walletDeduction,
                $amountToPay
            ]);
        }
        
        // Deduct wallet balance if used
        if ($walletDeduction > 0 && isset($_SESSION['user_id'])) {
            try {
                $stmt = $conn->prepare("UPDATE user_wallets SET balance = balance - ? WHERE user_id = ?");
                $stmt->execute([$walletDeduction, $_SESSION['user_id']]);
                
                $stmt = $conn->prepare("
                    INSERT INTO wallet_transactions 
                    (user_id, type, amount, description, order_id) 
                    VALUES (?, 'debit', ?, ?, ?)
                ");
                $description = "Platba za Custom Lightbox objednávku $mainOrderId";
                $stmt->execute([$_SESSION['user_id'], $walletDeduction, $description, $mainOrderId]);
            } catch (PDOException $e) {
                error_log("Wallet deduction error: " . $e->getMessage());
            }
        }
        
        // Odeslání emailu zákazníkovi (zatím bez návrhu - návrh přijde až po nahrání adminem)
        sendOrderConfirmationEmail($customerData, $mainOrderId, $trackingCode, $amountToPay, $deliveryData, $paymentData, $orderData);
        
        // Odeslání upozornění adminovi
        sendAdminNotificationEmail($customerData, $mainOrderId, $trackingCode, $amountToPay, $deliveryData, $paymentData, $orderData);
        
        // Vyčištění session
        unset($_SESSION['custom_lightbox_order']);
        unset($_SESSION['custom_lightbox_order_data']);
        unset($_SESSION['use_wallet']);
        unset($_SESSION['wallet_amount']);
        
        // Uložení do session pro confirmation stránku
        $_SESSION['custom_lightbox_confirmation'] = [
            'order_id' => $mainOrderId,
            'custom_order_id' => $orderId,
            'tracking_code' => $trackingCode,
            'total' => $amountToPay,
            'email' => $customerData['email'],
            'name' => $customerData['name']
        ];
        
    // Přesměrování na potvrzení
    header('Location: order_confirmation_custom_lightbox.php');
    exit;
    
} catch (PDOException $e) {
    error_log("Order processing error: " . $e->getMessage());
    error_log("Error details: " . print_r($e->getTraceAsString(), true));
    error_log("SQL State: " . $e->getCode());
    error_log("Error Info: " . print_r($e->errorInfo, true));
    
    // Uložení chybové hlášky do session
    $errorMsg = "Chyba při zpracování objednávky: " . htmlspecialchars($e->getMessage()) . ". Zkuste to prosím znovu nebo kontaktujte podporu.";
    $_SESSION['order_error'] = $errorMsg;
    
    // Zajištění, že session je uložena před přesměrováním
    session_write_close();
    
    // Dočasně zobrazíme chybu přímo (pro debug)
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Chyba</title></head><body>";
    echo "<h1>Chyba při zpracování objednávky</h1>";
    echo "<p style='color: red; font-weight: bold;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>SQL State: " . htmlspecialchars($e->getCode()) . "</p>";
    echo "<pre>" . htmlspecialchars(print_r($e->errorInfo, true)) . "</pre>";
    echo "<p><a href='order_summary_custom_lightbox.php'>Zpět na souhrn</a></p>";
    echo "</body></html>";
    exit;
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    $_SESSION['order_error'] = "Chyba při zpracování objednávky: " . htmlspecialchars($e->getMessage()) . ". Zkuste to prosím znovu.";
    session_write_close();
    
    // Dočasně zobrazíme chybu přímo (pro debug)
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Chyba</title></head><body>";
    echo "<h1>Chyba při zpracování objednávky</h1>";
    echo "<p style='color: red; font-weight: bold;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='order_summary_custom_lightbox.php'>Zpět na souhrn</a></p>";
    echo "</body></html>";
    exit;
}

function sendOrderConfirmationEmail($customerData, $orderId, $trackingCode, $total, $deliveryData, $paymentData, $orderData) {
    $to = $customerData['email'];
    $subject = "Potvrzení objednávky Custom Lightbox #$orderId - KJD";
    
    $imageUrl = 'https://kubajadesigns.eu/' . $orderData['image_path'];
    
    $sizes = [
        'small' => 'Malé (15x15 cm)',
        'medium' => 'Střední (20x20 cm)',
        'large' => 'Velké (25x25 cm)'
    ];
    
    $sizeText = $sizes[$orderData['size']] ?? $orderData['size'];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #fff; padding: 30px; border: 2px solid #4c6444; }
            .footer { background: #f5f0e8; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
            .alert { background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Potvrzení objednávky Custom Lightbox</h1>
            </div>
            <div class='content'>
                <p>Dobrý den <strong>" . htmlspecialchars($customerData['name']) . "</strong>,</p>
                
                <p>Děkujeme za vaši objednávku Custom Lightbox!</p>
                
                <h3>Detaily objednávky:</h3>
                <ul>
                    <li><strong>Číslo objednávky:</strong> $orderId</li>
                    <li><strong>Velikost:</strong> " . htmlspecialchars($sizeText) . "</li>
                    <li><strong>Podstavec:</strong> " . ($orderData['has_stand'] ? 'Ano' : 'Ne') . "</li>
                    <li><strong>Cena:</strong> " . number_format($total, 0, ',', ' ') . " Kč</li>
                </ul>
                
                <p><strong>Váš nahraný obrázek:</strong></p>
                <img src='" . htmlspecialchars($imageUrl) . "' alt='Váš obrázek' style='max-width: 100%; border-radius: 8px; margin: 10px 0;'>
                
                <div class='alert'>
                    <strong>⚠️ Důležité:</strong> Prosím, zkontrolujte si složku Spam/Promo, pokud tento email nevidíte v doručené poště!
                </div>
                
                <p><strong>Co bude dál?</strong></p>
                <p>Nyní připravíme návrh vašeho Custom Lightbox. Jakmile bude návrh hotový, obdržíte email s finálním návrhem k potvrzení. Po vašem potvrzení zahájíme výrobu.</p>
                
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

function sendAdminNotificationEmail($customerData, $orderId, $trackingCode, $total, $deliveryData, $paymentData, $orderData) {
    $to = 'mickeyjarolim3@gmail.com';
    $subject = "Nová Custom Lightbox objednávka #$orderId";
    
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
                <h1>Nová Custom Lightbox objednávka</h1>
            </div>
            <div class='content'>
                <p><strong>Číslo objednávky:</strong> $orderId</p>
                <p><strong>Zákazník:</strong> " . htmlspecialchars($customerData['name']) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($customerData['email']) . "</p>
                <p><strong>Telefon:</strong> " . htmlspecialchars($customerData['phone']) . "</p>
                <p><strong>Velikost:</strong> " . htmlspecialchars($orderData['size']) . "</p>
                <p><strong>Podstavec:</strong> " . ($orderData['has_stand'] ? 'Ano' : 'Ne') . "</p>
                <p><strong>Cena:</strong> " . number_format($total, 0, ',', ' ') . " Kč</p>
                <p><strong>Obrázek:</strong> " . htmlspecialchars($orderData['image_path']) . "</p>
                <p>Prosím, připravte návrh a nahrajte ho v admin panelu.</p>
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