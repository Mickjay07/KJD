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
        
        // Poslední objednávky pro tabulku a graf (zvýšeno na 10 pro lepší graf)
        $stmt = $conn->prepare("SELECT id, order_id, name, email, total_price, payment_status, created_at FROM orders ORDER BY created_at DESC LIMIT 10");
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
    <title>KJD Dashboard</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- KJD Premium Admin CSS -->
    <link rel="stylesheet" href="css/kjd_admin_v2.css?v=<?php echo time(); ?>">
    <!-- Fonts -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <div style="width: 280px; flex-shrink: 0;" class="d-none d-lg-block">
        <?php include 'admin_sidebar_v2.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 bg-light-subtle" style="min-height: 100vh; display: flex; flex-direction: column;">
        
        <!-- Topbar -->
        <div class="topbar glass-effect glass-panel sticky-top">
            <div class="d-flex align-items-center">
                <h1 class="page-title h4 mb-0 fw-bold">Přehled <small class="text-muted fs-6 fw-normal">(v2.1)</small></h1>
                <span class="text-muted ms-3 small d-none d-md-block"><?php echo date('j. n. Y'); ?></span>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-icon rounded-circle position-relative" type="button" data-bs-toggle="dropdown">
                        <i class="far fa-bell fa-lg text-secondary"></i>
                        <?php if($notificationCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light p-1">
                                <span class="visually-hidden">Upozornění</span>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-0 overflow-hidden" style="width: 300px;">
                        <li class="p-3 bg-light border-bottom"><h6 class="dropdown-header p-0 text-dark fw-bold">Upozornění</h6></li>
                        <?php if($notificationCount > 0): ?>
                            <li><a class="dropdown-item p-3 text-wrap" href="admin_manage_colors.php">
                                <div class="d-flex align-items-center text-danger">
                                    <i class="fas fa-exclamation-circle me-3 fa-lg"></i>
                                    <div>
                                        <span class="d-block fw-bold">Nízký stav skladu</span>
                                        <span class="small text-muted"><?php echo $notificationCount; ?> barev vyžaduje pozornost</span>
                                    </div>
                                </div>
                            </a></li>
                        <?php else: ?>
                            <li><div class="p-4 text-center text-muted small">Žádná nová upozornění</div></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <a href="../index.php" target="_blank" class="btn btn-kjd-outline btn-sm rounded-pill px-3">
                    <i class="fas fa-external-link-alt me-2"></i>Web
                </a>
                
                <!-- Mobile Toggle -->
                <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Content Container -->
        <div class="container-fluid page-shell">
            
            <?php if ($apiError): ?>
            <div class="alert alert-warning mb-4 shadow-sm border-0 rounded-3">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $apiError; ?>
            </div>
            <?php endif; ?>

            <!-- Welcome Section -->
            <div class="row mb-4 align-items-center">
                <div class="col-md-8">
                    <h2 class="h3 fw-bold text-dark-green mb-1">Vítej zpět, Jakube! 👋</h2>
                    <p class="text-muted mb-0">Tady je přehled toho nejdůležitějšího za dnešek.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="admin_invoice_create.php" class="btn btn-kjd shadow-sm me-2"><i class="fas fa-plus me-2"></i>Faktura</a>
                    <a href="admin_novy_produkt.php" class="btn btn-white border shadow-sm"><i class="fas fa-box me-2"></i>Produkt</a>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <!-- Revenue Card -->
                <div class="col-md-4">
                    <div class="kjd-card stat-card bg-gradient-gold text-white h-100 border-0 overflow-hidden position-relative">
                        <div class="position-relative z-1">
                            <div class="stat-label text-white-50 mb-1">Celkové tržby</div>
                            <div class="stat-value text-white mb-0"><?php echo number_format($totalRevenue, 0, ',', ' '); ?> Kč</div>
                            <small class="text-white-50 mt-2 d-block">
                                <i class="fas fa-check-circle me-1"></i> Uhrazené objednávky
                            </small>
                        </div>
                        <div class="stat-icon-bg opacity-25">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                </div>

                <!-- Orders Card -->
                <div class="col-md-4">
                    <div class="kjd-card stat-card h-100 border-0 glass-panel">
                        <div>
                            <div class="stat-label mb-1">Objednávky</div>
                            <div class="stat-value"><?php echo $orderCount; ?></div>
                            <div class="mt-2">
                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2 py-1 me-1">
                                    <?php echo $pendingOrderCount ?? 0; ?> čeká
                                </span>
                                <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-2 py-1">
                                    <?php echo $completedOrderCount ?? 0; ?> hotovo
                                </span>
                            </div>
                        </div>
                        <div class="stat-icon bg-icon-orders">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                </div>

                <!-- Average Value / Products Mix -->
                <div class="col-md-4">
                    <div class="kjd-card stat-card h-100 border-0 glass-panel">
                        <div>
                            <div class="stat-label mb-1">Průměrná objednávka</div>
                            <div class="stat-value">
                                <?php echo ($orderCount > 0) ? number_format($totalRevenue / $completedOrderCount, 0, ',', ' ') : 0; ?> Kč
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <?php echo ($productCount + $product2Count); ?> aktivních produktů
                            </small>
                        </div>
                        <div class="stat-icon bg-icon-products">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Chart Section -->
                <div class="col-lg-8">
                    <div class="kjd-card h-100 border-0 shadow-sm p-4 glass-panel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Vývoj tržeb</h5>
                            <span class="badge bg-light text-muted">Posledních 10 objednávek</span>
                        </div>
                        <div style="height: 300px; width: 100%;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Notifications / Stock -->
                <div class="col-lg-4">
                    <div class="kjd-card h-100 border-0 shadow-sm p-0 overflow-hidden glass-panel">
                        <div class="p-4 border-bottom bg-light-subtle glass-panel">
                            <h5 class="fw-bold mb-0">Sklad a systém</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if ($notificationCount > 0): ?>
                                <a href="admin_manage_colors.php" class="list-group-item list-group-item-action p-3 d-flex align-items-center border-start border-4 border-danger">
                                    <div class="bg-danger-subtle text-danger rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-exclamation"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-danger">Chybí materiál</div>
                                        <div class="small text-muted"><?php echo $notificationCount; ?> variant potřebuje doplnit.</div>
                                    </div>
                                </a>
                            <?php else: ?>
                                <div class="list-group-item p-4 text-center text-muted border-0">
                                    <div class="mb-2"><i class="fas fa-check-circle fa-2x text-success opacity-25"></i></div>
                                    <p class="mb-0 small">Skladové zásoby jsou v pořádku.</p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="list-group-item p-3 d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-server"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Databáze</div>
                                    <div class="small text-muted">Připojeno (<?php echo count($tables); ?> tabulek)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Table -->
            <div class="kjd-card mt-4 border-0 shadow-sm overflow-hidden glass-panel">
                <div class="card-header border-bottom p-4 d-flex justify-content-between align-items-center glass-panel">
                    <h5 class="fw-bold mb-0">Nedávné objednávky</h5>
                    <a href="admin_orders.php" class="btn btn-sm btn-light fw-medium">Zobrazit vše</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 glass-table">
                        <thead class="text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Zákazník</th>
                                <th>Datum</th>
                                <th>Částka</th>
                                <th>Stav</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentOrders) > 0): ?>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark">#<?php echo $order['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3 bg-light text-secondary small fw-bold d-flex align-items-center justify-content-center rounded-circle" style="width: 35px; height: 35px;">
                                                <?php echo substr($order['name'] ?? '?', 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="fw-medium text-dark"><?php echo htmlspecialchars($order['name'] ?? 'Neznámý'); ?></div>
                                                <div class="small text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($order['email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date('d.m.Y', strtotime($order['created_at'])); ?><br>
                                        <span class="text-muted opacity-75"><?php echo date('H:i', strtotime($order['created_at'])); ?></span>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        <?php echo number_format($order['total_price'] ?? 0, 0, ',', ' '); ?> Kč
                                    </td>
                                    <td>
                                        <?php 
                                            $statusClass = 'bg-secondary-subtle text-secondary';
                                            $statusText = $order['status'] ?? 'Neznámý';
                                            $paymentStatus = $order['payment_status'] ?? 'pending';
                                            
                                            // Jednoduchá logika pro badge
                                            if ($paymentStatus == 'paid') { 
                                                $statusClass = 'bg-success-subtle text-success'; 
                                                $statusText = 'Zaplaceno'; 
                                            } elseif ($paymentStatus == 'pending') { 
                                                $statusClass = 'bg-warning-subtle text-warning-emphasis'; 
                                                $statusText = 'Čeká na platbu'; 
                                            } elseif ($statusText == 'cancelled') { 
                                                $statusClass = 'bg-danger-subtle text-danger'; 
                                                $statusText = 'Zrušeno'; 
                                            }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> rounded-pill fw-normal px-3 py-2">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light border-0 text-muted hover-dark">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">Zatím žádné objednávky.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Mobile Sidebar Offcanvas (Keep existing or update) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header bg-dark text-white">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0 bg-dark">
        <?php include 'admin_sidebar_v2.php'; ?>
    </div>
</div>

<!-- Chart JS Init -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        // Data from PHP
        const orderData = <?php echo json_encode(array_reverse($recentOrders)); ?>;
        
        // Prepare chart data
        const labels = orderData.map(o => {
            const date = new Date(o.created_at);
            return date.getDate() + '.' + (date.getMonth() + 1) + '.';
        });
        const dataPoints = orderData.map(o => o.total_price);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Hodnota objednávky (Kč)',
                    data: dataPoints,
                    borderColor: '#8A6240',
                    backgroundColor: (context) => {
                        const ctx = context.chart.ctx;
                        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(138, 98, 64, 0.2)');
                        gradient.addColorStop(1, 'rgba(138, 98, 64, 0.0)');
                        return gradient;
                    },
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#8A6240',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#8A6240',
                    pointHoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#102820',
                        titleColor: '#CABA9C',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' Kč';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4], color: '#f0f0f0' },
                        ticks: { 
                            font: { family: "'SF Pro Display', sans-serif", size: 11 },
                            callback: function(value) { return value + ' Kč'; }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "'SF Pro Display', sans-serif", size: 11 } }
                    }
                }
            }
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
