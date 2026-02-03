<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'config.php';
require_once '../functions.php';

// Kontrola přihlášení - dočasně vypnuto pro testování
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     header('Location: admin_login.php');
//     exit;
// }

// Check if database connection is available
if (!isset($conn) || $conn === null) {
    echo "Databázové připojení není dostupné.";
    exit;
}

// Získání ID objednávky z URL
$order_id = $_GET['id'] ?? null;
echo "<!-- Debug: order_id = " . htmlspecialchars($order_id) . " -->";

if (!$order_id) {
    echo "<!-- Debug: No order_id, redirecting to admin_orders.php -->";
    // header('Location: admin_orders.php');
    // exit;
    $order_id = 1; // Test with order ID 1
}

try {
    echo "<!-- Debug: Starting database query -->";
    
    // Načtení detailů objednávky včetně informací o uživateli
    $stmt = $conn->prepare("
        SELECT o.*, 
               COALESCE(o.email, u.email) as email,
               u.first_name, u.last_name, u.phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<!-- Debug: Query executed, order found: " . ($order ? 'YES' : 'NO') . " -->";

    if (!$order) {
        echo "<!-- Debug: No order found, creating test order -->";
        // Create a test order for debugging
        $order = [
            'id' => $order_id,
            'order_id' => 'TEST-' . $order_id,
            'name' => 'Test Zákazník',
            'email' => 'test@example.com',
            'phone_number' => '+420 123 456 789',
            'delivery_method' => 'standard',
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'status' => 'Přijato',
            'total_price' => 1250.00,
            'products_json' => '[{"id":"1","name":"Test Produkt","quantity":1,"price":1250,"color":"Černá"}]',
            'note' => 'Test objednávka',
            'address' => 'Test Adresa 123',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    // Dekódování JSON produktů
    $products = json_decode($order['products_json'], true);
    echo "<!-- Debug: Products decoded: " . (is_array($products) ? count($products) : 'NO') . " items -->";
    
} catch(PDOException $e) {
    echo "<!-- Debug: Database error: " . $e->getMessage() . " -->";
    die("Chyba při načítání objednávky: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <!-- Debug: HTML started -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail objednávky - KJD Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
      :root { 
        --kjd-dark-green:#102820; 
        --kjd-earth-green:#4c6444; 
        --kjd-gold-brown:#8A6240; 
        --kjd-dark-brown:#4D2D18; 
        --kjd-beige:#CABA9C; 
      }
      
      /* Apple SF Pro Font */
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      /* Cart page background */
      .cart-page { 
        background: #f8f9fa; 
        min-height: 100vh; 
      }
      
      /* Cart header */
      .cart-header { 
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
        padding: 3rem 0; 
        margin-bottom: 2rem; 
        border-bottom: 3px solid var(--kjd-earth-green);
        box-shadow: 0 4px 20px rgba(16,40,32,0.1);
      }
      
      .cart-header h1 { 
        font-size: 2.5rem; 
        font-weight: 800; 
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        margin-bottom: 0.5rem;
        color: var(--kjd-dark-green);
      }
      
      .cart-header p { 
        font-size: 1.1rem; 
        font-weight: 500;
        opacity: 0.8;
        color: var(--kjd-gold-brown);
      }
      
      /* Cart items */
      .cart-item { 
        background: #fff; 
        border-radius: 16px; 
        padding: 2rem; 
        margin-bottom: 1.5rem; 
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      
      .cart-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      
      .cart-product-name { 
        color: var(--kjd-dark-green); 
        font-weight: 700; 
        font-size: 1.3rem;
        margin-bottom: 0.5rem; 
      }
      
      .cart-product-price { 
        color: var(--kjd-gold-brown); 
        font-weight: 800; 
        font-size: 1.2rem;
      }
      
      /* KJD Buttons */
      .btn-kjd-primary { 
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
        color: #fff; 
        border: none; 
        padding: 1rem 2.5rem; 
        border-radius: 12px; 
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(77,45,24,0.3);
      }
      
      .btn-kjd-primary:hover { 
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(77,45,24,0.4);
      }
      
      .btn-kjd-secondary { 
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
        color: var(--kjd-dark-green); 
        border: 2px solid var(--kjd-earth-green); 
        padding: 1rem 2.5rem; 
        border-radius: 12px; 
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s ease;
      }
      
      .btn-kjd-secondary:hover { 
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76,100,68,0.3);
      }
      
      /* Table styles */
      .table {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .table th {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        color: var(--kjd-dark-green);
        font-weight: 700;
        padding: 1rem;
        border: none;
      }
      
      .table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(202,186,156,0.1);
        vertical-align: middle;
      }
      
      .table tbody tr:hover {
        background: rgba(202,186,156,0.05);
      }
      
      /* Status badges */
      .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .status-waiting {
        background: linear-gradient(135deg, #ffc107, #e0a800);
        color: #000;
        box-shadow: 0 2px 8px rgba(255,193,7,0.3);
      }
      
      .status-paid {
        background: linear-gradient(135deg, #28a745, #1e7e34);
        color: #fff;
        box-shadow: 0 2px 8px rgba(40,167,69,0.3);
      }
      
      .status-sent {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: #fff;
        box-shadow: 0 2px 8px rgba(23,162,184,0.3);
      }
      
      .status-cancelled {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: #fff;
        box-shadow: 0 2px 8px rgba(220,53,69,0.3);
      }
      
      /* Preloader */
      .preloader-wrapper {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(248, 249, 250, 0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.3s ease;
      }
      
      .preloader {
        width: 50px;
        height: 50px;
        border: 3px solid var(--kjd-beige);
        border-top: 3px solid var(--kjd-dark-green);
        border-radius: 50%;
        animation: spin 1s linear infinite;
      }
      
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
      
      .preloader-wrapper.hidden {
        opacity: 0;
        pointer-events: none;
      }
      
      /* Mobile Styles */
      @media (max-width: 768px) {
        .cart-header {
          padding: 2rem 0;
        }
        
        .cart-header h1 {
          font-size: 2rem;
        }
        
        .cart-header p {
          font-size: 1rem;
        }
        
        .cart-item {
          padding: 1.5rem;
          margin-bottom: 1rem;
        }
        
        .cart-product-name {
          font-size: 1.1rem;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary {
          padding: 0.8rem 1.5rem;
          font-size: 1rem;
        }
        
        .table th, .table td {
          padding: 0.5rem;
        }
        
        .container-fluid {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
        }
      }
    </style>
</head>
<body class="cart-page">
    <!-- Debug: Body started -->
    <?php 
    echo "<!-- Debug: Including icons.php -->";
    if (file_exists('../includes/icons.php')) {
        include '../includes/icons.php';
        echo "<!-- Debug: icons.php included successfully -->";
    } else {
        echo "<!-- Debug: icons.php not found -->";
    }
    ?>
    
    <!-- Preloader -->
    <div class="preloader-wrapper">
      <div class="preloader"></div>
    </div>

    <!-- Navigation Menu -->
    <?php 
    echo "<!-- Debug: Including admin_sidebar.php -->";
    if (file_exists('admin_sidebar.php')) {
        include 'admin_sidebar.php';
        echo "<!-- Debug: admin_sidebar.php included successfully -->";
    } else {
        echo "<!-- Debug: admin_sidebar.php not found -->";
    }
    ?>

    <!-- Admin Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-file-alt me-3"></i>Detail objednávky #<?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></h1>
                    <!-- Debug: Order data -->
                    <?php echo "<!-- Debug: Order ID: " . htmlspecialchars($order['order_id'] ?? 'N/A') . " -->"; ?>
                    <?php echo "<!-- Debug: Order Name: " . htmlspecialchars($order['name'] ?? 'N/A') . " -->"; ?>
                    <p>Přehled a správa objednávky</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Action buttons -->
                <div class="cart-item mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="cart-product-name mb-0">
                                <i class="fas fa-cogs me-2"></i>Akce s objednávkou
                            </h3>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin_orders.php" class="btn btn-kjd-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Zpět na seznam
                            </a>
                            <a href="admin_edit_order.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="btn btn-kjd-primary">
                                <i class="fas fa-edit me-2"></i> Upravit objednávku
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Payment status alert -->
                <?php if ($order['payment_status'] === 'paid'): ?>
                <div class="alert alert-success alert-dismissible fade show cart-item">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Platba potvrzena</strong> 
                    <?php if ($order['payment_confirmed_at']): ?>
                        - <?php echo date('d.m.Y H:i', strtotime($order['payment_confirmed_at'])); ?>
                    <?php endif; ?>
                    <?php if ($order['invoice_file']): ?>
                        <br><i class="fas fa-file-pdf me-2"></i>
                        <a href="../../uploads/invoices/<?php echo htmlspecialchars($order['invoice_file']); ?>" target="_blank" class="btn btn-sm btn-outline-success mt-1">
                            Stáhnout fakturu
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php else: ?>
                <div class="alert alert-warning alert-dismissible fade show cart-item">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Objednávka čeká na platbu</strong>
                    <br><small>Použijte tlačítko "Upravit" pro potvrzení platby a odeslání faktury.</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <!-- Customer Information -->
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-user me-2"></i>Informace o zákazníkovi
                            </h3>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Jméno:</strong></td>
                                    <td><?php echo htmlspecialchars($order['name'] ?? ($order['first_name'] . ' ' . $order['last_name'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>E-mail:</strong></td>
                                    <td><?php if (!empty($order['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($order['email']); ?>"><?php echo htmlspecialchars($order['email']); ?></a>
                                    <?php else: ?>
                                        Neuvedeno
                                    <?php endif; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Telefon:</strong></td>
                                    <td><?php echo htmlspecialchars($order['phone_number'] ?? $order['phone'] ?? 'Neuvedeno'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Způsob doručení:</strong></td>
                                    <td><?php echo htmlspecialchars($order['delivery_method']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Způsob platby:</strong></td>
                                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                </tr>
                                <?php if (!empty($order['note'])): ?>
                                <tr>
                                    <td><strong>Poznámka:</strong></td>
                                    <td><?php echo nl2br(htmlspecialchars($order['note'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <?php if (!empty($order['note'])): ?>
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-sticky-note me-2"></i>Poznámka zákazníka
                            </h3>
                            <div class="alert alert-info mb-0">
                                <?php echo nl2br(htmlspecialchars($order['note'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Delivery Information -->
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-truck me-2"></i>Doručovací údaje
                            </h3>
                            <p><strong>Způsob doručení:</strong> <?php echo htmlspecialchars($order['delivery_method']); ?></p>
                            <?php if (!empty($order['tracking_code'])): ?>
                                <p><strong>Sledovací číslo:</strong> 
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-barcode me-1"></i>
                                        <?php echo htmlspecialchars($order['tracking_code']); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            <?php 
                                // Extract delivery info from products_json if available
                                $deliveryInfo = [];
                                if (is_array($products) && isset($products['_delivery_info']) && is_array($products['_delivery_info'])) {
                                    $deliveryInfo = $products['_delivery_info'];
                                }
                            ?>
                            <?php if ($order['delivery_method'] === 'Zásilkovna'):
                                $z = $deliveryInfo['zasilkovna'] ?? [];
                                $zName = $z['name'] ?? '';
                                $zStreet = $z['street'] ?? '';
                                $zPostal = $z['postal_code'] ?? '';
                                $zLines = array_filter([$zName, $zStreet, $zPostal], function($v){ return (string)$v !== ''; });
                            ?>
                                <?php if (!empty($zLines)): ?>
                                    <p><strong>Výdejní místo Zásilkovny:</strong><br>
                                    <?php echo nl2br(htmlspecialchars(implode("\n", $zLines))); ?></p>
                                <?php elseif (!empty($order['address'])): ?>
                                    <p><strong>Výdejní místo Zásilkovny:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                                <?php endif; ?>
                            <?php elseif ($order['delivery_method'] === 'AlzaBox'): ?>
                                <?php $abCode = $deliveryInfo['alzabox']['code'] ?? ''; ?>
                                <?php if (!empty($abCode)): ?>
                                    <p><strong>AlzaBox:</strong><br>
                                    <?php echo htmlspecialchars($abCode); ?></p>
                                <?php elseif (!empty($order['address'])): ?>
                                    <p><strong>AlzaBox:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($order['address'])): ?>
                                    <p><strong>Adresa:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- Products -->
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-box me-2"></i>Produkty
                            </h3>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Produkt</th>
                                            <th>Barva</th>
                                            <th>Množství</th>
                                            <th>Cena/ks</th>
                                            <th>Celkem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!is_array($products)) { $products = []; } ?>
                                        <?php foreach ($products as $pKey => $product): ?>
                                        <?php
                                            // Skip non-product/meta entries, e.g. _delivery_info
                                            $isNumericKey = is_int($pKey) || ctype_digit((string)$pKey);
                                            if (!$isNumericKey && is_string($pKey) && strlen($pKey) > 0 && $pKey[0] === '_') { continue; }
                                            if (!is_array($product)) { continue; }
                                            // Must have at least a name or id
                                            if (!isset($product['name']) && !isset($product['product_name']) && !isset($product['title']) && !isset($product['id'])) { continue; }
                                            $pName = $product['name'] ?? ($product['title'] ?? ($product['product_name'] ?? (isset($product['id']) ? ('Produkt #' . $product['id']) : 'Neznámý produkt')));
                                            $pQty  = (int)($product['quantity'] ?? ($product['qty'] ?? 1));
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pName); ?></td>
                                            <td>
                                                <?php 
                                                // Zkontroluj selected_color (nový formát) nebo color (starý formát)
                                                $productColor = $product['selected_color'] ?? $product['color'] ?? '';
                                                if (!empty($productColor)): ?>
                                                    <span style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($productColor); ?>; border: 1px solid #ccc; vertical-align: middle;"></span>
                                                    <?php echo htmlspecialchars(getColorName($productColor)); ?>
                                                <?php endif; ?>
                                                <?php 
                                                // Zkontroluj component_colors
                                                if (!empty($product['component_colors']) && is_array($product['component_colors'])): 
                                                    foreach ($product['component_colors'] as $compColor): 
                                                        if (!empty($compColor)): ?>
                                                            <br><small>Komponenta: <span style="display: inline-block; width: 15px; height: 15px; background-color: <?php echo htmlspecialchars($compColor); ?>; border: 1px solid #ccc; vertical-align: middle;"></span> <?php echo htmlspecialchars(getColorName($compColor)); ?></small>
                                                        <?php endif; 
                                                    endforeach; 
                                                endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($pQty); ?></td>
                                            <td><?php 
                                                // Check for different possible price fields (prefer final_price)
                                                $price = $product['final_price'] ?? $product['price'] ?? $product['base_price'] ?? 0;
                                                echo number_format($price, 0, ',', ' '); ?> Kč</td>
                                            <td><?php 
                                                // Use the same price logic for total calculation
                                                $price = $product['final_price'] ?? $product['price'] ?? $product['base_price'] ?? 0;
                                                echo number_format($price * $pQty, 0, ',', ' '); ?> Kč</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- Order Summary -->
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-receipt me-2"></i>Souhrn objednávky
                            </h3>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Stav objednávky:</span>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo getStatusText($order['status']); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Datum vytvoření:</span>
                                <span><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <strong>Celková cena:</strong>
                                <strong class="cart-product-price"><?php echo number_format($order['total_price'], 0, ',', ' '); ?> Kč</strong>
                            </div>
                            
                            <?php if (isset($order['wallet_used']) && $order['wallet_used']): ?>
                            <div class="d-flex justify-content-between" style="background: rgba(202,186,156,0.2); padding: 12px; border-radius: 8px; margin: 8px 0; border: 2px solid #CABA9C;">
                                <strong><i class="fas fa-wallet me-2"></i>Zůstatek z účtu:</strong>
                                <strong style="color: #4c6444;">-<?php echo number_format($order['wallet_amount'] ?? 0, 0, ',', ' '); ?> Kč</strong>
                            </div>
                            <div class="d-flex justify-content-between" style="background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 12px; border-radius: 8px; margin: 8px 0;">
                                <strong>K úhradě:</strong>
                                <strong><?php echo number_format($order['amount_to_pay'] ?? $order['total_price'], 0, ',', ' '); ?> Kč</strong>
                            </div>
                            <?php else: ?>
                            <div class="d-flex justify-content-between" style="background: rgba(108,117,125,0.1); padding: 12px; border-radius: 8px; margin: 8px 0;">
                                <strong><i class="fas fa-credit-card me-2"></i>Wallet použit:</strong>
                                <strong style="color: #6c757d;">Ne</strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Invoice Actions -->
                        <div class="cart-item">
                            <h3 class="cart-product-name mb-3">
                                <i class="fas fa-file-invoice me-2"></i>Vytvoření faktury
                            </h3>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-kjd-primary" onclick="createInvoiceFromOrder()">
                                    <i class="fas fa-file-invoice me-2"></i>Vytvořit fakturu z objednávky
                                </button>
                                <?php
                                // Check if invoice exists for this order
                                $invoiceStmt = $conn->prepare("SELECT id, invoice_number FROM invoices WHERE order_id = ?");
                                $invoiceStmt->execute([$order_id]);
                                $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($invoice): ?>
                                <button type="button" class="btn btn-kjd-secondary" onclick="sendInvoiceEmail(<?= $invoice['id'] ?>)">
                                    <i class="fas fa-envelope me-2"></i>Odeslat fakturu emailem
                                </button>
                                <div class="alert alert-success mt-2">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Faktura existuje:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Faktura neexistuje:</strong> Nejdříve vytvořte fakturu z objednávky.
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Vytvoření faktury:</strong> Kliknutím na tlačítko se přenesou všechny údaje z této objednávky do nové faktury.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center py-4">
                    <p class="mb-0" style="color: var(--kjd-gold-brown); font-weight: 600;">
                        © 2024 KJD Designs. Všechna práva vyhrazena.
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function createInvoiceFromOrder() {
        if (confirm('Opravdu chcete vytvořit fakturu z této objednávky? Všechny údaje z objednávky budou přeneseny do nové faktury.')) {
            // Připravíme data z objednávky pro přenos do faktury
            const orderData = {
                order_id: <?php echo json_encode($order['order_id'] ?? ''); ?>,
                customer_name: <?php echo json_encode($order['name'] ?? ($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')); ?>,
                customer_email: <?php echo json_encode($order['email'] ?? $order['customer_email'] ?? ''); ?>,
                customer_phone: <?php echo json_encode($order['phone_number'] ?? $order['phone'] ?? ''); ?>,
                customer_address: <?php echo json_encode($order['address'] ?? ''); ?>,
                customer_city: <?php echo json_encode($order['city'] ?? ''); ?>,
                customer_postal_code: <?php echo json_encode($order['postal_code'] ?? ''); ?>,
                delivery_method: <?php echo json_encode($order['delivery_method'] ?? ''); ?>,
                payment_method: <?php echo json_encode($order['payment_method'] ?? ''); ?>,
                total_price: <?php echo json_encode($order['total_price'] ?? 0); ?>,
                note: <?php echo json_encode($order['note'] ?? ''); ?>,
                products: <?php 
                    // Připravíme produkty pro přenos - vyfiltrujeme metadata a použijeme správné ceny
                    $productsForInvoice = [];
                    if (is_array($products)) {
                        foreach ($products as $pKey => $product) {
                            // Přeskoč metadata jako _delivery_info
                            if (is_string($pKey) && strlen($pKey) > 0 && $pKey[0] === '_') continue;
                            if (!is_array($product)) continue;
                            
                            // Zkontroluj, zda má produkt alespoň název nebo ID
                            if (!isset($product['name']) && !isset($product['product_name']) && !isset($product['title']) && !isset($product['id'])) continue;
                            
                            $pName = $product['name'] ?? ($product['title'] ?? ($product['product_name'] ?? (isset($product['id']) ? ('Produkt #' . $product['id']) : 'Neznámý produkt')));
                            $pQty = (int)($product['quantity'] ?? 1);
                            // Použij final_price, pokud existuje, jinak price
                            $pPrice = $product['final_price'] ?? $product['price'] ?? $product['base_price'] ?? 0;
                            
                            $productsForInvoice[] = [
                                'name' => $pName,
                                'quantity' => $pQty,
                                'price' => $pPrice,
                                'final_price' => $pPrice
                            ];
                        }
                    }
                    echo json_encode($productsForInvoice);
                ?>,
                delivery_info: <?php 
                    // Přidáme delivery_info pro případné použití
                    $deliveryInfoForInvoice = [];
                    if (is_array($products) && isset($products['_delivery_info'])) {
                        $deliveryInfoForInvoice = $products['_delivery_info'];
                    }
                    echo json_encode($deliveryInfoForInvoice);
                ?>,
                created_at: <?php echo json_encode($order['created_at'] ?? ''); ?>
            };
            
            // Vytvoříme formulář pro přenos dat
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_invoice_add.php';
            
            // Přidáme skrytý input s daty objednávky
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'order_data';
            input.value = JSON.stringify(orderData);
            form.appendChild(input);
            
            // Přidáme akci pro vytvoření z objednávky
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'create_from_order';
            form.appendChild(actionInput);
            
            // Přidáme formulář do stránky a odešleme
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Function to send invoice via email
    function sendInvoiceEmail(invoiceId) {
        if (confirm('Opravdu chcete odeslat fakturu emailem zákazníkovi?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Odesílám...';
            button.disabled = true;
            
            // Send AJAX request
            fetch('send_invoice_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'invoice_id=' + invoiceId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'Faktura byla úspěšně odeslána emailem!');
                } else {
                    alert('Chyba při odesílání faktury: ' + (data.error || 'Neznámá chyba'));
                }
            })
            .catch(error => {
                alert('Chyba při odesílání faktury: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    }
    
    // Hide preloader when page is loaded
    window.addEventListener('load', function() {
        const preloader = document.querySelector('.preloader-wrapper');
        if (preloader) {
            preloader.classList.add('hidden');
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 300);
        }
    });
    </script>
</body>
</html>

<?php
// Helper functions - only getStatusColor since getStatusText is in functions.php
function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'Přijato' => 'warning',
        'Zpracovává se' => 'info',
        'Odesláno' => 'primary',
        'Doručeno' => 'success',
        'Zrušeno' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

// getStatusText() and getColorName() are provided by functions.php
?> 