<?php
/**
 * GoPay Complete Flow Test
 * Tests the entire payment flow including email
 */

require_once 'config.php';
require_once 'includes/GoPayHelper.php';
require_once 'includes/InvoiceGenerator.php';
require_once 'includes/email_payment_confirmation.php';

echo "<h1>GoPay Complete Flow Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    pre { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
    hr { margin: 30px 0; border: 2px solid #ccc; }
</style>";

// ============================================
// STEP 1: Create Test Order
// ============================================
echo "<h2>Step 1: Creating Test Order</h2>";

try {
    $testOrderId = 'TEST-GOPAY-' . date('YmdHis');
    
    $stmt = $conn->prepare("
        INSERT INTO orders (
            order_id, email, phone_number, name, delivery_method,
            address, total_price, status, payment_method, payment_status,
            amount_to_pay, products_json, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $testProducts = json_encode([
        [
            'name' => 'Test Product',
            'price' => 500,
            'final_price' => 500,
            'quantity' => 1
        ]
    ]);
    
    $stmt->execute([
        $testOrderId,
        'mickeyjarolim3@gmail.com', // Change to your email to receive test email
        '+420123456789',
        'Test User',
        'Zásilkovna',
        'Test Address',
        500,
        'pending',
        'gopay',
        'pending',
        500,
        $testProducts
    ]);
    
    echo "<p class='success'>✓ Test order created: $testOrderId</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to create test order: " . $e->getMessage() . "</p>";
    exit;
}

// ============================================
// STEP 2: Initialize GoPay Payment
// ============================================
echo "<h2>Step 2: Initialize GoPay Payment</h2>";

try {
    $gopay = new GoPayHelper();
    
    $payment = $gopay->createPayment([
        'amount' => 500,
        'order_number' => $testOrderId,
        'description' => 'Test GoPay Payment',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'mickeyjarolim3@gmail.com',
        'phone' => '+420123456789',
        'items' => [
            [
                'name' => 'Test Product',
                'amount' => 50000, // 500 Kč in haléře
                'count' => 1
            ]
        ]
    ]);
    
    echo "<p class='success'>✓ GoPay payment created</p>";
    echo "<pre>";
    echo "Payment ID: " . $payment['id'] . "\n";
    echo "State: " . $payment['state'] . "\n";
    echo "Gateway URL: " . $payment['gw_url'] . "\n";
    echo "</pre>";
    
    $goPayId = $payment['id'];
    
    // Update order with GoPay ID
    $stmt = $conn->prepare("UPDATE orders SET gopay_payment_id = ? WHERE order_id = ?");
    $stmt->execute([$goPayId, $testOrderId]);
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to create GoPay payment: " . $e->getMessage() . "</p>";
    exit;
}

// ============================================
// STEP 3: Simulate Payment Success (Callback)
// ============================================
echo "<h2>Step 3: Simulate Payment Success</h2>";

try {
    // Get payment status from GoPay (will be CREATED until actually paid)
    $paymentStatus = $gopay->getPaymentStatus($goPayId);
    
    echo "<p class='info'>Current payment state: " . $paymentStatus['state'] . "</p>";
    echo "<p class='info'>Note: Payment is in CREATED state until you actually pay on the gateway</p>";
    
    // For testing purposes, let's SIMULATE what happens when payment is PAID
    echo "<h3>Simulating PAID callback...</h3>";
    
    // Create invoice
    $invoiceGen = new InvoiceGenerator($conn);
    $invoiceId = $invoiceGen->createFromOrder($testOrderId);
    
    echo "<p class='success'>✓ Invoice created: ID = $invoiceId</p>";
    
    // Mark invoice as paid
    $stmt = $conn->prepare("UPDATE invoices SET status = 'paid', paid_date = NOW() WHERE id = ?");
    $stmt->execute([$invoiceId]);
    
    echo "<p class='success'>✓ Invoice marked as paid</p>";
    
    // Get invoice details
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo "Invoice Number: " . $invoice['invoice_number'] . "\n";
    echo "Total: " . number_format($invoice['total_with_vat'], 2) . " Kč\n";
    echo "Status: " . $invoice['status'] . "\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to process invoice: " . $e->getMessage() . "</p>";
    exit;
}

// ============================================
// STEP 4: Test Email Sending
// ============================================
echo "<h2>Step 4: Test Email Sending</h2>";

try {
    // Send payment confirmation email
    $emailSent = sendPaymentConfirmationWithInvoice($testOrderId, $invoiceId);
    
    if ($emailSent) {
        echo "<p class='success'>✓ Email sent successfully!</p>";
        echo "<p class='info'>Check your inbox at: test@example.com</p>";
        echo "<p class='info'>Change the email in Step 1 to receive the test email</p>";
    } else {
        echo "<p class='error'>✗ Email sending failed (check error log)</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Email error: " . $e->getMessage() . "</p>";
}

// ============================================
// STEP 5: Cleanup (Optional)
// ============================================
echo "<hr>";
echo "<h2>Cleanup</h2>";
echo "<form method='post' action='?cleanup=1'>";
echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Delete Test Order & Invoice</button>";
echo "</form>";

if (isset($_GET['cleanup']) && $_GET['cleanup'] == '1') {
    try {
        // Delete invoice items
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        
        // Delete invoice
        $stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        
        // Delete order
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->execute([$testOrderId]);
        
        echo "<p class='success'>✓ Test data cleaned up</p>";
        echo "<p><a href='test_gopay_flow.php'>Run test again</a></p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Cleanup failed: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<ul>";
echo "<li>Test Order: <strong>$testOrderId</strong></li>";
echo "<li>GoPay Payment ID: <strong>$goPayId</strong></li>";
echo "<li>Invoice ID: <strong>$invoiceId</strong></li>";
echo "<li>Invoice Number: <strong>" . ($invoice['invoice_number'] ?? 'N/A') . "</strong></li>";
echo "</ul>";

echo "<h3>To Complete Full Test:</h3>";
echo "<ol>";
echo "<li>Visit the GoPay gateway URL above</li>";
echo "<li>Complete the test payment (use test card 4111 1111 1111 1111)</li>";
echo "<li>After payment, check if:
    <ul>
        <li>Order status changed to 'processing'</li>
        <li>Payment status changed to 'paid'</li>
        <li>Email was received with invoice link</li>
    </ul>
</li>";
echo "</ol>";

echo "<p><small>Test completed at " . date('Y-m-d H:i:s') . "</small></p>";
