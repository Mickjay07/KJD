<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $product_ids = $_POST['product_ids'] ?? [];

        switch ($action) {
            case 'add':
                // Kontrola, zda již kód existuje
                $stmt = $conn->prepare("SELECT COUNT(*) FROM discount_codes WHERE code = ?");
                $stmt->execute([$_POST['code']]);
                $count = $stmt->fetchColumn();

                if ($count > 0) {
                    $_SESSION['error'] = 'Tento kód již existuje.';
                    header('Location: discount_codes.php');
                    exit;
                }

                // Convert comma to dot and validate discount percent
                $discountPercent = (float)str_replace(',', '.', $_POST['discount_percent']);
                if ($discountPercent < 0 || $discountPercent > 100) {
                    $_SESSION['error'] = 'Sleva musí být mezi 0 a 100 %.';
                    header('Location: discount_codes.php');
                    exit;
                }
                
                // Pokud kód neexistuje, pokračujeme s vložením
                $stmt = $conn->prepare("
                    INSERT INTO discount_codes (code, discount_percent, valid_from, valid_to, usage_limit, active) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['code'],
                    $discountPercent,
                    $_POST['valid_from'],
                    $_POST['valid_to'],
                    empty($_POST['usage_limit']) ? null : $_POST['usage_limit'],
                    isset($_POST['active']) ? 1 : 0
                ]);

                $discount_code_id = $conn->lastInsertId();
                if (!empty($product_ids)) {
                    $stmt = $conn->prepare("INSERT INTO discount_code_products (discount_code_id, product_id, product_type) VALUES (?, ?, ?)");
                    foreach ($product_ids as $product_id) {
                        // Určení typu produktu podle ID
                        $productType = 'product'; // výchozí hodnota
                        if (strpos($product_id, 'product2_') === 0) {
                            $productType = 'product2';
                            $product_id = substr($product_id, 9); // odstranění prefixu 'product2_'
                        }
                        $stmt->execute([$discount_code_id, $product_id, $productType]);
                    }
                }

                $_SESSION['message'] = 'Slevový kód byl úspěšně vytvořen.';
                break;

            case 'edit':
                // Convert comma to dot and validate discount percent
                $discountPercent = (float)str_replace(',', '.', $_POST['discount_percent']);
                if ($discountPercent < 0 || $discountPercent > 100) {
                    $_SESSION['error'] = 'Sleva musí být mezi 0 a 100 %.';
                    header('Location: discount_codes.php');
                    exit;
                }
                
                // Kód pro úpravu
                $stmt = $conn->prepare("
                    UPDATE discount_codes 
                    SET code = ?, discount_percent = ?, valid_from = ?, valid_to = ?, usage_limit = ?, active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['code'],
                    $discountPercent,
                    $_POST['valid_from'],
                    $_POST['valid_to'],
                    empty($_POST['usage_limit']) ? null : $_POST['usage_limit'],
                    isset($_POST['active']) ? 1 : 0,
                    $_POST['id']
                ]);

                $discount_code_id = $_POST['id'];
                // Aktualizace přiřazených produktů
                $conn->prepare("DELETE FROM discount_code_products WHERE discount_code_id = ?")->execute([$discount_code_id]);
                if (!empty($product_ids)) {
                    $stmt = $conn->prepare("INSERT INTO discount_code_products (discount_code_id, product_id, product_type) VALUES (?, ?, ?)");
                    foreach ($product_ids as $product_id) {
                        // Určení typu produktu podle ID
                        $productType = 'product'; // výchozí hodnota
                        if (strpos($product_id, 'product2_') === 0) {
                            $productType = 'product2';
                            $product_id = substr($product_id, 9); // odstranění prefixu 'product2_'
                        }
                        $stmt->execute([$discount_code_id, $product_id, $productType]);
                    }
                }

                $_SESSION['message'] = 'Slevový kód byl úspěšně upraven.';
                break;

            case 'delete':
                // Nejprve smažeme vazby na produkty
                $conn->prepare("DELETE FROM discount_code_products WHERE discount_code_id = ?")->execute([$_POST['id']]);
                // Pak smažeme samotný kód
                $conn->prepare("DELETE FROM discount_codes WHERE id = ?")->execute([$_POST['id']]);
                $_SESSION['message'] = 'Slevový kód byl smazán.';
                break;
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Chyba: ' . $e->getMessage();
    }
    
    header('Location: discount_codes.php');
    exit;
}

// Načtení všech produktů pro výběr
try {
    // Zkontrolujeme, které tabulky existují
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $products = [];
    $products2 = [];
    
    // Načtení produktů z tabulky product
    if (in_array('product', $tables)) {
        $stmt = $conn->query("SELECT id, name FROM product ORDER BY name");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Načtení produktů z tabulky product2 (pokud existuje)
    if (in_array('product2', $tables)) {
        $stmt = $conn->query("SELECT id, name FROM product2 ORDER BY name");
        $products2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Upravíme také select pro produkty v kategorii 2
        foreach ($products2 as &$product) {
            $product['id'] = 'product2_' . $product['id'];  // Přidáme prefix pro rozlišení
            $product['name'] .= ' (Kategorie 2)';
        }
    }
    
    $allProducts = array_merge($products, $products2);
    
    // Načtení slevových kódů
    $stmt = $conn->query("
        SELECT 
            dc.*,
            GROUP_CONCAT(dcp.product_id) as product_ids,
            GROUP_CONCAT(CONCAT(dcp.product_id, ':', dcp.product_type)) as product_mappings
        FROM discount_codes dc
        LEFT JOIN discount_code_products dcp ON dc.id = dcp.discount_code_id
        GROUP BY dc.id
        ORDER BY dc.valid_from DESC
    ");
    $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Načtení přiřazených produktů pro každý kód
    foreach ($codes as &$code) {
        $code['assigned_products'] = [];
        if (!empty($code['product_mappings'])) {
            $mappings = explode(',', $code['product_mappings']);
            foreach ($mappings as $mapping) {
                list($productId, $productType) = explode(':', $mapping);
                if ($productType == 'product2') {
                    $code['assigned_products'][] = 'product2_' . $productId;
                } else {
                    $code['assigned_products'][] = $productId;
                }
            }
        }
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Chyba při načítání dat: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa slevových kódů - Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <link rel="stylesheet" href="admin_style.css">
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
        
        /* Form controls */
        .form-control, .form-select {
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--kjd-dark-green);
            box-shadow: 0 0 0 0.2rem rgba(16, 40, 32, 0.25);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            font-weight: 600;
        }
        
        .code-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .code-active {
            background-color: #28a745;
        }
        .code-inactive {
            background-color: #dc3545;
        }
        
        /* Preloader */
        .preloader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(248, 249, 250, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .preloader {
            width: 50px;
            height: 50px;
            border: 3px solid var(--kjd-beige);
            border-top: 3px solid var(--kjd-dark-green);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .preloader-wrapper.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .cart-header {
                padding: 2rem 0;
            }
            
            .cart-header h1 {
                font-size: 2rem;
            }
            
            .btn-kjd-primary, .btn-kjd-secondary {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body class="cart-page">
    <?php include '../includes/icons.php'; ?>
    
    <!-- Preloader -->
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
                    <h1><i class="fas fa-tags me-3"></i>Slevové kódy</h1>
                    <p>Správa slevových kódů pro produkty</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <!-- Flash zprávy -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show cart-item">
                        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show cart-item">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Nový kód -->
                <div class="cart-item">
                    <h3 class="cart-product-name mb-4">
                        <i class="fas fa-plus-circle me-2"></i>Nový slevový kód
                    </h3>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="code">Kód</label>
                                    <input type="text" class="form-control" id="code" name="code" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="discount_percent">Sleva (%)</label>
                                    <input type="text" class="form-control" id="discount_percent" name="discount_percent" 
                                           placeholder="např. 15.5" required>
                                    <small class="text-muted">Můžete zadat desetinné číslo, např. 15.5</small>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="valid_from">Platnost od</label>
                                    <input type="date" class="form-control" id="valid_from" name="valid_from" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="valid_to">Platnost do</label>
                                    <input type="date" class="form-control" id="valid_to" name="valid_to" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label for="usage_limit">Limit použití</label>
                                    <input type="number" class="form-control" id="usage_limit" name="usage_limit" min="0" value="0">
                                </div>
                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="active" name="active" checked>
                                        <label class="form-check-label" for="active">Aktivní</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Platí pro produkty</label>
                                <select class="form-select" name="product_ids[]" multiple size="5">
                                    <?php foreach ($allProducts as $product): ?>
                                        <option value="<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pro výběr více produktů držte Ctrl (Cmd na Mac)</small>
                            </div>
                            <button type="submit" class="btn-kjd-primary">
                                <i class="fas fa-plus me-2"></i>Přidat kód
                            </button>
                        </form>
                </div>

                <!-- Existující kódy -->
                <div class="cart-item">
                    <h3 class="cart-product-name mb-4">
                        <i class="fas fa-list me-2"></i>Existující slevové kódy
                    </h3>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Stav</th>
                                        <th>Kód</th>
                                        <th>Sleva</th>
                                        <th>Platnost od</th>
                                        <th>Platnost do</th>
                                        <th>Limit použití</th>
                                        <th>Akce</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (isset($codes) && is_array($codes)): ?>
                                    <?php foreach ($codes as $code): ?>
                                        <?php
                                        $now = new DateTime();
                                        $validFrom = new DateTime($code['valid_from']);
                                        $validTo = new DateTime($code['valid_to']);
                                        
                                        // Kontrola aktivního stavu
                                        $isActive = $code['active'] && $now >= $validFrom && $now <= $validTo;
                                        if ($code['usage_limit'] !== null && $code['times_used'] >= $code['usage_limit']) {
                                            $isActive = false;
                                        }
                                        
                                        $statusClass = $isActive ? 'code-active' : 'code-inactive';
                                        $statusTitle = $isActive ? 'Aktivní' : 'Neaktivní';
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="code-status <?php echo $statusClass; ?>" 
                                                    title="<?php echo $statusTitle; ?>">
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($code['code']); ?></td>
                                            <td><?php echo number_format($code['discount_percent'], 2, ',', ' '); ?>%</td>
                                            <td><?php echo date('d.m.Y', strtotime($code['valid_from'])); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($code['valid_to'])); ?></td>
                                            <td>
                                                <?php 
                                                if ($code['usage_limit'] === null) {
                                                    echo 'Neomezené';
                                                } else {
                                                    echo $code['times_used'] . ' / ' . $code['usage_limit'];
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary btn-sm me-1" 
                                                        onclick="editCode(<?php echo htmlspecialchars(json_encode($code)); ?>)">
                                                    <i class="fas fa-edit"></i> Upravit
                                                </button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $code['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Opravdu chcete smazat tento slevový kód?')">
                                                        <i class="fas fa-trash"></i> Smazat
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hide preloader when page is loaded
        window.addEventListener('load', function() {
            const preloader = document.querySelector('.preloader-wrapper');
            if (preloader) {
                preloader.classList.add('hidden');
                setTimeout(() => {
                    preloader.style.display = 'none';
                }, 300);
            }
        });
    </script>

    <!-- Modal pro editaci slevového kódu -->
    <div class="modal fade" id="editCodeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upravit slevový kód</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_code">Kód</label>
                                <input type="text" class="form-control" id="edit_code" name="code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_discount_percent">Sleva (%)</label>
                                <input type="text" class="form-control" id="edit_discount_percent" name="discount_percent" 
                                       placeholder="např. 15.5" required>
                                <small class="text-muted">Můžete zadat desetinné číslo, např. 15.5</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_valid_from">Platnost od</label>
                                <input type="date" class="form-control" id="edit_valid_from" name="valid_from" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_valid_to">Platnost do</label>
                                <input type="date" class="form-control" id="edit_valid_to" name="valid_to" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_usage_limit">Limit použití (0 = neomezeno)</label>
                                <input type="number" class="form-control" id="edit_usage_limit" name="usage_limit" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" id="edit_active" name="active">
                                    <label class="form-check-label" for="edit_active">Aktivní</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_product_ids">Platí pro produkty</label>
                            <select class="form-select" id="edit_product_ids" name="product_ids[]" multiple size="8">
                                <?php foreach ($allProducts as $product): ?>
                                    <option value="<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pro výběr více produktů držte Ctrl (Cmd na Mac)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-kjd-secondary" data-bs-dismiss="modal">Zrušit</button>
                        <button type="submit" class="btn-kjd-primary">Uložit změny</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editCode(code) {
        // Nastavení hodnot formuláře
        document.getElementById('edit_id').value = code.id;
        document.getElementById('edit_code').value = code.code;
        document.getElementById('edit_discount_percent').value = code.discount_percent;
        
        // Formátování data pro input type="date"
        const validFrom = new Date(code.valid_from);
        const validTo = new Date(code.valid_to);
        
        document.getElementById('edit_valid_from').value = formatDate(validFrom);
        document.getElementById('edit_valid_to').value = formatDate(validTo);
        
        document.getElementById('edit_usage_limit').value = code.usage_limit === null ? 0 : code.usage_limit;
        document.getElementById('edit_active').checked = code.active == 1;
        
        // Nastavení vybraných produktů
        const productSelect = document.getElementById('edit_product_ids');
        for (let i = 0; i < productSelect.options.length; i++) {
            productSelect.options[i].selected = false;
        }
        
        if (code.assigned_products && code.assigned_products.length > 0) {
            for (let i = 0; i < productSelect.options.length; i++) {
                if (code.assigned_products.includes(productSelect.options[i].value)) {
                    productSelect.options[i].selected = true;
                }
            }
        }
        
        // Otevření modálního okna
        const modal = new bootstrap.Modal(document.getElementById('editCodeModal'));
        modal.show();
    }
    
    // Pomocná funkce pro formátování data do formátu YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    </script>
</body>
</html> 