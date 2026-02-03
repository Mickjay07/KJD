<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";
$dbUser = $username;
$dbPassword = $password;

try {
    $conn = new PDO($dsn, $dbUser, $dbPassword);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES utf8");
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

$error = '';
$success = '';

// Zpracování uploadu obrázku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $image = $_FILES['image'];
    $size = $_POST['size'] ?? 'medium';
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    $hasStand = isset($_POST['has_stand']) && $_POST['has_stand'] === '1';
    $logoShape = $_POST['logo_shape'] ?? 'round';
    $aspectRatio = $_POST['aspect_ratio'] ?? '1:1';
    $boxColor = $_POST['box_color'] ?? 'white';
    $customerEmail = trim($_POST['email'] ?? '');
    $customerName = trim($_POST['name'] ?? '');
    $customerPhone = trim($_POST['phone'] ?? '');
    
    // Validace
    if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Zadejte platný email.';
    } elseif (empty($customerName)) {
        $error = 'Zadejte jméno.';
    } elseif ($image['error'] !== UPLOAD_ERR_OK) {
        $error = 'Chyba při nahrávání obrázku.';
    } elseif (!in_array(strtolower(pathinfo($image['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
        $error = 'Povolené formáty: JPG, PNG, WEBP.';
    } else {
        // Vytvoření složky pro uploady, pokud neexistuje
        $uploadDir = __DIR__ . '/uploads/custom_lightbox/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generování unikátního názvu souboru
        $fileExtension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
        $fileName = 'custom_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        // Přesunutí souboru
        if (move_uploaded_file($image['tmp_name'], $filePath)) {
            // Cena podle velikosti
            $prices = [
                'small' => 890,
                'medium' => 1290,
                'large' => 1690
            ];
            $basePrice = $prices[$size] ?? 1290;
            $standPrice = $hasStand ? 125 : 0;
            $unitPrice = $basePrice + $standPrice;
            $totalPrice = $unitPrice * $quantity;
            
            // Uložení do databáze
            try {
                // Kontrola a přidání sloupců, pokud neexistují
                try {
                    $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'logo_shape'");
                    if ($checkCol->rowCount() == 0) {
                        $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN logo_shape VARCHAR(20) DEFAULT 'round'");
                    }
                } catch (PDOException $e) {
                    error_log("Error checking/adding logo_shape column: " . $e->getMessage());
                }
                try {
                    $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'aspect_ratio'");
                    if ($checkCol->rowCount() == 0) {
                        $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN aspect_ratio VARCHAR(10) DEFAULT '1:1'");
                    }
                } catch (PDOException $e) {
                    error_log("Error checking/adding aspect_ratio column: " . $e->getMessage());
                }
                try {
                    $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'box_color'");
                    if ($checkCol->rowCount() == 0) {
                        $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN box_color VARCHAR(20) DEFAULT 'white'");
                    }
                } catch (PDOException $e) {
                    error_log("Error checking/adding box_color column: " . $e->getMessage());
                }
                try {
                    $checkCol = $conn->query("SHOW COLUMNS FROM custom_lightbox_orders LIKE 'quantity'");
                    if ($checkCol->rowCount() == 0) {
                        $conn->exec("ALTER TABLE custom_lightbox_orders ADD COLUMN quantity INT DEFAULT 1");
                    }
                } catch (PDOException $e) {
                    error_log("Error checking/adding quantity column: " . $e->getMessage());
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO custom_lightbox_orders 
                    (customer_name, customer_email, customer_phone, image_path, size, quantity, has_stand, logo_shape, aspect_ratio, box_color, base_price, stand_price, total_price, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', NOW())
                ");
                
                $relativePath = 'uploads/custom_lightbox/' . $fileName;
                $stmt->execute([
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    $relativePath,
                    $size,
                    $quantity,
                    $hasStand ? 1 : 0,
                    $logoShape,
                    $aspectRatio,
                    $boxColor,
                    $basePrice,
                    $standPrice,
                    $totalPrice
                ]);
                
                $orderId = $conn->lastInsertId();
                
                // Uložení do session pro další krok
                $_SESSION['custom_lightbox_order'] = [
                    'order_id' => $orderId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'image_path' => $relativePath,
                    'size' => $size,
                    'quantity' => $quantity,
                    'has_stand' => $hasStand,
                    'logo_shape' => $logoShape,
                    'aspect_ratio' => $aspectRatio,
                    'box_color' => $boxColor,
                    'base_price' => $basePrice,
                    'stand_price' => $standPrice,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice
                ];
                
                // Přesměrování na checkout (stejný jako běžné objednávky)
                header('Location: checkout_custom_lightbox.php');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Chyba při ukládání objednávky: ' . $e->getMessage();
                // Smazání nahraného souboru při chybě
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        } else {
            $error = 'Nepodařilo se nahrát obrázek.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Lightbox - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        body {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 2rem 0;
        }
        
        .custom-lightbox-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .custom-lightbox-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(16,40,32,0.15);
            border: 3px solid var(--kjd-earth-green);
            overflow: hidden;
        }
        
        .custom-lightbox-header {
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        
        .custom-lightbox-header h1 {
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .custom-lightbox-body {
            padding: 2.5rem;
        }
        
        .form-label {
            color: var(--kjd-dark-green);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--kjd-beige);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--kjd-earth-green);
            box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
        }
        
        .image-upload-area {
            border: 3px dashed var(--kjd-earth-green);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            background: rgba(202,186,156,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-upload-area:hover {
            background: rgba(202,186,156,0.2);
            border-color: var(--kjd-dark-green);
        }
        
        .image-upload-area.dragover {
            background: rgba(76,100,68,0.1);
            border-color: var(--kjd-dark-green);
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            margin-top: 1rem;
            display: none;
        }
        
        .size-option {
            border: 2px solid var(--kjd-beige);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .size-option:hover {
            border-color: var(--kjd-earth-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16,40,32,0.1);
        }
        
        .size-option.selected {
            border-color: var(--kjd-earth-green);
            background: rgba(76,100,68,0.1);
        }
        
        .size-option input[type="radio"] {
            display: none;
        }
        
        .size-option h4 {
            color: var(--kjd-dark-green);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .size-option .price {
            color: var(--kjd-gold-brown);
            font-weight: 800;
            font-size: 1.3rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(77,45,24,0.3);
        }
        
        .btn-submit:hover {
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.4);
            color: #fff;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 600;
        }
        
        .required {
            color: #dc3545;
        }
        
        .checkbox-custom {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            border: 2px solid var(--kjd-beige);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .checkbox-custom:hover {
            border-color: var(--kjd-earth-green);
        }
        
        .checkbox-custom input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .checkbox-custom label {
            cursor: pointer;
            margin: 0;
            font-weight: 600;
            color: var(--kjd-dark-green);
        }
        
        .price-summary {
            background: var(--kjd-beige);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 2px solid var(--kjd-earth-green);
        }
        
        .price-summary h4 {
            color: var(--kjd-dark-green);
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            color: var(--kjd-dark-brown);
        }
        
        .price-total {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--kjd-earth-green);
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--kjd-dark-green);
        }
        
        .preview-container {
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(202,186,156,0.1);
            border-radius: 12px;
            border: 2px solid var(--kjd-earth-green);
        }
        
        .preview-box {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            background: #fff;
            border: 3px solid var(--kjd-dark-green);
            border-radius: 12px;
            padding: 1rem;
            position: relative;
        }
        
        .preview-box.black {
            background: #1a1a1a;
            border-color: #333;
        }
        
        .preview-image-wrapper {
            width: 100%;
            position: relative;
            background: #f0f0f0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .preview-box.black .preview-image-wrapper {
            background: #2a2a2a;
        }
        
        .preview-image-wrapper.round {
            border-radius: 50%;
        }
        
        .preview-image-wrapper.ratio-1-1 {
            aspect-ratio: 1 / 1;
        }
        
        .preview-image-wrapper.ratio-4-3 {
            aspect-ratio: 4 / 3;
        }
        
        .preview-image-wrapper.ratio-3-2 {
            aspect-ratio: 3 / 2;
        }
        
        .preview-image-wrapper.ratio-16-9 {
            aspect-ratio: 16 / 9;
        }
        
        .preview-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .gallery-section {
            margin-top: 3rem;
            padding: 2rem;
            background: rgba(202,186,156,0.1);
            border-radius: 12px;
            border: 2px solid var(--kjd-earth-green);
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .gallery-item {
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--kjd-beige);
            transition: all 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(16,40,32,0.2);
            border-color: var(--kjd-earth-green);
        }
        
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .option-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .option-btn {
            flex: 1;
            min-width: 120px;
            padding: 1rem;
            border: 2px solid var(--kjd-beige);
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .option-btn:hover {
            border-color: var(--kjd-earth-green);
            transform: translateY(-2px);
        }
        
        .option-btn.selected {
            border-color: var(--kjd-earth-green);
            background: rgba(76,100,68,0.1);
        }
        
        .option-btn input[type="radio"] {
            display: none;
        }
        
        .color-option {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .color-option.white {
            background: #fff;
            border-color: #ccc;
        }
        
        .color-option.black {
            background: #1a1a1a;
            border-color: #333;
        }
        
        .color-option.selected {
            border-color: var(--kjd-earth-green);
            border-width: 4px;
            transform: scale(1.1);
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 1rem;
            margin: 1.5rem 0;
            color: #856404;
        }
        
        .warning-box i {
            color: #ffc107;
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .custom-lightbox-body {
                padding: 1.5rem;
            }
            
            .image-upload-area {
                padding: 2rem 1rem;
            }
            
            /* Mobilní styly pro výběr velikosti */
            .size-option {
                padding: 1rem 0.5rem;
                margin-bottom: 0;
                border-width: 3px;
            }
            
            .size-option h4 {
                font-size: 0.95rem;
                margin-bottom: 0.3rem;
            }
            
            .size-option p {
                font-size: 0.75rem;
                margin: 0.3rem 0;
                line-height: 1.2;
            }
            
            .size-option .price {
                font-size: 1rem;
                margin-top: 0.4rem;
            }
            
            .size-option.selected {
                border-width: 3px;
                box-shadow: 0 4px 15px rgba(76,100,68,0.25);
            }
            
            .option-group {
                flex-direction: column;
            }
            
            .option-btn {
                width: 100%;
            }
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.75rem;
            }
            
            .gallery-item img {
                height: 150px;
            }
            
            .preview-container {
                padding: 1rem;
            }
            
            .preview-box {
                max-width: 100%;
            }
            
            .d-flex.gap-3 {
                flex-direction: column;
                gap: 1rem !important;
            }
            
            #quantity {
                max-width: 100% !important;
            }
        }
        
        @media (max-width: 576px) {
            .size-option {
                padding: 0.85rem 0.4rem;
            }
            
            .size-option h4 {
                font-size: 0.9rem;
            }
            
            .size-option p {
                font-size: 0.7rem;
            }
            
            .size-option .price {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/icons.php'; ?>
    
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container custom-lightbox-container">
        <div class="custom-lightbox-card">
            <div class="custom-lightbox-header">
                <h1><i class="fas fa-lightbulb me-2"></i>Custom Lightbox</h1>
                <p class="mb-0">Nahrajte svůj obrázek a vytvořte si vlastní světlo</p>
            </div>
            
            <div class="custom-lightbox-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="customLightboxForm">
                    <!-- Kontaktní údaje -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-user me-2"></i>Kontaktní údaje
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Jméno a příjmení <span class="required">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="required">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                <div class="alert alert-warning mt-2 mb-0" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 0.75rem; font-size: 0.85rem;">
                                    <i class="fas fa-exclamation-triangle me-1" style="color: #856404;"></i>
                                    <strong style="color: #856404;">Pozor:</strong> <span style="color: #856404;">Naše emaily často končí ve spamu. Zkontrolujte prosím složku Spam/Promo!</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <!-- Upload obrázku -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-image me-2"></i>Váš obrázek
                        </h3>
                        
                        <div class="image-upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--kjd-earth-green); margin-bottom: 1rem;"></i>
                            <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">
                                Klikněte nebo přetáhněte obrázek sem
                            </h4>
                            <p style="color: #666; margin-bottom: 0;">
                                Podporované formáty: JPG, PNG, WEBP<br>
                                Maximální velikost: 10 MB
                            </p>
                            <input type="file" name="image" id="imageInput" accept="image/jpeg,image/jpg,image/png,image/webp" required style="display: none;">
                        </div>
                        
                        <img id="imagePreview" class="image-preview" alt="Náhled obrázku">
                        
                        <!-- Upozornění o barvách -->
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Důležité upozornění:</strong> V designu můžeme použít maximálně 4 barvy. Prosím, zvažte to při výběru obrázku.
                        </div>
                    </div>
                    
                    <!-- Náhled -->
                    <div class="preview-container" id="previewContainer" style="display: none;">
                        <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1rem; text-align: center;">
                            <i class="fas fa-eye me-2"></i>Náhled vašeho designu
                        </h4>
                        <div class="preview-box" id="previewBox">
                            <div class="preview-image-wrapper round ratio-1-1" id="previewWrapper">
                                <img id="previewImage" src="" alt="Náhled">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Velikost -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-ruler me-2"></i>Velikost světla
                        </h3>
                        
                        <div class="row g-2 g-md-3">
                            <div class="col-4 col-md-4">
                                <label class="size-option" data-size="small" data-price="890">
                                    <input type="radio" name="size" value="small" checked>
                                    <h4>Malé</h4>
                                    <p style="color: #666; margin: 0.5rem 0;">15x15 cm</p>
                                    <div class="price">890 Kč</div>
                                </label>
                            </div>
                            <div class="col-4 col-md-4">
                                <label class="size-option selected" data-size="medium" data-price="1290">
                                    <input type="radio" name="size" value="medium">
                                    <h4>Střední</h4>
                                    <p style="color: #666; margin: 0.5rem 0;">20x20 cm</p>
                                    <div class="price">1 290 Kč</div>
                                </label>
                            </div>
                            <div class="col-4 col-md-4">
                                <label class="size-option" data-size="large" data-price="1690">
                                    <input type="radio" name="size" value="large">
                                    <h4>Velké</h4>
                                    <p style="color: #666; margin: 0.5rem 0;">25x25 cm</p>
                                    <div class="price">1 690 Kč</div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tvar loga -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-shapes me-2"></i>Tvar loga
                        </h3>
                        
                        <div class="option-group">
                            <label class="option-btn selected" data-shape="round">
                                <input type="radio" name="logo_shape" value="round" checked>
                                <i class="fas fa-circle" style="font-size: 2rem; color: var(--kjd-earth-green); margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">Kulatý</div>
                            </label>
                            <label class="option-btn" data-shape="square">
                                <input type="radio" name="logo_shape" value="square">
                                <i class="fas fa-square" style="font-size: 2rem; color: var(--kjd-earth-green); margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">Hranatý</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Poměr stran -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-expand-arrows-alt me-2"></i>Poměr stran
                        </h3>
                        
                        <div class="option-group">
                            <label class="option-btn selected" data-ratio="1:1">
                                <input type="radio" name="aspect_ratio" value="1:1" checked>
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">1:1</div>
                                <div style="font-size: 0.85rem; color: #666;">Čtverec</div>
                            </label>
                            <label class="option-btn" data-ratio="4:3">
                                <input type="radio" name="aspect_ratio" value="4:3">
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">4:3</div>
                                <div style="font-size: 0.85rem; color: #666;">Klasický</div>
                            </label>
                            <label class="option-btn" data-ratio="3:2">
                                <input type="radio" name="aspect_ratio" value="3:2">
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">3:2</div>
                                <div style="font-size: 0.85rem; color: #666;">Foto</div>
                            </label>
                            <label class="option-btn" data-ratio="16:9">
                                <input type="radio" name="aspect_ratio" value="16:9">
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">16:9</div>
                                <div style="font-size: 0.85rem; color: #666;">Širokoúhlý</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Barva boxu -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-palette me-2"></i>Barva boxu
                        </h3>
                        
                        <div class="d-flex gap-3 align-items-center">
                            <div class="color-option white selected" data-color="white" onclick="selectColor('white')"></div>
                            <div class="color-option black" data-color="black" onclick="selectColor('black')"></div>
                            <input type="hidden" name="box_color" id="boxColor" value="white">
                        </div>
                    </div>
                    
                    <!-- Množství -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-shopping-cart me-2"></i>Množství
                        </h3>
                        
                        <div class="d-flex align-items-center gap-3">
                            <label for="quantity" class="form-label mb-0" style="min-width: 100px;">Počet kusů:</label>
                            <input type="number" id="quantity" name="quantity" min="1" max="50" value="1" 
                                   class="form-control" style="max-width: 120px; text-align: center; font-weight: 700; font-size: 1.1rem;" 
                                   onchange="updatePrice()" oninput="updatePrice()">
                            <span style="color: #666; font-size: 0.9rem;">(max. 50 kusů)</span>
                        </div>
                    </div>
                    
                    <!-- Podstavec -->
                    <div class="mb-4">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1.5rem;">
                            <i class="fas fa-cube me-2"></i>Doplňky
                        </h3>
                        
                        <div class="checkbox-custom">
                            <input type="checkbox" id="has_stand" name="has_stand" value="1">
                            <label for="has_stand">
                                <strong>Přidat podstavec</strong> (+125 Kč za kus)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Galerie hotových prací -->
                    <div class="gallery-section">
                        <h3 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1rem; text-align: center;">
                            <i class="fas fa-images me-2"></i>Naše hotové práce
                        </h3>
                        <p style="text-align: center; color: #666; margin-bottom: 0;">Inspirujte se našimi realizacemi</p>
                        
                        <div class="gallery-grid">
                            <div class="gallery-item">
                                <img src="images/custom-lightbox-1.jpg" alt="Custom Lightbox 1" onerror="this.style.display='none'">
                            </div>
                            <div class="gallery-item">
                                <img src="images/custom-lightbox-2.jpg" alt="Custom Lightbox 2" onerror="this.style.display='none'">
                            </div>
                            <div class="gallery-item">
                                <img src="images/custom-lightbox-3.jpg" alt="Custom Lightbox 3" onerror="this.style.display='none'">
                            </div>
                            <div class="gallery-item">
                                <img src="images/custom-lightbox-4.jpg" alt="Custom Lightbox 4" onerror="this.style.display='none'">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Souhrn ceny -->
                    <div class="price-summary">
                        <h4><i class="fas fa-calculator me-2"></i>Souhrn ceny</h4>
                        <div class="price-item">
                            <span>Světlo (střední):</span>
                            <span id="basePrice">1 290 Kč</span>
                        </div>
                        <div class="price-item" id="standPriceItem" style="display: none;">
                            <span>Podstavec:</span>
                            <span id="standPriceTotal">125 Kč</span>
                        </div>
                        <div class="price-item" id="quantityItem">
                            <span>Množství:</span>
                            <span id="quantityDisplay">1x</span>
                        </div>
                        <div class="price-total">
                            <span>Celkem:</span>
                            <span id="totalPrice">1 290 Kč</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit mt-4">
                        <i class="fas fa-arrow-right me-2"></i>Pokračovat k platbě
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image upload
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');
        
        uploadArea.addEventListener('click', () => imageInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                imageInput.files = files;
                handleImagePreview(files[0]);
            }
        });
        
        imageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImagePreview(e.target.files[0]);
            }
        });
        
        function handleImagePreview(file) {
            if (file.size > 10 * 1024 * 1024) {
                alert('Soubor je příliš velký. Maximální velikost je 10 MB.');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                setTimeout(updatePreview, 100);
            };
            reader.readAsDataURL(file);
        }
        
        // Size selection
        const sizeOptions = document.querySelectorAll('.size-option');
        sizeOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                option.classList.add('selected');
            }
            
            option.addEventListener('click', () => {
                sizeOptions.forEach(opt => {
                    opt.classList.remove('selected');
                    opt.querySelector('input[type="radio"]').checked = false;
                });
                option.classList.add('selected');
                option.querySelector('input[type="radio"]').checked = true;
                updatePrice();
            });
        });
        
        // Stand checkbox
        const hasStandCheckbox = document.getElementById('has_stand');
        hasStandCheckbox.addEventListener('change', updatePrice);
        
        function updatePrice() {
            const selectedSize = document.querySelector('.size-option.selected');
            const basePrice = parseInt(selectedSize.dataset.price);
            const hasStand = hasStandCheckbox.checked;
            const standPrice = hasStand ? 125 : 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            const unitPrice = basePrice + standPrice;
            const total = unitPrice * quantity;
            
            const sizeTexts = {
                'small': 'Malé',
                'medium': 'Střední',
                'large': 'Velké'
            };
            const sizeText = sizeTexts[selectedSize.dataset.size] || 'Střední';
            
            document.getElementById('basePrice').textContent = sizeText + ': ' + basePrice.toLocaleString('cs-CZ') + ' Kč';
            document.getElementById('standPriceItem').style.display = hasStand ? 'flex' : 'none';
            if (hasStand) {
                const standTotal = standPrice * quantity;
                document.getElementById('standPriceTotal').textContent = standTotal.toLocaleString('cs-CZ') + ' Kč';
            }
            document.getElementById('quantityDisplay').textContent = quantity + 'x';
            document.getElementById('totalPrice').textContent = total.toLocaleString('cs-CZ') + ' Kč';
        }
        
        // Quantity input validation
        const quantityInput = document.getElementById('quantity');
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value) || 1;
            if (value < 1) value = 1;
            if (value > 50) value = 50;
            this.value = value;
            updatePrice();
        });
        
        // Logo shape selection
        const logoShapeOptions = document.querySelectorAll('[data-shape]');
        logoShapeOptions.forEach(option => {
            option.addEventListener('click', () => {
                logoShapeOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                option.querySelector('input[type="radio"]').checked = true;
                updatePreview();
            });
        });
        
        // Aspect ratio selection
        const aspectRatioOptions = document.querySelectorAll('[data-ratio]');
        aspectRatioOptions.forEach(option => {
            option.addEventListener('click', () => {
                aspectRatioOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                option.querySelector('input[type="radio"]').checked = true;
                updatePreview();
            });
        });
        
        // Color selection
        function selectColor(color) {
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
            document.querySelector(`[data-color="${color}"]`).classList.add('selected');
            document.getElementById('boxColor').value = color;
            updatePreview();
        }
        
        // Preview update
        function updatePreview() {
            const previewContainer = document.getElementById('previewContainer');
            const previewWrapper = document.getElementById('previewWrapper');
            const previewBox = document.getElementById('previewBox');
            const previewImage = document.getElementById('previewImage');
            const imagePreview = document.getElementById('imagePreview');
            
            if (!imagePreview.src || imagePreview.style.display === 'none') {
                previewContainer.style.display = 'none';
                return;
            }
            
            previewContainer.style.display = 'block';
            previewImage.src = imagePreview.src;
            
            // Update shape
            const selectedShape = document.querySelector('[data-shape].selected');
            const shape = selectedShape ? selectedShape.dataset.shape : 'round';
            previewWrapper.className = 'preview-image-wrapper';
            if (shape === 'round') {
                previewWrapper.classList.add('round');
            }
            
            // Update aspect ratio
            const selectedRatio = document.querySelector('[data-ratio].selected');
            const ratio = selectedRatio ? selectedRatio.dataset.ratio : '1:1';
            previewWrapper.classList.add(`ratio-${ratio.replace(':', '-')}`);
            
            // Update box color
            const selectedColor = document.querySelector('.color-option.selected');
            const color = selectedColor ? selectedColor.dataset.color : 'white';
            previewBox.className = 'preview-box';
            if (color === 'black') {
                previewBox.classList.add('black');
            }
        }
        
        // Update preview when options change
        logoShapeOptions.forEach(option => {
            option.addEventListener('click', () => {
                setTimeout(updatePreview, 50);
            });
        });
        
        aspectRatioOptions.forEach(option => {
            option.addEventListener('click', () => {
                setTimeout(updatePreview, 50);
            });
        });
        
        // Initial price update
        updatePrice();
    </script>
</body>
</html>

