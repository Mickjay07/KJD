<?php
/**
 * GoPay Payment Callback Handler
 * Processes payment notifications from GoPay and updates order status
 */

require_once 'config.php';
require_once 'includes/GoPayHelper.php';

// Get payment ID from URL parameter
$paymentId = $_GET['id'] ?? null;

if (!$paymentId) {
    // Try to get from POST (notification)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $paymentId = $data['id'] ?? null;
}

if (!$paymentId) {
    error_log('GoPay callback: No payment ID provided');
    http_response_code(400);
    exit('No payment ID');
}

try {
    // Get payment status from GoPay API
    $gopay = new GoPayHelper();
    $payment = $gopay->getPaymentStatus($paymentId);
    
    if (!$payment || !$payment['order_number']) {
        throw new Exception('Invalid payment data from GoPay');
    }
    
    $orderId = $payment['order_number'];
    $state = $payment['state'];
    
    // Load order from database
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found: ' . $orderId);
    }
    
    // Update order status based on payment state
    $newStatus = null;
    $paymentStatus = null;
    
    switch ($state) {
        case 'PAID':
            $newStatus = 'processing';
            $paymentStatus = 'paid';
            
            // Create invoice if InvoiceGenerator exists
            if (file_exists(__DIR__ . '/includes/InvoiceGenerator.php')) {
                require_once __DIR__ . '/includes/InvoiceGenerator.php';
                try {
                    $invoiceGen = new InvoiceGenerator($conn);
                    $invoiceId = $invoiceGen->createFromOrder($orderId);
                    
                    // Mark invoice as paid immediately
                    $stmt = $conn->prepare("UPDATE invoices SET status = 'paid', paid_date = NOW() WHERE id = ?");
                    $stmt->execute([$invoiceId]);
                    
                    // Send payment confirmation email with invoice PDF
                    if (file_exists(__DIR__ . '/includes/email_payment_confirmation.php')) {
                        require_once __DIR__ . '/includes/email_payment_confirmation.php';
                        sendPaymentConfirmationWithInvoice($orderId, $invoiceId);
                    }
                    
                    error_log("Invoice #{$invoiceId} created and marked as paid for order {$orderId}");
                } catch (Exception $e) {
                    error_log('Failed to create invoice for order ' . $orderId . ': ' . $e->getMessage());
                }
            }
            
            break;
            
        case 'CANCELED':
            $newStatus = 'cancelled';
            $paymentStatus = 'cancelled';
            
            // Send payment reminder email immediately (don't wait for notification)
            if (file_exists(__DIR__ . '/includes/email_payment_reminder.php')) {
                require_once __DIR__ . '/includes/email_payment_reminder.php';
                sendPaymentReminderEmail(
                    $orderId, 
                    $order['email'], 
                    $order['name'], 
                    $order['total_price'],
                    $paymentId
                );
                error_log("Immediate payment reminder email sent for canceled order $orderId");
            }
            break;
            
        case 'TIMEOUTED':
            $newStatus = 'cancelled';
            $paymentStatus = 'timeout';
            
            // Send payment reminder email immediately (don't wait for notification)
            if (file_exists(__DIR__ . '/includes/email_payment_reminder.php')) {
                require_once __DIR__ . '/includes/email_payment_reminder.php';
                sendPaymentReminderEmail(
                    $orderId, 
                    $order['email'], 
                    $order['name'], 
                    $order['total_price'],
                    $paymentId
                );
                error_log("Immediate payment reminder email sent for timed out order $orderId");
            }
            break;
            
        case 'REFUNDED':
            $newStatus = 'refunded';
            $paymentStatus = 'refunded';
            break;
            
        default:
            // CREATED, PAYMENT_METHOD_CHOSEN, etc.
            $paymentStatus = 'pending';
            break;
    }
    
    // Update order
    if ($newStatus && $paymentStatus) {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$newStatus, $paymentStatus, $orderId]);
    }
    
    // Update gopay_payments table
    try {
        $stmt = $conn->prepare("UPDATE gopay_payments SET state = ?, payment_instrument = ?, updated_at = NOW() WHERE gopay_id = ?");
        $stmt->execute([$state, $payment['payment_instrument'] ?? null, $paymentId]);
    } catch (PDOException $e) {
        // Table might not exist, that's okay
    }
    
    // Log the callback
    error_log("GoPay callback processed: Order {$orderId}, Payment {$paymentId}, State: {$state}");
    
    // If this is a redirect (user returning from GoPay)
    if (isset($_GET['id'])) {
        // Repopulate session data for order_confirmation.php
        $_SESSION['order_confirmation'] = [
            'order_id' => $orderId,
            'tracking_code' => $order['tracking_code'],
            'total' => $order['total_price'],
            'wallet_deduction' => 0, // Not available from order table
            'amount_to_pay' => $order['total_price'],
            'email' => $order['email'],
            'name' => $order['name']
        ];
        
        if ($state === 'PAID') {
            header('Location: order_confirmation.php?order_id=' . urlencode($orderId) . '&payment=success');
        } else {
            header('Location: order_confirmation.php?order_id=' . urlencode($orderId) . '&payment=failed');
        }
        exit;
    }
    
    // If this is a notification (server-to-server)
    http_response_code(200);
    echo 'OK';
    exit;
    
} catch (Exception $e) {
    error_log('GoPay callback error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
    exit;
}
