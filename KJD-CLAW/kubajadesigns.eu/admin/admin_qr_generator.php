<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

$message = '';
$error = '';
$generated_qr = null;

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $order_id = trim($_POST['order_id'] ?? '');

        if ($order_id === '') {
            throw new Exception('Zadejte ID objednávky.');
        }

        // Ověření existence objednávky
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new Exception('Objednávka nebyla nalezena.');
        }

        // Získání všech produktů z objednávky
        $products_json = json_decode($order['products_json'], true) ?? [];
        if (empty($products_json)) {
            throw new Exception('V objednávce nejsou žádné produkty.');
        }

        // Připravíme dotazy
        $stmtProduct = $conn->prepare("SELECT * FROM product WHERE id = ?");
        $stmtInsert = $conn->prepare("INSERT INTO qr_codes (order_id, product_id, qr_code, token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token)");

        $generated_qrs = [];
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

        foreach ($products_json as $item) {
            $pid = $item['id'] ?? 0;
            if (!$pid) continue;
            
            $stmtProduct->execute([$pid]);
            $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);
            if (!$product) { continue; }

            // Unikátní token pro každý produkt
            $token = md5($order_id . '|' . $pid . '|' . ($order['email'] ?? '') . '|assembly_guide_salt');

            $qr_url = $scheme . $host . $base_path . '/assembly-guide.php?order=' . urlencode($order_id) . '&product=' . $pid . '&token=' . $token;
            $qr_code = 'QR_' . $order_id . '_' . $pid . '_' . time();

            $stmtInsert->execute([$order_id, $pid, $qr_code, $token]);

            $generated_qrs[] = [
                'url' => $qr_url,
                'qr_code' => $qr_code,
                'order' => $order,
                'product' => $product,
                'product_id' => $pid,
                'quantity' => $item['quantity'] ?? 1
            ];
        }

        if (empty($generated_qrs)) {
            throw new Exception('Nepodařilo se vygenerovat QR kódy pro produkty v objednávce.');
        }

        $message = 'QR kód(y) byly úspěšně vygenerovány pro všechny produkty v objednávce.';

    } catch (Exception $e) {
        $error = 'Chyba: ' . $e->getMessage();
    }
}

// Načtení posledních objednávek
$stmt = $conn->prepare("
    SELECT order_id, name, email, created_at, products_json 
    FROM orders 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Načtení všech již vygenerovaných tokenů (posledních 200)
$stmt = $conn->prepare("
    SELECT 
        qc.order_id,
        qc.product_id,
        qc.qr_code,
        qc.token,
        o.name AS customer_name,
        o.email AS customer_email,
        p.name AS product_name
    FROM qr_codes qc
    LEFT JOIN orders o ON o.order_id = qc.order_id
    LEFT JOIN product p ON p.id = qc.product_id
    ORDER BY qc.qr_code DESC
    LIMIT 200
");
$stmt->execute();
$qr_tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokumentační stránky - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // Base path for building local URLs when the site runs in a subdirectory
        window.__BASE_PATH = (function(){
            try { return <?php echo json_encode(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')); ?> || ''; } catch(e){ return ''; }
        })();
        // Fallback loader for QRCode library if primary CDN fails
        (function(){
            function loadScript(src, onload){
                var s = document.createElement('script');
                s.src = src; s.async = true; s.crossOrigin = 'anonymous';
                s.onload = onload; s.onerror = function(){ console.warn('[QRLoader] Failed to load', src); };
                document.head.appendChild(s);
            }
            function ensureQRCode(cb){
                if (window.QRCode && typeof window.QRCode.toCanvas === 'function') { cb(); return; }
                // Prefer local first to avoid CORS/CSP issues
                var localSrc = '/assets/js/qrcode.min.js';
                loadScript(localSrc, function(){
                    if (window.QRCode && typeof window.QRCode.toCanvas === 'function') { cb(); return; }
                    console.warn('[QRLoader] Local file not available, trying CDNs');
                    // As a backup (may be blocked by CORS/CSP)
                    loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js', function(){
                        if (window.QRCode && typeof window.QRCode.toCanvas === 'function') { cb(); return; }
                        loadScript('https://unpkg.com/qrcode@1.5.3/build/qrcode.min.js', function(){ cb(); });
                    });
                });
            }
            window.__ensureQRCode = ensureQRCode;
        })();
    </script>
    <link rel="stylesheet" href="admin_clean_styles.css?v=<?php echo time(); ?>">
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
      
      .order-card {
        border: 2px solid var(--kjd-earth-green);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #fff;
      }
      
      .order-card:hover {
        background-color: #f8f9fa;
        border-color: var(--kjd-beige);
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      
      .order-card.selected {
        background-color: #f0f8ff;
        border-color: var(--kjd-beige);
      }

      .card {
        border: 2px solid var(--kjd-earth-green);
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        background: #fff;
      }

      .card-header {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        border-bottom: 2px solid var(--kjd-earth-green);
        border-radius: 14px 14px 0 0;
        color: var(--kjd-dark-green);
        font-weight: 600;
      }

      .btn-primary {
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
        color: #fff;
        border: none;
        padding: 0.6rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s ease;
      }

      .btn-primary:hover {
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
        color: #fff;
        transform: translateY(-1px);
      }

      .btn-success {
        background: linear-gradient(135deg, var(--kjd-earth-green), #6b8e6b);
        border: none;
        border-radius: 8px;
        font-weight: 600;
      }

      .btn-outline-primary {
        border: 2px solid var(--kjd-earth-green);
        color: var(--kjd-earth-green);
        border-radius: 8px;
        font-weight: 600;
      }

      .btn-outline-primary:hover {
        background: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
        color: #fff;
      }

      .btn-outline-secondary {
        border: 2px solid var(--kjd-gold-brown);
        color: var(--kjd-gold-brown);
        border-radius: 8px;
      }

      .btn-outline-secondary:hover {
        background: var(--kjd-gold-brown);
        border-color: var(--kjd-gold-brown);
        color: #fff;
      }

      .form-control {
        border: 2px solid var(--kjd-earth-green);
        border-radius: 8px;
        padding: 0.75rem;
        transition: all 0.2s ease;
      }

      .form-control:focus {
        border-color: var(--kjd-earth-green);
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }

      .alert-success {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        border: 2px solid #4caf50;
        border-radius: 8px;
        color: #2e7d32;
      }

      .alert-danger {
        background: linear-gradient(135deg, #ffebee, #ffcdd2);
        border: 2px solid #f44336;
        border-radius: 8px;
        color: #c62828;
      }

      .badge {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        color: var(--kjd-dark-green);
        border-radius: 8px;
        font-weight: 600;
      }

      .text-success {
        color: var(--kjd-earth-green) !important;
      }

      .text-muted {
        color: #8A6240 !important;
      }

      .page-title {
        color: var(--kjd-dark-green);
        font-weight: 700;
        margin-bottom: 1.5rem;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 15px;
      }
      
      .qr-result {
        background: #fff;
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        margin-top: 1.5rem;
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .qr-canvas {
        margin: 1rem 0;
        border: 2px solid var(--kjd-beige);
        border-radius: 12px;
        display: inline-block;
        box-shadow: 0 4px 15px rgba(16,40,32,0.1);
      }

      /* Mobile responsive */
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
                                <i class="fas fa-file-text me-2"></i>Vytvoření dokumentačních stránek
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">QR kódy pro assembly-guide.php</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin.php" class="btn btn-kjd-primary d-flex align-items-center">
                                <i class="fas fa-arrow-left me-2"></i>Zpět na admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="cart-item">
                    <h3 class="cart-product-name mb-3">
                        <i class="fas fa-plus-circle me-2"></i>Vytvořit dokumentační stránku
                    </h3>
                    <p class="text-muted mb-3">
                        <i class="fas fa-info-circle"></i> 
                        Vygeneruje QR kódy a odkazy na <strong>assembly-guide.php</strong> pro každý produkt v objednávce. 
                        Zákazníci pak mohou snadno přistupovat k dokumentaci svých lamp.
                    </p>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">ID objednávky *</label>
                                        <input type="text" name="order_id" class="form-control" required 
                                               placeholder="např. ORD_20241220_001">
                                        <small class="text-muted">Vyberte z posledních objednávek níže nebo zadejte ručně. QR kódy se vygenerují automaticky pro všechny produkty v objednávce.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-kjd-primary">
                                <i class="fas fa-qrcode"></i> Vytvořit dokumentační stránku
                            </button>
                        </form>
                </div>

                <!-- Výsledek QR kódů -->
                <?php if (!empty($generated_qrs)): ?>
                    <div class="cart-item">
                        <h3 class="cart-product-name mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>QR kódy byly vygenerovány
                        </h3>

                        <?php foreach ($generated_qrs as $g): ?>
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6>Informace o objednávce:</h6>
                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($g['order']['order_id']); ?></p>
                                    <p><strong>Zákazník:</strong> <?php echo htmlspecialchars($g['order']['name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($g['order']['email']); ?></p>

                                    <h6 class="mt-3">Produkt:</h6>
                                    <p><strong><?php echo htmlspecialchars($g['product']['name']); ?></strong></p>
                                    <p><?php echo htmlspecialchars($g['product']['category_name'] ?? 'Lampa'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>QR kód:</h6>
                                    <?php $canvasId = 'qrCanvas_' . (int)$g['product_id']; ?>
                                    <canvas id="<?php echo $canvasId; ?>" class="qr-canvas"></canvas>
                                    <br>
                                    <button class="btn btn-success btn-sm mt-2" onclick="downloadQRById('<?php echo $canvasId; ?>','QR_<?php echo $g['order']['order_id'] . '_' . (int)$g['product_id']; ?>.png')">
                                        <i class="fas fa-download"></i> Stáhnout QR kód
                                    </button>
                                    <div class="mt-3">
                                        <a class="btn btn-outline-primary btn-sm" href="track_order.php?order_id=<?php echo urlencode($g['order']['order_id']); ?>" target="_blank">
                                            <i class="fas fa-location-dot"></i> Sledovat objednávku
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <h6><i class="fas fa-link"></i> Odkaz pro zákazníka:</h6>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($g['url']); ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling.value)"><i class="fas fa-copy"></i> Kopírovat</button>
                                    <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($g['url']); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Otevřít</a>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    <i class="fas fa-info-circle"></i> Tento odkaz můžete poslat zákazníkovi - vede přímo na assembly-guide.php s jeho objednávkou
                                </small>
                            </div>
                            <hr>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <div class="cart-item">
                    <h3 class="cart-product-name mb-3">
                        <i class="fas fa-clock me-2"></i>Poslední objednávky
                    </h3>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-card" onclick="selectOrder('<?php echo htmlspecialchars($order['order_id']); ?>')">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($order['order_id']); ?></strong>
                                    <small><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></small>
                                </div>
                                <div><?php echo htmlspecialchars($order['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectOrder(orderId) {
            document.querySelector('input[name="order_id"]').value = orderId;
            
            // Vizuální označení vybrané objednávky
            document.querySelectorAll('.order-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }

        <?php if (!empty($generated_qrs)): ?>
            // Generování více QR kódů – zajistit načtení knihovny s fallbacky a pak vykreslit
            (function() {
                const items = <?php echo json_encode(array_map(function($g){
                    return [
                        'canvasId' => 'qrCanvas_' . (int)$g['product_id'],
                        'url' => $g['url']
                    ];
                }, $generated_qrs)); ?>;

                function drawAll() {
                    try {
                        if (!window.QRCode || typeof window.QRCode.toCanvas !== 'function') {
                            throw new Error('Knihovna QRCode nebyla načtena.');
                        }
                        items.forEach(function(it){
                            const canvas = document.getElementById(it.canvasId);
                            if (!canvas) return;
                            window.QRCode.toCanvas(canvas, it.url, {
                                width: 200,
                                height: 200,
                                margin: 2,
                                color: { dark: '#4D2D18', light: '#FFFFFF' }
                            });
                        });
                    } catch (e) {
                        console.error('Chyba při vykreslení QR:', e);
                    }
                }
                function start() {
                    var before = !!(window.QRCode && window.QRCode.toCanvas);
                    (window.__ensureQRCode || function(cb){cb();})(function(){
                        var after = !!(window.QRCode && window.QRCode.toCanvas);
                        console.log('[QRLoader] QRCode available before:', before, 'after:', after);
                        drawAll();
                    });
                }
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
            })();
        <?php endif; ?>

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('URL zkopírována do schránky!');
            });
        }

        function downloadQRById(canvasId, filename) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const link = document.createElement('a');
            link.download = filename || 'qr.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        }
    </script>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="cart-item">
                <h3 class="cart-product-name mb-3">
                    <i class="fas fa-list me-2"></i>Vygenerované tokeny
                </h3>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Objednávka</th>
                                <th>Zákazník</th>
                                <th>Produkt</th>
                                <th>Token</th>
                                <th>Odkaz</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                            $host = $_SERVER['HTTP_HOST'];
                            $base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                            foreach ($qr_tokens as $row): 
                                $url = $scheme . $host . $base_path . '/assembly-guide.php?order=' . urlencode($row['order_id']) . '&product=' . (int)$row['product_id'] . '&token=' . urlencode($row['token']);
                            ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($row['order_id']); ?></code></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['customer_name'] ?: ''); ?>
                                        <?php if (!empty($row['customer_email'])): ?>
                                            <div class="text-muted" style="font-size: .85rem;"><?php echo htmlspecialchars($row['customer_email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['product_name'] ?: ('ID ' . (int)$row['product_id'])); ?></td>
                                    <td><code style="user-select: all;"><?php echo htmlspecialchars($row['token']); ?></code></td>
                                    <td style="max-width: 320px;">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($url); ?>" readonly title="Odkaz na assembly-guide.php">
                                            <button class="btn btn-outline-secondary" onclick="copyToClipboard(this.previousElementSibling.value)" title="Kopírovat odkaz"><i class="fas fa-copy"></i></button>
                                            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" title="Otevřít assembly-guide.php"><i class="fas fa-external-link-alt"></i></a>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-file-alt"></i> assembly-guide.php
                                        </small>
                                    </td>
                                    <td class="text-end"><span class="badge bg-light text-dark">QR: <?php echo htmlspecialchars($row['qr_code']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($qr_tokens)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Zatím nebyly vygenerovány žádné tokeny.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
