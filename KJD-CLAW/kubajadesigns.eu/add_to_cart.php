<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle Custom Print
    if (isset($_POST['action']) && $_POST['action'] === 'add_custom_print') {
        $filament_id = intval($_POST['filament_id']);
        $weight = floatval($_POST['weight']);
        $price = floatval($_POST['price']);
        $volume = floatval($_POST['volume']);
        
        // Handle File Upload
        $uploadDir = 'uploads/custom_prints/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['stl_file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['stl_file']['tmp_name'], $targetPath)) {
            // Fetch filament name
            $stmt = $conn->prepare("SELECT name FROM filaments WHERE id = ?");
            $stmt->execute([$filament_id]);
            $filament = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $item = [
                'id' => 'custom_' . uniqid(),
                'name' => 'Zakázkový 3D Tisk',
                'type' => 'custom_print',
                'price' => $price,
                'quantity' => 1,
                'image' => 'images/custom_print_icon.png', // Placeholder
                'details' => [
                    'file' => $targetPath,
                    'original_name' => $_FILES['stl_file']['name'],
                    'filament' => $filament['name'],
                    'weight' => $weight,
                    'volume' => $volume
                ]
            ];
            
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            $_SESSION['cart'][] = $item;
            
            $_SESSION['cart_message'] = 'Zakázkový tisk byl přidán do košíku!';
            header('Location: cart.php');
            exit;
        } else {
            die("Chyba při nahrávání souboru.");
        }
    }

    // Standard Product Add
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($product_id > 0 && $quantity > 0) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if product is already in cart (simple logic for now, might need improvement for complex carts)
        // Note: The previous logic was using product_id as key, which is simple but doesn't support variants well if they share ID.
        // Assuming simple cart for now based on previous code.
        
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        
        $_SESSION['cart_message'] = 'Produkt byl přidán do košíku!';
    } else {
        $_SESSION['cart_error'] = 'Chyba při přidávání produktu do košíku.';
    }
}

// Redirect back
if (isset($_SERVER['HTTP_REFERER'])) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: index.php');
}
exit;
?>