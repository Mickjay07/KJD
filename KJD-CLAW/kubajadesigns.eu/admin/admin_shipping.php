<?php
session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config.php';
require_once '../packeta_api.php';

$packeta = new PacketaAPI();
$successMessage = '';
$errorMessage = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_packet') {
        $orderId = (int)$_POST['order_id'];
        
        // Get order details
        $stmt = $conn->prepare("SELECT o.* FROM orders o WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order && $order['delivery_method'] === 'Zásilkovna') {
            // Parse name
            $nameParts = explode(' ', $order['name'], 2);
            $name = $nameParts[0] ?? '';
            $surname = $nameParts[1] ?? $nameParts[0];
            
            // Create packet
            $packetData = [
                'order_number' => 'KJD-' . $order['id'],
                'name' => $name,
                'surname' => $surname,
                'email' => $order['email'],
                'phone' => $order['phone'] ?? $order['phone_number'] ?? '',
                'branch_id' => $order['packeta_branch_id'] ?? $order['zasilkovna_branch_id'] ?? '',
                'value' => $order['total_price'],
                'weight' => 1,
                'cod' => $order['payment_method'] === 'dobírka' ? $order['total_price'] : 0
            ];
            
            // Validate branch ID
            if (empty($packetData['branch_id'])) {
                $errorMessage = "Nelze vytvořit zásilku: Chybí ID pobočky (Zásilkovna).";
                // DEBUG: Show packet data
                if (isset($_GET['debug'])) {
                    $errorMessage .= " DATA: " . print_r($packetData, true);
                }
            } else {
                $result = $packeta->createPacket($packetData);
                
                // DEBUG: Force output of result
                if (isset($_GET['debug'])) {
                    echo "<pre>DEBUG RESULT:\n";
                    var_dump($result);
                    echo "</pre>";
                }
                
                if ($result['success']) {
                    $trackingUrl = $packeta->getTrackingUrl($result['barcode']);
                    
                    $updateStmt = $conn->prepare("
                        UPDATE orders 
                        SET packeta_packet_id = ?, packeta_barcode = ?, packeta_tracking_url = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$result['packet_id'], $result['barcode'], $trackingUrl, $orderId]);
                    
                    $successMessage = "Zásilka byla úspěšně vytvořena! ID: " . $result['packet_id'];
                } else {
                    $errorMessage = "Chyba při vytváření zásilky: " . ($result['message'] ?? 'Neznámá chyba');
                
                // VŽDY zobrazit raw response pro debugging, dokud to nevyřešíme
                if (isset($result['raw_response'])) {
                    $errorMessage .= " <br><small>API Raw: " . htmlspecialchars(substr($result['raw_response'], 0, 500)) . "</small>";
                } elseif (isset($result['error']) && is_array($result['error'])) {
                     $errorMessage .= " <br><small>API Error Array: " . print_r($result['error'], true) . "</small>";
                } else {
                     $errorMessage .= " <br><small>Full Result: " . print_r($result, true) . "</small>";
                }
                }
            }
        } else {
            $errorMessage = "Objednávka není pro Zásilkovnu.";
        }
    }
    
    if ($action === 'print_label') {
        $packetId = $_POST['packet_id'] ?? '';
        
        if ($packetId) {
            $result = $packeta->getPacketLabel($packetId);
            
            if ($result['success']) {
                $conn->prepare("UPDATE orders SET packeta_label_printed = 1 WHERE packeta_packet_id = ?")->execute([$packetId]);
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="zasilka_' . $packetId . '.pdf"');
                echo $result['pdf'];
                exit;
            } else {
                $errorMessage = "Chyba při tisku štítku: " . $result['error'];
            }
        }
    }
}

// Get orders for Zásilkovna
$stmt = $conn->prepare("
    SELECT o.* FROM orders o
    WHERE o.delivery_method = 'Zásilkovna'
    ORDER BY o.created_at DESC
    LIMIT 100
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa dopravy - KJD Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
      :root { 
        --kjd-dark-green:#102820; 
        --kjd-earth-green:#4c6444; 
        --kjd-gold-brown:#8A6240; 
        --kjd-dark-brown:#4D2D18; 
        --kjd-beige:#CABA9C; 
      }
      
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      .cart-page { background: #f8f9fa; min-height: 100vh; }
      
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
      
      .btn-kjd-primary {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: white;
        border: none;
        font-weight: 700;
        padding: 1rem 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(76,100,68,0.3);
        transition: all 0.3s ease;
      }
      
      .btn-kjd-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 25px rgba(76,100,68,0.4);
        color: white;
      }
      
      .btn-kjd-secondary {
        background: linear-gradient(135deg, #0dcaf0, #0aa2c0);
        color: white;
        border: none;
        font-weight: 700;
        padding: 1rem 2rem;
        border-radius: 12px;
        transition: all 0.3s ease;
      }
      
      .badge {
        padding: 0.5rem 1rem;
        font-weight: 700;
        border-radius: 8px;
      }
      
      .table {
        margin: 0;
      }
      
      .table thead th {
        background: var(--kjd-dark-green);
        color: white;
        font-weight: 700;
        padding: 1rem;
        border: none;
      }
      
      .table tbody td {
        padding: 1rem;
        vertical-align: middle;
      }
    </style>
</head>
<body class="cart-page">
    <?php include '../includes/icons.php'; ?>
    
    <div class="preloader-wrapper">
      <div class="preloader"></div>
    </div>

    <?php include 'admin_sidebar.php'; ?>

    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-truck me-3"></i>Správa dopravy - Zásilkovna</h1>
                    <p>Vytváření zásilek a tisk štítků pro Zásilkovnu</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show cart-item">
                        <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show cart-item">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="cart-item">
                    <h2 class="cart-product-name mb-3"><i class="fas fa-list me-2"></i>Objednávky Zásilkovna</h2>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Zákazník</th>
                                    <th>Pobočka</th>
                                    <th>Cena</th>
                                    <th>Stav</th>
                                    <th>Tracking</th>
                                    <th>Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['id'] ?></strong></td>
                                    <td>
                                        <strong><?= htmlspecialchars($order['name']) ?></strong><br>
                                        <small><?= htmlspecialchars($order['email']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($order['zasilkovna_name'] ?? 'N/A') ?>
                                        <br><small class="text-muted">ID: <?= htmlspecialchars($order['packeta_branch_id'] ?? 'N/A') ?></small>
                                    </td>
                                    <td><strong><?= number_format($order['total_price'], 0, ',', ' ') ?> Kč</strong></td>
                                    <td>
                                        <?php if ($order['packeta_packet_id']): ?>
                                            <?php if ($order['packeta_label_printed']): ?>
                                                <span class="badge bg-primary">Vytištěno</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Vytvořeno</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Čeká na vytvoření</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['packeta_barcode']): ?>
                                            <a href="<?= htmlspecialchars($order['packeta_tracking_url']) ?>" target="_blank" class="btn btn-sm btn-kjd-secondary">
                                                <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($order['packeta_barcode']) ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$order['packeta_packet_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="create_packet">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-kjd-primary">
                                                    <i class="fas fa-plus me-1"></i>Vytvořit zásilku
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="print_label">
                                                <input type="hidden" name="packet_id" value="<?= $order['packeta_packet_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-kjd-primary">
                                                    <i class="fas fa-print me-1"></i>Tisknout štítek
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
