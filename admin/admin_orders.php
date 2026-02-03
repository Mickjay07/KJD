<?php
// Turn on error reporting at the top of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$success_message = $_SESSION['admin_success'] ?? '';
$error_message = $_SESSION['admin_error'] ?? '';

// Vyčištění flash zpráv
unset($_SESSION['admin_success']);
unset($_SESSION['admin_error']);

// Zpracování mazání objednávky
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $order_id = $_POST['order_id'] ?? null;
    
    if ($order_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $_SESSION['admin_success'] = 'Objednávka byla úspěšně smazána.';
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = 'Chyba při mazání objednávky: ' . $e->getMessage();
        }
        header('Location: admin_orders.php');
        exit;
    }
}

// Kontrola a přidání sloupce final_design_path, pokud neexistuje
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'final_design_path'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN final_design_path varchar(500) DEFAULT NULL");
    }
} catch (PDOException $e) {
    error_log("Chyba při kontrole/přidání sloupce final_design_path: " . $e->getMessage());
}

// Načtení objednávek z databáze
try {
    // Zkusíme načíst s final_design_path, pokud selže, použijeme bez něj
    try {
        $stmt = $conn->prepare("
            SELECT o.*, u.email as user_email, u.first_name, u.last_name, u.phone,
                   clo.id as custom_lightbox_id, clo.final_design_path, clo.status as custom_status
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN custom_lightbox_orders clo ON o.custom_lightbox_order_id = clo.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Pokud selže kvůli chybějícímu sloupci, načteme bez něj
        $stmt = $conn->prepare("
            SELECT o.*, u.email as user_email, u.first_name, u.last_name, u.phone,
                   clo.id as custom_lightbox_id, clo.status as custom_status
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN custom_lightbox_orders clo ON o.custom_lightbox_order_id = clo.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Přidáme prázdný final_design_path pro kompatibilitu
        foreach ($orders as &$order) {
            $order['final_design_path'] = null;
        }
    }
} catch (PDOException $e) {
    $error_message = 'Chyba při načítání objednávek: ' . $e->getMessage();
    $orders = [];
}

// Zpracování nahrání finálního designu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_final_design') {
    $customOrderId = $_POST['custom_order_id'] ?? null;
    $orderId = $_POST['order_id'] ?? null;
    
    if ($customOrderId && isset($_FILES['final_design']) && $_FILES['final_design']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/custom_lightbox/final/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['final_design']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'final_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['final_design']['tmp_name'], $filePath)) {
                $relativePath = 'uploads/custom_lightbox/final/' . $fileName;
                
                try {
                    // Aktualizace custom_lightbox_orders
                    $stmt = $conn->prepare("
                        UPDATE custom_lightbox_orders 
                        SET final_design_path = ?, status = 'pending_approval', updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$relativePath, $customOrderId]);
                    
                    // Odeslání emailu zákazníkovi s odkazem na potvrzení
                    $stmt = $conn->prepare("SELECT * FROM custom_lightbox_orders WHERE id = ?");
                    $stmt->execute([$customOrderId]);
                    $customOrder = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($customOrder) {
                        $confirmUrl = 'https://kubajadesigns.eu/confirm_custom_design.php?order_id=' . $customOrderId . '&token=' . md5($customOrderId . $customOrder['customer_email']);
                        
                        $to = $customOrder['customer_email'];
                        $subject = "Finální návrh vašeho Custom Lightbox je připraven - KJD";
                        $message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 20px; text-align: center; }
                                .content { background: #fff; padding: 30px; border: 2px solid #4c6444; }
                                .button { display: inline-block; background: #4c6444; color: #fff; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Finální návrh je připraven!</h1>
                                </div>
                                <div class='content'>
                                    <p>Dobrý den <strong>" . htmlspecialchars($customOrder['customer_name']) . "</strong>,</p>
                                    <p>Finální návrh vašeho Custom Lightbox je připraven k potvrzení.</p>
                                    <p>Prosím, zkontrolujte návrh a potvrďte ho, nebo požádejte o změny.</p>
                                    <div style='text-align: center;'>
                                        <a href='" . htmlspecialchars($confirmUrl) . "' class='button'>Zobrazit a potvrdit návrh</a>
                                    </div>
                                    <p>S pozdravem,<br><strong>Tým KJD</strong></p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        $headers .= "From: info@kubajadesigns.eu" . "\r\n";
                        
                        mail($to, $subject, $message, $headers);
                    }
                    
                    $_SESSION['admin_success'] = 'Finální design byl úspěšně nahrán a zákazník byl informován.';
                } catch (PDOException $e) {
                    $_SESSION['admin_error'] = 'Chyba při ukládání designu: ' . $e->getMessage();
                }
            } else {
                $_SESSION['admin_error'] = 'Chyba při nahrávání souboru.';
            }
        } else {
            $_SESSION['admin_error'] = 'Neplatný formát souboru. Povolené formáty: ' . implode(', ', $allowedExtensions);
        }
        
        header('Location: admin_orders.php');
        exit;
    }
}

// Funkce pro získání barvy stavu (pokud neexistuje v functions.php)
if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        switch ($status) {
            case 'completed': return 'bg-success';
            case 'processing': return 'bg-primary';
            case 'preparing': return 'bg-info';
            case 'shipped': return 'bg-info';
            case 'cancelled': return 'bg-danger';
            default: return 'bg-warning';
        }
    }
}

if (!function_exists('getPaymentStatusBadge')) {
    function getPaymentStatusBadge($status) {
        switch ($status) {
            case 'paid': return '<span class="badge bg-success">Zaplaceno</span>';
            case 'pending': return '<span class="badge bg-warning">Čeká na platbu</span>';
            default: return '<span class="badge bg-danger">Neplateno</span>';
        }
    }
}

if (!function_exists('getStatusText')) {
    function getStatusText($status) {
        switch ($status) {
            case 'pending': return 'Čeká na platbu';
            case 'processing': return 'Zpracovává se';
            case 'preparing': return 'Příprava k odeslání';
            case 'shipped': return 'Odesláno';
            case 'completed': return 'Dokončeno';
            case 'cancelled': return 'Zrušeno';
            default: return 'Neznámý';
        }
    }
}

if (!function_exists('getColorName')) {
    function getColorName($color) {
        return $color; // Fallback function
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa objednávek - Admin</title>
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
    
    <!-- Preloader -->
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
                    <h1><i class="fas fa-shopping-cart me-3"></i>Správa objednávek</h1>
                    <p>Přehled a správa všech objednávek</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <!-- Flash zprávy -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show cart-item">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show cart-item">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filtry -->
                <div class="cart-item">
                    <h3 class="cart-product-name mb-3">
                        <i class="fas fa-filter me-2"></i>Filtry a vyhledávání
                    </h3>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stav objednávky</label>
                            <select id="statusFilter" class="form-select" onchange="filterOrders()">
                                <option value="">Všechny stavy</option>
                                <option value="pending">Čeká na platbu</option>
                                <option value="processing">Zpracovává se</option>
                                <option value="shipped">Odesláno</option>
                                <option value="completed">Dokončeno</option>
                                <option value="cancelled">Zrušeno</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stav platby</label>
                            <select id="paymentFilter" class="form-select" onchange="filterOrders()">
                                <option value="">Všechny platby</option>
                                <option value="paid">Zaplaceno</option>
                                <option value="pending">Čeká na platbu</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Typ objednávky</label>
                            <select id="typeFilter" class="form-select" onchange="filterOrders()">
                                <option value="">Všechny typy</option>
                                <option value="standard">Standardní</option>
                                <option value="preorder">Předobjednávky</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Vyhledávání</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Hledat objednávku..." onkeyup="filterOrders()">
                        </div>
                    </div>
                </div>

                <!-- Statistiky -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="cart-item text-center">
                            <i class="fas fa-shopping-cart fa-3x mb-3" style="color: var(--kjd-earth-green);"></i>
                            <h3 class="cart-product-price"><?php echo count($orders); ?></h3>
                            <p class="cart-product-name mb-0">Celkem objednávek</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="cart-item text-center">
                            <i class="fas fa-check-circle fa-3x mb-3" style="color: var(--kjd-earth-green);"></i>
                            <h3 class="cart-product-price"><?php echo count(array_filter($orders, fn($o) => $o['payment_status'] === 'paid')); ?></h3>
                            <p class="cart-product-name mb-0">Zaplaceno</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="cart-item text-center">
                            <i class="fas fa-clock fa-3x mb-3" style="color: var(--kjd-earth-green);"></i>
                            <h3 class="cart-product-price"><?php echo count(array_filter($orders, fn($o) => $o['payment_status'] === 'pending')); ?></h3>
                            <p class="cart-product-name mb-0">Čeká na platbu</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="cart-item text-center">
                            <i class="fas fa-calendar-alt fa-3x mb-3" style="color: var(--kjd-earth-green);"></i>
                            <h3 class="cart-product-price"><?php echo count(array_filter($orders, fn($o) => $o['is_preorder'] == 1)); ?></h3>
                            <p class="cart-product-name mb-0">Předobjednávky</p>
                        </div>
                    </div>
                </div>
                <!-- Tabulka objednávek -->
                <div class="cart-item">
                    <h2 class="cart-product-name mb-4">
                        <i class="fas fa-list me-2"></i>Seznam objednávek
                    </h2>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="width: 50px;"></th>
                                            <th scope="col">Objednávka</th>
                                            <th scope="col">Datum</th>
                                            <th scope="col">Zákazník</th>
                                            <th scope="col">Platba</th>
                                            <th scope="col">Celkem</th>
                                            <th scope="col">Wallet</th>
                                            <th scope="col">Stav</th>
                                            <th scope="col">Typ</th>
                                            <th scope="col">Akce</th>
                                        </tr>
                                    </thead>
                                <tbody>
                                    <?php if (count($orders) > 0): ?>
                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            $orderProducts = json_decode($order['products_json'] ?? '[]', true);
                                            $isPreorder = $order['is_preorder'] ?? 0;
                                            $rowClass = $isPreorder ? 'order-row-preorder' : '';
                                            ?>
                                            <tr class="order-row <?php echo $rowClass; ?>" 
                                                data-status="<?php echo $order['status']; ?>" 
                                                data-payment="<?php echo $order['payment_status']; ?>"
                                                data-preorder="<?php echo $isPreorder; ?>"
                                                data-search="<?php echo strtolower($order['order_id'] . ' ' . $order['name'] . ' ' . $order['email']); ?>">
                                                <td>
                                                    <span class="expand-button" onclick="toggleOrderDetails(this, <?php echo $order['id']; ?>)">
                                                        <i class="fas fa-chevron-down"></i>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['order_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                                                </td>
                                                <td class="customer-info">
                                                    <div><?php echo htmlspecialchars($order['name']); ?></div>
                                                    <small><?php echo htmlspecialchars($order['email']); ?></small>
                                                </td>
                                                <td class="payment-status">
                                                    <?php echo getPaymentStatusBadge($order['payment_status']); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo number_format($order['total_price'], 0, ',', ' '); ?> Kč</strong>
                                                </td>
                                                <td>
                                                    <?php if (isset($order['wallet_used']) && $order['wallet_used']): ?>
                                                        <span class="badge bg-success" title="Použit zůstatek: <?php echo number_format($order['wallet_amount'] ?? 0, 0, ',', ' '); ?> Kč">
                                                            <i class="fas fa-wallet me-1"></i><?php echo number_format($order['wallet_amount'] ?? 0, 0, ',', ' '); ?> Kč
                                                        </span>
                                                        <br><small class="text-muted">K úhradě: <?php echo number_format($order['amount_to_pay'] ?? $order['total_price'], 0, ',', ' '); ?> Kč</small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-credit-card me-1"></i>Ne
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>">
                                                        <?php echo getStatusText($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (isset($order['is_custom_lightbox']) && $order['is_custom_lightbox']): ?>
                                                        <span class="badge" style="background: linear-gradient(135deg, #8A6240, #4D2D18); color: #fff;">Custom Lightbox</span>
                                                    <?php elseif ($isPreorder): ?>
                                                        <span class="badge-preorder">Předobjednávka</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Standardní</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <a href="admin_order_detail.php?id=<?php echo $order['id']; ?>" 
                                                           class="btn btn-kjd-primary btn-sm" title="Zobrazit detail">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="admin_edit_order.php?id=<?php echo $order['order_id']; ?>" 
                                                           class="btn btn-kjd-secondary btn-sm" title="Upravit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php
                                                        // Check if invoice exists for this order
                                                        $stmtInvoice = $conn->prepare("SELECT id FROM invoices WHERE order_id = ? LIMIT 1");
                                                        $stmtInvoice->execute([$order['order_id']]);
                                                        $existingInvoice = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
                                                        
                                                        if ($existingInvoice): ?>
                                                            <a href="admin_invoice_preview.php?id=<?php echo $existingInvoice['id']; ?>" 
                                                               class="btn btn-success btn-sm" title="Zobrazit fakturu"
                                                               style="background: linear-gradient(135deg, #28a745, #20c997); border: none;">
                                                                <i class="fas fa-file-invoice"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="admin_invoice_create.php?order_id=<?php echo urlencode($order['order_id']); ?>" 
                                                               class="btn btn-warning btn-sm" title="Vytvořit fakturu"
                                                               style="background: linear-gradient(135deg, #ffc107, #ff8c00); border: none; color: #fff;">
                                                                <i class="fas fa-file-invoice"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                onclick="confirmDelete(<?php echo $order['id']; ?>)" title="Smazat">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Order details row -->
                                            <tr class="order-detail-row" id="details-<?php echo $order['id']; ?>">
                                                <td colspan="9">
                                                    <div class="order-detail-content">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6 style="color: #1d1d1f; font-weight: 600; margin-bottom: 1rem;">
                                                                    <i class="fas fa-shopping-bag me-2" style="color: #CABA9C;"></i>Produkty
                                                                </h6>
                                                                <ul class="list-unstyled">
                                                                    <?php
                                                                    if (is_array($orderProducts)) {
                                                                        foreach ($orderProducts as $product) {
                                                                            echo "<li class='mb-2 p-2' style='background: #f8f9fa; border-radius: 8px;'>";
                                                                            echo "<strong style='color: #1d1d1f;'>" . htmlspecialchars($product['name'] ?? 'Neznámý produkt') . "</strong>";
                                                                            echo "<br><span class='text-muted'>Množství: " . ($product['quantity'] ?? 1) . "</span>";
                                                                            // Zkontroluj selected_color (nový formát) nebo color (starý formát)
                                                                            $productColor = $product['selected_color'] ?? $product['color'] ?? '';
                                                                            if (!empty($productColor)) {
                                                                                echo "<br><small style='color: #6c757d;'>Barva: " . htmlspecialchars(getColorName($productColor)) . "</small>";
                                                                            }
                                                                            // Zkontroluj component_colors
                                                                            if (!empty($product['component_colors']) && is_array($product['component_colors'])) {
                                                                                foreach ($product['component_colors'] as $compColor) {
                                                                                    if (!empty($compColor)) {
                                                                                        echo "<br><small style='color: #6c757d;'>Komponenta: " . htmlspecialchars(getColorName($compColor)) . "</small>";
                                                                                    }
                                                                                }
                                                                            }
                                                                            if (!empty($product['image_url'])) {
                                                                                echo "<br><img src='../" . htmlspecialchars($product['image_url']) . "' style='max-width: 100px; max-height: 100px; border-radius: 8px; margin-top: 0.5rem;'>";
                                                                            } elseif (!empty($product['image_path'])) {
                                                                                echo "<br><img src='../" . htmlspecialchars($product['image_path']) . "' style='max-width: 100px; max-height: 100px; border-radius: 8px; margin-top: 0.5rem;'>";
                                                                            }
                                                                            echo "</li>";
                                                                        }
                                                                    }
                                                                    ?>
                                                                </ul>
                                                                
                                                                <?php if (isset($order['is_custom_lightbox']) && $order['is_custom_lightbox'] && isset($order['custom_lightbox_id'])): ?>
                                                                    <div class="mt-3 p-3" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px;">
                                                                        <h6 style="color: #856404; font-weight: 700; margin-bottom: 1rem;">
                                                                            <i class="fas fa-lightbulb me-2"></i>Custom Lightbox - Nahrání finálního designu
                                                                        </h6>
                                                                        <?php if (!empty($order['final_design_path'])): ?>
                                                                            <p style="color: #856404; margin-bottom: 0.5rem;"><strong>Finální design:</strong></p>
                                                                            <img src="../<?= htmlspecialchars($order['final_design_path']) ?>" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-bottom: 1rem; border: 2px solid #ffc107;">
                                                                            <p style="color: #856404; font-size: 0.9rem; margin-bottom: 0.5rem;">Status: <strong><?= htmlspecialchars($order['custom_status'] ?? 'paid') ?></strong></p>
                                                                        <?php endif; ?>
                                                                        <form method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
                                                                            <input type="hidden" name="action" value="upload_final_design">
                                                                            <input type="hidden" name="custom_order_id" value="<?= htmlspecialchars($order['custom_lightbox_id']) ?>">
                                                                            <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                                                            <div class="mb-2">
                                                                                <input type="file" name="final_design" accept="image/*,.pdf" class="form-control form-control-sm" required>
                                                                            </div>
                                                                            <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #4c6444, #102820); color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600;">
                                                                                <i class="fas fa-upload me-1"></i><?= !empty($order['final_design_path']) ? 'Nahradit design' : 'Nahrát finální design' ?>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6 style="color: #1d1d1f; font-weight: 600; margin-bottom: 1rem;">
                                                                    <i class="fas fa-truck me-2" style="color: #CABA9C;"></i>Doručení
                                                                </h6>
                                                                <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem;">
                                                                    <p class="mb-2"><strong>Způsob:</strong> <?php echo htmlspecialchars($order['delivery_method']); ?></p>
                                                                    <?php if (!empty($order['note'])): ?>
                                                                        <p class="mb-0"><strong>Poznámka:</strong><br><?php echo nl2br(htmlspecialchars($order['note'])); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Žádné objednávky nebyly nalezeny.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
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
        
        function filterOrders() {
            const statusFilter = document.getElementById('statusFilter').value;
            const paymentFilter = document.getElementById('paymentFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            
            const rows = document.querySelectorAll('.order-row');
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const payment = row.getAttribute('data-payment');
                const preorder = row.getAttribute('data-preorder');
                const searchText = row.getAttribute('data-search');
                
                let showRow = true;
                
                // Status filter
                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }
                
                // Payment filter
                if (paymentFilter && payment !== paymentFilter) {
                    showRow = false;
                }
                
                // Type filter
                if (typeFilter) {
                    if (typeFilter === 'preorder' && preorder !== '1') {
                        showRow = false;
                    } else if (typeFilter === 'standard' && preorder === '1') {
                        showRow = false;
                    }
                }
                
                // Search filter
                if (searchInput && !searchText.includes(searchInput)) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
                
                // Hide detail row if parent is hidden
                const detailRow = document.getElementById('details-' + row.querySelector('.expand-button').onclick.toString().match(/\d+/)[0]);
                if (detailRow) {
                    detailRow.style.display = showRow ? detailRow.classList.contains('show') ? 'table-row' : 'none' : 'none';
                }
            });
        }

        function toggleOrderDetails(button, orderId) {
            const detailRow = document.getElementById('details-' + orderId);
            const icon = button.querySelector('i');
            
            if (detailRow.classList.contains('show')) {
                detailRow.classList.remove('show');
                button.classList.remove('expanded');
            } else {
                detailRow.classList.add('show');
                button.classList.add('expanded');
            }
        }

        function confirmDelete(orderId) {
            if (confirm('Opravdu chcete smazat tuto objednávku? Tato akce je nevratná.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="${orderId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-refresh každých 30 sekund
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
