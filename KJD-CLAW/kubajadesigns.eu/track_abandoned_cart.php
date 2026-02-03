<?php
// Track abandoned cart for logged-in users
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";
try { 
    $conn = new PDO($dsn, $username, $password); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $conn->query("SET NAMES utf8"); 
} catch (PDOException $e) { 
    error_log("Database connection failed: " . $e->getMessage());
    exit; 
}

// Only track for logged-in users with items in cart
if (!isset($_SESSION['user_id']) || !isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$email = $_SESSION['user_email'] ?? '';

// Calculate cart total and items count
$cart_total = 0;
$items_count = 0;
$cart_data = [];

foreach ($_SESSION['cart'] as $item) {
    $quantity = (int)($item['quantity'] ?? 0);
    $price = (float)($item['price'] ?? 0);
    $cart_total += $price * $quantity;
    $items_count += $quantity;
    
    $cart_data[] = [
        'product_id' => $item['id'] ?? 0,
        'name' => $item['name'] ?? '',
        'quantity' => $quantity,
        'price' => $price
    ];
}

if ($items_count === 0) {
    exit;
}

// Create table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS abandoned_cart_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    cart_data TEXT NOT NULL,
    cart_total DECIMAL(10,2) NOT NULL,
    items_count INT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    email_sent_at TIMESTAMP NULL,
    reminder_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email_sent (email_sent_at),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $conn->exec($createTable);
} catch (PDOException $e) {
    error_log("Error creating table: " . $e->getMessage());
}

// Check if we already have a record for this user
$stmt = $conn->prepare("SELECT id FROM abandoned_cart_notifications WHERE user_id = ? AND email_sent_at IS NULL");
$stmt->execute([$user_id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    // Update existing record
    $stmt = $conn->prepare("UPDATE abandoned_cart_notifications SET 
        cart_data = ?, 
        cart_total = ?, 
        items_count = ?, 
        last_activity = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND email_sent_at IS NULL");
    $stmt->execute([json_encode($cart_data), $cart_total, $items_count, $user_id]);
} else {
    // Insert new record
    $stmt = $conn->prepare("INSERT INTO abandoned_cart_notifications 
        (user_id, email, cart_data, cart_total, items_count, last_activity) 
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$user_id, $email, json_encode($cart_data), $cart_total, $items_count]);
}

error_log("Abandoned cart tracked for user $user_id with $items_count items totaling $cart_total Kč");
?>
