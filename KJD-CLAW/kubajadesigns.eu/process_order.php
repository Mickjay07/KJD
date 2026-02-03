<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Kontrola, zda máme data z order_summary
if (!isset($_SESSION['order_data']) || empty($_SESSION['cart'])) {
    // DEBUG: Show why we are redirecting
    echo "<h1>Session Debug</h1>";
    echo "<p>Order Data set: " . (isset($_SESSION['order_data']) ? 'YES' : 'NO') . "</p>";
    echo "<p>Cart empty: " . (empty($_SESSION['cart']) ? 'YES' : 'NO') . "</p>";
    echo "<pre>";
    var_dump($_SESSION);
    echo "</pre>";
    exit;
    // header('Location: cart.php');
    // exit;
}

$orderData = $_SESSION['order_data'];
$customerData = $orderData['customer'];
$deliveryData = $orderData['delivery'];
$paymentData = $orderData['payment'];

// Generování order_id a tracking kódu
$orderId = 'KJD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
$trackingCode = 'TRK-' . strtoupper(substr(md5(uniqid()), 0, 8));

// Výpočet cen
$total = 0;
$shippingCost = 0;
$discount = 0;
$discountCode = '';

foreach ($_SESSION['cart'] as $cartKey => $productData) {
    $quantity = (int)($productData['quantity'] ?? 0);
    $price = (float)($productData['final_price'] ?? $productData['price'] ?? 0);
    $total += $price * $quantity;
}

// Aplikace slevy
if (isset($_SESSION['applied_discount'])) {
    $discountPercent = (int)$_SESSION['applied_discount']['discount_percent'];
    $discount = ($total * $discountPercent) / 100;
    $discountCode = $_SESSION['applied_discount']['code'];
}

// Výpočet dopravy
// Check if free shipping discount code is applied
$hasFreeShippingCode = false;
if (isset($_SESSION['applied_discount'])) {
    $appliedCode = strtoupper(trim($_SESSION['applied_discount']['code']));
    // Codes that grant free shipping
    $freeShippingCodes = ['DOPRAVAZDARMA', 'FREESHIP', 'SHIPFREE'];
    if (in_array($appliedCode, $freeShippingCodes)) {
        $hasFreeShippingCode = true;
    }
}

if ($hasFreeShippingCode) {
    // Free shipping code applied - no shipping cost
    $shippingCost = 0;
} elseif ($total < 1000) {
    $shippingCost = 100;
}

$finalTotal = $total - $discount + $shippingCost;

// Get user wallet balance and calculate wallet deduction based on user selection
$walletBalance = 0;
$walletDeduction = 0;
$amountToPay = $finalTotal;

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $walletBalance = $wallet ? (float)$wallet['balance'] : 0;

        // Use wallet deduction based on user selection from cart
        if (isset($_SESSION['use_wallet']) && $_SESSION['use_wallet'] && $walletBalance > 0 && $finalTotal > 0) {
            $requestedAmount = (float)($_SESSION['wallet_amount'] ?? 0);
            $walletDeduction = min($requestedAmount, $walletBalance, $finalTotal);
            $amountToPay = $finalTotal - $walletDeduction;
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

// Příprava produktů jako JSON
$productsJson = json_encode($_SESSION['cart'], JSON_UNESCAPED_UNICODE);

// Kontrola preorder
$isPreorder = 0;
$releaseDate = null;

foreach ($_SESSION['cart'] as $item) {
    if (isset($item['is_preorder']) && $item['is_preorder'] == 1) {
        $isPreorder = 1;
        if (!empty($item['available_from']) && $item['available_from'] != '0000-00-00 00:00:00') {
            $releaseDate = date('Y-m-d', strtotime($item['available_from']));
        }
        break;
    }
}

// Vložení objednávky do databáze
try {
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, order_id, email, phone_number, name, delivery_method,
            zasilkovna_name, address, postal_code, total_price, status,
            tracking_code, note, products_json, is_preorder, release_date,
            payment_method, payment_status, shipping_cost, wallet_used, wallet_amount, amount_to_pay, 
            packeta_branch_id, packeta_packet_id, packeta_barcode, packeta_tracking_url, packeta_label_printed,
            created_at
        ) VALUES (
            :user_id, :order_id, :email, :phone_number, :name, :delivery_method,
            :zasilkovna_name, :address, :postal_code, :total_price, :status,
            :tracking_code, :note, :products_json, :is_preorder, :release_date,
            :payment_method, :payment_status, :shipping_cost, :wallet_used, :wallet_amount, :amount_to_pay,
            :packeta_branch_id, :packeta_packet_id, :packeta_barcode, :packeta_tracking_url, :packeta_label_printed,
            NOW()
        )
    ");
    
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'] ?? null,
        ':order_id' => $orderId,
        ':email' => $customerData['email'],
        ':phone_number' => $customerData['phone'],
        ':name' => $customerData['name'],
        ':delivery_method' => $deliveryData['method'],
        ':zasilkovna_name' => $deliveryData['zasilkovna_name'] ?? '',
        ':address' => $address,
        ':postal_code' => $deliveryData['zasilkovna_zip'] ?? '',
        ':total_price' => $finalTotal,
        ':status' => 'Přijato',
        ':tracking_code' => $trackingCode,
        ':note' => $customerData['note'],
        ':products_json' => $productsJson,
        ':is_preorder' => $isPreorder,
        ':release_date' => $releaseDate,
        ':payment_method' => $paymentData['method'],
        ':payment_status' => 'pending',
        ':shipping_cost' => $shippingCost,
        ':wallet_used' => $walletDeduction > 0 ? 1 : 0,
        ':wallet_amount' => $walletDeduction,
        ':amount_to_pay' => $amountToPay,
        ':packeta_branch_id' => $deliveryData['packeta_branch_id'] ?? null,
        ':packeta_packet_id' => null,
        ':packeta_barcode' => null,
        ':packeta_tracking_url' => null,
        ':packeta_label_printed' => 0
    ]);
    
    $orderDbId = $conn->lastInsertId();
    
    // Deduct wallet balance if user has wallet and used it
    if ($walletDeduction > 0 && isset($_SESSION['user_id'])) {
        try {
            // Update wallet balance
            $stmt = $conn->prepare("
                UPDATE user_wallet 
                SET balance = balance - ? 
                WHERE user_id = ?
            ");
            $stmt->execute([$walletDeduction, $_SESSION['user_id']]);
            
            // Record wallet transaction
            $stmt = $conn->prepare("
                INSERT INTO wallet_transactions 
                (user_id, type, amount, description, order_id) 
                VALUES (?, 'debit', ?, ?, ?)
            ");
            $description = "Platba za objednávku $orderId";
            $stmt->execute([
                $_SESSION['user_id'], 
                $walletDeduction, 
                $description,
                $orderId
            ]);
            
        } catch (PDOException $e) {
            error_log("Wallet deduction error: " . $e->getMessage());
            // Don't fail the order if wallet deduction fails
        }
    }
    
    // Uložení order_id do session pro confirmation stránku
    $_SESSION['order_confirmation'] = [
        'order_id' => $orderId,
        'tracking_code' => $trackingCode,
        'total' => $finalTotal,
        'wallet_deduction' => $walletDeduction,
        'amount_to_pay' => $amountToPay,
        'email' => $customerData['email'],
        'name' => $customerData['name']
    ];
    
    // ALWAYS send admin notification (for all payment methods)
    sendAdminNotificationEmail($customerData, $orderId, $trackingCode, $finalTotal, $deliveryData, $paymentData, $productsJson, $amountToPay, $walletDeduction);
    
    // Check if payment method is GoPay
    if ($paymentData['method'] === 'gopay') {
        // Store order ID in session for gopay_init.php
        $_SESSION['gopay_pending_order_id'] = $orderId;
        
        // Send initial order confirmation email (before payment)
        // Customer will receive a second email with invoice after payment in gopay_notify.php
        sendOrderConfirmationEmail($customerData, $orderId, $trackingCode, $finalTotal, $deliveryData, $paymentData, $productsJson, $amountToPay, $walletDeduction);
        
        // DON'T clear cart yet - we need it if GoPay fails
        // Only clear order_data to prevent re-submission
        unset($_SESSION['order_data']);
        
        error_log("GoPay order created: $orderId - emails sent, redirecting to payment");
        
        // Redirect to GoPay initialization
        header('Location: gopay_init.php');
        exit;
    }
    
    // For non-GoPay payments, send customer email and clean up as usual
    sendOrderConfirmationEmail($customerData, $orderId, $trackingCode, $finalTotal, $deliveryData, $paymentData, $productsJson, $amountToPay, $walletDeduction);
    
    // Vyčištění košíku a order_data (pouze pro non-GoPay platby)
    unset($_SESSION['cart']);
    unset($_SESSION['order_data']);
    unset($_SESSION['applied_discount']);
    unset($_SESSION['use_wallet']);
    unset($_SESSION['wallet_amount']);
    
    // Přesměrování na potvrzení (pro ostatní platební metody)
    header('Location: order_confirmation.php');
    exit;
    
} catch (PDOException $e) {
    error_log("Order processing error: " . $e->getMessage());
    $_SESSION['order_error'] = "Chyba DB: " . $e->getMessage(); // DEBUG: Show real error
    header('Location: order_summary.php');
    exit;
}

function sendOrderConfirmationEmail($customerData, $orderId, $trackingCode, $total, $deliveryData, $paymentData, $productsJson, $amountToPay = null, $walletDeduction = 0) {
    $to = $customerData['email'];
    $subject = "Potvrzení objednávky #$orderId - KJD";
    
    // Pokud není předáno, použij total
    if ($amountToPay === null) {
        $amountToPay = $total;
    }
    
    // Parse products from JSON
    $products = json_decode($productsJson, true);
    $productsList = '';
    if ($products) {
        foreach ($products as $product) {
            // Zobrazit barvy, pokud existují
            $colorInfo = '';
            if (!empty($product['component_colors']) && is_array($product['component_colors'])) {
                $colorParts = [];
                foreach ($product['component_colors'] as $component => $color) {
                    $colorParts[] = htmlspecialchars($component) . ': ' . htmlspecialchars($color);
                }
                if (!empty($colorParts)) {
                    $colorInfo = '<div class="product-colors" style="margin-top: 8px; padding: 8px; background: rgba(202,186,156,0.1); border-radius: 6px; font-size: 13px; color: #4c6444;"><strong>Barvy:</strong> ' . implode(' | ', $colorParts) . '</div>';
                }
            } elseif (!empty($product['selected_color'])) {
                $colorInfo = '<div class="product-colors" style="margin-top: 8px; padding: 8px; background: rgba(202,186,156,0.1); border-radius: 6px; font-size: 13px; color: #4c6444;"><strong>Barva:</strong> ' . htmlspecialchars($product['selected_color']) . '</div>';
            }
            
            $productsList .= "
            <div class='product-item'>
                <div class='product-name'>{$product['name']}</div>
                <div class='product-details'>
                    Množství: {$product['quantity']}x | 
                    Cena: " . number_format($product['final_price'] ?? $product['price'], 0, ',', ' ') . " Kč | 
                    Celkem: " . number_format(($product['final_price'] ?? $product['price']) * $product['quantity'], 0, ',', ' ') . " Kč
                </div>
                {$colorInfo}
            </div>";
        }
    }
    
    // Delivery address info
    $deliveryAddress = '';
    if ($deliveryData['method'] === 'Zásilkovna') {
        $deliveryAddress = "
        <div class='delivery-info'>
            <h4>Adresa Zásilkovny</h4>
            <p><strong>Název:</strong> " . htmlspecialchars($deliveryData['zasilkovna_name']) . "</p>
            <p><strong>Adresa:</strong> " . htmlspecialchars($deliveryData['zasilkovna_street']) . "</p>
            <p><strong>Město:</strong> " . htmlspecialchars($deliveryData['zasilkovna_zip']) . " " . htmlspecialchars($deliveryData['zasilkovna_city']) . "</p>
        </div>";
    } else {
        $deliveryAddress = "
        <div class='delivery-info'>
            <h4>Dodací adresa</h4>
            <p><strong>Jméno:</strong> " . htmlspecialchars($customerData['name']) . "</p>
            <p><strong>Adresa:</strong> " . htmlspecialchars($deliveryData['address']) . "</p>
            <p><strong>Město:</strong> " . htmlspecialchars($deliveryData['zip']) . " " . htmlspecialchars($deliveryData['city']) . "</p>
        </div>";
    }
    
    $message = "
    <!DOCTYPE html>
    <html lang='cs'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: Arial, Helvetica, sans-serif; 
                color: #102820; 
                margin: 0; 
                padding: 0; 
                background-color: #f8f9fa;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            table {
                border-collapse: collapse;
                width: 100%;
            }
            .email-wrapper {
                background-color: #f8f9fa;
                padding: 20px 0;
            }
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: #ffffff; 
                border-radius: 8px; 
                overflow: hidden;
            }
            .header { 
                background-color: #102820; 
                color: #ffffff; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: bold; 
            }
            .header .logo { 
                font-size: 32px; 
                font-weight: bold; 
                margin-bottom: 10px;
            }
            .content { 
                padding: 30px 25px; 
                line-height: 1.6;
            }
            .content h2 { 
                color: #102820; 
                font-size: 24px; 
                font-weight: bold; 
                margin: 0 0 20px 0;
            }
            .content h3 { 
                color: #4D2D18; 
                font-size: 20px; 
                font-weight: bold; 
                margin: 25px 0 15px 0;
                padding-bottom: 8px;
                border-bottom: 2px solid #CABA9C;
            }
            .order-details { 
                background-color: #ffffff; 
                padding: 25px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border: 2px solid #4c6444;
            }
            .order-details h3 { 
                color: #102820; 
                margin-top: 0; 
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #4c6444; 
            }
            .detail-row { 
                margin-bottom: 12px; 
                padding: 8px 0;
                border-bottom: 1px solid #e0e0e0;
            }
            .detail-row:last-child { 
                border-bottom: none; 
                margin-bottom: 0;
            }
            .detail-label { 
                font-weight: bold; 
                color: #102820;
                display: inline-block;
                width: 45%;
            }
            .detail-value { 
                font-weight: 600; 
                color: #4c6444;
                display: inline-block;
                width: 50%;
                text-align: right;
            }
            .payment-info { 
                background-color: #f5f0e8; 
                padding: 20px; 
                border-radius: 8px; 
                border-left: 4px solid #8A6240;
                margin: 20px 0;
            }
            .payment-info p { 
                margin: 8px 0; 
                font-weight: 600;
            }
            .highlight { 
                background-color: #8A6240; 
                color: #ffffff; 
                padding: 3px 8px; 
                border-radius: 4px; 
                font-weight: bold;
            }
            .footer { 
                background-color: #4D2D18; 
                color: #ffffff; 
                padding: 25px 20px; 
                text-align: center; 
                font-size: 14px;
            }
            .footer p { 
                margin: 5px 0;
            }
            .social-links { 
                margin: 15px 0;
            }
            .social-links a { 
                color: #CABA9C; 
                text-decoration: none; 
                margin: 0 10px;
                font-weight: 600;
            }
            .products-section {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #8A6240;
                margin: 20px 0;
            }
            .product-item {
                background-color: #ffffff;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 10px;
                border: 1px solid #e9ecef;
            }
            .product-item:last-child {
                margin-bottom: 0;
            }
            .product-name {
                font-weight: bold;
                color: #102820;
                font-size: 16px;
                margin-bottom: 8px;
            }
            .product-details {
                color: #4c6444;
                font-size: 14px;
                margin-top: 5px;
            }
            .product-colors {
                margin-top: 8px;
                padding: 8px;
                background-color: #f5f0e8;
                border-radius: 6px;
                font-size: 13px;
                color: #4c6444;
            }
            .customer-info {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #8A6240;
                margin: 20px 0;
            }
            .delivery-info {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #8A6240;
                margin: 20px 0;
            }
            .customer-info h4, .delivery-info h4 {
                color: #102820;
                font-weight: bold;
                margin: 0 0 15px 0;
                font-size: 18px;
            }
            .customer-info p, .delivery-info p {
                margin: 8px 0;
                color: #4c6444;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='email-container'>
                <div class='header'>
                    <div class='logo'>KJ<span style='color: #CABA9C;'>D</span></div>
                    <h1>Potvrzení objednávky</h1>
                </div>
            
            <div class='content'>
                <h2>Dobrý den, " . htmlspecialchars($customerData['name']) . "!</h2>
                
                <p style='font-size: 16px; color: #4c6444; font-weight: 600;'>Děkujeme za vaši objednávku! Zde jsou detaily vaší objednávky:</p>
                
                <div class='order-details'>
                    <h3>Detaily objednávky</h3>
                    <div class='detail-row'>
                        <span class='detail-label'>Číslo objednávky:</span>
                        <span class='detail-value highlight'>$orderId</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Sledovací kód:</span>
                        <span class='detail-value highlight'>$trackingCode</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Celková částka:</span>
                        <span class='detail-value' style='font-size: 18px; font-weight: 800; color: #102820;'>" . number_format($total, 0, ',', ' ') . " Kč</span>
                    </div>";
                    
                    // Add wallet deduction if used
                    if ($walletDeduction > 0) {
                        $message .= "
                    <div class='detail-row' style='background: rgba(202,186,156,0.2); border: 2px solid #CABA9C; border-radius: 8px; padding: 12px; margin: 8px 0;'>
                        <span class='detail-label'><i class='fas fa-wallet' style='margin-right: 8px;'></i>Zůstatek z účtu:</span>
                        <span class='detail-value' style='color: #4c6444; font-weight: 700;'>-" . number_format($walletDeduction, 0, ',', ' ') . " Kč</span>
                    </div>
                    <div class='detail-row' style='background: linear-gradient(135deg, #4c6444, #102820); color: #fff; border-radius: 8px; padding: 12px; margin: 8px 0;'>
                        <span class='detail-label' style='color: rgba(255,255,255,0.9);'>K úhradě:</span>
                        <span class='detail-value' style='color: #fff; font-weight: 800; font-size: 18px;'>" . number_format($amountToPay, 0, ',', ' ') . " Kč</span>
                    </div>";
                    } else {
                        $message .= "
                    <div class='detail-row' style='background: linear-gradient(135deg, #4c6444, #102820); color: #fff; border-radius: 8px; padding: 12px; margin: 8px 0;'>
                        <span class='detail-label' style='color: rgba(255,255,255,0.9);'>K úhradě:</span>
                        <span class='detail-value' style='color: #fff; font-weight: 800; font-size: 18px;'>" . number_format($amountToPay, 0, ',', ' ') . " Kč</span>
                    </div>";
                    }
                    
                    $message .= "
                    <div class='detail-row'>
                        <span class='detail-label'>Způsob dopravy:</span>
                        <span class='detail-value'>" . htmlspecialchars($deliveryData['method']) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Způsob platby:</span>
                        <span class='detail-value'>" . ($paymentData['method'] === 'bank_transfer' ? 'Bankovní převod' : ($paymentData['method'] === 'revolut' ? 'Revolut' : 'Individuální domluva')) . "</span>
                    </div>
                </div>
                
                <h3>Objednané produkty</h3>
                <div class='products-section'>
                    $productsList
                </div>
                
                <h3>Vaše údaje</h3>
                <div class='customer-info'>
                    <h4>Kontaktní informace</h4>
                    <p><strong>Jméno:</strong> " . htmlspecialchars($customerData['name']) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($customerData['email']) . "</p>
                    <p><strong>Telefon:</strong> " . htmlspecialchars($customerData['phone']) . "</p>
                </div>
                
                $deliveryAddress
                
                <h3>Platební údaje</h3>";
    
    if ($paymentData['method'] === 'bank_transfer') {
        $message .= "
            <div class='payment-info'>
                <p><strong>Číslo účtu:</strong> <span class='highlight'>296614297/0300</span></p>
                <p><strong>Variabilní symbol:</strong> <span class='highlight'>" . substr($orderId, -4) . "</span></p>
                <p><strong>Částka k úhradě:</strong> <span class='highlight' style='font-size: 18px;'>" . number_format($amountToPay, 0, ',', ' ') . " Kč</span></p>
                <p><strong>Banka:</strong> ČSOB</p>
            </div>";
    } elseif ($paymentData['method'] === 'revolut') {
        $message .= "
            <div class='payment-info'>
                <p><strong>Částka k úhradě:</strong> <span class='highlight' style='font-size: 18px;'>" . number_format($amountToPay, 0, ',', ' ') . " Kč</span></p>
                <p>Platební odkaz pro Revolut bude zaslán v samostatném emailu.</p>
            </div>";
    } else {
        // Individual payment
        $message .= "
            <div class='payment-info'>
                <p><strong>Způsob platby:</strong> Individuální domluva</p>
                <p>Ohledně platby a dopravy vás budeme kontaktovat.</p>
            </div>";
    }
    
    $message .= "
                <p style='font-size: 16px; color: #4c6444; font-weight: 600; margin-top: 30px;'>Po připsání platby na náš účet vám zašleme potvrzení a začneme s přípravou vaší objednávky.</p>
                
                <p style='font-size: 16px; color: #102820; font-weight: 600; margin-top: 25px;'>S pozdravem,<br><strong>Tým KJD</strong></p>
            </div>
            
            <div class='footer'>
                <div class='logo' style='font-size: 20px; margin-bottom: 10px;'>KJ<span style='color: #CABA9C;'>D</span></div>
                <p><strong>Kubajadesigns.eu</strong></p>
                <p>Email: info@kubajadesigns.eu</p>
                <div class='social-links'>
                    <a href='#'>Facebook</a> | 
                    <a href='#'>Instagram</a>
                </div>
            </div>
        </div>
        </div>
    </body>
    </html>";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: KJD <info@kubajadesigns.eu>',
        'Reply-To: info@kubajadesigns.eu',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    mail($to, $subject, $message, implode("\r\n", $headers));
}

function sendAdminNotificationEmail($customerData, $orderId, $trackingCode, $total, $deliveryData, $paymentData, $productsJson, $amountToPay = null, $walletDeduction = 0) {
    $adminEmail = 'mickeyjarolim3@gmail.com';
    $subject = "Nová objednávka #$orderId - KJD";
    
    // Pokud není předáno, použij total
    if ($amountToPay === null) {
        $amountToPay = $total;
    }
    
    // Parse products from JSON
    $products = json_decode($productsJson, true);
    $productsList = '';
    if ($products) {
        foreach ($products as $product) {
            $colorInfo = '';
            if (!empty($product['component_colors']) && is_array($product['component_colors'])) {
                $colorParts = [];
                foreach ($product['component_colors'] as $component => $color) {
                    $colorParts[] = htmlspecialchars($component) . ': ' . htmlspecialchars($color);
                }
                if (!empty($colorParts)) {
                    $colorInfo = ' <span style="color: #8A6240;">(' . implode(', ', $colorParts) . ')</span>';
                }
            } elseif (!empty($product['selected_color'])) {
                $colorInfo = ' <span style="color: #8A6240;">(Barva: ' . htmlspecialchars($product['selected_color']) . ')</span>';
            }
            
            $productsList .= "<li><strong>{$product['name']}</strong>{$colorInfo} - {$product['quantity']}x " . number_format($product['final_price'] ?? $product['price'], 0, ',', ' ') . " Kč</li>";
        }
    }
    
    $message = "
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
                background: linear-gradient(135deg, #c62828, #ff7043); 
                color: #fff; 
                padding: 30px 20px; 
                text-align: center; 
                border-bottom: 3px solid #ffab91;
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
            .order-details { 
                background: #ffffff; 
                padding: 25px; 
                border-radius: 12px; 
                margin: 20px 0; 
                border: 2px solid #4c6444;
                box-shadow: 0 4px 15px rgba(76,100,68,0.2);
            }
            .order-details h3 { 
                color: #102820; 
                margin-top: 0; 
                border-bottom: 2px solid #4c6444; 
                padding-bottom: 10px;
            }
            .detail-row { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 12px; 
                padding: 8px 0;
                border-bottom: 1px solid rgba(16,40,32,0.1);
            }
            .detail-row:last-child { 
                border-bottom: none; 
                margin-bottom: 0;
            }
            .detail-label { 
                font-weight: 700; 
                color: #102820;
            }
            .detail-value { 
                font-weight: 600; 
                color: #4c6444;
            }
            .products-list {
                background: rgba(202,186,156,0.1); 
                padding: 20px; 
                border-radius: 10px; 
                border-left: 4px solid #8A6240;
                margin: 20px 0;
            }
            .products-list ul {
                margin: 0;
                padding-left: 20px;
            }
            .products-list li {
                margin-bottom: 8px;
                font-weight: 600;
            }
            .alert-box {
                background: #fff3cd;
                border: 2px solid #ffc107;
                border-radius: 8px;
                padding: 15px;
                margin: 20px 0;
                color: #856404;
                font-weight: 600;
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
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='logo'>KJ<span style='color: #ffab91;'>D</span></div>
                <h1>🚨 Nová objednávka!</h1>
            </div>
            
            <div class='content'>
                <h2>Dobrý den, administrátore!</h2>
                
                <div class='alert-box'>
                    <strong>⚠️ POZOR:</strong> Byla vytvořena nová objednávka, která vyžaduje vaši pozornost!
                </div>
                
                <div class='order-details'>
                    <h3>📋 Detaily objednávky</h3>
                    <div class='detail-row'>
                        <span class='detail-label'>Číslo objednávky:</span>
                        <span class='detail-value'><strong>$orderId</strong></span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Tracking kód:</span>
                        <span class='detail-value'>$trackingCode</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Celková částka:</span>
                        <span class='detail-value'><strong>" . number_format($total, 0, ',', ' ') . " Kč</strong></span>
                    </div>";
                    
                    // Add wallet deduction info for admin
                    if ($walletDeduction > 0) {
                        $message .= "
                    <div class='detail-row' style='background: rgba(202,186,156,0.2); border: 2px solid #CABA9C; border-radius: 8px; padding: 12px; margin: 8px 0;'>
                        <span class='detail-label'><i class='fas fa-wallet' style='margin-right: 8px;'></i>Zůstatek z účtu:</span>
                        <span class='detail-value' style='color: #4c6444; font-weight: 700;'>-" . number_format($walletDeduction, 0, ',', ' ') . " Kč</span>
                    </div>
                    <div class='detail-row' style='background: linear-gradient(135deg, #4c6444, #102820); color: #fff; border-radius: 8px; padding: 12px; margin: 8px 0;'>
                        <span class='detail-label' style='color: rgba(255,255,255,0.9);'>K úhradě:</span>
                        <span class='detail-value' style='color: #fff; font-weight: 800; font-size: 18px;'>" . number_format($amountToPay, 0, ',', ' ') . " Kč</span>
                    </div>";
                    } else {
                        $message .= "
                    <div class='detail-row' style='background: linear-gradient(135deg, #4c6444, #102820); color: #fff; border-radius: 8px; padding: 12px; margin: 8px 0;'>
                        <span class='detail-label' style='color: rgba(255,255,255,0.9);'>K úhradě:</span>
                        <span class='detail-value' style='color: #fff; font-weight: 800; font-size: 18px;'>" . number_format($amountToPay, 0, ',', ' ') . " Kč</span>
                    </div>";
                    }
                    
                    $message .= "
                    <div class='detail-row'>
                        <span class='detail-label'>Datum vytvoření:</span>
                        <span class='detail-value'>" . date('d.m.Y H:i') . "</span>
                    </div>
                </div>
                
                <h3>👤 Zákaznické údaje</h3>
                <div class='order-details'>
                    <div class='detail-row'>
                        <span class='detail-label'>Jméno:</span>
                        <span class='detail-value'>" . htmlspecialchars($customerData['name']) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Email:</span>
                        <span class='detail-value'>" . htmlspecialchars($customerData['email']) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Telefon:</span>
                        <span class='detail-value'>" . htmlspecialchars($customerData['phone']) . "</span>
                    </div>
                    " . (!empty($customerData['note']) ? "
                    <div class='detail-row'>
                        <span class='detail-label'>Poznámka:</span>
                        <span class='detail-value'>" . htmlspecialchars($customerData['note']) . "</span>
                    </div>" : "") . "
                </div>
                
                <h3>🚚 Doprava a platba</h3>
                <div class='order-details'>
                    <div class='detail-row'>
                        <span class='detail-label'>Způsob dopravy:</span>
                        <span class='detail-value'>" . htmlspecialchars($deliveryData['method']) . "</span>
                    </div>
                    " . ($deliveryData['method'] === 'Zásilkovna' ? "
                    <div class='detail-row'>
                        <span class='detail-label'>Adresa Zásilkovny:</span>
                        <span class='detail-value'>" . htmlspecialchars($deliveryData['zasilkovna_name']) . ", " . htmlspecialchars($deliveryData['zasilkovna_street']) . ", " . htmlspecialchars($deliveryData['zasilkovna_zip']) . " " . htmlspecialchars($deliveryData['zasilkovna_city']) . "</span>
                    </div>" : "") . "
                    <div class='detail-row'>
                        <span class='detail-label'>Způsob platby:</span>
                        <span class='detail-value'>" . ($paymentData['method'] === 'bank_transfer' ? 'Bankovní převod' : 'Revolut') . "</span>
                    </div>
                </div>
                
                <h3>🛍️ Produkty v objednávce</h3>
                <div class='products-list'>
                    <ul>
                        $productsList
                    </ul>
                </div>
                
                <div class='alert-box'>
                    <strong>📝 Další kroky:</strong><br>
                    1. Zkontrolujte platbu na bankovním účtu<br>
                    2. Připravte produkty k odeslání<br>
                    3. Aktualizujte stav objednávky v admin panelu<br>
                    4. Odešlete tracking informace zákazníkovi
                </div>
                
                <p style='font-size: 16px; color: #102820; font-weight: 600; margin-top: 25px;'>
                    S pozdravem,<br><strong>Systém KJD</strong>
                </p>
            </div>
            
            <div class='footer'>
                <div class='logo' style='font-size: 20px; margin-bottom: 10px;'>KJ<span style='color: #CABA9C;'>D</span></div>
                <p><strong>Kubajadesigns.eu</strong></p>
                <p>Admin notifikace</p>
            </div>
        </div>
    </body>
    </html>";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: KJD System <info@kubajadesigns.eu>',
        'Reply-To: info@kubajadesigns.eu',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    error_log("Sending admin notification email to: $adminEmail for order: $orderId");
    $result = mail($adminEmail, $subject, $message, implode("\r\n", $headers));
    
    if ($result) {
        error_log("Admin notification email sent successfully to $adminEmail");
    } else {
        error_log("Failed to send admin notification email to $adminEmail");
    }
}
?>
