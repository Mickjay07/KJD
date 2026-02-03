<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: #1d1d1f;
            font-weight: 600;
            margin-bottom: 2rem;
            font-size: 2rem;
            text-align: center;
        }
        
        .card {
            border: 1px solid #e5e5e7;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            background: #ffffff;
        }
        
        .card-header {
            background: #CABA9C;
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .card-header h5 {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .card-header small {
            opacity: 0.9;
            font-size: 0.85rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .color-item:last-child {
            border-bottom: none;
        }
        
        .color-preview {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            margin-right: 0.75rem;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .notification-badge {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
            font-weight: 500;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 2rem;
        }
        
        .row {
            margin: 0 -0.75rem;
        }
        
        .col-md-6 {
            padding: 0 0.75rem;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
        
        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
            border: 1px solid #ddd;
        }
        
        .notification-badge {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 8px;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f0f0f0;
            padding: 0.75rem 0;
        }
        
        .color-item:last-child {
            border-bottom: none;
        }
        
        .color-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'admin_header.php'; ?>

            <!-- Main content -->
            <div class="col-md-10 main-content">
                <h1 class="mb-4">Správa barev a notifikací</h1>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?php echo $successMessage; ?></div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
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
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        <small class="text-muted">ID: <?php echo $product['id']; ?> (<?php echo ($product['type'] === 'product2') ? 'Kategorie 2' : 'Kategorie 1'; ?>)</small>
                                    </div>
                                    <div class="card-body">
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
                                                                <button type="submit" name="update_color" class="btn btn-sm btn-success">
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
                                                                <button type="submit" name="update_color" class="btn btn-sm btn-warning">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 