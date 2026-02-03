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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KJD Administrace</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- KJD Premium Admin CSS -->
    <link rel="stylesheet" href="css/kjd_admin_v2.css">
    <!-- Fonts -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <div style="width: 280px; flex-shrink: 0;" class="d-none d-lg-block">
        <?php include 'admin_sidebar_v2.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1" style="min-height: 100vh; display: flex; flex-direction: column;">
        
        <!-- Topbar -->
        <div class="topbar">
            <h1 class="page-title"><i class="fas fa-tachometer-alt me-2 text-muted"></i>Dashboard</h1>
            <div class="d-flex align-items-center gap-3">
                <a href="../index.php" target="_blank" class="btn btn-kjd-outline btn-sm">
                    <i class="fas fa-external-link-alt me-2"></i>Přejít na web
                </a>
                <!-- Mobile Toggle -->
                <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Content Container -->
        <div class="container-fluid px-4 pb-5">
            
            <?php if ($apiError): ?>
            <div class="alert alert-warning mb-4 shadow-sm border-0 rounded-3">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $apiError; ?>
            </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <!-- Orders Card -->
                <div class="col-md-3">
                    <div class="kjd-card stat-card">
                        <div>
                            <div class="stat-value"><?php echo $orderCount; ?></div>
                            <div class="stat-label">Objednávek</div>
                            <small class="text-muted mt-1 d-block">
                                <span class="badge bg-warning text-dark me-1"><?php echo $pendingOrderCount ?? 0; ?> čeká</span>
                                <span class="badge bg-success"><?php echo $completedOrderCount ?? 0; ?> hotovo</span>
                            </small>
                        </div>
                        <div class="stat-icon bg-icon-orders">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                </div>

                <!-- Revenue Card -->
                <div class="col-md-3">
                    <div class="kjd-card stat-card">
                        <div>
                            <div class="stat-value text-success"><?php echo number_format($totalRevenue, 0, ',', ' '); ?></div>
                            <div class="stat-label">Tržby (Kč)</div>
                            <small class="text-muted mt-1 d-block">Pouze uhrazené</small>
                        </div>
                        <div class="stat-icon bg-icon-revenue">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>

                <!-- Products Card -->
                <div class="col-md-3">
                    <div class="kjd-card stat-card">
                        <div>
                            <div class="stat-value"><?php echo ($productCount + $product2Count); ?></div>
                            <div class="stat-label">Produktů</div>
                            <small class="text-muted mt-1 d-block">
                                <?php echo $productCount; ?> standard + <?php echo $product2Count; ?> variace
                            </small>
                        </div>
                        <div class="stat-icon bg-icon-products">
                            <i class="fas fa-box-open"></i>
                        </div>
                    </div>
                </div>
                
                 <!-- Notifications Card -->
                <div class="col-md-3">
                    <div class="kjd-card stat-card">
                        <div>
                            <div class="stat-value text-danger"><?php echo $notificationCount; ?></div>
                            <div class="stat-label">Notifikace</div>
                            <small class="text-muted mt-1 d-block">Žádosti o barvu</small>
                        </div>
                        <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Orders Table -->
                <div class="col-lg-8">
                    <div class="kjd-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="h5 mb-0">Poslední objednávky</h3>
                            <a href="admin_orders.php" class="btn btn-sm btn-kjd-outline">Všechny objednávky</a>
                        </div>
                        
                        <div class="table-responsive table-container">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Zákazník</th>
                                        <th>Částka</th>
                                        <th>Stav</th>
                                        <th class="text-end">Akce</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentOrders)): ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td class="fw-bold">#<?php echo htmlspecialchars($order['order_id'] ?? $order['id']); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($order['name']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($order['email']); ?></div>
                                                </td>
                                                <td class="fw-bold"><?php echo number_format(floatval($order['total_price']), 0, ',', ' '); ?> Kč</td>
                                                <td>
                                                    <?php 
                                                    $status = $order['payment_status'];
                                                    $badgeClass = ($status == 'paid') ? 'badge-paid' : (($status == 'pending') ? 'badge-pending' : 'badge-cancelled');
                                                    $statusText = ($status == 'paid') ? 'Zaplaceno' : (($status == 'pending') ? 'Čeká na platbu' : 'Zrušeno');
                                                    ?>
                                                    <span class="badge badge-pill <?php echo $badgeClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="admin_order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light text-muted border">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">Žádné objednávky</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="kjd-card h-100">
                        <h3 class="h5 mb-4">Rychlé akce</h3>
                        <div class="d-grid gap-3">
                            <a href="novy_produkt.php" class="btn btn-kjd text-start p-3 d-flex align-items-center">
                                <i class="fas fa-plus-circle fa-lg me-3"></i>
                                <div>
                                    <div class="fw-bold">Přidat produkt</div>
                                    <small style="opacity: 0.8">Vytvořit novou položku v e-shopu</small>
                                </div>
                            </a>
                            <a href="manage_colors.php" class="btn btn-light text-start p-3 d-flex align-items-center border">
                                <i class="fas fa-palette fa-lg me-3 text-muted"></i>
                                <div>
                                    <div class="fw-bold text-dark">Správa barev</div>
                                    <small class="text-muted">Editace dostupných filamentů</small>
                                </div>
                            </a>
                            <a href="admin_newsletter.php" class="btn btn-light text-start p-3 d-flex align-items-center border">
                                <i class="fas fa-paper-plane fa-lg me-3 text-muted"></i>
                                <div>
                                    <div class="fw-bold text-dark">Newsletter</div>
                                    <small class="text-muted">Poslat e-mail zákazníkům</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- End container -->
    </div> <!-- End Main Content -->
</div> <!-- End Flex wrapper -->

<!-- Offcanvas Sidebar for Mobile -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">KJD Admin</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php include 'admin_sidebar_v2.php'; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
