<?php
session_start();
require_once 'config.php';
require_once 'includes/lamp_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base_id = isset($_POST['base_id']) ? intval($_POST['base_id']) : 0;
    $shade_id = isset($_POST['shade_id']) ? intval($_POST['shade_id']) : 0;
    
    if ($base_id > 0 && $shade_id > 0) {
        $base = get_component_by_id($conn, $base_id);
        $shade = get_component_by_id($conn, $shade_id);
        
        if ($base && $shade) {
            $base_lamp_price = 1500; // Should match the frontend or be fetched from DB
            $final_price = $base_lamp_price + floatval($base['price_modifier']) + floatval($shade['price_modifier']);
            
            $product_name = "Designová Lampa (" . $base['name'] . " + " . $shade['name'] . ")";
            
            // Create a unique ID for this custom configuration
            $cart_id = 'lamp_' . uniqid();
            
            $cart_item = [
                'id' => 99999, // Dummy ID for custom product
                'name' => "Designová Lampa",
                'price' => $final_price,
                'quantity' => 1,
                'image_url' => 'images/lamp_placeholder.jpg', // Placeholder image
                'variants' => [
                    'Podstavec' => $base['name'],
                    'Stínidlo' => $shade['name']
                ],
                'is_custom_lamp' => true,
                'base_id' => $base_id,
                'shade_id' => $shade_id
            ];
            
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            $_SESSION['cart'][$cart_id] = $cart_item;
            
            header("Location: cart.php");
            exit;
        }
    }
}

// If something went wrong, redirect back
header("Location: lamp_configurator.php?error=invalid_selection");
exit;
?>
