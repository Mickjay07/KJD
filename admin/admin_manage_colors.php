<?php
session_start();
require_once 'config.php';



// PHPMailer loader – správná cesta
$path = '/www/kubajadesigns.eu/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/';

if (
    file_exists($path . 'exception.php') &&
    file_exists($path . 'PHPMailer.php') &&
    file_exists($path . 'SMTP.php')
) {
    require_once $path . 'Exception.php';
    require_once $path . 'PHPMailer.php';
    require_once $path . 'SMTP.php';
} else {
    die('PHPMailer not found at: ' . $path);
}



// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Zpracování akcí
$successMessage = '';
$errorMessage = '';

// Zpracování aktualizace dostupnosti barvy
if (isset($_POST['update_color'])) {
    $productId = (int)$_POST['product_id'];
    $productType = $_POST['product_type'];
    $color = $_POST['color'];
    $action = $_POST['action']; // make_available nebo make_unavailable
    
    try {
        $table = ($productType === 'product2') ? 'product2' : 'product';
        
        // Získání aktuálních barev produktu
        $stmt = $conn->prepare("SELECT colors, unavailable_colors FROM $table WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $availableColors = !empty($product['colors']) ? array_map('trim', explode(',', $product['colors'])) : [];
            $unavailableColors = !empty($product['unavailable_colors']) ? array_map('trim', explode(',', $product['unavailable_colors'])) : [];
            
            if ($action === 'make_available') {
                // Odstranit barvu z nedostupných
                $unavailableColors = array_filter($unavailableColors, function($c) use ($color) {
                    return strtolower(trim($c)) !== strtolower(trim($color));
                });
                
                // Odeslat notifikace uživatelům
                sendColorNotifications($conn, $productId, $productType, $color);
                
                $successMessage = "Barva $color byla označena jako dostupná a notifikace byly odeslány.";
                
            } else { // make_unavailable
                // Přidat barvu k nedostupným, pokud tam ještě není
                if (!in_array($color, $unavailableColors)) {
                    $unavailableColors[] = $color;
                }
                
                $successMessage = "Barva $color byla označena jako nedostupná.";
            }
            
            // Aktualizace produktu
            $stmt = $conn->prepare("UPDATE $table SET unavailable_colors = ? WHERE id = ?");
            $stmt->execute([
                implode(', ', $unavailableColors),
                $productId
            ]);
        }
        
    } catch(PDOException $e) {
        $errorMessage = "Chyba při aktualizaci barvy: " . $e->getMessage();
    }
}

// Získání seznamu produktů a nedostupných barev
try {
    // Zkontrolujeme, které tabulky existují
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $products = [];
    $products2 = [];
    
    // Produkty z první tabulky
    if (in_array('product', $tables)) {
        $stmt = $conn->prepare("SELECT id, name, colors, unavailable_colors FROM product ORDER BY id DESC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Přidání typu produktu do výsledků
        foreach ($products as &$product) {
            $product['type'] = 'product';
        }
    }
    
    // Produkty z druhé tabulky (pokud existuje)
    if (in_array('product2', $tables)) {
        $stmt = $conn->prepare("SELECT id, name, colors, unavailable_colors FROM product2 ORDER BY id DESC");
        $stmt->execute();
        $products2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Přidání typu produktu do výsledků
        foreach ($products2 as &$product) {
            $product['type'] = 'product2';
        }
    }
    
    // Sloučení obou seznamů
    $allProducts = array_merge($products, $products2);
    
    // Získání počtu notifikací pro každou barvu
    $colorNotifications = [];
    $stmt = $conn->prepare("SELECT product_id, product_type, color, COUNT(*) as count 
                           FROM color_notifications 
                           WHERE notified = 0 
                           GROUP BY product_id, product_type, color");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($notifications as $notification) {
        $key = $notification['product_id'] . '_' . $notification['product_type'] . '_' . $notification['color'];
        $colorNotifications[$key] = $notification['count'];
    }
    
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání produktů: " . $e->getMessage();
}

// Funkce pro odeslání e-mailových notifikací
function sendColorNotifications($conn, $productId, $productType, $color) {
    // Kontrola, zda máme PHPMailer
    if (!file_exists(__DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        return "Knihovna PHPMailer nenalezena.";
    }
    
    try {
        // Získání seznamu e-mailů pro notifikaci
        $stmt = $conn->prepare("SELECT email FROM color_notifications WHERE product_id = ? AND product_type = ? AND color = ? AND notified = 0");
        $stmt->execute([$productId, $productType, $color]);
        $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($emails)) {
            return "Žádné e-maily k notifikaci.";
        }
        
        // Získání informací o produktu
        $table = ($productType === 'product2') ? 'product2' : 'product';
        $stmt = $conn->prepare("SELECT name FROM $table WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return "Produkt nenalezen.";
        }
        
        // Nastavení PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'mail.gigaserver.cz';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@kubajadesigns.eu';
        $mail->Password = '2007Mickey++';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('info@kubajadesigns.eu', 'KJD');
        
        // Sestavení e-mailu
        $mail->Subject = "Dostupnost produktu - " . $product['name'];
        $mail->isHTML(true);
        
        // Počet úspěšně odeslaných e-mailů
        $successCount = 0;
        
        // Odeslání e-mailů všem zájemcům
        foreach ($emails as $email) {
            $mail->clearAddresses();
            $mail->addAddress($email);
            
            $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h1 style='color: #FF69B4;'>Dobrá zpráva!</h1>
                <p>Vaše oblíbená barva <strong>$color</strong> pro produkt <strong>{$product['name']}</strong> je nyní dostupná.</p>
                <p><a href='https://www.kubajadesigns.eu/product-detail.php?id=$productId" . ($productType === 'product2' ? "&type=2" : "") . "' style='display: inline-block; padding: 10px 15px; background-color: #FF69B4; color: white; text-decoration: none; border-radius: 5px;'>Zobrazit produkt</a></p>
                <p>Děkujeme za váš zájem!</p>
                <p>S pozdravem,<br>Tým KJD</p>
            </div>";
            
            if ($mail->send()) {
                $successCount++;
                
                // Označení jako notifikováno
                $updateStmt = $conn->prepare("UPDATE color_notifications SET notified = 1 WHERE email = ? AND product_id = ? AND product_type = ? AND color = ?");
                $updateStmt->execute([$email, $productId, $productType, $color]);
            }
        }
        
        return "Odesláno $successCount notifikačních e-mailů.";
        
    } catch (Exception $e) {
        return "Chyba při odesílání e-mailu: " . $e->getMessage();
    }
}

// Použití funkce při zpracování formuláře
if (isset($_POST['notify_users']) && isset($_POST['product_id']) && isset($_POST['product_type']) && isset($_POST['color'])) {
    $result = sendColorNotifications($conn, $_POST['product_id'], $_POST['product_type'], $_POST['color']);
    // Zobrazení výsledku
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KJD Administrace - Správa barev</title>
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
      
      /* Color preview */
      .color-preview {
        display: inline-block;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        margin-right: 8px;
        vertical-align: middle;
        border: 2px solid var(--kjd-earth-green);
        box-shadow: 0 2px 4px rgba(16,40,32,0.1);
      }
      
      .notification-badge {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.75rem;
        margin-left: 8px;
        font-weight: 600;
      }
      
      .color-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(202,186,156,0.2);
        padding: 0.75rem 0;
      }
      
      .color-item:last-child {
        border-bottom: none;
      }
      
      .color-actions {
        display: flex;
        gap: 0.5rem;
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
        
        .color-preview {
          width: 18px;
          height: 18px;
        }
        
        .color-item {
          flex-direction: column;
          align-items: flex-start;
          gap: 0.5rem;
        }
        
        .color-actions {
          width: 100%;
          justify-content: flex-start;
        }
        
        .dropdown-menu {
          position: static !important;
          transform: none !important;
          box-shadow: none;
          border: 1px solid var(--kjd-earth-green);
          margin-top: 0.5rem;
        }
        
        .navbar-toggler {
          border: 2px solid var(--kjd-earth-green);
          padding: 0.5rem;
        }
        
        .navbar-toggler:focus {
          box-shadow: 0 0 0 0.2rem rgba(76, 100, 68, 0.25);
        }
      }
      
      @media (max-width: 576px) {
        .cart-header {
          padding: 1.5rem 0;
        }
        
        .cart-header h1 {
          font-size: 1.8rem;
        }
        
        .cart-item {
          padding: 1rem;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary {
          padding: 0.7rem 1.2rem;
          font-size: 0.9rem;
        }
        
        .color-preview {
          width: 16px;
          height: 16px;
        }
        
        .color-item {
          padding: 0.5rem 0;
        }
        
        .container-fluid {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
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
                    <h1><i class="fas fa-palette me-3"></i>Správa barev a notifikací</h1>
                    <p>Přehled a správa dostupnosti barev produktů</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                
                <!-- Flash zprávy -->
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
                
                <div class="row">
                    <?php foreach ($allProducts as $product): ?>
                        <?php 
                        $unavailableColors = !empty($product['unavailable_colors']) ? 
                                           array_map('trim', explode(',', $product['unavailable_colors'])) : 
                                           [];
                        $availableColors = !empty($product['colors']) ? 
                                         array_map('trim', explode(',', $product['colors'])) : 
                                         [];
                        
                        // Jen pokud produkt má nějaké barvy (dostupné nebo nedostupné)
                        if (count($unavailableColors) > 0 || count($availableColors) > 0):
                        ?>
                            <div class="col-md-6 mb-4">
                                <div class="cart-item">
                                    <h3 class="cart-product-name mb-3">
                                        <i class="fas fa-box me-2"></i><?php echo htmlspecialchars($product['name']); ?>
                                    </h3>
                                    <p class="text-muted mb-3">ID: <?php echo $product['id']; ?> (<?php echo ($product['type'] === 'product2') ? 'Kategorie 2' : 'Kategorie 1'; ?>)</p>
                                        <h6>Nedostupné barvy:</h6>
                                        <?php if (count($unavailableColors) > 0): ?>
                                            <div class="mb-3">
                                                <?php foreach ($unavailableColors as $color): ?>
                                                    <?php 
                                                    $notificationKey = $product['id'] . '_' . $product['type'] . '_' . $color;
                                                    $notificationCount = isset($colorNotifications[$notificationKey]) ? 
                                                                       $colorNotifications[$notificationKey] : 0;
                                                    ?>
                                                    <div class="color-item">
                                                        <div>
                                                            <span class="color-preview" style="background-color: <?php echo $color; ?>;"></span>
                                                            <?php echo $color; ?>
                                                            <?php if ($notificationCount > 0): ?>
                                                                <span class="notification-badge">
                                                                    <?php echo $notificationCount; ?> žádostí
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="color-actions">
                                                            <form method="post">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                <input type="hidden" name="product_type" value="<?php echo $product['type']; ?>">
                                                                <input type="hidden" name="color" value="<?php echo $color; ?>">
                                                                <input type="hidden" name="action" value="make_available">
                                                                <button type="submit" name="update_color" class="btn btn-sm btn-kjd-primary">
                                                                    <i class="fas fa-check"></i> Označit jako dostupnou
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Žádné nedostupné barvy</p>
                                        <?php endif; ?>
                                        
                                        <h6 class="mt-4">Dostupné barvy:</h6>
                                        <?php if (count($availableColors) > 0): ?>
                                            <div>
                                                <?php foreach ($availableColors as $color): ?>
                                                    <div class="color-item">
                                                        <div>
                                                            <span class="color-preview" style="background-color: <?php echo $color; ?>;"></span>
                                                            <?php echo $color; ?>
                                                        </div>
                                                        <div class="color-actions">
                                                            <form method="post">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                <input type="hidden" name="product_type" value="<?php echo $product['type']; ?>">
                                                                <input type="hidden" name="color" value="<?php echo $color; ?>">
                                                                <input type="hidden" name="action" value="make_unavailable">
                                                                <button type="submit" name="update_color" class="btn btn-sm btn-kjd-secondary">
                                                                    <i class="fas fa-times"></i> Označit jako nedostupnou
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Žádné dostupné barvy</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center py-4">
                    <p class="mb-0" style="color: var(--kjd-gold-brown); font-weight: 600;">
                        © 2024 KJD Designs. Všechna práva vyhrazena.
                    </p>
                </div>
            </div>
        </div>
    </footer>

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
</body>
</html> 