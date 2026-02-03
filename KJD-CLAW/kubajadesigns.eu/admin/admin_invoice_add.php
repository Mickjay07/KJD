<?php
session_start();
require_once 'config.php';

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Get settings
$stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch() ?: [];

// Initialize variables for pre-filling form
$prefill_data = [];
$prefill_items = [];

// Handle creation from order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_from_order') {
    if (isset($_POST['order_data'])) {
        $order_data = json_decode($_POST['order_data'], true);
        if ($order_data) {
            // Pre-fill customer data
            $buyer_address1 = $order_data['customer_address'] ?? '';
            $buyer_city = $order_data['customer_city'] ?? '';
            $buyer_zip = $order_data['customer_postal_code'] ?? '';
            
            // Zkus získat adresu z delivery_info, pokud není v základních údajích
            if (empty($buyer_address1) && isset($order_data['delivery_info'])) {
                $deliveryInfo = $order_data['delivery_info'];
                if (isset($deliveryInfo['address'])) {
                    $buyer_address1 = $deliveryInfo['address']['street'] ?? '';
                    $buyer_city = $deliveryInfo['address']['city'] ?? $buyer_city;
                    $buyer_zip = $deliveryInfo['address']['postal_code'] ?? $buyer_zip;
                } elseif (isset($deliveryInfo['zasilkovna'])) {
                    $z = $deliveryInfo['zasilkovna'];
                    $buyer_address1 = ($z['name'] ?? '') . (!empty($z['street']) ? ', ' . $z['street'] : '');
                    $buyer_city = $z['city'] ?? $buyer_city;
                    $buyer_zip = $z['postal_code'] ?? $buyer_zip;
                }
            }
            
            $prefill_data = [
                'buyer_name' => $order_data['customer_name'] ?? '',
                'buyer_email' => $order_data['customer_email'] ?? '',
                'buyer_phone' => $order_data['customer_phone'] ?? '',
                'buyer_address1' => $buyer_address1,
                'buyer_city' => $buyer_city,
                'buyer_zip' => $buyer_zip,
                'order_id' => $order_data['order_id'] ?? '',
                'note' => 'Faktura vytvořená z objednávky #' . ($order_data['order_id'] ?? '') . 
                         (!empty($order_data['note']) ? "\n\nPoznámka z objednávky: " . $order_data['note'] : '')
            ];
            
            // Pre-fill items from order
            if (isset($order_data['products']) && is_array($order_data['products'])) {
                foreach ($order_data['products'] as $product) {
                    // Použij final_price, pokud existuje, jinak price
                    $price = $product['final_price'] ?? $product['price'] ?? 0;
                    $prefill_items[] = [
                        'name' => $product['name'] ?? '',
                        'quantity' => $product['quantity'] ?? 1,
                        'price' => $price
                    ];
                }
            }
            
            // Automaticky přidat dopravu 100 Kč
            $prefill_items[] = [
                'name' => 'Doprava',
                'quantity' => 1,
                'price' => 100
            ];
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'create_from_order')) {
    $buyer_name = $_POST['buyer_name'] ?? '';
    $buyer_email = $_POST['buyer_email'] ?? '';
    $buyer_phone = $_POST['buyer_phone'] ?? '';
    $buyer_address1 = $_POST['buyer_address1'] ?? '';
    $buyer_address2 = $_POST['buyer_address2'] ?? '';
    $buyer_city = $_POST['buyer_city'] ?? '';
    $buyer_zip = $_POST['buyer_zip'] ?? '';
    $buyer_ico = $_POST['buyer_ico'] ?? '';
    $buyer_dic = $_POST['buyer_dic'] ?? '';
    $order_id = $_POST['order_id'] ?? '';
    $note = $_POST['note'] ?? '';
    
    $items = $_POST['items'] ?? [];
    
    // Validation
    $errors = [];
    if (empty($buyer_name)) $errors[] = 'Jméno zákazníka je povinné';
    if (empty($buyer_email)) $errors[] = 'Email zákazníka je povinný';
    if (empty($items)) $errors[] = 'Musí být přidána alespoň jedna položka';
    
    if (empty($errors)) {
        try {
            // Check and add buyer_ico and buyer_dic columns if they don't exist
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM invoices LIKE 'buyer_ico'");
                if ($checkCol->rowCount() == 0) {
                    $conn->exec("ALTER TABLE invoices ADD COLUMN buyer_ico VARCHAR(20) NULL AFTER buyer_zip");
                }
            } catch (PDOException $e) {
                error_log("Error checking buyer_ico column: " . $e->getMessage());
            }
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM invoices LIKE 'buyer_dic'");
                if ($checkCol->rowCount() == 0) {
                    $conn->exec("ALTER TABLE invoices ADD COLUMN buyer_dic VARCHAR(20) NULL AFTER buyer_ico");
                }
            } catch (PDOException $e) {
                error_log("Error checking buyer_dic column: " . $e->getMessage());
            }
            
            $conn->beginTransaction();
            
            // Generate invoice number
            $year = date('Y');
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)) as max_num FROM invoices WHERE invoice_number LIKE ?");
            $stmt->execute([$year . '%']);
            $result = $stmt->fetch();
            $next_num = ($result['max_num'] ?? 0) + 1;
            $invoice_number = $year . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            
            // Calculate totals
            $total_without_vat = 0;
            $total_with_vat = 0;
            
            foreach ($items as $item) {
                if (!empty($item['name']) && !empty($item['quantity']) && !empty($item['price'])) {
                    $quantity = (int)$item['quantity'];
                    $unit_price = (float)$item['price'];
                    $item_total = $quantity * $unit_price;
                    $total_without_vat += $item_total;
                    $total_with_vat += $item_total; // No VAT
                }
            }
            
            // Apply discount if any
            $discount_type = $_POST['discount_type'] ?? 'none';
            $discount_value = (float)($_POST['discount_value'] ?? 0);
            $discount_amount = 0;
            
            if ($discount_type === 'amount') {
                $discount_amount = min($discount_value, $total_without_vat);
            } elseif ($discount_type === 'percentage') {
                $discount_amount = ($total_without_vat * min($discount_value, 100)) / 100;
            }
            
            $total_without_vat -= $discount_amount;
            $total_with_vat -= $discount_amount;
            
            $vat_total = 0; // No VAT
            
            // Insert invoice
            $stmt = $conn->prepare("
                INSERT INTO invoices (
                    invoice_number, order_id, buyer_name, buyer_email, buyer_phone,
                    buyer_address1, buyer_address2, buyer_city, buyer_zip, buyer_ico, buyer_dic,
                    issue_date, due_date, total_without_vat, vat_total, total_with_vat,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '0', NOW())
            ");
            
            $issue_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+14 days'));
            
            $stmt->execute([
                $invoice_number, $order_id, $buyer_name, $buyer_email, $buyer_phone,
                $buyer_address1, $buyer_address2, $buyer_city, $buyer_zip, $buyer_ico, $buyer_dic,
                $issue_date, $due_date, $total_without_vat, $vat_total, $total_with_vat
            ]);
            
            $invoice_id = $conn->lastInsertId();
            
            // Insert invoice items
            $stmt = $conn->prepare("
                INSERT INTO invoice_items (invoice_id, name, quantity, unit_price_without_vat, total_with_vat)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($items as $item) {
                if (!empty($item['name']) && !empty($item['quantity']) && !empty($item['price'])) {
                    $quantity = (int)$item['quantity'];
                    $unit_price = (float)$item['price'];
                    $item_total = $quantity * $unit_price;
                    
                    $stmt->execute([$invoice_id, $item['name'], $quantity, $unit_price, $item_total]);
                }
            }
            
            $conn->commit();
            
            $_SESSION['admin_success'] = 'Faktura byla úspěšně vytvořena.';
            header('Location: admin_invoice_preview.php?id=' . $invoice_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Chyba při vytváření faktury: ' . $e->getMessage();
        }
    }
}

// Pre-fill from order if order_id provided
$order_data = null;
$order_items = [];
if (!empty($_GET['order_id'])) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$_GET['order_id']]);
    $order_data = $stmt->fetch();
    
    // Load order items from products_json
    if ($order_data && !empty($order_data['products_json'])) {
        $products_data = json_decode($order_data['products_json'], true);
        if (is_array($products_data)) {
            foreach ($products_data as $key => $item) {
                // Skip delivery info and other meta data
                if (strpos($key, '_') === 0) continue;
                
                if (isset($item['name']) && isset($item['quantity']) && isset($item['final_price'])) {
                    $order_items[] = [
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['final_price']
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nová faktura - KubaJa Designs Admin</title>
    
    <link rel="icon" type="image/x-icon" href="favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    
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
      
      /* Admin page background */
      .admin-page { 
        background: #f8f9fa; 
        min-height: 100vh; 
        }
        
      /* Admin header */
      .admin-header { 
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
        padding: 3rem 0; 
        margin-bottom: 2rem; 
        border-bottom: 3px solid var(--kjd-earth-green);
        box-shadow: 0 4px 20px rgba(16,40,32,0.1);
      }
      
      .admin-header h1 { 
        font-size: 2.5rem; 
        font-weight: 800; 
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        margin-bottom: 0.5rem;
        color: var(--kjd-dark-green);
        }
        
      .admin-header p { 
        font-size: 1.1rem; 
            font-weight: 500;
        opacity: 0.8;
        color: var(--kjd-dark-green);
      }
      
      /* KJD Buttons - matching website style */
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
      
      .btn-kjd-info { 
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
        color: #fff; 
        border: none; 
        padding: 1rem 2.5rem; 
        border-radius: 12px; 
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(76,100,68,0.3);
      }
      
      .btn-kjd-info:hover { 
        background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green)); 
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76,100,68,0.4);
      }
      
      .btn-kjd-dark { 
        background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green)); 
        color: #fff; 
        border: none; 
        padding: 1rem 2.5rem; 
        border-radius: 12px; 
        font-weight: 700;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(16,40,32,0.3);
      }
      
      .btn-kjd-dark:hover { 
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); 
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16,40,32,0.4);
      }
      
      /* Apple-style sections */
      .apple-section { 
        background: white; 
        border-radius: 16px; 
        padding: 2rem; 
        margin-bottom: 2rem; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .apple-section h3 { 
        color: var(--kjd-dark-green); 
        font-weight: 700; 
        margin-bottom: 1.5rem;
        font-size: 1.4rem;
        border-bottom: 2px solid var(--kjd-beige);
        padding-bottom: 0.5rem;
      }
      
      /* Apple-style form controls */
      .form-control { 
        border: 2px solid #e9ecef; 
        border-radius: 12px; 
        padding: 0.75rem 1rem; 
        font-size: 1rem;
        transition: all 0.3s ease;
        }
        
        .form-control:focus {
        border-color: var(--kjd-earth-green); 
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .form-label {
        font-weight: 600;
        color: var(--kjd-dark-green);
        margin-bottom: 0.5rem;
      }
      
      .card {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .card-header {
        background: rgba(202,186,156,0.1);
        border-bottom: 2px solid var(--kjd-beige);
        border-radius: 16px 16px 0 0;
        padding: 1.5rem 2rem;
        margin: -2rem -2rem 2rem -2rem;
      }
      
      .card-title {
        color: var(--kjd-dark-green);
        font-weight: 700;
        font-size: 1.2rem;
        margin: 0;
        }
        
        .btn-add-item {
        background: rgba(76,100,68,0.16);
        color: var(--kjd-earth-green);
        border: 1px dashed var(--kjd-earth-green);
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        }
        
        .btn-add-item:hover {
        background: var(--kjd-earth-green);
            color: white;
        transform: translateY(-2px);
        }
        
        .item-row {
        background: rgba(202,186,156,0.1);
        border-radius: 16px;
        padding: 1.5rem;
            margin-bottom: 1rem;
        border: 2px solid var(--kjd-beige);
        transition: all 0.3s ease;
      }
      
      .item-row:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
        }
        
        .btn-remove-item {
        background: rgba(220,53,69,0.16);
        color: #dc3545;
        border: 1px solid rgba(220,53,69,0.3);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        }
        
        .btn-remove-item:hover {
        background: #dc3545;
            color: white;
        transform: translateY(-2px);
      }
      
      .alert {
        border-radius: 16px;
        border: 2px solid;
        font-weight: 500;
      }
      
      .alert-danger {
        background: rgba(220,53,69,0.1);
        border-color: #dc3545;
        color: #721c24;
      }
      
      .alert-success {
        background: rgba(40,167,69,0.1);
        border-color: #28a745;
        color: #155724;
      }
      
      .alert-info {
        background: rgba(13,202,240,0.1);
        border-color: #0dcaf0;
        color: #055160;
      }
      
      /* Mobile responsiveness */
      @media (max-width: 768px) {
        .admin-header {
          padding: 2rem 0;
        }
        
        .admin-header h1 {
          font-size: 2rem;
        }
        
        .apple-section {
          padding: 1.5rem;
        }
        
        .card {
          padding: 1.5rem;
        }
        
        .card-header {
          padding: 1rem 1.5rem;
          margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary, .btn-kjd-info, .btn-kjd-dark {
          padding: 0.75rem 1.5rem;
          font-size: 1rem;
        }
      }
      
      @media (max-width: 576px) {
        .admin-header {
          padding: 1.5rem 0;
        }
        
        .admin-header h1 {
          font-size: 1.8rem;
        }
        
        .apple-section {
          padding: 1rem;
        }
        
        .card {
          padding: 1rem;
        }
        
        .card-header {
          padding: 0.75rem 1rem;
          margin: -1rem -1rem 1rem -1rem;
        }
        
        .container-fluid {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
        }
        }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <div class="layout-container">
            
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="layout-page">
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <!-- Page Header -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="fw-bold py-3 mb-2">
                                    <span class="text-muted fw-light">Faktury /</span> Nová faktura
                                </h4>
                            </div>
                            <div>
                                <a href="admin_invoices.php" class="btn btn-kjd-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Zpět na seznam
                                </a>
                            </div>
                        </div>
                        
                        <!-- Alerts -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= h($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" id="invoiceForm">
                            <div class="row">
                                
                                <!-- Left Column -->
                                <div class="col-lg-8">
                                    
                                    <!-- Customer Information -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Informace o zákazníkovi</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Jméno / Firma *</label>
                                                    <input type="text" name="buyer_name" class="form-control" 
                                                           value="<?= h($prefill_data['buyer_name'] ?? $_POST['buyer_name'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Email *</label>
                                                    <input type="email" name="buyer_email" class="form-control" 
                                                           value="<?= h($prefill_data['buyer_email'] ?? $_POST['buyer_email'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Telefon</label>
                                                    <input type="text" name="buyer_phone" class="form-control" 
                                                           value="<?= h($prefill_data['buyer_phone'] ?? $_POST['buyer_phone'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">IČO</label>
                                                    <input type="text" name="buyer_ico" class="form-control" 
                                                           value="<?= h($_POST['buyer_ico'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Adresa</label>
                                                    <input type="text" name="buyer_address1" class="form-control" 
                                                           value="<?= h($prefill_data['buyer_address1'] ?? $_POST['buyer_address1'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Adresa 2</label>
                                                    <input type="text" name="buyer_address2" class="form-control" 
                                                           value="<?= h($_POST['buyer_address2'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Město</label>
                                                    <input type="text" name="buyer_city" class="form-control" 
                                                           value="<?= h($prefill_data['buyer_city'] ?? $_POST['buyer_city'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">PSČ</label>
                                                    <input type="text" name="buyer_zip" class="form-control" 
                                                           value="<?= h($prefill_data['buyer_zip'] ?? $_POST['buyer_zip'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">DIČ</label>
                                                    <input type="text" name="buyer_dic" class="form-control" 
                                                           value="<?= h($_POST['buyer_dic'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Invoice Items -->
                                    <div class="card mb-4">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="card-title mb-0">Položky faktury</h5>
                                            <button type="button" class="btn btn-sm btn-add-item" onclick="addItem()">
                                                <i class="fas fa-plus me-2"></i>Přidat položku
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="itemsContainer">
                                                <!-- Items will be added here -->
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                                
                                <!-- Right Column -->
                                <div class="col-lg-4">
                                    
                                    <!-- Order Search -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Načíst z objednávky</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Vyhledat objednávku</label>
                                                <div class="input-group">
                                                    <input type="text" id="orderSearch" class="form-control" placeholder="Zadejte číslo objednávky nebo email zákazníka">
                                                    <button type="button" class="btn btn-sm btn-kjd-info" onclick="searchOrder()">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </div>
                                                <div class="form-text">Automaticky načte údaje zákazníka a položky z objednávky</div>
                                            </div>
                                            <div id="orderResults" class="d-none">
                                                <div class="alert alert-info">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong id="foundOrderId"></strong>
                                                            <br><small id="foundOrderCustomer"></small>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-kjd-primary" onclick="loadOrder()">
                                                            <i class="fas fa-download me-1"></i>Načíst
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Discount -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Sleva</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Typ slevy</label>
                                                <select class="form-select" id="discountType" onchange="calculateTotals()">
                                                    <option value="none">Bez slevy</option>
                                                    <option value="amount">Částka (Kč)</option>
                                                    <option value="percentage">Procento (%)</option>
                                                </select>
                                            </div>
                                            <div class="mb-3" id="discountValueContainer" style="display: none;">
                                                <label class="form-label" id="discountValueLabel">Hodnota slevy</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="discountValue" 
                                                           placeholder="0" min="0" step="0.01" onchange="calculateTotals()">
                                                    <span class="input-group-text" id="discountUnit">Kč</span>
                                                </div>
                                                <div class="form-text" id="discountHelp">Zadejte částku slevy</div>
                                            </div>
                                            <!-- Hidden fields for form submission -->
                                            <input type="hidden" name="discount_type" id="discountTypeHidden" value="none">
                                            <input type="hidden" name="discount_value" id="discountValueHidden" value="0">
                                        </div>
                                    </div>
                                    
                                    <!-- Invoice Details -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Detaily faktury</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Číslo objednávky</label>
                                                <input type="text" name="order_id" id="orderIdField" class="form-control" 
                                                       value="<?= h($prefill_data['order_id'] ?? $_GET['order_id'] ?? $_POST['order_id'] ?? '') ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Poznámka</label>
                                                <textarea name="note" class="form-control" rows="3"><?= h($prefill_data['note'] ?? $_POST['note'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Invoice Summary -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Souhrn</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Celkem bez DPH:</span>
                                                <span id="subtotal">0,00 Kč</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>DPH:</span>
                                                <span>0,00 Kč</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2" id="discountRow" style="display: none;">
                                                <span>Sleva:</span>
                                                <span id="discountAmount" class="text-success">-0,00 Kč</span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between fw-bold">
                                                <span>Celkem k úhradě:</span>
                                                <span id="total">0,00 Kč</span>
                                            </div>

                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="card">
                                        <div class="card-body">
                                            <button type="submit" class="btn btn-kjd-primary w-100 mb-2">
                                                <i class="fas fa-save me-2"></i>Vytvořit fakturu
                                            </button>
                                            <a href="admin_invoices.php" class="btn btn-kjd-secondary w-100">
                                                <i class="fas fa-times me-2"></i>Zrušit
                                            </a>
                                        </div>
                                    </div>
                                    
                                </div>
                                
                            </div>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemIndex = 0;
        
        function addItem(name = '', quantity = 1, price = '') {
            const container = document.getElementById('itemsContainer');
            const itemHtml = `
                <div class="item-row" id="item-${itemIndex}">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Název položky</label>
                            <input type="text" name="items[${itemIndex}][name]" class="form-control" value="${name}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Množství</label>
                            <input type="number" name="items[${itemIndex}][quantity]" class="form-control" value="${quantity}" min="1" required onchange="calculateTotals()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cena za kus</label>
                            <input type="number" name="items[${itemIndex}][price]" class="form-control" value="${price}" step="0.01" min="0" required onchange="calculateTotals()">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-remove-item w-100" onclick="removeItem(${itemIndex})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', itemHtml);
            itemIndex++;
            calculateTotals();
        }
        
        function removeItem(index) {
            document.getElementById(`item-${index}`).remove();
            calculateTotals();
        }
        
        function calculateTotals() {
            const items = document.querySelectorAll('.item-row');
            let subtotal = 0;
            
            items.forEach(item => {
                const quantity = parseFloat(item.querySelector('input[name*="[quantity]"]').value) || 0;
                const price = parseFloat(item.querySelector('input[name*="[price]"]').value) || 0;
                subtotal += quantity * price;
            });
            
            // Calculate discount
            const discountType = document.getElementById('discountType').value;
            const discountValue = parseFloat(document.getElementById('discountValue').value) || 0;
            let discountAmount = 0;
            
            if (discountType === 'amount') {
                discountAmount = Math.min(discountValue, subtotal); // Can't discount more than subtotal
            } else if (discountType === 'percentage') {
                discountAmount = (subtotal * Math.min(discountValue, 100)) / 100; // Max 100%
            }
            
            const subtotalAfterDiscount = subtotal - discountAmount;
            const total = subtotalAfterDiscount;
            
            // Update display
            document.getElementById('subtotal').textContent = subtotal.toLocaleString('cs-CZ', {minimumFractionDigits: 2}) + ' Kč';
            
            // Show/hide discount row
            const discountRow = document.getElementById('discountRow');
            if (discountAmount > 0) {
                discountRow.style.display = 'flex';
                document.getElementById('discountAmount').textContent = '-' + discountAmount.toLocaleString('cs-CZ', {minimumFractionDigits: 2}) + ' Kč';
            } else {
                discountRow.style.display = 'none';
            }
            
            document.getElementById('total').textContent = total.toLocaleString('cs-CZ', {minimumFractionDigits: 2}) + ' Kč';
            
            // Update hidden fields for form submission
            document.getElementById('discountTypeHidden').value = discountType;
            document.getElementById('discountValueHidden').value = discountValue;
        }
        
        // Order search functionality
        let foundOrderData = null;
        
        function searchOrder() {
            const searchTerm = document.getElementById('orderSearch').value.trim();
            if (!searchTerm) {
                alert('Zadejte číslo objednávky nebo email zákazníka');
                return;
            }
            
            fetch('admin_order_search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ search: searchTerm })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.order) {
                    foundOrderData = data.order;
                    document.getElementById('foundOrderId').textContent = 'Objednávka #' + data.order.order_id;
                    document.getElementById('foundOrderCustomer').textContent = data.order.customer_name + ' (' + data.order.customer_email + ')';
                    document.getElementById('orderResults').classList.remove('d-none');
                } else {
                    alert('Objednávka nebyla nalezena');
                    document.getElementById('orderResults').classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Chyba při vyhledávání objednávky');
            });
        }
        
        function loadOrder() {
            if (!foundOrderData) return;
            
            // Fill customer data
            document.querySelector('input[name="customer_name"]').value = foundOrderData.customer_name || '';
            document.querySelector('input[name="customer_email"]').value = foundOrderData.customer_email || '';
            document.querySelector('input[name="customer_phone"]').value = foundOrderData.customer_phone || '';
            document.querySelector('textarea[name="customer_address"]').value = foundOrderData.customer_address || '';
            document.getElementById('orderIdField').value = foundOrderData.order_id || '';
            
            // Clear existing items
            document.getElementById('invoiceItems').innerHTML = '';
            
            // Load order items
            if (foundOrderData.items && foundOrderData.items.length > 0) {
                foundOrderData.items.forEach(item => {
                    addItem(item.name, item.quantity, item.price);
                });
            } else {
                addItem();
            }
            
            // Hide search results
            document.getElementById('orderResults').classList.add('d-none');
            document.getElementById('orderSearch').value = '';
            
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                Objednávka #${foundOrderData.order_id} byla úspěšně načtena
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.content-wrapper').insertBefore(alert, document.querySelector('.content-wrapper').firstChild);
            
            // Auto-hide alert after 3 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        }
        
        // Handle discount type changes
        function handleDiscountTypeChange() {
            const discountType = document.getElementById('discountType').value;
            const container = document.getElementById('discountValueContainer');
            const label = document.getElementById('discountValueLabel');
            const unit = document.getElementById('discountUnit');
            const help = document.getElementById('discountHelp');
            const input = document.getElementById('discountValue');
            
            if (discountType === 'none') {
                container.style.display = 'none';
                input.value = '';
            } else {
                container.style.display = 'block';
                
                if (discountType === 'amount') {
                    label.textContent = 'Částka slevy';
                    unit.textContent = 'Kč';
                    help.textContent = 'Zadejte částku slevy v korunách';
                    input.placeholder = '0';
                    input.max = '';
                } else if (discountType === 'percentage') {
                    label.textContent = 'Procento slevy';
                    unit.textContent = '%';
                    help.textContent = 'Zadejte procento slevy (0-100%)';
                    input.placeholder = '0';
                    input.max = '100';
                }
            }
            
            calculateTotals();
        }
        
        // Load order items or add initial empty item
        document.addEventListener('DOMContentLoaded', function() {
            const orderItems = <?= json_encode($order_items) ?>;
            
            if (orderItems && orderItems.length > 0) {
                // Load items from order
                orderItems.forEach(item => {
                    addItem(item.name, item.quantity, item.price);
                });
            } else {
                // Add initial empty item
                addItem();
            }
            
            // Add Enter key support for order search
            document.getElementById('orderSearch').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchOrder();
                }
            });
            
            // Add discount type change handler
            document.getElementById('discountType').addEventListener('change', handleDiscountTypeChange);
            
            // Load prefilled items from order if available
            <?php if (!empty($prefill_items)): ?>
            const prefillItems = <?php echo json_encode($prefill_items); ?>;
            prefillItems.forEach(item => {
                addItem(item.name, item.quantity, item.price);
            });
            <?php else: ?>
            // Add at least one empty item if no prefilled items
            addItem();
            <?php endif; ?>
        });
    </script>
</body>
</html>
