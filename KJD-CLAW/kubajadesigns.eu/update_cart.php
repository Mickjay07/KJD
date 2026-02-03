<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $change = (int)($_POST['change'] ?? 0);
    $remove = isset($_POST['remove']) ? (int)$_POST['remove'] : 0;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if ($remove) {
        // Remove item from cart - find by product ID in cart keys
        foreach ($_SESSION['cart'] as $key => $item) {
            if (isset($item['id']) && $item['id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                break;
            }
        }
        session_write_close(); // Ensure session is saved
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
    } else {
        // Update quantity - find by product ID in cart keys
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if (isset($item['id']) && $item['id'] == $product_id) {
                $current_qty = (int)($item['quantity'] ?? 0);
                $new_qty = $current_qty + $change;
                
                if ($new_qty <= 0) {
                    unset($_SESSION['cart'][$key]);
                } else {
                    $_SESSION['cart'][$key]['quantity'] = $new_qty;
                }
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'Product not found in cart']);
            exit;
        }
        
        session_write_close(); // Ensure session is saved
        echo json_encode(['success' => true, 'message' => 'Cart updated']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>