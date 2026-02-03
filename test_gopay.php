<?php
/**
 * Test GoPay Configuration
 * Visit this file to check if GoPay is properly configured
 */

require_once 'config.php';
require_once 'includes/GoPayHelper.php';

echo "<h1>GoPay Configuration Test</h1>";

echo "<h2>1. Config Check</h2>";
echo "<pre>";
echo "GOPAY_GOID: " . (defined('GOPAY_GOID') ? GOPAY_GOID : 'NOT DEFINED') . "\n";
echo "GOPAY_CLIENT_ID: " . (defined('GOPAY_CLIENT_ID') ? GOPAY_CLIENT_ID : 'NOT DEFINED') . "\n";
echo "GOPAY_CLIENT_SECRET: " . (defined('GOPAY_CLIENT_SECRET') ? (substr(GOPAY_CLIENT_SECRET, 0, 4) . '***') : 'NOT DEFINED') . "\n";
echo "GOPAY_IS_PRODUCTION: " . (defined('GOPAY_IS_PRODUCTION') ? (GOPAY_IS_PRODUCTION ? 'true' : 'false') : 'NOT DEFINED') . "\n";
echo "GOPAY_GATEWAY_URL: " . (defined('GOPAY_GATEWAY_URL') ? GOPAY_GATEWAY_URL : 'NOT DEFINED') . "\n";
echo "</pre>";

echo "<h2>2. GoPayHelper Class Test</h2>";
try {
    $gopay = new GoPayHelper();
    echo "<p style='color: green;'>✓ GoPayHelper class loaded successfully</p>";
    
    echo "<h3>Testing API Connection...</h3>";
    
    // Test payment creation with minimal data
    $testData = [
        'amount' => 100, // 100 Kč
        'order_number' => 'TEST-' . time(),
        'description' => 'Test GoPay Payment',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'phone' => '+420123456789',
        'items' => [
            [
                'name' => 'Test Product',
                'amount' => 10000, // 100 Kč in haléře
                'count' => 1
            ]
        ]
    ];
    
    $payment = $gopay->createPayment($testData);
    
    echo "<p style='color: green;'>✓ Payment created successfully!</p>";
    echo "<pre>";
    echo "Payment ID: " . $payment['id'] . "\n";
    echo "Payment State: " . $payment['state'] . "\n";
    echo "Gateway URL: " . $payment['gw_url'] . "\n";
    echo "</pre>";
    
    echo "<p><a href='" . $payment['gw_url'] . "' target='_blank'>Click here to test payment gateway</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>3. Database Check</h2>";
try {
    $conn = new PDO("mysql:host=wh51.farma.gigaserver.cz;dbname=kubajadesigns_eu_;charset=utf8", "81986_KJD", "2007mickey");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if gopay_payment_id column exists
    $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'gopay_payment_id'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Column 'gopay_payment_id' exists in orders table</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Column 'gopay_payment_id' NOT found in orders table</p>";
        echo "<p>Run this SQL: <code>ALTER TABLE orders ADD COLUMN gopay_payment_id BIGINT NULL;</code></p>";
    }
    
    // Check if gopay_payments table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'gopay_payments'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Table 'gopay_payments' exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Table 'gopay_payments' NOT found</p>";
        echo "<p>Create table using admin/create_gopay_tables.sql</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>Test completed at " . date('Y-m-d H:i:s') . "</small></p>";
