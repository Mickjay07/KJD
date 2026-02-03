<?php
header('Content-Type: application/json');
session_start();

// Database connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES utf8");
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log the input
error_log("Notification input: " . print_r($input, true));

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$email = trim($input['email'] ?? '');
$name = trim($input['name'] ?? '');
$product_id = (int)($input['product_id'] ?? 0);
$color_code = trim($input['color_code'] ?? '');
$color_name = trim($input['color_name'] ?? '');
$type = trim($input['type'] ?? '');

// Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Neplatný email']);
    exit;
}

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Neplatné ID produktu']);
    exit;
}

if (empty($color_code) || empty($color_name)) {
    echo json_encode(['success' => false, 'message' => 'Neplatná barva']);
    exit;
}

try {
    // Check if notification already exists (using existing table structure)
    $stmt = $conn->prepare("
        SELECT id FROM color_notifications 
        WHERE email = ? AND product_id = ? AND color = ? AND notified = 0
    ");
    $stmt->execute([$email, $product_id, $color_code]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Upozornění pro tuto barvu již máte zaregistrované']);
        exit;
    }

    // Insert new notification (using existing table structure)
    $stmt = $conn->prepare("
        INSERT INTO color_notifications (product_id, product_type, color, email, notified, date_requested) 
        VALUES (?, 'product', ?, ?, 0, NOW())
    ");
    
    $result = $stmt->execute([$product_id, $color_code, $email]);
    
    // Debug: Log the result
    error_log("Insert result: " . ($result ? 'success' : 'failed'));
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Upozornění bylo úspěšně zaregistrováno']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepodařilo se zaregistrovat upozornění']);
    }
    
} catch(PDOException $e) {
    error_log("Notification registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Nastala chyba při registraci upozornění']);
}
?>