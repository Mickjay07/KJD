<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$invoice_id = (int)($_GET['id'] ?? 0);
if ($invoice_id <= 0) {
    header('Location: admin_invoices.php');
    exit;
}

$success = $_SESSION['admin_success'] ?? '';
$error = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

// Load invoice
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
    $_SESSION['admin_error'] = 'Faktura nenalezena.';
    header('Location: admin_invoices.php');
    exit;
}

// Load items
$it = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$it->execute([$invoice_id]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktura <?= h($inv['invoice_number']) ?> - Admin</title>
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
      
      .apple-section-title {
        color: var(--kjd-dark-green);
        font-weight: 800;
        font-size: 1.8rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
      }
      
      .apple-section-title i {
        margin-right: 1rem;
        color: var(--kjd-earth-green);
      }
      
      /* Apple-style buttons */
      .apple-btn-primary {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1em;
        padding: 14px 38px;
        box-shadow: 0 4px 15px rgba(76,100,68,0.3);
        transition: all 0.3s ease;
      }
      
      .apple-btn-primary:hover {
        background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green));
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76,100,68,0.4);
      }
      
      .apple-btn-secondary {
        background: #f5f5f5;
        color: #111;
        border: 1px solid #ddd;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1em;
        padding: 14px 38px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: all 0.2s;
      }
      
      .apple-btn-secondary:hover {
        background: #e9e9e9;
        color: #111;
      }
      
      .apple-btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1em;
        padding: 14px 38px;
        box-shadow: 0 4px 15px rgba(40,167,69,0.3);
        transition: all 0.3s ease;
      }
      
      .apple-btn-success:hover {
        background: linear-gradient(135deg, #20c997, #28a745);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(40,167,69,0.4);
      }
      
      .apple-btn-info {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1em;
        padding: 14px 38px;
        box-shadow: 0 4px 15px rgba(23,162,184,0.3);
        transition: all 0.3s ease;
      }
      
      .apple-btn-info:hover {
        background: linear-gradient(135deg, #6f42c1, #17a2b8);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(23,162,184,0.4);
      }
      
      .apple-btn-dark {
        background: linear-gradient(135deg, #343a40, #495057);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1em;
        padding: 14px 38px;
        box-shadow: 0 4px 15px rgba(52,58,64,0.3);
        transition: all 0.3s ease;
      }
      
      .apple-btn-dark:hover {
        background: linear-gradient(135deg, #495057, #343a40);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(52,58,64,0.4);
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
      
      /* Status badges */
      .status-paid {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(40,167,69,0.3);
      }
      
      .status-canceled {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(108,117,125,0.3);
      }
      
      .status-issued {
        background: linear-gradient(135deg, #ffc107, #ff8c00);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(255,193,7,0.3);
      }
      
      /* Alert styles */
      .alert-custom {
        border-radius: 12px;
        border: none;
        padding: 16px 20px;
        font-weight: 600;
      }
      
      .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
      }
      
      .alert-danger {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
      }
      
      /* Info cards */
      .info-card {
        background: rgba(202,186,156,0.1);
        border: 2px solid var(--kjd-beige);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
      }
      
      .info-card:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
      }
      
      .info-card-title {
        color: var(--kjd-dark-green);
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
      }
      
      .info-card-title i {
        margin-right: 0.5rem;
        color: var(--kjd-earth-green);
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
        
        .apple-section-title {
          font-size: 1.5rem;
        }
        
        .table th, .table td {
          padding: 0.5rem;
          font-size: 0.9rem;
        }
        
        .apple-btn-primary, .apple-btn-secondary, .apple-btn-success, .apple-btn-info, .apple-btn-dark {
          padding: 12px 24px;
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

<body class="admin-page">
    <?php include '../includes/icons.php'; ?>
    
    <!-- Navigation Menu -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-file-invoice me-3"></i>Faktura <?= h($inv['invoice_number']) ?></h1>
                    <p>Detail faktury a správa</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <!-- Flash zprávy -->
                <?php if ($success): ?>
                    <div class="alert alert-success alert-custom alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= h($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= h($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Invoice Preview Card -->
                <div class="invoice-preview">
                    
                    <!-- Invoice Header -->
                    <div class="invoice-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h1 class="invoice-title">KubaJa Designs</h1>
                                <p class="invoice-subtitle">Moderní osvětlení a design</p>
                            </div>
                            <div class="text-end">
                                <h2 class="invoice-number">Faktura <?= h($inv['invoice_number']) ?></h2>
                                <?php 
                                $due_date = strtotime($inv['due_date']);
                                $today = time();
                                $is_overdue = $due_date < $today && $inv['status'] !== 'paid';
                                ?>
                                <?php if ($inv['status'] === 'paid'): ?>
                                    <span class="status-badge status-paid">
                                        <i class="fas fa-check-circle me-2"></i>Zaplaceno
                                    </span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="status-badge status-overdue">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Po splatnosti
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock me-2"></i>Čeká na platbu
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Info Row -->
                    <div class="invoice-info-row">
                        <div class="invoice-info-card">
                            <div class="info-label">Datum vystavení</div>
                            <div class="info-value"><?= date('d.m.Y', strtotime($inv['issue_date'])) ?></div>
                        </div>
                        <div class="invoice-info-card">
                            <div class="info-label">Datum splatnosti</div>
                            <div class="info-value <?= $is_overdue ? 'text-danger' : '' ?>">
                                <?= date('d.m.Y', $due_date) ?>
                            </div>
                        </div>
                        <?php if (!empty($inv['order_id'])): ?>
                        <div class="invoice-info-card">
                            <div class="info-label">Číslo objednávky</div>
                            <div class="info-value">#<?= h($inv['order_id']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Invoice Items Table -->
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Název položky</th>
                                <th style="width: 15%; text-align: center;">Množství</th>
                                <th style="width: 20%; text-align: right;">Jedn. cena</th>
                                <th style="width: 20%; text-align: right;">Celkem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= h($item['name']) ?></div>
                                </td>
                                <td style="text-align: center;"><?= (int)$item['quantity'] ?></td>
                                <td style="text-align: right;"><?= number_format((float)$item['unit_price_without_vat'], 2, ',', ' ') ?> Kč</td>
                                <td style="text-align: right;"><?= number_format((float)$item['total_with_vat'], 2, ',', ' ') ?> Kč</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Invoice Totals -->
                    <div class="invoice-totals">
                        <div class="totals-card">
                            <div class="total-row">
                                <span>Mezisoučet:</span>
                                <span><?= number_format((float)$inv['total_without_vat'], 2, ',', ' ') ?> Kč</span>
                            </div>
                            <?php if (!empty($inv['sleva']) && (float)$inv['sleva'] > 0): ?>
                            <div class="total-row" style="color: #28a745;">
                                <span>Sleva:</span>
                                <span>-<?= number_format((float)$inv['sleva'], 2, ',', ' ') ?> Kč</span>
                            </div>
                            <?php endif; ?>
                            <div class="total-row">
                                <span>Celkem bez DPH:</span>
                                <span><?= number_format(max(0, (float)$inv['total_without_vat'] - (float)($inv['sleva'] ?? 0)), 2, ',', ' ') ?> Kč</span>
                            </div>
                            <div class="total-row">
                                <span>DPH:</span>
                                <span><?= number_format((float)$inv['vat_total'], 2, ',', ' ') ?> Kč</span>
                            </div>
                            <div class="total-row final">
                                <span>Celkem k úhradě:</span>
                                <span><?= number_format((float)$inv['total_with_vat'], 2, ',', ' ') ?> Kč</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Parties -->
                    <div class="invoice-parties">
                        <div class="party-card">
                            <div class="party-title">
                                <i class="fas fa-building"></i>Dodavatel
                            </div>
                            <div class="party-info">
                                <strong>KubaJa Designs</strong><br>
                                Mezilesí 2078<br>
                                19300 Praha 20<br>
                                Tel: 722 341 256<br>
                                Email: info@kubajadesigns.eu
                            </div>
                        </div>
                        <div class="party-card">
                            <div class="party-title">
                                <i class="fas fa-user"></i>Odběratel
                            </div>
                            <div class="party-info">
                                <strong><?= h($inv['buyer_name']) ?></strong><br>
                                <?php if (!empty($inv['buyer_address1'])): ?>
                                    <?= h($inv['buyer_address1']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($inv['buyer_address2'])): ?>
                                    <?= h($inv['buyer_address2']) ?><br>
                                <?php endif; ?>
                                <?php 
                                $buyerLine = trim(($inv['buyer_zip']??'').' '.($inv['buyer_city']??''));
                                if ($buyerLine !== ''): ?>
                                    <?= h($buyerLine) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($inv['buyer_ico'])): ?>
                                    IČO: <?= h($inv['buyer_ico']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($inv['buyer_dic'])): ?>
                                    DIČ: <?= h($inv['buyer_dic']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($inv['buyer_phone'])): ?>
                                    Tel: <?= h($inv['buyer_phone']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($inv['buyer_email'])): ?>
                                    Email: <?= h($inv['buyer_email']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="invoice-notes">
                        <div class="mb-3">
                            <div class="notes-title">
                                <i class="fas fa-credit-card"></i>Způsob platby
                            </div>
                            <?php 
                            $paymentMethods = [
                                'bank_transfer' => 'Bankovní převod',
                                'revolut' => 'Revolut',
                                'cash' => 'Hotovost',
                                'card' => 'Kartou'
                            ];
                            $paymentMethod = $inv['payment_method'] ?? 'bank_transfer';
                            ?>
                            <p class="mb-0"><?= h($paymentMethods[$paymentMethod] ?? 'Bankovní převod') ?></p>
                            
                            <?php if ($paymentMethod === 'bank_transfer'): ?>
                            <div class="mt-2">
                                <strong>Bankovní účet:</strong> 2502903320/3030 (Air Bank)<br>
                                <strong>Variabilní symbol:</strong> <?= h($inv['order_id'] ?? $inv['invoice_number']) ?>
                            </div>
                            <?php elseif ($paymentMethod === 'revolut'): ?>
                            <div class="mt-2">
                                <strong>Revolut:</strong> +420 722 341 256<br>
                                <strong>Variabilní symbol:</strong> <?= h($inv['order_id'] ?? $inv['invoice_number']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <?php if (!empty($inv['note'])): ?>
                    <div class="invoice-notes">
                        <div class="mb-3">
                            <div class="notes-title">
                                <i class="fas fa-sticky-note"></i>Poznámka
                            </div>
                            <p class="mb-0"><?= nl2br(h($inv['note'])) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Footer -->
                    <div class="text-center text-muted">
                        <small>KubaJa Designs — automaticky generovaná faktura</small><br>
                        <small>Dodavatel není plátcem DPH</small>
                    </div>
                    
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>