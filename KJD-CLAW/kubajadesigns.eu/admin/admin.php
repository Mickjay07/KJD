<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Získání statistik pro dashboard
$productCount = 0;
$product2Count = 0;
$orderCount = 0;
$totalRevenue = 0;
$notificationCount = 0;
$recentOrders = [];
$apiError = null;

try {
    // Počet produktů
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (in_array('product', $tables)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM product");
        $stmt->execute();
        $productCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    if (in_array('product2', $tables)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM product2");
        $stmt->execute();
        $product2Count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    // Počet nedostupných barev s požadavky na notifikaci
    if (in_array('color_notifications', $tables)) {
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT product_id, product_type, color) as count FROM color_notifications WHERE notified = 0");
        $stmt->execute();
        $notificationCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    // Načtení objednávek z lokální databáze
    if (in_array('orders', $tables)) {
        // Celkový počet objednávek
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders");
        $stmt->execute();
        $orderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Počet čekajících na platbu
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'");
        $stmt->execute();
        $pendingOrderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Počet dokončených objednávek
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'paid'");
        $stmt->execute();
        $completedOrderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Celkové tržby
        $stmt = $conn->prepare("SELECT SUM(total_price) as revenue FROM orders WHERE payment_status = 'paid'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalRevenue = $result['revenue'] ? floatval($result['revenue']) : 0;
        
        // Poslední objednávky pro tabulku
        $stmt = $conn->prepare("SELECT id, order_id, name, email, total_price, payment_status, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>Chyba při získávání statistik: " . $e->getMessage() . "</div>";
}


?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>KJD Administrace</title>
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
      
      /* Status badges */
        .status-badge {
        padding: 0.5rem 1rem;
            border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
        .status-waiting {
        background: linear-gradient(135deg, #ffc107, #ff8c00);
        color: #fff;
        box-shadow: 0 2px 8px rgba(255,193,7,0.3);
        }
      
        .status-paid {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: #fff;
        box-shadow: 0 2px 8px rgba(40,167,69,0.3);
        }
      
        .status-sent {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: #fff;
        box-shadow: 0 2px 8px rgba(23,162,184,0.3);
        }
      
        .status-cancelled {
        background: linear-gradient(135deg, #dc3545, #e83e8c);
        color: #fff;
        box-shadow: 0 2px 8px rgba(220,53,69,0.3);
      }
      
      /* Table styles */
      .table {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .table th {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: #fff;
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
        
        .table-responsive {
          font-size: 0.9rem;
        }
        
        .table th, .table td {
          padding: 0.5rem;
        }
        
        .badge {
          font-size: 0.7rem;
          padding: 0.25rem 0.5rem;
        }
        
        .dropdown-menu {
          position: static !important;
          transform: none !important;
          box-shadow: none;
          border: 1px solid var(--kjd-earth-green);
          margin-top: 0.5rem;
        }
        
        .navbar-toggler {
          border: 2px solid var(--kjd-earth-green);
          padding: 0.5rem;
        }
        
        .navbar-toggler:focus {
          box-shadow: 0 0 0 0.2rem rgba(76, 100, 68, 0.25);
        }
      }
      
      @media (max-width: 576px) {
        .cart-header {
          padding: 1.5rem 0;
        }
        
        .cart-header h1 {
          font-size: 1.8rem;
        }
        
        .cart-item {
          padding: 1rem;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary {
          padding: 0.7rem 1.2rem;
          font-size: 0.9rem;
        }
        
        .table th, .table td {
          padding: 0.3rem;
          font-size: 0.8rem;
        }
        
        .container-fluid {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
            }
        }
    </style>
</head>
<body class="cart-page">
    <?php include '../includes/icons.php'; ?>

    <div class="preloader-wrapper">
      <div class="preloader"></div>
    </div>

    <!-- Navigation Menu -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Admin Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                                <i class="fas fa-tachometer-alt me-2"></i>KJD Administrace
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Přehled administrace systému</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin_products.php" class="btn btn-kjd-primary d-flex align-items-center">
                                <i class="fas fa-box me-2"></i>Produkty
                            </a>
                            <a href="admin_orders.php" class="btn btn-kjd-secondary d-flex align-items-center">
                                <i class="fas fa-shopping-cart me-2"></i>Objednávky
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
                <?php if ($apiError): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $apiError; ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistics cards -->
                <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="cart-item">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; color: var(--kjd-earth-green);"></i>
                    </div>
                    <h3 class="cart-product-name text-center"><?php echo $orderCount; ?></h3>
                    <p class="cart-product-price text-center mb-2">Celkem objednávek</p>
                    <small class="text-muted text-center d-block">
                        Čeká: <?php echo $pendingOrderCount ?? 0; ?> | 
                        Dokončeno: <?php echo $completedOrderCount ?? 0; ?>
                    </small>
                            </div>
                        </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="cart-item">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--kjd-earth-green);"></i>
                    </div>
                    <h3 class="cart-product-name text-center"><?php echo number_format($totalRevenue, 0, ',', ' '); ?> Kč</h3>
                    <p class="cart-product-price text-center mb-2">Celkové tržby</p>
                    <small class="text-muted text-center d-block">Z dokončených objednávek</small>
                            </div>
                        </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="cart-item">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-box" style="font-size: 3rem; color: var(--kjd-earth-green);"></i>
                    </div>
                    <h3 class="cart-product-name text-center"><?php echo ($productCount + $product2Count); ?></h3>
                    <p class="cart-product-price text-center mb-2">Celkem produktů</p>
                    <small class="text-muted text-center d-block">
                        Kategorie 1: <?php echo $productCount; ?> | 
                        Kategorie 2: <?php echo $product2Count; ?>
                    </small>
                            </div>
                        </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="cart-item">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-bell" style="font-size: 3rem; color: var(--kjd-earth-green);"></i>
                    </div>
                    <h3 class="cart-product-name text-center"><?php echo $notificationCount; ?></h3>
                    <p class="cart-product-price text-center mb-2">Notifikace barvy</p>
                    <small class="text-muted text-center d-block">Žádosti o notifikaci</small>
                        </div>
                    </div>
                </div>
                
                <!-- Recent orders -->
                <div class="row mt-4">
                    <div class="col-12">
                <div class="cart-item">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="cart-product-name mb-0">
                            <i class="fas fa-clock me-2"></i>Poslední objednávky
                        </h3>
                        <a href="admin_orders.php" class="btn btn-kjd-secondary">
                            <i class="fas fa-list me-2"></i>Zobrazit všechny
                        </a>
                            </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Zákazník</th>
                                                <th>E-mail</th>
                                                <th>Celková cena</th>
                                                <th>Stav</th>
                                                <th>Akce</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recentOrders)): ?>
                                                <?php foreach ($recentOrders as $order): ?>
                                                    <tr>
                                            <td><strong>#<?php echo htmlspecialchars($order['order_id'] ?? $order['id']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($order['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($order['email']); ?></td>
                                            <td><strong><?php echo number_format(floatval($order['total_price']), 0, ',', ' '); ?> Kč</strong></td>
                                                        <td>
                                                            <?php 
                                                            $status = $order['payment_status'];
                                                            $statusClass = '';
                                                            $statusText = '';
                                                            switch ($status) {
                                                                case 'paid':
                                                        $statusClass = 'status-paid';
                                                                    $statusText = 'Zaplaceno';
                                                                    break;
                                                                case 'pending':
                                                        $statusClass = 'status-waiting';
                                                                    $statusText = 'Čeká na platbu';
                                                                    break;
                                                                case 'cancelled':
                                                        $statusClass = 'status-cancelled';
                                                                    $statusText = 'Zrušeno';
                                                                    break;
                                                                default:
                                                        $statusClass = 'status-badge';
                                                                    $statusText = 'Neznámý';
                                                                    break;
                                                            }
                                                            ?>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                                <?php echo htmlspecialchars($statusText); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                <div class="d-flex gap-2">
                                                            <a href="admin_order_detail.php?id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-kjd-primary btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                    <a href="admin_edit_order.php?id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-kjd-secondary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--kjd-beige); margin-bottom: 1rem;"></i>
                                            <h5 style="color: var(--kjd-dark-green);">Žádné objednávky k zobrazení</h5>
                                            <p class="text-muted">Zatím nebyly vytvořeny žádné objednávky.</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick actions section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="cart-item">
                    <h3 class="cart-product-name mb-3">
                        <i class="fas fa-bolt me-2"></i>Rychlé akce
                    </h3>
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="novy_produkt.php" class="btn btn-kjd-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-plus-circle me-2"></i>
                                <span>Nový produkt</span>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="manage_colors.php" class="btn btn-kjd-secondary w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-palette me-2"></i>
                                <span>Správa barev</span>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="discount_codes.php" class="btn btn-kjd-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-tag me-2"></i>
                                <span>Slevové kódy</span>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="admin_newsletter.php" class="btn btn-kjd-secondary w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-paper-plane me-2"></i>
                                <span>Rozeslat newsletter</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="py-5 mt-5" style="background: var(--kjd-dark-green); color: #fff;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-menu">
                        <span style="font-size: 1.5rem; font-weight: 700; color: #fff;">KJ</span><span style="font-size: 1.5rem; font-weight: 700; color: var(--kjd-beige);">D</span>
                        <div class="social-links mt-5">
                            <ul class="d-flex list-unstyled gap-2">
                                <li>
                                    <a href="#" class="btn btn-outline-light">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M15.12 5.32H17V2.14A26.11 26.11 0 0 0 14.26 2c-2.72 0-4.58 1.66-4.58 4.7v2.62H6.61v3.56h3.07V22h3.68v-9.12h3.06l.46-3.56h-3.52V7.05c0-1.05.28-1.73 1.76-1.73Z"/></svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="btn btn-outline-light">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M22.991 3.95a1 1 0 0 0-1.51-.86a7.48 7.48 0 0 1-1.874.794a5.152 5.152 0 0 0-3.374-1.242a5.232 5.232 0 0 0-5.223 5.063a11.032 11.032 0 0 1-6.814-3.924a1.012 1.012 0 0 0-.857-.365a.999.999 0 0 0-.785.5a5.276 5.276 0 0 0-.242 4.769l-.002.001a1.041 1.041 0 0 0-.496.89a3.042 3.042 0 0 0 .027.439a5.185 5.185 0 0 0 1.568 3.312a.998.998 0 0 0-.066.77a5.204 5.204 0 0 0 2.362 2.922a7.465 7.465 0 0 1-3.59.448A1 1 0 0 0 1.45 19.3a12.942 12.942 0 0 0 7.01 2.061a12.788 12.788 0 0 0 12.465-9.363a12.822 12.822 0 0 0 .535-3.646l-.001-.2a5.77 5.77 0 0 0 1.532-4.202Zm-3.306 3.212a.995.995 0 0 0-.234.702c.01.165.009.331.009.488a10.824 10.824 0 0 1-.454 3.08a10.685 10.685 0 0 1-10.546 7.93a10.938 10.938 0 0 1-2.55-.301a9.48 9.48 0 0 0 2.942-1.564a1 1 0 0 0-.602-1.786a3.208 3.208 0 0 1-2.214-.935q.224-.042.445-.105a1 1 0 0 0-.08-1.943a3.198 3.198 0 0 1-2.25-1.726a5.3 5.3 0 0 0 .545.046a1 1 0 0 0 .984-.696a1 1 0 0 0-.4-1.137a3.196 3.196 0 0 1-1.425-2.673c0-.066.002-.133.006-.198a13.014 13.014 0 0 0 8.21 3.48a1.02 1.02 0 0 0 .817-.36a1 1 0 0 0 .206-.867a3.157 3.157 0 0 1-.087-.729a3.23 3.23 0 0 1 3.226-3.226a3.184 3.184 0 0 1 2.345 1.02a.993.993 0 0 0 .921.298a9.27 9.27 0 0 0 1.212-.322a6.681 6.681 0 0 1-1.026 1.524Z"/></svg>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
                </div>
    </footer>

    <footer class="py-5 mt-5" style="background: var(--kjd-dark-green); color: #fff;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="footer-menu">
              <span style="font-size: 1.5rem; font-weight: 700; color: #fff;">KJ</span><span style="font-size: 1.5rem; font-weight: 700; color: var(--kjd-beige);">D</span>
              <div class="social-links mt-5">
                <ul class="d-flex list-unstyled gap-2">
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M15.12 5.32H17V2.14A26.11 26.11 0 0 0 14.26 2c-2.72 0-4.58 1.66-4.58 4.7v2.62H6.61v3.56h3.07V22h3.68v-9.12h3.06l.46-3.56h-3.52V7.05c0-1.05.28-1.73 1.76-1.73Z"/></svg>
                    </a>
                  </li>
                  <li>
                    <a href="#" class="btn btn-outline-light">
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M22.991 3.95a1 1 0 0 0-1.51-.86a7.48 7.48 0 0 1-1.874.794a5.152 5.152 0 0 0-3.374-1.242a5.232 5.232 0 0 0-5.223 5.063a11.032 11.032 0 0 1-6.814-3.924a1.012 1.012 0 0 0-.857-.365a.999.999 0 0 0-.785.5a5.276 5.276 0 0 0-.242 4.769l-.002.001a1.041 1.041 0 0 0-.496.89a3.042 3.042 0 0 0 .027.439a5.185 5.185 0 0 0 1.568 3.312a.998.998 0 0 0-.066.77a5.204 5.204 0 0 0 2.362 2.922a7.465 7.465 0 0 1-3.59.448A1 1 0 0 0 1.45 19.3a12.942 12.942 0 0 0 7.01 2.061a12.788 12.788 0 0 0 12.465-9.363a12.822 12.822 0 0 0 .535-3.646l-.001-.2a5.77 5.77 0 0 0 1.532-4.202Zm-3.306 3.212a.995.995 0 0 0-.234.702c.01.165.009.331.009.488a10.824 10.824 0 0 1-.454 3.08a10.685 10.685 0 0 1-10.546 7.93a10.938 10.938 0 0 1-2.55-.301a9.48 9.48 0 0 0 2.942-1.564a1 1 0 0 0-.602-1.786a3.208 3.208 0 0 1-2.214-.935q.224-.042.445-.105a1 1 0 0 0-.08-1.943a3.198 3.198 0 0 1-2.25-1.726a5.3 5.3 0 0 0 .545.046a1 1 0 0 0 .984-.696a1 1 0 0 0-.4-1.137a3.196 3.196 0 0 1-1.425-2.673c0-.066.002-.133.006-.198a13.014 13.014 0 0 0 8.21 3.48a1.02 1.02 0 0 0 .817-.36a1 1 0 0 0 .206-.867a3.157 3.157 0 0 1-.087-.729a3.23 3.23 0 0 1 3.226-3.226a3.184 3.184 0 0 1 2.345 1.02a.993.993 0 0 0 .921.298a9.27 9.27 0 0 0 1.212-.322a6.681 6.681 0 0 1-1.026 1.524Z"/></svg>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </footer>

    <div id="footer-bottom" style="background: var(--kjd-dark-brown); color: #fff;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 copyright">
            <p>© 2023 KJD. All rights reserved.</p>
          </div>
          <div class="col-md-6 credit-link text-start text-md-end">
            <p>Kubajadesigns.eu</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 