<?php
// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Clean any output buffer and set JSON header
ob_clean();
header('Content-Type: application/json');

// Get search term from POST data
$input = json_decode(file_get_contents('php://input'), true);
$search = trim($input['search'] ?? '');

// Debug logging
error_log("Order search - Input: " . print_r($input, true));
error_log("Order search - Search term: '$search'");

if (empty($search)) {
    error_log("Order search - Empty search term");
    echo json_encode(['success' => false, 'error' => 'No search term provided']);
    exit;
}

try {
    // Search by order_id (exact/LIKE), numeric id, or any known email column
    $stmt = $conn->prepare("
        SELECT * FROM orders
        WHERE order_id = ?
           OR order_id LIKE ?
           OR id = ?
           OR customer_email LIKE ?
           OR email LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    
    $orderIdLike = '%' . $search . '%';
    $emailSearch = '%' . $search . '%';
    $numericId = ctype_digit($search) ? (int)$search : 0;
    
    // Debug SQL parameters
    error_log("Order search - SQL params: [exact='$search', like='$orderIdLike', id='$numericId', email1='$emailSearch', email2='$emailSearch']");
    
    $stmt->execute([$search, $orderIdLike, $numericId, $emailSearch, $emailSearch]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug SQL result
    error_log("Order search - SQL result: " . ($order ? "Found order ID: {$order['order_id']}" : "No order found"));
    
    if (!$order) {
        // Try a simpler query to see if there are any orders at all
        $stmt2 = $conn->prepare("SELECT COUNT(*) as total FROM orders");
        $stmt2->execute();
        $count = $stmt2->fetch(PDO::FETCH_ASSOC);
        error_log("Order search - Total orders in DB: {$count['total']}");
        
        // Try to find any order with similar ID
        $stmt3 = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? OR order_id LIKE ? OR id = ? LIMIT 3");
        $stmt3->execute([$search, "%$search%", $numericId]);
        $similar = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        error_log("Order search - Similar IDs found: " . print_r($similar, true));
        
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    
    // Parse order items from products_json
    $order_items = [];
    if (!empty($order['products_json'])) {
        $products_data = json_decode($order['products_json'], true);
        if (is_array($products_data)) {
            foreach ($products_data as $key => $item) {
                // Skip delivery info and other meta data
                if (strpos($key, '_') === 0) continue;
                
                if (isset($item['name']) && isset($item['quantity']) && isset($item['final_price'])) {
                    $order_items[] = [
                        'name' => $item['name'],
                        'quantity' => intval($item['quantity']),
                        'price' => floatval($item['final_price'])
                    ];
                }
            }
        }
    }
    
    // Extract delivery address from products_json if available
    $delivery_address = '';
    if (!empty($order['products_json'])) {
        $products_data = json_decode($order['products_json'], true);
        if (isset($products_data['_delivery_info'])) {
            $delivery = $products_data['_delivery_info'];
            $address_parts = [];
            
            if (!empty($delivery['street'])) $address_parts[] = $delivery['street'];
            if (!empty($delivery['city'])) $address_parts[] = $delivery['city'];
            if (!empty($delivery['postal_code'])) $address_parts[] = $delivery['postal_code'];
            
            $delivery_address = implode(', ', $address_parts);
        }
    }
    
    // Map fields with fallbacks (different schemas)
    $customer_name  = $order['customer_name']  ?? ($order['name'] ?? '');
    $customer_email = $order['customer_email'] ?? ($order['email'] ?? '');
    $customer_phone = $order['customer_phone'] ?? ($order['phone_number'] ?? ($order['phone'] ?? ''));
    $customer_addr_raw = $order['customer_address'] ?? ($order['address'] ?? '');
    $total = isset($order['total_with_vat']) ? (float)$order['total_with_vat'] : (isset($order['total_price']) ? (float)$order['total_price'] : 0);
    
    // Use delivery address if available, otherwise fallback to address column
    $customer_address = !empty($delivery_address) ? $delivery_address : $customer_addr_raw;
    
    // Prepare response
    $response = [
        'success' => true,
        'order' => [
            'order_id' => $order['order_id'],
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_address' => $customer_address,
            'total_with_vat' => $total,
            'created_at' => $order['created_at'] ?? ($order['order_date'] ?? ''),
            'items' => $order_items
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Order search error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

// Ensure clean JSON output
ob_end_flush();
?>
