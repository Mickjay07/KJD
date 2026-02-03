<?php
// Test soubor pro admin_edit_order.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Debug: Test file loaded -->";
echo "<!-- Debug: GET = " . print_r($_GET, true) . " -->";
echo "<!-- Debug: POST = " . print_r($_POST, true) . " -->";

$order_id = $_GET['id'] ?? null;
echo "<!-- Debug: order_id = " . htmlspecialchars($order_id ?? 'NULL') . " -->";

if (!$order_id) {
    echo "<!-- Debug: No order_id provided -->";
    $error_message = "Nebyla zadána platná objednávka. Zadejte ID objednávky v URL.";
} else {
    echo "<!-- Debug: Order ID provided: " . htmlspecialchars($order_id) . " -->";
    $order = ['order_id' => 'TEST-' . $order_id, 'name' => 'Test Order', 'email' => 'test@example.com'];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Test Edit Order</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Test Edit Order</h1>
    
    <div class="debug">
        <h3>Debug Info:</h3>
        <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order_id ?? 'NULL'); ?></p>
        <p><strong>GET params:</strong> <?php echo htmlspecialchars(print_r($_GET, true)); ?></p>
    </div>
    
    <?php if (!$order_id): ?>
        <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffeaa7;">
            <h3>Žádná objednávka k úpravě</h3>
            <p>Pro úpravu objednávky zadejte ID objednávky v URL, například:</p>
            <code>test_edit_order.php?id=86</code>
        </div>
    <?php else: ?>
        <div style="background: #d4edda; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb;">
            <h3>Objednávka nalezena</h3>
            <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_id']); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
        </div>
    <?php endif; ?>
    
    <p><a href="test_edit_order.php">Test bez ID</a> | <a href="test_edit_order.php?id=86">Test s ID</a></p>
</body>
</html>
