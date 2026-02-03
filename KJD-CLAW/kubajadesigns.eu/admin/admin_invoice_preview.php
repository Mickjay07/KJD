<?php
session_start();
require_once 'config.php';

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
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

// Get settings
$stmt = $conn->prepare("SELECT * FROM settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch() ?: [];

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

$number = $invoice['invoice_number'];
$inv = $invoice;

// Extract delivery info from products_json
$deliveryInfo = '';
if (!empty($inv['products_json'])) {
    $productsData = json_decode($inv['products_json'], true);
    if (isset($productsData['_delivery_info'])) {
        $delivery = $productsData['_delivery_info'];
        if (!empty($delivery['method'])) {
            $deliveryInfo = $delivery['method'];
            if (!empty($delivery['address'])) {
                $deliveryInfo .= ' - ' . $delivery['address'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Náhled faktury <?= h($number) ?> - Admin</title>
    
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
      
      /* Invoice preview */
      .invoice-preview {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .invoice-header {
        border-bottom: 3px solid var(--kjd-earth-green);
        padding-bottom: 2rem;
        margin-bottom: 2rem;
      }
      
      .invoice-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--kjd-dark-green);
        margin: 0;
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
      }
      
      .invoice-subtitle {
        font-size: 1.2rem;
        font-weight: 500;
        color: var(--kjd-earth-green);
        margin-top: 0.5rem;
      }
      
      .invoice-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--kjd-dark-green);
        text-align: right;
      }
      
      .invoice-info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
        gap: 1rem;
      }
      
      .invoice-info-card {
        background: rgba(202,186,156,0.1);
        border: 2px solid var(--kjd-beige);
        border-radius: 16px;
        padding: 1.5rem;
        flex: 1;
        transition: all 0.3s ease;
      }
      
      .invoice-info-card:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
      }
      
      .info-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--kjd-earth-green);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
      }
      
      .info-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--kjd-dark-green);
      }
      
      .invoice-parties {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
      }
      
      .party-card {
        background: rgba(202,186,156,0.1);
        border: 2px solid var(--kjd-beige);
        border-radius: 16px;
        padding: 1.5rem;
        transition: all 0.3s ease;
      }
      
      .party-card:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
      }
      
      .party-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--kjd-dark-green);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
      }
      
      .party-title i {
        margin-right: 0.5rem;
        color: var(--kjd-earth-green);
      }
      
      .party-info {
        line-height: 1.6;
        color: var(--kjd-dark-green);
        font-weight: 500;
      }
      
      .invoice-table {
        width: 100%;
        margin-bottom: 2rem;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .invoice-table th {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: #fff;
        padding: 1rem;
        font-weight: 700;
        text-align: left;
        border: none;
      }
      
      .invoice-table th:last-child,
      .invoice-table td:last-child {
        text-align: right;
      }
      
      .invoice-table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(202,186,156,0.1);
        color: var(--kjd-dark-green);
        font-weight: 500;
      }
      
      .invoice-table tbody tr:hover {
        background: rgba(202,186,156,0.05);
      }
      
      .invoice-totals {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 2rem;
      }
      
      .totals-card {
        background: rgba(202,186,156,0.1);
        border: 2px solid var(--kjd-beige);
        border-radius: 16px;
        padding: 1.5rem;
        min-width: 300px;
        transition: all 0.3s ease;
      }
      
      .totals-card:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
      }
      
      .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        padding: 0.25rem 0;
        color: var(--kjd-dark-green);
        font-weight: 500;
      }
      
      .total-row.final {
        border-top: 3px solid var(--kjd-earth-green);
        padding-top: 1rem;
        margin-top: 1rem;
        font-weight: 800;
        font-size: 1.2rem;
        color: var(--kjd-dark-green);
      }
      
      .invoice-notes {
        background: rgba(202,186,156,0.1);
        border: 2px solid var(--kjd-beige);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        transition: all 0.3s ease;
      }
      
      .invoice-notes:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
      }
      
      .notes-title {
        font-weight: 700;
        color: var(--kjd-dark-green);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
      }
      
      .notes-title i {
        margin-right: 0.5rem;
        color: var(--kjd-earth-green);
      }
      
      .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .status-paid {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: #fff;
        box-shadow: 0 2px 8px rgba(40,167,69,0.3);
      }
      
      .status-pending {
        background: linear-gradient(135deg, #ffc107, #ff8c00);
        color: #fff;
        box-shadow: 0 2px 8px rgba(255,193,7,0.3);
      }
      
      .status-overdue {
        background: linear-gradient(135deg, #dc3545, #e83e8c);
        color: #fff;
        box-shadow: 0 2px 8px rgba(220,53,69,0.3);
      }
      
      /* Mobile responsiveness */
      @media (max-width: 768px) {
        .admin-header {
          padding: 1.5rem 0;
          margin-bottom: 1rem;
        }
        
        .admin-header h1 {
          font-size: 1.8rem;
          margin-bottom: 0.25rem;
        }
        
        .admin-header p {
          font-size: 0.95rem;
        }
        
        .invoice-preview {
          padding: 1rem;
          border-radius: 12px;
          margin: 0 0.5rem;
        }
        
        .invoice-title {
          font-size: 1.6rem;
        }
        
        .invoice-subtitle {
          font-size: 1rem;
        }
        
        .invoice-number {
          font-size: 1.2rem;
        }
        
        .invoice-info-row {
          flex-direction: column;
          gap: 0.75rem;
          margin-bottom: 1.5rem;
        }
        
        .invoice-info-card {
          padding: 1rem;
          border-radius: 12px;
        }
        
        .invoice-parties {
          grid-template-columns: 1fr;
          gap: 1rem;
          margin-bottom: 1.5rem;
        }
        
        .party-card {
          padding: 1rem;
          border-radius: 12px;
        }
        
        .party-title {
          font-size: 1.1rem;
          margin-bottom: 0.75rem;
        }
        
        .invoice-table {
          font-size: 0.9rem;
          border-radius: 8px;
        }
        
        .invoice-table th,
        .invoice-table td {
          padding: 0.75rem 0.5rem;
        }
        
        .invoice-totals {
          justify-content: center;
          margin-bottom: 1.5rem;
        }
        
        .totals-card {
          min-width: 100%;
          padding: 1rem;
          border-radius: 12px;
        }
        
        .total-row {
          font-size: 0.95rem;
        }
        
        .total-row.final {
          font-size: 1.1rem;
        }
        
        .invoice-notes {
          padding: 1rem;
          border-radius: 12px;
          margin-bottom: 1rem;
        }
        
        .notes-title {
          font-size: 1rem;
          margin-bottom: 0.75rem;
        }
        
        .btn-group {
          flex-direction: column;
          gap: 0.5rem;
          width: 100%;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary, .btn-kjd-info, .btn-kjd-dark {
          padding: 12px 20px;
          font-size: 0.95rem;
          border-radius: 10px;
          width: 100%;
          justify-content: center;
        }
        
        .status-badge {
          font-size: 0.8rem;
          padding: 0.4rem 0.8rem;
        }
      }
      
      @media (max-width: 576px) {
        .admin-header {
          padding: 1rem 0;
        }
        
        .admin-header h1 {
          font-size: 1.5rem;
        }
        
        .admin-header p {
          font-size: 0.9rem;
        }
        
        .invoice-preview {
          padding: 0.75rem;
          margin: 0 0.25rem;
        }
        
        .invoice-title {
          font-size: 1.4rem;
        }
        
        .invoice-subtitle {
          font-size: 0.9rem;
        }
        
        .invoice-number {
          font-size: 1.1rem;
        }
        
        .invoice-header {
          padding-bottom: 1rem;
          margin-bottom: 1rem;
        }
        
        .invoice-info-card {
          padding: 0.75rem;
        }
        
        .info-label {
          font-size: 0.8rem;
        }
        
        .info-value {
          font-size: 1rem;
        }
        
        .party-card {
          padding: 0.75rem;
        }
        
        .party-title {
          font-size: 1rem;
        }
        
        .invoice-table {
          font-size: 0.85rem;
        }
        
        .invoice-table th,
        .invoice-table td {
          padding: 0.5rem 0.25rem;
        }
        
        .totals-card {
          padding: 0.75rem;
        }
        
        .total-row {
          font-size: 0.9rem;
        }
        
        .total-row.final {
          font-size: 1rem;
        }
        
        .invoice-notes {
          padding: 0.75rem;
        }
        
        .notes-title {
          font-size: 0.95rem;
        }
        
        .container-fluid {
          padding-left: 0.25rem;
          padding-right: 0.25rem;
        }
        
        .d-flex.justify-content-between {
          flex-direction: column;
          align-items: flex-start !important;
          gap: 1rem;
        }
        
        .btn-group {
          width: 100%;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary, .btn-kjd-info, .btn-kjd-dark {
          padding: 10px 16px;
          font-size: 0.9rem;
        }
      }
      
      /* Extra small devices */
      @media (max-width: 400px) {
        .invoice-preview {
          padding: 0.5rem;
          margin: 0;
        }
        
        .invoice-title {
          font-size: 1.2rem;
        }
        
        .invoice-number {
          font-size: 1rem;
        }
        
        .invoice-table {
          font-size: 0.8rem;
        }
        
        .invoice-table th,
        .invoice-table td {
          padding: 0.4rem 0.2rem;
        }
        
        .total-row {
          font-size: 0.85rem;
        }
        
        .total-row.final {
          font-size: 0.95rem;
        }
      }
      
      @media print {
        .no-print {
          display: none !important;
        }
        
        body {
          background: white;
        }
        
        .invoice-preview {
          box-shadow: none;
          border: none;
        }
      }
    </style>
</head>

<body class="admin-page">
    <?php include '../includes/icons.php'; ?>
    
    <!-- Navigation Menu -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-eye me-3"></i>Náhled faktury <?= h($number) ?></h1>
                    <p>Předvádění faktury před tiskem nebo odesláním</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                
                <!-- Action buttons -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <div>
                        <h4 class="fw-bold py-3 mb-2">
                            <span class="text-muted fw-light">Faktury /</span> Náhled faktury
                        </h4>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                        <a href="admin_invoices.php" class="btn btn-kjd-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Zpět
                        </a>
                        <a href="admin_invoice_edit.php?id=<?= (int)$invoice_id ?>" class="btn btn-kjd-info">
                            <i class="fas fa-edit me-2"></i>Upravit
                        </a>
                        
                        <div class="d-flex align-items-center bg-white px-3 py-2 rounded border" style="border-color: var(--kjd-earth-green) !important;">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="invoiceModified" value="1" style="cursor: pointer;">
                                <label class="form-check-label ms-2" for="invoiceModified" style="font-weight: 600; color: var(--kjd-dark-green); cursor: pointer; user-select: none;">
                                    Upozornit na změnu
                                </label>
                            </div>
                        </div>

                        <button type="button" class="btn btn-kjd-primary" onclick="sendInvoiceEmail(<?= (int)$invoice_id ?>)">
                            <i class="fas fa-envelope me-2"></i>Odeslat emailem
                        </button>
                        <a href="admin_invoice_download_pdf.php?id=<?= (int)$invoice_id ?>" class="btn btn-kjd-primary">
                            <i class="fas fa-download me-2"></i>Stáhnout PDF
                        </a>
                        <button onclick="window.print()" class="btn btn-kjd-dark">
                            <i class="fas fa-print me-2"></i>Tisk
                        </button>
                    </div>
                </div>
                
                <!-- PDF Preview -->
                <div class="pdf-preview-container" style="background: white; border-radius: 16px; padding: 1rem; box-shadow: 0 2px 16px rgba(16,40,32,0.08); border: 2px solid var(--kjd-earth-green); min-height: 800px;">
                    <iframe 
                        src="admin_invoice_download_pdf.php?id=<?= (int)$invoice_id ?>&preview=1" 
                        style="width: 100%; height: 1000px; border: none; border-radius: 12px;"
                        title="Náhled faktury <?= h($number) ?>">
                    </iframe>
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function sendInvoiceEmail(invoiceId) {
        if (confirm('Opravdu chcete odeslat fakturu emailem zákazníkovi?')) {
            const modified = document.getElementById('invoiceModified')?.checked ? 1 : 0;
            fetch('send_invoice_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'invoice_id=' + invoiceId + '&invoice_modified=' + modified
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Faktura byla úspěšně odeslána!');
                } else {
                    alert('Chyba při odesílání: ' + (data.message || 'Neznámá chyba'));
                }
            })
            .catch(error => {
                alert('Chyba při odesílání emailu: ' + error);
            });
        }
    }
    </script>
</body>
</html>
