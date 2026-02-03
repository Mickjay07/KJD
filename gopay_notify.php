<?php
/**
 * GoPay Notification Handler
 * Server-to-server notification endpoint
 * According to: https://doc.gopay.cz/#odeslani-notifikace
 */

error_log('=== GOPAY NOTIFICATION START ===');

require_once 'config.php';
require_once 'includes/GoPayHelper.php';

// Log incoming request
error_log('GoPay Notification: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);
error_log('GoPay Notification POST: ' . print_r($_POST, true));
error_log('GoPay Notification GET: ' . print_r($_GET, true));

// Get payment ID from either POST or GET
$paymentId = $_POST['id'] ?? $_GET['id'] ?? null;

if (!$paymentId) {
    // Try to get from raw input (JSON)
    $input = file_get_contents('php://input');
    error_log('GoPay Notification raw input: ' . $input);
    
    $data = json_decode($input, true);
    $paymentId = $data['id'] ?? null;
}

if (!$paymentId) {
    error_log('GoPay notification: No payment ID provided');
    http_response_code(400);
    echo 'No payment ID';
    exit;
}

error_log("GoPay notification received for payment ID: $paymentId");

try {
    // CRITICAL: Always query payment status via API
    // This is REQUIRED by GoPay after receiving notification
    $gopay = new GoPayHelper();
    $payment = $gopay->getPaymentStatus($paymentId);
    
    error_log("GoPay payment status retrieved: " . json_encode($payment));
    
    if (!$payment || !$payment['order_number']) {
        throw new Exception('Invalid payment data from GoPay API');
    }
    
    $orderId = $payment['order_number'];
    $state = $payment['state'];
    
    error_log("Processing payment for order $orderId with state $state");
    
    // Load order from database
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        error_log("Order not found: $orderId");
        // Still return 200 OK to GoPay
        http_response_code(200);
        echo 'Order not found';
        exit;
    }
    
    // Update order status based on payment state
    $newStatus = null;
    $paymentStatus = null;
    
    switch ($state) {
        case 'PAID':
            $newStatus = 'Zaplaceno';
            $paymentStatus = 'paid';
            
            error_log("Payment PAID for order $orderId - creating invoice");
            
            // Create invoice if InvoiceGenerator exists
            if (file_exists(__DIR__ . '/includes/InvoiceGenerator.php')) {
                require_once __DIR__ . '/includes/InvoiceGenerator.php';
                try {
                    $invoiceGen = new InvoiceGenerator($conn);
                    $invoiceId = $invoiceGen->createFromOrder($orderId);
                    
                    // Mark invoice as paid immediately
                    $stmt = $conn->prepare("UPDATE invoices SET status = 'paid', paid_date = NOW() WHERE id = ?");
                    $stmt->execute([$invoiceId]);
                    
                    error_log("Invoice #$invoiceId created and marked as paid");
                    
                    // Send payment confirmation email with PDF invoice
                    if (file_exists(__DIR__ . '/includes/email_payment_confirmation.php')) {
                        require_once __DIR__ . '/includes/email_payment_confirmation.php';
                        sendPaymentConfirmationWithInvoice($orderId, $invoiceId);
                        error_log("Payment confirmation email sent");
                    }
                    
                } catch (Exception $e) {
                    error_log('Failed to create invoice for order ' . $orderId . ': ' . $e->getMessage());
                }
            }
            
            // Clear cart from session if still exists
            session_start();
            unset($_SESSION['cart']);
            unset($_SESSION['gopay_pending_order_id']);
            
            break;
            
        case 'CANCELED':
            $newStatus = 'cancelled';
            $paymentStatus = 'cancelled';
            error_log("Payment CANCELED for order $orderId");
            
            // Send payment reminder email
            if (file_exists(__DIR__ . '/includes/email_payment_reminder.php')) {
                require_once __DIR__ . '/includes/email_payment_reminder.php';
                sendPaymentReminderEmail(
                    $orderId, 
                    $order['email'], 
                    $order['name'], 
                    $order['total_price'],
                    $paymentId
                );
                error_log("Payment reminder email sent for canceled order $orderId");
            }
            break;
            
        case 'TIMEOUTED':
            $newStatus = 'cancelled';
            $paymentStatus = 'timeout';
            error_log("Payment TIMEOUTED for order $orderId");
            
            // Send payment reminder email for timeout as well
            if (file_exists(__DIR__ . '/includes/email_payment_reminder.php')) {
                require_once __DIR__ . '/includes/email_payment_reminder.php';
                sendPaymentReminderEmail(
                    $orderId, 
                    $order['email'], 
                    $order['name'], 
                    $order['total_price'],
                    $paymentId
                );
                error_log("Payment reminder email sent for timed out order $orderId");
            }
            break;
            
        case 'REFUNDED':
            $newStatus = 'refunded';
            $paymentStatus = 'refunded';
            error_log("Payment REFUNDED for order $orderId");
            break;
            
        default:
            // CREATED, PAYMENT_METHOD_CHOSEN, etc.
            $paymentStatus = 'pending';
            error_log("Payment in state $state for order $orderId");
            break;
    }
    
    // Update order
    if ($newStatus && $paymentStatus) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$newStatus, $paymentStatus, $orderId]);
        error_log("Order $orderId updated: status=$newStatus, payment_status=$paymentStatus");
    }
    
    // Update gopay_payments table
    try {
        $stmt = $conn->prepare("UPDATE gopay_payments SET state = ?, payment_instrument = ?, updated_at = NOW() WHERE gopay_id = ?");
        $stmt->execute([$state, $payment['payment_instrument'] ?? null, $paymentId]);
    } catch (PDOException $e) {
        error_log("GoPay payments table update failed: " . $e->getMessage());
    }
    
    // Log success
    error_log("GoPay notification processed successfully: Order {$orderId}, Payment {$paymentId}, State: {$state}");
    
    // Always return 200 OK to GoPay
    http_response_code(200);
    echo 'OK';
    exit;
    
} catch (Exception $e) {
    error_log('GoPay notification error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Still return 200 OK to prevent GoPay from retrying
    http_response_code(200);
    echo 'Error: ' . $e->getMessage();
    exit;
}
