<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$period = $_GET['period'] ?? '';
$where = '';
$params = [];
if ($period !== '') {
    // Try issue_date first, fallback to id if column doesn't exist
    try {
        $testStmt = $conn->prepare("SELECT issue_date FROM invoices LIMIT 1");
        $testStmt->execute();
        $where = 'WHERE DATE_FORMAT(issue_date, "%Y%m") = :period';
    } catch (PDOException $e) {
        // Fallback to id-based filtering if issue_date doesn't exist
        $where = 'WHERE DATE_FORMAT(FROM_UNIXTIME(id), "%Y%m") = :period';
    }
    $params[':period'] = $period;
}

// Check if issue_date column exists, fallback to id if not
try {
    $stmt = $conn->prepare("SELECT * FROM invoices $where ORDER BY issue_date DESC, id DESC");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback if issue_date column doesn't exist
    $stmt = $conn->prepare("SELECT * FROM invoices $where ORDER BY id DESC");
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Load last 5 orders for quick invoice creation
try {
    $stmtRecent = $conn->prepare("SELECT order_id, name, email, total_price, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
    $stmtRecent->execute();
    $recent_orders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_orders = [];
}

?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa faktur - Admin</title>
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
      
      /* Apple-style sections */
      .apple-section {
        background: #fff;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
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
      .invoice-status-paid {
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
      
      .invoice-status-overdue {
        background: linear-gradient(135deg, #dc3545, #e83e8c);
        color: #fff;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(220,53,69,0.3);
      }
      
      .invoice-status-pending {
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
      
      /* Form controls */
      .form-control, .form-select {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 12px 16px;
        font-weight: 500;
        transition: border-color 0.2s;
      }
      
      .form-control:focus, .form-select:focus {
        border-color: var(--kjd-earth-green);
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .form-label {
        color: var(--kjd-dark-green);
        font-weight: 600;
        margin-bottom: 8px;
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
      
      .alert-warning {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        color: #856404;
      }
      
      /* Quick invoice creation */
      .quick-invoice-card {
        background: rgba(202,186,156,0.1);
        border: 2px solid var(--kjd-beige);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
      }
      
      .quick-invoice-card:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.2);
      }
      
      .order-item {
        background: rgba(202,186,156,0.1);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 8px;
        border: 1px solid var(--kjd-beige);
      }
      
      .order-item:hover {
        background: rgba(202,186,156,0.2);
        border-color: var(--kjd-earth-green);
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
        
        .apple-btn-primary, .apple-btn-secondary {
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
                    <h1><i class="fas fa-file-invoice me-3"></i>Správa faktur</h1>
                    <p>Přehled a správa všech faktur</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <!-- Flash zprávy -->
                <?php if (!empty($_SESSION['admin_error'])): ?>
                    <div class="alert alert-danger alert-custom alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= h($_SESSION['admin_error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['admin_error']); ?>
                <?php endif; ?>

                <?php if (!empty($_SESSION['admin_success'])): ?>
                    <div class="alert alert-success alert-custom alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= h($_SESSION['admin_success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['admin_success']); ?>
                <?php endif; ?>

                <!-- Filtry -->
                <div class="apple-section">
                    <h2 class="apple-section-title">
                        <i class="fas fa-filter"></i>Filtry
                    </h2>
                    
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="period" class="form-label">Období</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="period" 
                                   name="period" 
                                   placeholder="RRRRMM (např. 202412)"
                                   value="<?= h($period) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="apple-btn-primary me-2">
                                <i class="fas fa-search me-2"></i>Filtrovat
                            </button>
                            <a href="admin_invoices.php" class="apple-btn-secondary">
                                <i class="fas fa-times me-2"></i>Vymazat
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Seznam faktur -->
                <div class="apple-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="apple-section-title mb-0">
                            <i class="fas fa-file-invoice"></i>Faktury
                        </h2>
                        <div>
                            <a href="admin_invoice_add.php" class="apple-btn-primary">
                                <i class="fas fa-plus me-2"></i>Nová faktura
                            </a>
                        </div>
                    </div>
                    
                    <p class="text-muted mb-4">Celkem <?= count($invoices) ?> faktur</p>

                    <?php if (count($invoices) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 50px;"></th>
                                        <th scope="col">Faktura</th>
                                        <th scope="col">Zákazník</th>
                                        <th scope="col">Vystaveno</th>
                                        <th scope="col">Splatnost</th>
                                        <th scope="col">Částka</th>
                                        <th scope="col">Stav</th>
                                        <th scope="col" class="text-end">Akce</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                        <?php 
                                        $due_date = isset($inv['due_date']) ? strtotime($inv['due_date']) : strtotime($inv['created_at'] ?? 'now');
                                        $today = time();
                                        $is_overdue = $due_date < $today && ($inv['status'] ?? 'draft') !== 'paid';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-sm me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <i class="fas fa-file-invoice"></i>
                                                        </span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <div class="fw-bold text-primary">#<?= h($inv['invoice_number'] ?? 'N/A') ?></div>
                                                        <?php if (!empty($inv['order_id'] ?? '')): ?>
                                                            <div class="text-muted small">Obj. <?= h($inv['order_id']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-sm me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <?= strtoupper(substr($inv['buyer_name'] ?? 'N', 0, 1)) ?>
                                                        </span>
                                                    </span>
                                                    <div>
                                                        <div class="fw-medium"><?= h($inv['buyer_name'] ?? 'Neznámý zákazník') ?></div>
                                                        <?php if (!empty($inv['buyer_email'] ?? '')): ?>
                                                            <div class="text-muted small"><?= h($inv['buyer_email']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= isset($inv['issue_date']) ? date('d.m.Y', strtotime($inv['issue_date'])) : date('d.m.Y', strtotime($inv['created_at'] ?? 'now')) ?></td>
                                            <td>
                                                <div class="<?= $is_overdue ? 'text-danger fw-bold' : '' ?>">
                                                    <?= date('d.m.Y', $due_date) ?>
                                                    <?php if ($is_overdue): ?>
                                                        <br><small class="text-danger">Po splatnosti</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= number_format((float)($inv['total_with_vat'] ?? $inv['total_price'] ?? 0), 2, ',', ' ') ?> Kč</div>
                                            </td>
                                            <td>
                                                <?php if (($inv['status'] ?? 'draft') === 'paid'): ?>
                                                    <span class="invoice-status-paid">Zaplaceno</span>
                                                <?php elseif ($is_overdue): ?>
                                                    <span class="invoice-status-overdue">Po splatnosti</span>
                                                <?php else: ?>
                                                    <span class="invoice-status-pending">Čeká na platbu</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="admin_invoice_preview.php?id=<?= (int)$inv['id'] ?>"><i class="fas fa-eye me-2"></i>Náhled</a></li>
                                                        <li><a class="dropdown-item" href="admin_invoice_edit.php?id=<?= (int)$inv['id'] ?>"><i class="fas fa-edit me-2"></i>Upravit</a></li>
                                                        <li><a class="dropdown-item" href="admin_invoice_download.php?id=<?= (int)$inv['id'] ?>"><i class="fas fa-download me-2"></i>Stáhnout PDF</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item" href="send_invoice_email.php?id=<?= (int)$inv['id'] ?>"><i class="fas fa-paper-plane me-2"></i>Odeslat email</a></li>
                                                        <?php if (($inv['status'] ?? 'draft') !== 'paid'): ?>
                                                            <li><a class="dropdown-item" href="admin_invoice_mark_paid.php?id=<?= (int)$inv['id'] ?>" onclick="return confirm('Označit fakturu jako zaplacenou?')"><i class="fas fa-check me-2"></i>Označit jako zaplaceno</a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Žádné faktury</h4>
                            <p class="text-muted">Zatím nebyly vytvořeny žádné faktury.</p>
                            <a href="admin_invoice_add.php" class="apple-btn-primary">
                                <i class="fas fa-plus me-2"></i>Vytvořit první fakturu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rychlé vytvoření faktury -->
                <?php if (count($recent_orders) > 0): ?>
                    <div class="apple-section">
                        <h2 class="apple-section-title">
                            <i class="fas fa-bolt"></i>Rychlé vytvoření faktury
                        </h2>
                        <p class="text-muted mb-4">Posledních 5 objednávek</p>
                        
                        <div class="row">
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="order-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="fw-bold text-primary">#<?= h($order['order_id']) ?></div>
                                                <div class="text-muted small"><?= h($order['name']) ?></div>
                                                <div class="text-muted small"><?= h($order['email']) ?></div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold"><?= number_format((float)$order['total_price'], 0, ',', ' ') ?> Kč</div>
                                                <div class="text-muted small"><?= date('d.m.Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                        </div>
                                        <div class="d-grid">
                                            <a href="admin_invoice_create.php?order_id=<?= h($order['order_id']) ?>" class="apple-btn-primary btn-sm">
                                                <i class="fas fa-file-invoice me-2"></i>Vytvořit fakturu
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>