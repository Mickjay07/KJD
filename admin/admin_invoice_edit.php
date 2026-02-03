<?php
// Completely suppress all errors and warnings
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('html_errors', 0);

// Start output buffering to catch any stray output
ob_start();

session_start();
require_once 'config.php';

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Safe get with @ to suppress warnings
function inv($invoice, $key, $default = '') {
    return @$invoice[$key] ?? $default;
}

$invoice_id = $_GET['id'] ?? '';
if (empty($invoice_id)) {
    header('Location: admin_invoices.php');
    exit;
}

// Get invoice data
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: admin_invoices.php');
    exit;
}

// Get invoice items
$stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$items = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $issue_date = $_POST['issue_date'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $discount_type = $_POST['discount_type'] ?? 'none';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
    
    $errors = [];
    
    if (empty($buyer_name)) $errors[] = 'Jméno zákazníka je povinné.';
    if (empty($buyer_email)) $errors[] = 'Email zákazníka je povinný.';
    if (empty($issue_date)) $errors[] = 'Datum vystavení je povinné.';
    if (empty($due_date)) $errors[] = 'Datum splatnosti je povinné.';
    
    // Process items
    $form_items = [];
    if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
        for ($i = 0; $i < count($_POST['item_name']); $i++) {
            if (!empty($_POST['item_name'][$i])) {
                $form_items[] = [
                    'name' => $_POST['item_name'][$i],
                    'quantity' => $_POST['item_quantity'][$i] ?? 1,
                    'unit_price_without_vat' => $_POST['item_price'][$i] ?? 0
                ];
            }
        }
    }
    
    if (empty($form_items)) {
        $errors[] = 'Faktura musí obsahovat alespoň jednu položku.';
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Calculate totals
            $total_without_vat = 0;
            foreach ($form_items as $item) {
                $total_without_vat += (float)$item['quantity'] * (float)$item['unit_price_without_vat'];
            }
            
            $vat_rate = 0; // Neplátce DPH
            $vat_total = 0;
            $subtotal = $total_without_vat;
            
            // Apply discount
            $discount_amount = 0;
            if ($discount_type === 'percentage' && $discount_value > 0) {
                $discount_amount = $subtotal * ($discount_value / 100);
            } elseif ($discount_type === 'fixed' && $discount_value > 0) {
                $discount_amount = $discount_value;
            }
            
            $total_with_vat = $subtotal - $discount_amount;
            
            // Check if 'note' column exists
            $hasNoteColumn = false;
            try {
                $checkCol = $conn->query("SHOW COLUMNS FROM invoices LIKE 'note'");
                $hasNoteColumn = $checkCol->rowCount() > 0;
            } catch (PDOException $e) {
                error_log("Error checking note column: " . $e->getMessage());
            }
            
            // Update invoice - conditionally include 'note' column
            if ($hasNoteColumn) {
                $stmt = $conn->prepare("
                    UPDATE invoices SET 
                        order_id = ?, buyer_name = ?, buyer_email = ?, buyer_phone = ?,
                        buyer_address1 = ?, buyer_address2 = ?, buyer_city = ?, buyer_zip = ?, 
                        issue_date = ?, due_date = ?,
                        total_without_vat = ?, vat_total = ?, total_with_vat = ?, note = ?, payment_method = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $order_id, $buyer_name, $buyer_email, $buyer_phone,
                    $buyer_address1, $buyer_address2, $buyer_city, $buyer_zip, 
                    $issue_date, $due_date,
                    $total_without_vat, $vat_total, $total_with_vat, $note, $payment_method, $invoice_id
                ]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE invoices SET 
                        order_id = ?, buyer_name = ?, buyer_email = ?, buyer_phone = ?,
                        buyer_address1 = ?, buyer_address2 = ?, buyer_city = ?, buyer_zip = ?, 
                        issue_date = ?, due_date = ?,
                        total_without_vat = ?, vat_total = ?, total_with_vat = ?, payment_method = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $order_id, $buyer_name, $buyer_email, $buyer_phone,
                    $buyer_address1, $buyer_address2, $buyer_city, $buyer_zip, 
                    $issue_date, $due_date,
                    $total_without_vat, $vat_total, $total_with_vat, $payment_method, $invoice_id
                ]);
            }
            
            // Delete existing items
            $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            
            // Insert new items
            $stmt = $conn->prepare("
                INSERT INTO invoice_items (invoice_id, name, quantity, unit_price_without_vat, total_with_vat)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($form_items as $item) {
                if (!empty($item['name']) && !empty($item['quantity']) && !empty($item['unit_price_without_vat'])) {
                    $quantity = (int)$item['quantity'];
                    $unit_price = (float)$item['unit_price_without_vat'];
                    $item_total = $quantity * $unit_price;
                    
                    $stmt->execute([$invoice_id, $item['name'], $quantity, $unit_price, $item_total]);
                }
            }
            
            $conn->commit();
            
            $_SESSION['admin_success'] = 'Faktura byla úspěšně aktualizována.';
            header('Location: admin_invoice_preview.php?id=' . $invoice_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Chyba při aktualizaci faktury: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upravit fakturu <?= h($invoice['invoice_number']) ?> - Admin</title>
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
      
      /* Admin page background */
      .admin-page { 
        background: #f8f9fa; 
        min-height: 100vh; 
      }
      
      /* Main content with sidebar */
      .main-content {
        margin-left: 250px;
        min-height: 100vh;
        padding-left: 0 !important;
        padding-right: 1rem;
      }
      
      /* Force left alignment for all content */
      .main-content .container-fluid {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100%;
      }
      
      .main-content .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      
      /* COMPLETE override of Bootstrap column padding */
      .main-content [class*="col-"] {
        padding-left: 0 !important;
      }
      
      .main-content .col-lg-8,
      .main-content .col-lg-4,
      .main-content .col-md-8,
      .main-content .col-md-6,
      .main-content .col-md-4,
      .main-content .col-md-12,
      .main-content .col-12 {
        padding-left: 0 !important;
      }
      
      /* Remove padding from admin header */
      .admin-header {
        padding-left: 0 !important;
      }
      
      .admin-header .container-fluid {
        padding-left: 0 !important;
      }
      
      @media (max-width: 768px) {
        .main-content {
          margin-left: 0;
          padding-left: 1rem;
        }
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
        color: var(--kjd-earth-green);
        margin-bottom: 0;
        font-weight: 500;
      }
      
      /* Apple-style sections */
      .apple-section { 
        background: white; 
        border-radius: 16px; 
        padding: 2rem; 
        padding-left: 1rem !important;
        margin-bottom: 2rem; 
        margin-left: 0 !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.05);
      }
      
      .apple-section h3 { 
        color: var(--kjd-dark-green); 
        font-weight: 700; 
        margin-bottom: 1.5rem;
        font-size: 1.4rem;
        border-bottom: 2px solid var(--kjd-beige);
        padding-bottom: 0.5rem;
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
        font-size: 0.95rem;
      }
      
      /* Status badges */
      .status-badge { 
        padding: 0.5rem 1rem; 
        border-radius: 20px; 
        font-weight: 600; 
        font-size: 0.85rem;
      }
      
      .status-overdue { 
        background: linear-gradient(135deg, #ff6b6b, #ee5a52); 
        color: white; 
      }
      
      .status-paid { 
        background: linear-gradient(135deg, #51cf66, #40c057); 
        color: white; 
      }
      
      .status-draft { 
        background: linear-gradient(135deg, #ffd43b, #fab005); 
        color: var(--kjd-dark-green); 
      }
      
      /* Alert styling */
      .alert { 
        border-radius: 12px; 
        border: none; 
        font-weight: 500;
      }
      
      .alert-danger { 
        background: linear-gradient(135deg, #ff6b6b, #ee5a52); 
        color: white; 
      }
      
      .alert-success { 
        background: linear-gradient(135deg, #51cf66, #40c057); 
        color: white; 
      }
      
      /* Item management */
      .item-row { 
        background: #f8f9fa; 
        border-radius: 12px; 
        padding: 1rem; 
        margin-bottom: 1rem; 
        border: 1px solid #e9ecef;
      }
      
      .remove-item { 
        background: #ff6b6b; 
        border: none; 
        color: white; 
        border-radius: 8px; 
        padding: 0.5rem; 
        width: 40px; 
        height: 40px;
        transition: all 0.3s ease;
      }
      
      .remove-item:hover { 
        background: #ee5a52; 
        transform: scale(1.1);
      }
      
      /* Summary section */
      .summary-card { 
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
        border-radius: 16px; 
        padding: 2rem; 
        border: 2px solid var(--kjd-earth-green);
      }
      
      .summary-row { 
        display: flex; 
        justify-content: space-between; 
        margin-bottom: 0.75rem; 
        font-size: 1.1rem;
      }
      
      .summary-total { 
        font-weight: 800; 
        font-size: 1.3rem; 
        color: var(--kjd-dark-green);
        border-top: 2px solid var(--kjd-earth-green);
        padding-top: 0.75rem;
        margin-top: 1rem;
      }
      
      
      /* Responsive */
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
        
        .summary-card { 
          padding: 1.5rem; 
        }
      }
    </style>
</head>
<body class="admin-page">
    <!-- Admin Sidebar -->
    <?php include 'admin_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">

    <!-- Header -->
    <div class="admin-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-edit"></i> Upravit fakturu</h1>
                    <p>Faktura <?= h($invoice['invoice_number']) ?></p>
                </div>
                <div class="col-md-4 text-end">
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Chyby:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="invoiceForm">
            <div class="container-fluid">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="apple-section">
                        <h3><i class="fas fa-info-circle"></i> Základní informace</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Datum vystavení *</label>
                                <input type="date" name="issue_date" class="form-control" 
                                       value="<?= h($_POST['issue_date'] ?? inv($invoice, 'issue_date')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Datum splatnosti *</label>
                                <input type="date" name="due_date" class="form-control" 
                                       value="<?= h($_POST['due_date'] ?? inv($invoice, 'due_date')) ?>" required>
                            </div>
                            <div class="col-md-12 mt-3">
                                <label class="form-label">Číslo objednávky</label>
                                <input type="text" name="order_id" class="form-control" 
                                       value="<?= h($_POST['order_id'] ?? inv($invoice, 'order_id')) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="apple-section">
                        <h3><i class="fas fa-user"></i> Informace o zákazníkovi</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Jméno / Firma *</label>
                                <input type="text" name="buyer_name" class="form-control" 
                                       value="<?= h($_POST['buyer_name'] ?? inv($invoice, 'buyer_name')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" name="buyer_email" class="form-control" 
                                       value="<?= h($_POST['buyer_email'] ?? inv($invoice, 'buyer_email')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefon</label>
                                <input type="text" name="buyer_phone" class="form-control" 
                                       value="<?= h($_POST['buyer_phone'] ?? inv($invoice, 'buyer_phone')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IČO</label>
                                <input type="text" name="buyer_ico" class="form-control" 
                                       value="<?= h($_POST['buyer_ico'] ?? inv($invoice, 'buyer_ico')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Adresa</label>
                                <input type="text" name="buyer_address1" class="form-control" 
                                       value="<?= h($_POST['buyer_address1'] ?? inv($invoice, 'buyer_address1')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Adresa 2</label>
                                <input type="text" name="buyer_address2" class="form-control" 
                                       value="<?= h($_POST['buyer_address2'] ?? inv($invoice, 'buyer_address2')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Město</label>
                                <input type="text" name="buyer_city" class="form-control" 
                                       value="<?= h($_POST['buyer_city'] ?? inv($invoice, 'buyer_city')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">PSČ</label>
                                <input type="text" name="buyer_zip" class="form-control" 
                                       value="<?= h($_POST['buyer_zip'] ?? inv($invoice, 'buyer_zip')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">DIČ</label>
                                <input type="text" name="buyer_dic" class="form-control" 
                                       value="<?= h($_POST['buyer_dic'] ?? inv($invoice, 'buyer_dic')) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Items -->
                    <div class="apple-section">
                        <h3><i class="fas fa-list"></i> Položky faktury</h3>
                        <div id="itemsContainer">
                            <?php 
                            $items_to_show = !empty($form_items) ? $form_items : $items;
                            if (empty($items_to_show)) {
                                $items_to_show = [['name' => '', 'quantity' => 1, 'unit_price_without_vat' => 0]];
                            }
                            foreach ($items_to_show as $index => $item): 
                            ?>
                            <div class="item-row" data-index="<?= $index ?>">
                                <div class="row align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label">Název položky</label>
                                        <input type="text" name="item_name[]" class="form-control" 
                                               value="<?= h($item['name']) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Množství</label>
                                        <input type="number" name="item_quantity[]" class="form-control" 
                                               value="<?= h($item['quantity']) ?>" min="1" step="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Cena za kus</label>
                                        <input type="number" name="item_price[]" class="form-control item-price" 
                                               value="<?= h($item['unit_price_without_vat'] ?? $item['price'] ?? 0) ?>" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn remove-item" onclick="removeItem(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-kjd-secondary" onclick="addItem()">
                            <i class="fas fa-plus me-2"></i>Přidat položku
                        </button>
                    </div>

                    <!-- Discount and Payment -->
                    <div class="apple-section">
                        <h3><i class="fas fa-percentage"></i> Sleva</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Typ slevy</label>
                                <select name="discount_type" class="form-control" onchange="updateDiscountDisplay()">
                                    <option value="none" <?= ($_POST['discount_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>Bez slevy</option>
                                    <option value="percentage" <?= ($_POST['discount_type'] ?? 'none') === 'percentage' ? 'selected' : '' ?>>Procentuální</option>
                                    <option value="fixed" <?= ($_POST['discount_type'] ?? 'none') === 'fixed' ? 'selected' : '' ?>>Pevná částka</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hodnota slevy</label>
                                <input type="number" name="discount_value" class="form-control" 
                                       value="<?= h($_POST['discount_value'] ?? 0) ?>" min="0" step="0.01">
                            </div>
                        </div>
                    </div>

                    <div class="apple-section">
                        <h3><i class="fas fa-credit-card"></i> Způsob platby</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Způsob platby</label>
                                <select name="payment_method" class="form-control">
                                    <option value="bank_transfer" <?= ($_POST['payment_method'] ?? inv($invoice, 'payment_method', 'bank_transfer')) === 'bank_transfer' ? 'selected' : '' ?>>Bankovní převod</option>
                                    <option value="revolut" <?= ($_POST['payment_method'] ?? inv($invoice, 'payment_method', 'bank_transfer')) === 'revolut' ? 'selected' : '' ?>>Revolut</option>
                                    <option value="card" <?= ($_POST['payment_method'] ?? inv($invoice, 'payment_method', 'bank_transfer')) === 'card' ? 'selected' : '' ?>>Kartou</option>
                                    <option value="cash" <?= ($_POST['payment_method'] ?? inv($invoice, 'payment_method', 'bank_transfer')) === 'cash' ? 'selected' : '' ?>>Hotově</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="apple-section">
                        <h3><i class="fas fa-sticky-note"></i> Poznámky</h3>
                        <div class="row">
                            <div class="col-md-12">
                                <label class="form-label">Poznámka k faktuře</label>
                                <textarea name="note" class="form-control" rows="4"><?= h($_POST['note'] ?? inv($invoice, 'note')) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Summary -->
                <div class="col-lg-4">
                    <div class="summary-card">
                        <h3><i class="fas fa-calculator"></i> Souhrn</h3>
                        <div class="summary-row">
                            <span>Celkem:</span>
                            <span id="subtotal">0,00 Kč</span>
                        </div>
                        <div class="summary-row">
                            <span>Sleva:</span>
                            <span id="discount">-0,00 Kč</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Celkem k úhradě:</span>
                            <span id="total">0,00 Kč</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="apple-section">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-kjd-primary">
                                <i class="fas fa-save me-2"></i>Uložit změny
                            </button>
                            <a href="admin_invoice_preview.php?id=<?= $invoice_id ?>" class="btn btn-kjd-info">
                                <i class="fas fa-eye me-2"></i>Náhled faktury
                            </a>
                            <a href="admin_invoices.php" class="btn btn-kjd-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Zpět na seznam
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addItem() {
            const container = document.getElementById('itemsContainer');
            const index = container.children.length;
            const itemHtml = `
                <div class="item-row" data-index="${index}">
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Název položky</label>
                            <input type="text" name="item_name[]" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Množství</label>
                            <input type="number" name="item_quantity[]" class="form-control" value="1" min="1" step="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cena za kus</label>
                            <input type="number" name="item_price[]" class="form-control item-price" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn remove-item" onclick="removeItem(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', itemHtml);
            updateTotals();
        }

        function removeItem(button) {
            const itemRow = button.closest('.item-row');
            itemRow.remove();
            updateTotals();
        }

        function updateTotals() {
            let subtotal = 0;
            const itemRows = document.querySelectorAll('.item-row');
            
            itemRows.forEach(row => {
                const quantity = parseFloat(row.querySelector('input[name="item_quantity[]"]').value) || 0;
                const price = parseFloat(row.querySelector('input[name="item_price[]"]').value) || 0;
                subtotal += quantity * price;
            });

            const vat = 0; // Neplátce DPH
            const discountType = document.querySelector('select[name="discount_type"]').value;
            const discountValue = parseFloat(document.querySelector('input[name="discount_value"]').value) || 0;
            
            let discount = 0;
            if (discountType === 'percentage') {
                discount = subtotal * (discountValue / 100);
            } else if (discountType === 'fixed') {
                discount = discountValue;
            }

            const total = subtotal - discount;

            document.getElementById('subtotal').textContent = subtotal.toLocaleString('cs-CZ', {minimumFractionDigits: 2}) + ' Kč';
            document.getElementById('discount').textContent = '-' + discount.toLocaleString('cs-CZ', {minimumFractionDigits: 2}) + ' Kč';
            document.getElementById('total').textContent = total.toLocaleString('cs-CZ', {minimumFractionDigits: 2}) + ' Kč';
        }

        function updateDiscountDisplay() {
            updateTotals();
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Update totals when inputs change
            document.addEventListener('input', function(e) {
                if (e.target.matches('input[name="item_quantity[]"], input[name="item_price[]"], input[name="discount_value"]') || 
                    e.target.matches('select[name="discount_type"]')) {
                    updateTotals();
                }
            });

            // Initial calculation
            updateTotals();
        });
    </script>
    </div> <!-- End main-content -->
</body>
</html>