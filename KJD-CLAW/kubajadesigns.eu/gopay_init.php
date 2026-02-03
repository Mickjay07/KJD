<?php
/**
 * GoPay Payment Initialization
 * This script initializes a GoPay payment and redirects user to GoPay gateway
 */

// Enable error logging
error_log('=== GOPAY_INIT START ===');

require_once 'config.php';
require_once 'includes/GoPayHelper.php';

session_start();

error_log('GoPay Init: Session ID = ' . session_id());
error_log('GoPay Init: Order ID from session = ' . ($_SESSION['gopay_pending_order_id'] ?? 'MISSING'));

// Get order ID from session (set by process_order.php)
$orderId = $_SESSION['gopay_pending_order_id'] ?? null;

if (!$orderId) {
    $_SESSION['checkout_error'] = 'Chyba: Nebylo možné inicializovat platbu. Chybí ID objednávky.';
    header('Location: checkout.php');
    exit;
}

try {
    // Load order from database
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Objednávka nenalezena');
    }
    
    // Parse products for GoPay items
    $products = json_decode($order['products_json'], true);
    $goPayItems = [];
    
    if (is_array($products)) {
        foreach ($products as $product) {
            if (is_array($product) && isset($product['name'])) {
                $goPayItems[] = [
                    'name' => $product['name'],
                    'amount' => (int)(($product['final_price'] ?? $product['price'] ?? 0) * 100), // haléře
                    'count' => (int)($product['quantity'] ?? 1)
                ];
            }
        }
    }
    
    // Split customer name
    $nameParts = explode(' ', $order['name'] ?? '');
    $firstName = $nameParts[0] ?? '';
    $lastName = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
    
    // Prepare order data for GoPay
    $orderData = [
        'amount' => $order['amount_to_pay'] ?? $order['total_price'],
        'order_number' => $orderId,
        'description' => 'Objednávka KJD Designs #' . $orderId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $order['email'] ?? '',
        'phone' => $order['phone_number'] ?? '',
        'items' => $goPayItems
    ];
    
    // Initialize GoPay payment
    $gopay = new GoPayHelper();
    $payment = $gopay->createPayment($orderData);
    
    if (!$payment['gw_url']) {
        throw new Exception('GoPay nevrátil platební URL');
    }
    
    // Save GoPay payment ID to order
    $stmt = $conn->prepare("UPDATE orders SET gopay_payment_id = ?, payment_status = 'pending' WHERE order_id = ?");
    $stmt->execute([$payment['id'], $orderId]);
    
    // Save to gopay_payments table if exists
    try {
        $stmt = $conn->prepare("INSERT INTO gopay_payments (order_id, gopay_id, amount, currency, state) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $orderId,
            $payment['id'],
            $orderData['amount'],
            GOPAY_CURRENCY,
            $payment['state']
        ]);
    } catch (PDOException $e) {
        // Table might not exist yet, that's okay
    }
    
    // Clear session
    unset($_SESSION['gopay_pending_order_id']);
    
    // Redirect to GoPay gateway
    header('Location: ' . $payment['gw_url']);
    exit;
    
} catch (Exception $e) {
    error_log('GoPay initialization error: ' . $e->getMessage());
    $_SESSION['checkout_error'] = 'Chyba při inicializaci platby: ' . $e->getMessage();
    header('Location: checkout.php');
    exit;
}
