<?php
require_once 'config.php';

// Získání parametrů z URL
$order_id = isset($_GET['order']) ? $_GET['order'] : (isset($_GET['order_id']) ? $_GET['order_id'] : '');
$product_id = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';
$admin_preview = isset($_GET['admin_preview']) && $_GET['admin_preview'] == '1';

$error_message = '';
$order = null;
$product = null; // kept for backward compatibility but not required
$lamps = [];

try {
    if (empty($order_id)) {
        throw new Exception('Chybí číslo objednávky.');
    }

    // Ověření tokenu a načtení objednávky
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Objednávka nebyla nalezena.');
    }

    if (!$admin_preview) {
        // Podpora nového per‑produkt tokenu i starého globálního tokenu (zpětná kompatibilita)
        $isValid = false;
        $old_expected_token = md5($order['order_id'] . $order['email'] . 'assembly_guide_salt');

        if ($product_id > 0) {
            // Primárně ověř per‑produkt token v tabulce qr_codes
            $stmt = $conn->prepare("SELECT 1 FROM qr_codes WHERE order_id = ? AND product_id = ? AND token = ? LIMIT 1");
            $stmt->execute([$order_id, $product_id, $token]);
            $isValid = (bool)$stmt->fetchColumn();
            if (!$isValid) {
                // Fallback: starý token bez ohledu na produkt
                $isValid = hash_equals($old_expected_token, $token);
            }
        } else {
            // Pokud není specifikován produkt, zkusíme ho odvodit z qr_codes dle tokenu
            $stmt = $conn->prepare("SELECT product_id FROM qr_codes WHERE order_id = ? AND token = ? LIMIT 1");
            $stmt->execute([$order_id, $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['product_id'])) {
                $product_id = (int)$row['product_id'];
                $isValid = true;
            } else {
                // Fallback: ověř starý token
                $isValid = hash_equals($old_expected_token, $token);
            }
        }

        if (!$isValid) {
            throw new Exception('Neplatný odkaz nebo token.');
        }
    }

    // Načtení všech lamp z objednávky + název/obrázek produktu
    $stmt = $conn->prepare("SELECT l.*, p.name AS product_name, p.image_url AS product_image FROM lamps l LEFT JOIN product p ON p.id = l.product_id WHERE l.order_id = ? ORDER BY p.name, l.serial_number");
    $stmt->execute([$order_id]);
    $lamps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Volitelné: načtení CE souborů pro lampy, pokud tabulka existuje
    $lampIds = array_map(function($r){ return (int)$r['id']; }, $lamps);
    $ceFilesMap = [];
    if (!empty($lampIds)) {
        try {
            $in = implode(',', array_fill(0, count($lampIds), '?'));
            $stmt = $conn->prepare("SELECT lamp_id, ce_file_path FROM lamp_ce_files WHERE lamp_id IN ($in)");
            $stmt->execute($lampIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lid = (int)$row['lamp_id'];
                if (!isset($ceFilesMap[$lid])) $ceFilesMap[$lid] = [];
                $ceFilesMap[$lid][] = $row['ce_file_path'];
            }
        } catch (Exception $e2) {
            // Pokud tabulka neexistuje, prostě CE soubory nezobrazíme
            $ceFilesMap = [];
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// URL k PDF manuálu (bez diakritiky v názvu)
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$pdf_manual_url = $base_path . '/CE/SHROOM/Navod-k-pouziti.pdf';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objednávka #<?php echo htmlspecialchars($order_id ?: ''); ?> – Přehled lamp</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">
    
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      
      /* Apple SF Pro Font */
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      /* Fix for header icons - make them properly round */
      .rounded-circle {
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 48px !important;
        height: 48px !important;
      }
      
      /* Ensure SVG icons are visible */
      svg {
        width: 24px !important;
        height: 24px !important;
        fill: currentColor !important;
      }
      
      /* Cart specific styles */
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
      }
      .cart-header p { 
        font-size: 1.1rem; 
        font-weight: 500;
        opacity: 0.8;
      }
      .cart-item { 
        background: #fff; 
        border-radius: 16px; 
        padding: 2rem; 
        margin-bottom: 1.5rem; 
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 1px solid rgba(202,186,156,0.2);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      .cart-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      
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
      
      .btn-outline-primary {
        border-color: var(--kjd-earth-green);
        color: var(--kjd-earth-green);
      }
      
      .btn-outline-primary:hover {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
        color: #fff;
      }
      
      .btn-outline-secondary {
        border-color: var(--kjd-gold-brown);
        color: var(--kjd-gold-brown);
      }
      
      .btn-outline-secondary:hover {
        background-color: var(--kjd-gold-brown);
        border-color: var(--kjd-gold-brown);
        color: #fff;
      }
      
      /* Cart summary styles from cart.php */
      .cart-summary {
        background: #fff;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 1px solid rgba(202,186,156,0.2);
        margin-bottom: 2rem;
      }
      
      .summary-title {
        color: var(--kjd-dark-green);
        font-weight: 700;
        margin-bottom: 1.5rem;
        font-size: 1.25rem;
        border-bottom: 2px solid var(--kjd-beige);
        padding-bottom: 0.75rem;
      }
      
      .summary-row { 
        display: flex; 
        justify-content: space-between; 
        margin-bottom: 0.75rem;
        padding: 0.75rem 1rem;
        background: rgba(202,186,156,0.1);
        border-radius: 8px;
        font-weight: 600;
        font-size: 1rem;
        color: var(--kjd-dark-green);
        border: none;
      }
      
      .summary-total {
        display: flex;
        justify-content: space-between;
        margin-top: 1rem;
        padding: 1rem;
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        border-radius: 12px;
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--kjd-dark-green);
        border: 2px solid var(--kjd-earth-green);
      }
    </style>
</head>
<body class="cart-page">

    <?php include 'includes/icons.php'; ?>

    <?php include 'includes/navbar.php'; ?>

    <!-- Assembly Guide Header -->
    <div class="cart-header">
      <div class="container-fluid">
        <div class="row">
            <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                  <i class="fas fa-lightbulb me-2"></i>Dokumentace lamp
                </h1>
                <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Přehled lamp a dokumentace k vaší objednávce</p>
              </div>
              <a href="index.php" class="btn btn-kjd-secondary d-flex align-items-center">
                <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                Zpět na hlavní stránku
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
                
    <!-- Main Content -->
    <div class="container-fluid">
      <div class="row">
        <?php if ($order && !$error_message): ?>
            <div class="col-lg-8">
                <!-- Order Info Card -->
                <div class="cart-item">
                    <h3 class="mb-3" style="color: var(--kjd-dark-green); font-weight: 700;"><i class="fas fa-user me-2"></i> Údaje o objednávce</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Číslo objednávky:</strong> #<?php echo htmlspecialchars($order['order_id']); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($order['created_at'])): ?>
                        <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Datum vytvoření:</strong> <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a class="btn btn-outline-primary" href="track_order.php?order_id=<?php echo urlencode($order['order_id']); ?>" target="_blank">
                            <i class="fas fa-location-dot"></i> Sledovat objednávku
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Empty sidebar matching cart.php structure -->
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="col-12">
                <div class="cart-item text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #dc3545;"></i>
                    </div>
                    <h2 style="color: var(--kjd-dark-green); margin-bottom: 1rem;">Chyba</h2>
                    <p style="color: #666; font-size: 1.1rem;"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        <?php else: ?>
            <?php
            // Compute order total from products_json if available
            $order_total = null;
            if (!empty($order['products_json'])) {
                $decoded = json_decode($order['products_json'], true);
                if (is_array($decoded)) {
                    $sum = 0.0;
                    foreach ($decoded as $it) {
                        if (is_array($it)) {
                            if (isset($it['total'])) {
                                $sum += (float)$it['total'];
                            } elseif (isset($it['line_total'])) {
                                $sum += (float)$it['line_total'];
                            } elseif (isset($it['price_total'])) {
                                $sum += (float)$it['price_total'];
                            } else {
                                $price = isset($it['price']) ? (float)$it['price'] : 0.0;
                                $qty = isset($it['quantity']) ? (int)$it['quantity'] : (isset($it['qty']) ? (int)$it['qty'] : 1);
                                $sum += $price * $qty;
                            }
                        }
                    }
                    $order_total = $sum;
                }
            }
            ?>
            <div class="col-lg-8">
                <!-- Lamps Section -->
                <div class="cart-item">
                    <h3 class="mb-3" style="color: var(--kjd-dark-green); font-weight: 700;"><i class="fas fa-receipt me-2"></i> Údaje o objednávce</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Jméno:</strong> <?php echo htmlspecialchars($order['name'] ?? ''); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($order['email'])): ?>
                        <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">E‑mail:</strong> <?php echo htmlspecialchars($order['email']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['phone'])): ?>
                        <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Telefon:</strong> <?php echo htmlspecialchars($order['phone']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($order_total !== null): ?>
                        <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Celková částka:</strong> <?php echo number_format($order_total, 2, ',', ' '); ?> Kč</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <div class="col-md-6">
                            <div class="p-3" style="background: rgba(202,186,156,0.1); border-radius: 8px;">
                            <?php
                            $deliveryMethod = $order['delivery_method'] ?? ($order['delivery'] ?? '');
                            $addressParts = [];
                            foreach (['delivery_address','address','dress'] as $k) { if (!empty($order[$k])) $addressParts[] = $order[$k]; }
                            foreach (['city','postal_code','zip','country'] as $k) { if (!empty($order[$k])) $addressParts[] = $order[$k]; }
                            $addressStr = trim(implode(', ', array_filter(array_map('trim', $addressParts))));
                            ?>
                            <?php if (!empty($deliveryMethod)): ?>
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Doprava:</strong> <?php echo htmlspecialchars($deliveryMethod); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($addressStr)): ?>
                                <div class="mb-2"><strong style="color: var(--kjd-dark-green);">Adresa:</strong> <?php echo htmlspecialchars($addressStr); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Přehled lamp v objednávce -->
            <div class="cart-item">
                <h2 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1rem;"><i class="fas fa-lightbulb me-2"></i> Lampy v objednávce</h2>
                <p style="color: var(--kjd-gold-brown); font-size: 1.1rem; margin-bottom: 2rem;">
                    Níže najdete všechny lampy přiřazené k této objednávce včetně sériových čísel, materiálu a dokumentace.
                </p>

                <?php if (empty($lamps)): ?>
                    <div class="p-4 text-center" style="background: rgba(255,193,7,0.1); border: 2px solid #ffc107; border-radius: 12px;">
                        <div class="mb-3">
                            <i class="fas fa-info-circle" style="font-size: 2rem; color: #ffc107;"></i>
                        </div>
                        <h4 style="color: var(--kjd-dark-green); font-weight: 600; margin-bottom: 1rem;">V této objednávce zatím nejsou žádné lampy</h4>
                        <p style="color: #666;">Jakmile lampy vytvoříme a přiřadíme, uvidíte je zde včetně jejich dokumentace.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    // Seskupit dle produktu
                    $byProduct = [];
                    foreach ($lamps as $l) {
                        $key = $l['product_id'] . '|' . ($l['product_name'] ?? $l['name'] ?? 'Produkt');
                        if (!isset($byProduct[$key])) $byProduct[$key] = ['meta' => $l, 'items' => []];
                        $byProduct[$key]['items'][] = $l;
                    }
                    ?>
                    <?php foreach ($byProduct as $key => $group): 
                        $meta = $group['meta'];
                        $items = $group['items'];
                        $pName = $meta['product_name'] ?? $meta['name'] ?? 'Produkt';
                        $pImage = $meta['product_image'] ?? '';
                    ?>
                        <div class="cart-item">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <?php if (!empty($pImage)):
                                    $images = explode(',', $pImage);
                                    $firstImage = trim($images[0]);
                                ?>
                                    <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($pName) ?>" style="width:90px;height:90px; object-fit: cover; border-radius: 12px; border: 2px solid var(--kjd-beige);">
                                <?php endif; ?>
                                <div>
                                    <h4 class="m-0" style="color: var(--kjd-dark-green); font-weight: 700;"><?= htmlspecialchars($pName) ?></h4>
                                    <div style="color: var(--kjd-gold-brown); font-weight: 600;">Souhrn přiřazených lamp</div>
                                </div>
                            </div>

                            <?php foreach ($items as $row): 
                                $lid = (int)$row['id'];
                                $files = $ceFilesMap[$lid] ?? [];
                            ?>
                                <div class="summary-row">
                                    <span><strong>Sériové číslo:</strong> <code><?= htmlspecialchars($row['serial_number'] ?? '') ?></code></span>
                                    <span><?= htmlspecialchars($row['material'] ?? '') ?></span>
                                </div>
                                <div class="summary-row">
                                    <span><strong>Datum výroby:</strong> <?= !empty($row['date_produced']) ? date('d.m.Y', strtotime($row['date_produced'])) : 'N/A' ?></span>
                                    <span>
                                        <?php if (!empty($files)): ?>
                                            <?php foreach ($files as $f): ?>
                                                <a href="<?= htmlspecialchars($f) ?>" class="btn btn-outline-secondary btn-sm me-1" target="_blank"><i class="fas fa-file"></i> Otevřít</a>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Žádné soubory</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="cart-summary">
                    <h4 class="summary-title">Souhrn objednávky</h4>
                    
                    <div class="summary-row">
                        <span>Číslo objednávky:</span>
                        <span>#<?php echo htmlspecialchars($order['order_id']); ?></span>
                    </div>
                    
                    <?php if ($order_total !== null): ?>
                    <div class="summary-row">
                        <span>Celková částka:</span>
                        <span><?= number_format($order_total, 0, ',', ' ') ?> Kč</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-row">
                        <span>Stav:</span>
                        <span style="color: var(--kjd-earth-green); font-weight: 600;">Vytvořeno</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Počet lamp:</span>
                        <span><?= count($lamps) ?></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Dokumentace:</span>
                        <span>Dostupná</span>
                    </div>
                    
                    <a href="track_order.php?order_id=<?php echo urlencode($order['order_id']); ?>" class="btn btn-kjd-primary w-100 mb-3" target="_blank">
                        <i class="fas fa-location-dot me-2"></i>Sledovat objednávku
                    </a>
                    
                    <a href="index.php" class="btn btn-kjd-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i>Zpět na hlavní stránku
                    </a>
                </div>
                
                <!-- Podpora -->
                <div class="cart-item text-center mt-3">
                    <h5 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1rem;">Potřebujete pomoc?</h5>
                    
                    <div class="summary-row">
                        <span><i class="fas fa-envelope me-2"></i>Email:</span>
                        <span style="color: var(--kjd-earth-green); font-weight: 600;">info@kubajadesigns.eu</span>
                    </div>
                    
                    <div class="summary-row">
                        <span><i class="fas fa-phone me-2"></i>Telefon:</span>
                        <span style="color: var(--kjd-earth-green); font-weight: 600;">+420 722 341 256</span>
                    </div>
                    
                    <div class="summary-row">
                        <span><i class="fas fa-clock me-2"></i>Otevírací doba:</span>
                        <span style="color: var(--kjd-earth-green); font-weight: 600;">Po-Pá 9:00-17:00</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div>
      </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
