<?php
require_once 'config.php';
session_start();

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Ensure lamps has product_id and order_id (safe migration attempt)
try {
    $conn->exec("ALTER TABLE lamps ADD COLUMN product_id INT NULL");
} catch (Exception $e) { /* ignore if exists */ }
try {
    $conn->exec("ALTER TABLE lamps ADD COLUMN order_id VARCHAR(64) NULL");
} catch (Exception $e) { /* ignore if exists */ }
// Ensure CE mapping table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS lamp_ce_files (
        lamp_id INT NOT NULL,
        ce_file_path VARCHAR(512) NOT NULL,
        PRIMARY KEY (lamp_id, ce_file_path)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

// Handle saving CE file selections for a lamp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_ce_files') {
    try {
        $lampId = isset($_POST['lamp_id']) ? (int)$_POST['lamp_id'] : 0;
        $selected = isset($_POST['selected']) && is_array($_POST['selected']) ? $_POST['selected'] : [];
        if ($lampId <= 0) { throw new Exception('Chybí ID lampy.'); }

        // Ensure lamp exists
        $st = $conn->prepare('SELECT id FROM lamps WHERE id = ?');
        $st->execute([$lampId]);
        if (!$st->fetchColumn()) { throw new Exception('Lampa nebyla nalezena.'); }

        // Normalize and restrict files to /CE directory
        $ce_root = realpath(__DIR__ . '/CE');
        if ($ce_root === false || !is_dir($ce_root)) { throw new Exception('Složka CE nebyla nalezena.'); }
        $clean = [];
        foreach ($selected as $p) {
            $p = trim((string)$p);
            if ($p === '' || substr($p, 0, 3) !== '/CE') { continue; }
            // Build absolute path and confirm within CE root
            $rel = ltrim(substr($p, 3), '/');
            $abs = realpath($ce_root . DIRECTORY_SEPARATOR . $rel);
            if ($abs === false) { continue; }
            if (strpos($abs, $ce_root) !== 0) { continue; }
            $clean[] = '/CE/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
        }

        // Replace mappings transactionally
        $conn->beginTransaction();
        $del = $conn->prepare('DELETE FROM lamp_ce_files WHERE lamp_id = ?');
        $del->execute([$lampId]);
        if (!empty($clean)) {
            $ins = $conn->prepare('INSERT INTO lamp_ce_files (lamp_id, ce_file_path) VALUES (?, ?)');
            foreach ($clean as $path) {
                $ins->execute([$lampId, $path]);
            }
        }
        $conn->commit();

        $success = 'CE soubory byly uloženy.';
        // Refresh selections for client-side prefill on next render
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $error = $e->getMessage();
    }
}

$success = '';
$error = '';

function generate_serial()
{
    return 'KJD-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

// Handle creation of a new lamp unit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_lamp') {
    try {
        $orderId = trim($_POST['order_id'] ?? '');
        $groups = $_POST['groups'] ?? null; // expected structure: groups[g][product_id], groups[g][serial_number][], groups[g][material][]
        $dateProduced = trim($_POST['date_produced'] ?? '');
        if ($dateProduced === '') { $dateProduced = date('Y-m-d'); }
        // simple YYYY-MM-DD validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateProduced)) { throw new Exception('Neplatné datum výroby.'); }

        // Basic validations
        if ($orderId === '') {
            throw new Exception('Vyberte nebo zadejte ID objednávky.');
        }
        $toInsert = [];
        if (is_array($groups)) {
            foreach ($groups as $g) {
                $productId = isset($g['product_id']) ? (int)$g['product_id'] : 0;
                if ($productId <= 0) { throw new Exception('Vyberte lampu (produkt) pro všechny skupiny.'); }
                $sns = $g['serial_number'] ?? [];
                $mats = $g['material'] ?? [];
                $localPairs = [];
                foreach ($sns as $i => $sn) {
                    $sn = trim($sn);
                    $mat = isset($mats[$i]) ? trim($mats[$i]) : '';
                    if ($sn !== '') { $localPairs[] = [$sn, $mat, $productId]; }
                }
                if (!empty($localPairs)) { $toInsert = array_merge($toInsert, $localPairs); }
            }
        } else {
            // Fallback: single product + multiple serials (previous behavior)
            $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
            if ($productId <= 0) { throw new Exception('Vyberte lampu (produkt).'); }
            $serialInput = $_POST['serial_number'] ?? '';
            $materialInput = $_POST['material'] ?? '';
            $serials = is_array($serialInput) ? array_map('trim', $serialInput) : [trim($serialInput)];
            $materials = is_array($materialInput) ? array_map('trim', $materialInput) : [trim($materialInput)];
            foreach ($serials as $idx => $sn) {
                $sn = trim($sn);
                $mat = $materials[$idx] ?? '';
                if ($sn !== '') { $toInsert[] = [$sn, trim($mat), $productId]; }
            }
        }
        if (empty($toInsert)) { throw new Exception('Zadejte alespoň jedno sériové číslo.'); }

        // Verify order exists
        $stmt = $conn->prepare('SELECT order_id FROM orders WHERE order_id = ?');
        $stmt->execute([$orderId]);
        if (!$stmt->fetchColumn()) {
            throw new Exception('Zadaná objednávka neexistuje.');
        }

        // Verify all products exist and build a set for quick validation + name map
        $productIds = array_values(array_unique(array_map(function($r){ return (int)$r[2]; }, $toInsert)));
        $productNameMap = [];
        if (!empty($productIds)) {
            $in = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $conn->prepare("SELECT id, name FROM product WHERE id IN ($in)");
            $stmt->execute($productIds);
            $foundRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $found = [];
            foreach ($foundRows as $row) {
                $pid = (int)$row['id'];
                $found[] = $pid;
                $productNameMap[$pid] = $row['name'];
            }
            $missing = array_diff($productIds, $found);
            if (!empty($missing)) { throw new Exception('Některé vybrané produkty neexistují.'); }
        }

        // Transactional insert of multiple lamps across groups
        $conn->beginTransaction();
        // Include required 'name' column (product name) and 'date_produced'
        $stmt = $conn->prepare('INSERT INTO lamps (serial_number, order_id, product_id, name, material, date_produced, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        foreach ($toInsert as [$sn, $mat, $pid]) {
            $pname = $productNameMap[(int)$pid] ?? ('Produkt #' . (int)$pid);
            $stmt->execute([$sn, $orderId, $pid, $pname, $mat, $dateProduced]);
        }
        $conn->commit();

        $success = 'Bylo vytvořeno ' . count($toInsert) . ' lamp a přiřazeno k objednávce.';
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $error = $e->getMessage();
    }
}

// Fetch lamps list
$stmt = $conn->prepare("SELECT l.*, p.name AS product_name FROM lamps l LEFT JOIN product p ON p.id = l.product_id ORDER BY l.id DESC LIMIT 200");
$stmt->execute();
$lamps = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent orders for selection
$stmt = $conn->prepare("SELECT order_id, name, email, created_at FROM orders ORDER BY created_at DESC LIMIT 50");
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch lamp products (collections-based query like in admin_qr_generator)
$stmt = $conn->prepare("
    SELECT p.id, p.name, GROUP_CONCAT(DISTINCT pcm.name SEPARATOR ', ') AS category_name
    FROM product p
    LEFT JOIN product_collection_items pci ON pci.product_id = p.id
    LEFT JOIN product_collections_main pcm ON pcm.id = pci.collection_id
    WHERE LOWER(pcm.name) LIKE '%lamp%' OR LOWER(pcm.name) LIKE '%lampa%'
    GROUP BY p.id, p.name
    ORDER BY p.name
");
$stmt->execute();
$lamp_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build CE files list from filesystem (under /CE)
$ce_files = [];
$ce_root = realpath(__DIR__ . '/CE');
if ($ce_root && is_dir($ce_root)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ce_root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['pdf','png','jpg','jpeg','gif','doc','docx'])) continue;
        $abs = $file->getPathname();
        $rel = str_replace($ce_root, '/CE', $abs);
        $ce_files[] = $rel;
    }
    sort($ce_files);
}

// Existing CE selections per lamp
$lamp_selections = [];
if (!empty($lamps)) {
    $ids = array_map(fn($r)=> (int)$r['id'], $lamps);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $conn->prepare("SELECT lamp_id, ce_file_path FROM lamp_ce_files WHERE lamp_id IN ($in)");
    $st->execute($ids);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $lid = (int)$r['lamp_id'];
        if (!isset($lamp_selections[$lid])) $lamp_selections[$lid] = [];
        $lamp_selections[$lid][] = $r['ce_file_path'];
    }
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa lamp - KJD Admin</title>
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
      
      .alert {
        border-radius: 12px;
        border: 2px solid;
        font-weight: 600;
      }
      
      .alert-success {
        background: rgba(40,167,69,0.1);
        border-color: #28a745;
        color: #155724;
      }
      
      .alert-danger {
        background: rgba(220,53,69,0.1);
        border-color: #dc3545;
        color: #721c24;
      }
      
      .btn-main { 
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
        color: #fff; 
        border: none;
        padding: 0.75rem 1.5rem; 
        border-radius: 12px; 
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(77,45,24,0.3);
      }
      
      .btn-main:hover { 
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(77,45,24,0.4);
      }
      
      /* Mobile Styles */
      @media (max-width: 768px) {
        .cart-header {
          padding: 2rem 0;
        }
        
        .cart-header h1 {
          font-size: 2rem;
        }
        
        .cart-item {
          padding: 1.5rem;
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
      }
    </style>
</head>
<body class="cart-page">
    <?php include 'admin_sidebar.php'; ?>

    <!-- Admin Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                                <i class="fas fa-lightbulb me-2"></i>Správa lamp
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Správa lamp a jejich CE souborů</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-kjd-primary" data-bs-toggle="modal" data-bs-target="#createLampModal">
                                <i class="fas fa-plus me-2"></i>Nová lampa
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="cart-item">
            <h3 class="cart-product-name mb-4">
                <i class="fas fa-list me-2"></i>Seznam lamp
            </h3>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sériové číslo</th>
                                <th>Objednávka</th>
                                <th>Produkt</th>
                                <th>Materiál</th>
                                <th>Vytvořeno</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lamps as $l): ?>
                                <tr>
                                    <td><?= (int)$l['id'] ?></td>
                                    <td><code><?= htmlspecialchars($l['serial_number']) ?></code></td>
                                    <td><?= htmlspecialchars($l['order_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($l['product_name'] ?? (isset($l['product_id']) ? ('ID ' . (int)$l['product_id']) : '')) ?></td>
                                    <td><?= htmlspecialchars($l['material'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($l['created_at'] ?? '') ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <?php if (!empty($l['order_id'])): ?>
                                            <a class="btn btn-kjd-secondary btn-sm" target="_blank" href="assembly-guide.php?order_id=<?= urlencode($l['order_id']) ?>&admin_preview=1">
                                                <i class="fas fa-eye"></i> Zobrazit
                                            </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-kjd-primary btn-sm" data-action="edit-ce" data-lamp-id="<?= (int)$l['id'] ?>">
                                                <i class="fas fa-paperclip"></i> CE soubory
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lamps)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: var(--kjd-beige); margin-bottom: 1rem;"></i>
                                        <h5 style="color: var(--kjd-dark-green);">Žádné lampy k zobrazení</h5>
                                        <p class="text-muted">Zatím nebyly vytvořeny žádné lampy.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>

    <!-- Create Lamp Modal (supports multiple serials) -->
    <div class="modal fade" id="createLampModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="create_lamp">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Vytvořit novou lampu / lampy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Objednávka</label>
                                <input list="orders" name="order_id" class="form-control" placeholder="Zadejte nebo vyberte" required>
                                <datalist id="orders">
                                    <?php foreach ($recent_orders as $o): ?>
                                        <option value="<?= htmlspecialchars($o['order_id']) ?>"><?= htmlspecialchars($o['order_id'] . ' – ' . ($o['name'] ?? '') . ' – ' . date('d.m.Y', strtotime($o['created_at']))) ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Datum výroby</label>
                                <input type="date" name="date_produced" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addGroupBtn"><i class="fas fa-layer-group"></i> Přidat typ lampy</button>
                            </div>
                        </div>
                        <div id="groupsContainer" class="mt-3">
                            <!-- Group template will be cloned via JS -->
                        </div>
                        <div class="form-text mt-2">Můžete přidat více typů lamp v rámci jedné objednávky. Každý typ může mít více sériových čísel.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-kjd-secondary" data-bs-dismiss="modal">Zrušit</button>
                        <button type="submit" class="btn btn-kjd-primary">Uložit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- CE Files Modal -->
    <div class="modal fade" id="ceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" id="ceForm">
                    <input type="hidden" name="action" value="save_ce_files">
                    <input type="hidden" name="lamp_id" id="ceLampId" value="0">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-paperclip"></i> CE soubory pro lampu <span id="ceLampLabel"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($ce_files)): ?>
                            <div class="alert alert-warning m-0">Ve složce <code>/CE</code> nebyly nalezeny žádné soubory (pdf, obrázky, doc).</div>
                        <?php else: ?>
                            <div class="row" style="max-height: 50vh; overflow:auto;">
                                <?php foreach ($ce_files as $path): ?>
                                    <div class="col-12 col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="<?= htmlspecialchars($path) ?>" id="f<?= md5($path) ?>" name="selected[]">
                                            <label class="form-check-label" for="f<?= md5($path) ?>"><?= htmlspecialchars($path) ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-kjd-secondary" data-bs-dismiss="modal">Zavřít</button>
                        <button type="submit" class="btn btn-kjd-primary">Uložit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Simple JS serial generator similar to PHP generate_serial()
    function genSerial() {
        const padHex = (n, len) => n.toString(16).padStart(len, '0');
        const rand = () => padHex(Math.floor(Math.random()*0xffffff), 6).toUpperCase();
        const d = new Date();
        const yy = ('' + d.getFullYear()).slice(-2);
        const mm = String(d.getMonth()+1).padStart(2,'0');
        const dd = String(d.getDate()).padStart(2,'0');
        return `KJD-${yy}${mm}${dd}-${rand()}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const groupsContainer = document.getElementById('groupsContainer');
        const addGroupBtn = document.getElementById('addGroupBtn');
        const ceModal = new bootstrap.Modal(document.getElementById('ceModal'));
        const ceLampId = document.getElementById('ceLampId');
        const ceLampLabel = document.getElementById('ceLampLabel');

        function createRow(groupIdx, sn) {
            const div = document.createElement('div');
            div.className = 'col-12 d-flex serial-row';
            div.setAttribute('data-row','');
            div.innerHTML = `
                <div class="input-group flex-grow-1 me-2">
                    <span class="input-group-text">SN</span>
                    <input type="text" name="groups[${groupIdx}][serial_number][]" class="form-control" value="${sn || genSerial()}" required>
                    <button type="button" class="btn btn-outline-secondary" data-action="regen">Vygenerovat jiné</button>
                </div>
                <input type="text" name="groups[${groupIdx}][material][]" class="form-control" placeholder="Materiál (volitelné)" style="max-width: 260px;">
                <button type="button" class="btn btn-outline-danger ms-2" data-action="remove" title="Odstranit řádek"><i class="fas fa-times"></i></button>
            `;
            return div;
        }

        function createGroup(groupIdx) {
            const wrap = document.createElement('div');
            wrap.className = 'card mb-3';
            wrap.setAttribute('data-group', groupIdx);
            wrap.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="flex-grow-1 me-3">
                            <label class="form-label">Produkt (lampa)</label>
                            <select name="groups[${groupIdx}][product_id]" class="form-select" required>
                                <option value="">-- Vyberte lampu --</option>
                                ${generateProductOptions()}
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline-danger" data-action="remove-group" title="Odstranit typ"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Sériová čísla a materiál</h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" data-action="add-row"><i class="fas fa-plus"></i> Přidat řádek</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="bulk-add"><i class="fas fa-clone"></i> Přidat 5</button>
                        </div>
                    </div>
                    <div class="row g-2" data-rows></div>
                </div>
            `;

            const rows = wrap.querySelector('[data-rows]');
            rows.appendChild(createRow(groupIdx));

            wrap.addEventListener('click', function(e){
                const btn = e.target.closest('button');
                if (!btn) return;
                const action = btn.getAttribute('data-action');
                if (action === 'add-row') {
                    rows.appendChild(createRow(groupIdx));
                } else if (action === 'bulk-add') {
                    for (let i=0;i<5;i++) rows.appendChild(createRow(groupIdx));
                } else if (action === 'remove-group') {
                    wrap.remove();
                } else if (action === 'remove') {
                    const row = btn.closest('[data-row]');
                    if (row && rows.children.length > 1) row.remove();
                } else if (action === 'regen') {
                    const row = btn.closest('[data-row]');
                    const input = row ? row.querySelector('input[name^="groups["][name$="[serial_number][]"]') : null;
                    if (input) input.value = genSerial();
                }
            });

            return wrap;
        }

        function generateProductOptions() {
            // Server-side inject options safely via a script tag
            return window.__lampProductOptions || '';
        }

        let groupCounter = 0;
        function addGroup() {
            groupsContainer.appendChild(createGroup(groupCounter++));
        }

        // Prepare product options from PHP array
        window.__lampProductOptions = `<?php foreach ($lamp_products as $p): ?>`+
            `<option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['category_name'] ?? 'Bez kategorie') ?>)</option>`+
            `<?php endforeach; ?>`;

        addGroup(); // initial group
        addGroupBtn?.addEventListener('click', addGroup);

        // Prepare selections and attach edit buttons
        window.__lampSelections = <?= json_encode($lamp_selections ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        document.body.addEventListener('click', function(e){
            const btn = e.target.closest('[data-action="edit-ce"]');
            if (!btn) return;
            const lampId = parseInt(btn.getAttribute('data-lamp-id'), 10);
            ceLampId.value = String(lampId);
            ceLampLabel.textContent = '#' + lampId;
            // Reset all checkboxes
            document.querySelectorAll('#ceModal input[type="checkbox"]').forEach(ch => ch.checked = false);
            // Check selected for this lamp
            const sel = (window.__lampSelections && window.__lampSelections[lampId]) ? window.__lampSelections[lampId] : [];
            sel.forEach(function(p){
                const safe = CSS.escape(p);
                const input = document.querySelector('#ceModal input[type="checkbox"][value="' + safe + '"]');
                if (input) input.checked = true;
            });
            ceModal.show();
        });

    });
    </script>
</body>
</html>
