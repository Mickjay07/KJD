<?php
session_start();
require_once 'config.php';
require_once '../functions.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Check if database connection is available
if (!isset($conn) || $conn === null) {
    $errorMessage = "Databázové připojení není dostupné. Zkuste to prosím později.";
    // Set a flag to skip database operations
    $db_available = false;
} else {
    $db_available = true;
}

// Inicializace proměnných
$productType = isset($_GET['type']) ? $_GET['type'] : 'product';
$table = ($productType === 'product2') ? 'product2' : (($productType === 'product3') ? 'product3' : 'product');
$successMessage = '';
$errorMessage = '';

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_available) {
    try {
        // Získání dat z formuláře
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = (float)($_POST['price'] ?? 0);
        $colorNames = $_POST['color_names'] ?? [];
        $colorPrices = $_POST['color_prices'] ?? [];
        $unavailableColors = $_POST['unavailable_colors'] ?? '';
        $availableFrom = $_POST['available_from'] ?? null;
        $baleni = $_POST['baleni'] ?? '';
        $stock_status = $_POST['stock_status'] ?? 'in_stock';
        $is_preorder = isset($_POST['is_preorder']) ? 1 : 0;
        $is_hidden = isset($_POST['is_hidden']) ? 1 : 0;

        // Zpracování variant
        $variants = [];
        $variantTypes = $_POST['variant_types'] ?? [];
        $variantOptions = $_POST['variant_options'] ?? [];
        $variantPrices = $_POST['variant_prices'] ?? [];
        $variantStocks = $_POST['variant_stocks'] ?? [];

        for ($i = 0; $i < count($variantTypes); $i++) {
            $type = trim($variantTypes[$i]);
            if (!empty($type)) {
                $variants[$type] = [];
                foreach ($variantOptions as $j => $option) {
                    if (!empty($option)) {
                        $variants[$type][$option] = [
                            'price' => (float)($variantPrices[$j] ?? 0),
                            'stock' => (int)($variantStocks[$j] ?? 0)
                        ];
                    }
                }
            }
        }

        // Převod variant na JSON
        $variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE);
        
        // Vytvoření pole barev a cen
        $colorPriceData = [];
        $colors = [];
        
        for ($i = 0; $i < count($colorNames); $i++) {
            if (!empty($colorNames[$i])) {
                $colorName = trim($colorNames[$i]);
                $colorPrice = isset($colorPrices[$i]) ? (int)$colorPrices[$i] : 0;
                
                $colorPriceData[$colorName] = $colorPrice;
                $colors[] = $colorName;
            }
        }
        
        // Převod na JSON pro uložení
        $colorPricesJson = json_encode($colorPriceData, JSON_UNESCAPED_UNICODE);
        $colorsString = implode(', ', $colors);

        // Upload obrázků
        $imageUrls = [];
        if (!empty($_FILES['images']['name'][0])) {
            $targetDir = __DIR__ . "/../uploads/products/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // Log debugging info
            error_log("Processing " . count($_FILES['images']['name']) . " image uploads");
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === 0) {
                    $fileName = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                    $targetFilePath = $targetDir . $fileName;
                    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
                    
                    error_log("Processing file: " . $_FILES['images']['name'][$key] . " of type: " . $fileType);
                    
                    if (in_array(strtolower($fileType), ['jpg', 'png', 'jpeg', 'webp', 'heic'])) {
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $targetFilePath)) {
                            // Save path relative to web root for database
                            $dbImagePath = "uploads/products/" . $fileName;
                            $imageUrls[] = $dbImagePath;
                            error_log("Successfully uploaded file to: " . $targetFilePath . " (DB path: " . $dbImagePath . ")");
                        } else {
                            error_log("Failed to move uploaded file: " . $_FILES['images']['name'][$key] . " to " . $targetFilePath);
                            $errorMessage .= "Nepodařilo se nahrát soubor " . $_FILES['images']['name'][$key] . ". ";
                        }
                    } else {
                        error_log("Invalid file type: " . $fileType);
                        $errorMessage .= "Neplatný typ souboru pro " . $_FILES['images']['name'][$key] . ". ";
                    }
                } else {
                    error_log("Upload error code: " . $_FILES['images']['error'][$key] . " for file: " . $_FILES['images']['name'][$key]);
                    $errorMessage .= "Chyba při nahrávání souboru " . $_FILES['images']['name'][$key] . ". ";
                }
            }
        }

        $imageUrl = implode(',', $imageUrls);
        error_log("Final image URL string: " . $imageUrl);

        // Vložení produktu do databáze
        $stmt = $conn->prepare("INSERT INTO $table (
            name, description, price, colors, unavailable_colors, 
            image_url, available_from, baleni, color_prices, variants, stock_status, is_preorder, is_hidden
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $name, $description, $price, $colorsString, $unavailableColors,
            $imageUrl, $availableFrom, $baleni, $colorPricesJson, $variantsJson, $stock_status, $is_preorder, $is_hidden
        ]);
        
        $successMessage = "Produkt byl úspěšně přidán.";
        
    } catch(PDOException $e) {
        $errorMessage = "Chyba při přidávání produktu: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_available) {
    $errorMessage = "Databázové připojení není dostupné. Zkuste to prosím později.";
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nový produkt - KJD Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    
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
      
      /* Form styles */
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
      
      .form-label {
        font-weight: 600;
        color: var(--kjd-dark-green);
        margin-bottom: 0.5rem;
      }
      
      /* Color palette */
      .color-palette-container {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        border: 2px solid var(--kjd-earth-green);
        border-radius: 12px;
        padding: 1.5rem;
      }
      
      .color-swatch {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s ease;
      }
      
      .color-swatch:hover {
        transform: scale(1.1);
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
        
        .form-control, .form-select {
          padding: 0.6rem;
        }
        
        .color-palette-container {
          padding: 1rem;
        }
        
        .color-swatch {
          width: 35px;
          height: 35px;
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
        
        .form-control, .form-select {
          padding: 0.5rem;
        }
        
        .color-palette-container {
          padding: 0.8rem;
        }
        
        .color-swatch {
          width: 30px;
          height: 30px;
        }
        
        .container-fluid {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
        }
      }
    </style>
    <script>
        $(document).ready(function() {
            // Inicializace Summernote pro editor
            $('#description, #baleni').summernote({
                height: 300,
                lang: 'cs-CZ',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                callbacks: {
                    onImageUpload: function(files) {
                        for (let i = 0; i < files.length; i++) {
                            uploadImage(files[i]);
                        }
                    }
                }
            });
            
            // Funkce pro nahrání obrázku
            function uploadImage(file) {
                let formData = new FormData();
                formData.append('file', file);
                
                $.ajax({
                    url: 'upload_image.php',
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(url) {
                        $('#description').summernote('insertImage', url);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error(textStatus + " " + errorThrown);
                        alert('Chyba při nahrávání obrázku.');
                    }
                });
            }
            
            // Přidání další barvy
            $('#addColorBtn').click(function() {
                var newRow = `
                    <div class="color-price-row d-flex mb-2">
                        <input type="text" class="form-control me-2" name="color_names[]" placeholder="Název barvy" required>
                        <input type="number" class="form-control me-2" name="color_prices[]" placeholder="Příplatek (Kč)" value="0" min="0" step="1">
                        <button type="button" class="btn btn-danger remove-color-btn"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                $('#colorPriceContainer').append(newRow);
            });
            
            // Odstranění barvy
            $(document).on('click', '.remove-color-btn', function() {
                $(this).closest('.color-price-row').remove();
            });

            // JavaScript pro správu variant
            $('#add-variant-type').click(function() {
                // ... (kód pro přidání nového typu varianty) ...
            });

            $(document).on('click', '.add-variant-option', function() {
                // ... (kód pro přidání nové možnosti varianty) ...
            });

            $(document).on('click', '.remove-variant-option', function() {
                $(this).closest('.variant-option-row').remove();
            });
        });

        // Funkce pro výběr barvy z palety
        function selectColor(hexColor, colorName) {
            // Přidání nového řádku s vybranou barvou, pokud ještě není vytvořen
            let colorAdded = false;
            
            // Zkontrolujeme, zda barva již nebyla přidána
            $('.color-price-container input[name="color_names[]"]').each(function() {
                if ($(this).val() === colorName) {
                    colorAdded = true;
                    // Zvýrazníme řádek s již přidanou barvou
                    const row = $(this).closest('.color-price-row');
                    row.css('background-color', '#e8f4ff').delay(1000).queue(function(next) {
                        $(this).css('background-color', '');
                        next();
                    });
                    return false; // Ukončí cyklus each
                }
            });
            
            if (!colorAdded) {
                // Přidáme nový řádek s vybranou barvou
                const newRow = `
                    <div class="color-price-row d-flex mb-2" style="background-color: #e8f4ff; transition: background-color 0.5s ease;">
                        <input type="text" class="form-control me-2" name="color_names[]" value="${colorName}" required>
                        <input type="number" class="form-control me-2" name="color_prices[]" placeholder="Příplatek (Kč)" value="0" min="0" step="1">
                        <div class="d-flex align-items-center me-2" style="width: 40px; height: 38px; background-color: ${hexColor}; border-radius: 4px;"></div>
                        <button type="button" class="btn btn-danger remove-color-btn"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                $('#colorPriceContainer').append(newRow);
                
                // Po 1 sekundě odstraníme zvýrazněný efekt
                setTimeout(function() {
                    $('.color-price-row').css('background-color', '');
                }, 1000);
            }
        }
        
        // Funkce pro náhled vybraných obrázků
        document.getElementById('images').addEventListener('change', function() {
            const previewContainer = document.getElementById('image-preview-container');
            previewContainer.innerHTML = '';
            
            if (this.files) {
                // Kontrola maximálního počtu obrázků
                if (this.files.length > 10) {
                    alert('Můžete nahrát maximálně 10 obrázků najednou.');
                    this.value = '';
                    return;
                }
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    
                    // Kontrola typu souboru pomocí nové funkce
                    if (!checkFileType(file)) {
                        continue;
                    }
                    
                    // Kontrola velikosti souboru (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Soubor ' + file.name + ' je příliš velký. Maximální velikost je 5 MB.');
                        continue;
                    }
                    
                    const col = document.createElement('div');
                    col.className = 'col-md-4 col-6 mb-3';
                    
                    const card = document.createElement('div');
                    card.className = 'card';
                    
                    const img = document.createElement('img');
                    img.className = 'card-img-top';
                    img.style.height = '150px';
                    img.style.objectFit = 'cover';
                    img.alt = 'Náhled';
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    
                    const cardBody = document.createElement('div');
                    cardBody.className = 'card-body p-2';
                    cardBody.innerHTML = '<p class="card-text small text-truncate">' + file.name + '</p>';
                    
                    card.appendChild(img);
                    card.appendChild(cardBody);
                    col.appendChild(card);
                    previewContainer.appendChild(col);
                }
            }
        });

        function checkFileType(file) {
            // Kontrola typu souboru
            const fileType = file.type;
            const fileName = file.name.toLowerCase();
            
            if (fileType.match('image.*') || fileName.endsWith('.heic')) {
                return true;
            }
            return false;
        }
    </script>
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
                    <h1><i class="fas fa-plus me-3"></i>Nový produkt</h1>
                    <p>Přidání nového produktu do systému</p>
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
                
                <form method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Základní informace -->
                            <div class="cart-item">
                                <h3 class="cart-product-name mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Základní informace
                                </h3>
                                <div class="mb-3">
                                    <label for="product_type" class="form-label">Typ produktu</label>
                                    <select class="form-select" id="product_type" name="product_type" <?php echo !$db_available ? 'disabled' : ''; ?>>
                                        <option value="product" <?php echo ($productType == 'product') ? 'selected' : ''; ?>>Kategorie 1</option>
                                        <option value="product2" <?php echo ($productType == 'product2') ? 'selected' : ''; ?>>Kategorie 2</option>
                                        <option value="product3" <?php echo ($productType == 'product3') ? 'selected' : ''; ?>>Kategorie 3</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Název produktu *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Popis</label>
                                    <textarea class="form-control" id="description" name="description" rows="5"><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
                                    <small class="text-muted">Pomocí editoru můžete text formátovat, přidávat odkazy a obrázky.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="price" class="form-label">Cena (Kč) *</label>
                                    <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $_POST['price'] ?? ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="available_from" class="form-label">Dostupnost od</label>
                                    <input type="datetime-local" class="form-control" id="available_from" name="available_from" 
                                           value="<?php echo htmlspecialchars($_POST['available_from'] ?? ''); ?>">
                                    <small class="text-muted">Ponechte prázdné pro okamžitou dostupnost</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="baleni" class="form-label">Co najdete v balení</label>
                                    <textarea class="form-control" id="baleni" name="baleni" rows="3"><?php echo isset($_POST['baleni']) ? $_POST['baleni'] : ''; ?></textarea>
                                    <small class="text-muted">Použijte editor pro formátování obsahu balení</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="stock_status" class="form-label">Stav skladu</label>
                                    <select class="form-control" id="stock_status" name="stock_status">
                                        <option value="in_stock" <?php echo (isset($_POST['stock_status']) && $_POST['stock_status'] === 'in_stock') ? 'selected' : ''; ?>>Skladem</option>
                                        <option value="out_of_stock" <?php echo (isset($_POST['stock_status']) && $_POST['stock_status'] === 'out_of_stock') ? 'selected' : ''; ?>>Vyprodáno</option>
                                        <option value="preorder" <?php echo (isset($_POST['stock_status']) && $_POST['stock_status'] === 'preorder') ? 'selected' : ''; ?>>Předobjednávky</option>
                                    </select>
                                    <small class="text-muted">Vyberte aktuální dostupnost produktu. Předobjednávky umožní zákazníkům objednat produkt, který aktuálně není skladem.</small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_preorder" name="is_preorder" <?php echo (isset($_POST['is_preorder'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_preorder">Jedná se o předobjednávku</label>
                                    <small class="form-text text-muted d-block">Zaškrtněte, pokud se jedná o produkt dostupný pouze na předobjednávku. Status předobjednávky bude zobrazen zákazníkům na stránce produktu.</small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_hidden" name="is_hidden" <?php echo (isset($_POST['is_hidden'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_hidden">Skrýt produkt (pouze s přímým odkazem)</label>
                                    <small class="form-text text-muted d-block">Zaškrtněte, pokud chcete produkt skrýt z hlavní stránky. Produkt bude dostupný pouze s přímým odkazem.</small>
                                </div>
                            </div>
                            
                            <!-- Barvy a ceny -->
                            <div class="cart-item">
                                <h3 class="cart-product-name mb-3">
                                    <i class="fas fa-palette me-2"></i>Barvy a ceny
                                </h3>
                                
                                <div class="mb-3">
                                    <label class="form-label">Dostupné barvy v systému</label>
                                    <div class="color-palette-container p-3" style="background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                                        <div class="row">
                                            <?php
                                            // Načtení mapování barev z functions.php
                                            if ($db_available) {
                                                $colorMap = getColorMap();
                                            } else {
                                                $colorMap = [];
                                            }
                                            
                                            if (!empty($colorMap)) {
                                                foreach ($colorMap as $hexColor => $colorName) {
                                                // Určení, zda je barva světlá nebo tmavá pro kontrastní text
                                                $r = hexdec(substr($hexColor, 1, 2));
                                                $g = hexdec(substr($hexColor, 3, 2));
                                                $b = hexdec(substr($hexColor, 5, 2));
                                                $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                                                $textColor = ($brightness > 128) ? 'black' : 'white';
                                                
                                                echo '<div class="col-md-2 col-4 mb-3">';
                                                echo '<div class="color-item" 
                                                           onclick="selectColor(\'' . $hexColor . '\', \'' . $colorName . '\')" 
                                                           style="cursor: pointer; border: 1px solid #ccc; border-radius: 8px; overflow: hidden;">';
                                                echo '<div style="height: 50px; background-color: ' . $hexColor . ';"></div>';
                                                echo '<div style="padding: 5px; background-color: rgba(0,0,0,0.05); font-size: 12px;">
                                                        <div class="text-center" style="font-weight: bold;">' . $colorName . '</div>
                                                        <div class="text-center">' . $hexColor . '</div>
                                                      </div>';
                                                echo '</div>';
                                                echo '</div>';
                                                }
                                            } else {
                                                echo '<div class="col-12 text-center"><p class="text-muted">Paleta barev není dostupná (databázové připojení).</p></div>';
                                            }
                                            ?>
                                        </div>
                                        <div class="mt-3">
                                            <p class="small text-muted">Klikněte na barvu pro přidání do formuláře.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="colors" class="form-label">Dostupné barvy</label>
                                    <div class="color-price-container" id="colorPriceContainer">
                                        <div class="color-price-row d-flex mb-2">
                                            <input type="text" class="form-control me-2" name="color_names[]" placeholder="Název barvy" required>
                                            <input type="number" class="form-control me-2" name="color_prices[]" placeholder="Příplatek (Kč)" value="0" min="0" step="1">
                                            <button type="button" class="btn btn-danger remove-color-btn"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary mt-2" id="addColorBtn">
                                        <i class="fas fa-plus"></i> Přidat další barvu
                                    </button>
                                    <small class="text-muted d-block mt-2">Zadejte názvy barev a případné příplatky k základní ceně produktu.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="unavailable_colors" class="form-label">Nedostupné barvy (oddělené čárkou)</label>
                                    <input type="text" class="form-control" id="unavailable_colors" name="unavailable_colors" value="<?php echo htmlspecialchars($_POST['unavailable_colors'] ?? ''); ?>">
                                    <small class="text-muted">Tyto barvy budou zobrazeny jako nedostupné.</small>
                                </div>
                            </div>

                            <!-- Varianty -->
                            <div class="cart-item">
                                <h3 class="cart-product-name mb-3">
                                    <i class="fas fa-cogs me-2"></i>Varianty produktu
                                </h3>
                                <div id="variants-container">
                                    <div class="variant-type mb-4">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label>Název typu varianty</label>
                                                <input type="text" class="form-control variant-type-name" 
                                                       placeholder="např. Velikost" name="variant_types[]">
                                            </div>
                                            <div class="col-md-8">
                                                <label>Možnosti a ceny</label>
                                                <div class="variant-options-container">
                                                    <div class="variant-option-row mb-2">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control variant-option-name" 
                                                                   placeholder="Název možnosti" name="variant_options[]">
                                                            <input type="number" class="form-control variant-option-price" 
                                                                   placeholder="Příplatek" name="variant_prices[]" value="0">
                                                            <input type="number" class="form-control variant-option-stock" 
                                                                   placeholder="Skladem" name="variant_stocks[]" value="0">
                                                            <button type="button" class="btn btn-danger remove-variant-option">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-secondary btn-sm mt-2 add-variant-option">
                                                    <i class="fas fa-plus"></i> Přidat možnost
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary mt-3" id="add-variant-type">
                                    <i class="fas fa-plus"></i> Přidat další typ varianty
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Obrázek -->
                            <div class="cart-item">
                                <h3 class="cart-product-name mb-3">
                                    <i class="fas fa-image me-2"></i>Obrázek produktu
                                </h3>
                                <div class="mb-3">
                                    <label for="images" class="form-label">Obrázky</label>
                                    <input type="file" class="form-control" id="images" name="images[]" multiple>
                                    <small class="text-muted">Vyberte jeden nebo více obrázků (JPG, PNG, JPEG, WEBP)</small>
                                </div>
                                <div id="image-preview-container" class="row mt-3">
                                    <!-- Zde se zobrazí náhled vybraných obrázků -->
                                </div>
                            </div>
                            
                            <!-- Tlačítka -->
                            <div class="cart-item">
                                <h3 class="cart-product-name mb-3">
                                    <i class="fas fa-cog me-2"></i>Akce
                                </h3>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-kjd-primary" <?php echo !$db_available ? 'disabled' : ''; ?>>
                                        <i class="fas fa-plus-circle me-2"></i> Přidat produkt
                                    </button>
                                    <a href="admin_products.php" class="btn btn-kjd-secondary">
                                        <i class="fas fa-times me-2"></i> Zrušit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
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