<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Inicializace proměnných
$productType = isset($_GET['type']) ? $_GET['type'] : 'product';
$table = ($productType === 'product2') ? 'product2' : (($productType === 'product3') ? 'product3' : 'product');
$successMessage = '';
$errorMessage = '';

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $targetDir = "uploads/products/";
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
                            $imageUrls[] = $targetFilePath;
                            error_log("Successfully uploaded file to: " . $targetFilePath);
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



        // Handle URL images
        $newImageUrls = $_POST['new_image_urls'] ?? [];
        // Filter out empty URLs
        $newImageUrls = array_filter($newImageUrls, function($url) { return !empty(trim($url)); });
        
        // Convert URL shorteners and Google Drive URLs - use direct image URL
        $newImageUrls = array_map(function($url) {
            // Follow shorturl redirects
            if (preg_match('/(shorturl\.at|bit\.ly|tinyurl\.com|goo\.gl)/', $url)) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                $actualUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                curl_close($ch);
                if ($actualUrl && $actualUrl !== $url) {
                    $url = $actualUrl;
                }
            }
            
            // Convert Google Drive URL
            if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return 'https://lh3.googleusercontent.com/d/' . $matches[1];
            }
            // Also handle /uc format and convert it
            if (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return 'https://lh3.googleusercontent.com/d/' . $matches[1];
            }
            return $url;
        }, $newImageUrls);
        
        $allImages = array_merge($imageUrls, $newImageUrls);
        $imageUrl = implode(',', $allImages);
        error_log("Final image URL string: " . $imageUrl);

        // Získat no_color_required checkbox
        $no_color_required = isset($_POST['no_color_required']) ? 1 : 0;
        $is_lamp_config = isset($_POST['is_lamp_config']) ? 1 : 0;
        
        // Zpracování barevných komponentů
        // Preferuj JSON z hidden pole, aby se předešlo problémům s dynamickými inputy
        $colorComponents = [];
        $postedComponentsJson = isset($_POST['color_components_json']) ? trim($_POST['color_components_json']) : '';
        if ($postedComponentsJson !== '') {
            $decoded = json_decode($postedComponentsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Enforce required=true for all
                foreach ($decoded as &$comp) { $comp['required'] = true; }
                $colorComponents = $decoded;
            }
        }
        if (empty($colorComponents) && !empty($_POST['component_names'])) {
            $componentNames = $_POST['component_names'];
            $componentColors = $_POST['component_colors'];
            for ($i = 0; $i < count($componentNames); $i++) {
                if (!empty($componentNames[$i]) && !empty($componentColors[$i])) {
                    $colorComponents[] = [
                        'name' => trim($componentNames[$i]),
                        'colors' => array_map('trim', explode(',', $componentColors[$i])),
                        'required' => true
                    ];
                }
            }
        }
        $colorComponentsJson = !empty($colorComponents) ? json_encode($colorComponents, JSON_UNESCAPED_UNICODE) : null;
        
        // Handle component images (Nožičky, Vršek variants)
        $componentImages = [];
        $componentImageTypes = ['nozicky', 'vrsek'];
        
        foreach ($componentImageTypes as $type) {
            $componentImages[$type] = [];
            
            // Get existing images for this component (from form)
            $existingComponentImages = $_POST['existing_component_images_' . $type] ?? [];
            $componentNames = $_POST['component_image_names_' . $type] ?? [];
            $existingComponentColors = $_POST['existing_component_colors_' . $type] ?? [];
            
            // Process existing images with their color variants
            foreach ($existingComponentImages as $idx => $imgPath) {
                if (!empty($imgPath) && !empty($componentNames[$idx])) {
                    $variant = [
                        'image' => $imgPath,
                        'name' => trim($componentNames[$idx]),
                        'colors' => []
                    ];
                    
                    // Process existing color variants for this variant
                    if (isset($existingComponentColors[$idx]) && is_array($existingComponentColors[$idx])) {
                        foreach ($existingComponentColors[$idx] as $colorIdx => $colorData) {
                            if (!empty($colorData['image']) && !empty($colorData['name'])) {
                                $variant['colors'][] = [
                                    'color' => trim($colorData['name']),
                                    'image' => trim($colorData['image'])
                                ];
                            }
                        }
                    }
                    
                    $componentImages[$type][] = $variant;
                }
            }
            
            // Handle new uploads for this component
            if (!empty($_FILES['component_images_' . $type]['name'][0])) {
                $uploadDir = "uploads/products/components/";
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                foreach ($_FILES['component_images_' . $type]['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['component_images_' . $type]['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = uniqid() . '_' . basename($_FILES['component_images_' . $type]['name'][$key]);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $dbImagePath = $uploadDir . $fileName;
                            $newName = $_POST['new_component_image_names_' . $type][$key] ?? 'Varianta ' . (count($componentImages[$type]) + 1);
                            
                            $variant = [
                                'image' => $dbImagePath,
                                'name' => trim($newName),
                                'colors' => []
                            ];
                            
                            // Handle color images for this new variant
                            if (!empty($_FILES['component_color_images_' . $type]['name'][$key])) {
                                foreach ($_FILES['component_color_images_' . $type]['tmp_name'][$key] as $colorKey => $colorTmpName) {
                                    if ($_FILES['component_color_images_' . $type]['error'][$key][$colorKey] === UPLOAD_ERR_OK) {
                                        $colorFileName = uniqid() . '_' . basename($_FILES['component_color_images_' . $type]['name'][$key][$colorKey]);
                                        $colorTargetPath = $uploadDir . $colorFileName;
                                        
                                        if (move_uploaded_file($colorTmpName, $colorTargetPath)) {
                                            $colorDbPath = $uploadDir . $colorFileName;
                                            $colorName = $_POST['new_component_color_names_' . $type][$key][$colorKey] ?? 'Barva ' . (count($variant['colors']) + 1);
                                            $variant['colors'][] = [
                                                'color' => trim($colorName),
                                                'image' => $colorDbPath
                                            ];
                                        }
                                    }
                                }
                            }
                            
                            $componentImages[$type][] = $variant;
                        }
                    }
                }
            }
        }
        
        $componentImagesJson = !empty($componentImages) ? json_encode($componentImages, JSON_UNESCAPED_UNICODE) : null;
        
        // Vložení produktu do databáze
        try {
            $stmt = $conn->prepare("INSERT INTO $table (
                name, description, price, colors, unavailable_colors, no_color_required, color_components,
                image_url, available_from, baleni, color_prices, variants, stock_status, is_preorder, component_images, is_lamp_config
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $name, $description, $price, $colorsString, $unavailableColors, $no_color_required, $colorComponentsJson,
                $imageUrl, $availableFrom, $baleni, $colorPricesJson, $variantsJson, $stock_status, $is_preorder, $componentImagesJson, $is_lamp_config
            ]);
        } catch (PDOException $e) {
            // If component_images column doesn't exist, insert without it
            if (strpos($e->getMessage(), 'component_images') !== false) {
                $stmt = $conn->prepare("INSERT INTO $table (
                    name, description, price, colors, unavailable_colors, no_color_required, color_components,
                    image_url, available_from, baleni, color_prices, variants, stock_status, is_preorder, is_lamp_config
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $name, $description, $price, $colorsString, $unavailableColors, $no_color_required, $colorComponentsJson,
                    $imageUrl, $availableFrom, $baleni, $colorPricesJson, $variantsJson, $stock_status, $is_preorder, $is_lamp_config
                ]);
            } else {
                throw $e;
            }
        }
        
        $successMessage = "Produkt byl úspěšně přidán.";
        
    } catch(PDOException $e) {
        $errorMessage = "Chyba při přidávání produktu: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nový produkt - KJD Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .form-section {
            background: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e5e5e7;
        }
        
        .form-section h5 {
            color: #1d1d1f;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #1d1d1f;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #d2d2d7;
            border-radius: 8px;
            padding: 0.75rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #CABA9C;
            box-shadow: 0 0 0 0.2rem rgba(202, 186, 156, 0.25);
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: #CABA9C;
            border-color: #CABA9C;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #b5a084;
            border-color: #b5a084;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .color-palette-container {
            background-color: #f8f9fa;
            border: 1px solid #e5e5e7;
            border-radius: 8px;
            padding: 1rem;
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
        
        // Barevné komponenty (např. Nožičky + Vršek)
        let componentIndex = 0;
        $('#addColorComponentBtn').click(function() {
            const componentHtml = `
                <div class="card mb-3 color-component-card" data-index="${componentIndex}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Komponent #${componentIndex + 1}</h6>
                            <button type="button" class="btn btn-sm btn-danger remove-component-btn">
                                <i class="fas fa-trash"></i> Odstranit
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Název komponenty *</label>
                                <input type="text" class="form-control" name="component_names[]" 
                                       placeholder="např. Nožičky, Vršek" required>
                                <small class="text-muted">Zobrazí se zákazníkovi</small>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Dostupné barvy *</label>
                                <input type="text" class="form-control" name="component_colors[]" 
                                       placeholder="Černá, Bílá, Přírodní" required>
                                <small class="text-muted">Oddělujte čárkou (všechny komponenty jsou povinné)</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('#colorComponentsContainer').append(componentHtml);
            componentIndex++;
        });
        
        // Odstranění komponenty
        $(document).on('click', '.remove-component-btn', function() {
            $(this).closest('.color-component-card').remove();
        });
        
        // Component image variants management
        $('input[type="file"][id^="component_images_"]').on('change', function() {
            const type = $(this).attr('id').replace('component_images_', '');
            const namesContainer = $(`.new-variant-names[data-type="${type}"]`);
            namesContainer.empty();
            
            if (this.files && this.files.length > 0) {
                Array.from(this.files).forEach(function(file, index) {
                    const nameInput = $('<div>').addClass('mb-2').html(`
                        <label class="form-label small">Název pro: ${file.name}</label>
                        <input type="text" class="form-control form-control-sm" 
                               name="new_component_image_names_${type}[]" 
                               placeholder="např. Varianta ${index + 1}" 
                               value="Varianta ${index + 1}">
                    `);
                    namesContainer.append(nameInput);
                });
            }
        });
        
        // Remove variant button
        $(document).on('click', '.remove-variant-btn', function() {
            $(this).closest('.variant-item').remove();
        });
        // Handle URL image addition
        document.getElementById('add_image_url_btn')?.addEventListener('click', function() {
            const urlInput = document.getElementById('image_url_input');
            let url = urlInput.value.trim();
            if (!url) return;

            // Handle URL shorteners - for preview (JavaScript can't follow redirects easily, so we'll show original)
            // The PHP backend will handle the redirect when saving
            
            // Convert Google Drive URL - use direct image URL
            const driveMatch = url.match(/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/);
            if (driveMatch) {
                url = 'https://lh3.googleusercontent.com/d/' + driveMatch[1];
            } else {
                // Also handle /uc format and convert it
                const ucMatch = url.match(/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/);
                if (ucMatch) {
                    url = 'https://lh3.googleusercontent.com/d/' + ucMatch[1];
                }
            }

            const previewContainer = document.getElementById('image-preview-container');
            
            const col = document.createElement('div');
            col.className = 'col-md-4 col-6 mb-3';
            
            const card = document.createElement('div');
            card.className = 'card';
            
            const img = document.createElement('img');
            img.className = 'card-img-top';
            img.style.height = '150px';
            img.style.objectFit = 'cover';
            img.alt = 'Náhled';
            img.referrerPolicy = 'no-referrer';
            img.src = url;
            img.onerror = function() { this.src = '../assets/images/placeholder.png'; };
            
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body p-2';
            cardBody.innerHTML = `
                <p class="card-text small text-truncate text-break">${url}</p>
                <input type="hidden" name="new_image_urls[]" value="${url}">
                <button type="button" class="btn btn-sm btn-danger w-100 remove-url-image">Odstranit</button>
            `;
            
            card.appendChild(img);
            card.appendChild(cardBody);
            col.appendChild(card);
            previewContainer.appendChild(col);
            
            urlInput.value = '';
        });

        // Remove URL image
        $(document).on('click', '.remove-url-image', function() {
            $(this).closest('.col-md-4').remove();
        });
    </script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'admin_header.php'; ?>

            <!-- Main content -->
            <div class="col-md-10 main-content">
                <h1 class="mb-4">Nový produkt</h1>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errorMessage; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data" id="newProductForm">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Základní informace -->
                            <div class="form-section">
                                <h5 class="mb-3">Základní informace</h5>
                                <div class="mb-3">
                                    <label for="product_type" class="form-label">Typ produktu</label>
                                    <select class="form-select" id="product_type" name="product_type">
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
                            </div>
                            
                            <!-- Barvy a ceny -->
                            <div class="form-section">
                                <h5 class="mb-3">Barvy a ceny</h5>
                                
                                <div class="mb-3">
                                    <label class="form-label">Dostupné barvy v systému</label>
                                    <div class="color-palette-container p-3" style="background-color: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                                        <div class="row">
                                            <?php
                                            // Načtení mapování barev z functions.php
                                            $colorMap = getColorMap();
                                            
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
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="no_color_required" name="no_color_required">
                                        <label class="form-check-label" for="no_color_required">
                                            <strong>Produkt bez barvy</strong> (nevyžadovat výběr barvy - pro vouchery, multibarevné produkty apod.)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_lamp_config" name="is_lamp_config">
                                        <label class="form-check-label" for="is_lamp_config">
                                            <strong>Aktivovat konfigurátor lamp</strong> (zobrazí výběr podstavce a stínidla z katalogu)
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Barevné komponenty (např. nožičky + vršek) -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-palette me-2"></i>Barevné komponenty
                                        <small class="text-muted">(pro produkty s více barvami - např. lampy)</small>
                                    </h5>
                                    <div id="colorComponentsContainer">
                                        <!-- Komponenty se přidají dynamicky -->
                                    </div>
                                    <!-- Hidden JSON field to ensure submission even for dynamic inputs -->
                                    <input type="hidden" name="color_components_json" id="color_components_json" value="">
                                    <button type="button" class="btn btn-success" id="addColorComponentBtn">
                                        <i class="fas fa-plus"></i> Přidat komponent (např. Nožičky, Vršek)
                                    </button>
                                    <small class="text-muted d-block mt-2">
                                        Použij pro produkty, kde si zákazník může vybrat barvu pro různé části (např. barva nožiček a barva vršku lampy).
                                    </small>
                                </div>
                                
                                <!-- Varianty obrázků komponent (Nožičky, Vršek) -->
                                <div class="mb-4">
                                    <h5 class="mb-3">
                                        <i class="fas fa-images me-2"></i>Varianty obrázků komponent
                                        <small class="text-muted">(pro konfigurátor vlastní barvy)</small>
                                    </h5>
                                    
                                    <?php
                                    $componentImageTypes = [
                                        'nozicky' => 'Nožičky',
                                        'vrsek' => 'Vršek'
                                    ];
                                    
                                    foreach ($componentImageTypes as $type => $label):
                                    ?>
                                    <div class="card mb-3 component-image-variants" data-type="<?= $type ?>">
                                        <div class="card-body">
                                            <h6 class="mb-3"><?= htmlspecialchars($label) ?></h6>
                                            
                                            <!-- Existing variants (empty for new product) -->
                                            <div class="existing-variants mb-3" data-type="<?= $type ?>">
                                                <p class="text-muted small">Zatím nejsou přidány žádné varianty.</p>
                                            </div>
                                            
                                            <!-- Add new variants -->
                                            <div class="mb-3">
                                                <label class="form-label small">Přidat nové varianty</label>
                                                <input type="file" class="form-control form-control-sm" 
                                                       name="component_images_<?= $type ?>[]" 
                                                       multiple 
                                                       accept="image/*"
                                                       id="component_images_<?= $type ?>">
                                                <div class="form-text small">Můžete nahrát více obrázků najednou</div>
                                            </div>
                                            
                                            <!-- Names for new uploads -->
                                            <div class="new-variant-names" data-type="<?= $type ?>"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <small class="text-muted d-block mt-2">
                                        Tyto varianty se zobrazí v konfigurátoru na stránce produktu. Zákazníci si pomocí šipek mohou vybírat různé varianty nožiček a vršku.
                                    </small>
                                </div>
                            </div>

                            <!-- Varianty -->
                            <div class="form-section">
                                <h5 class="mb-3">Varianty produktu</h5>
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
                            <div class="form-section">
                                <h5 class="mb-3">Obrázek produktu</h5>
                                <div class="mb-3">
                                    <label for="images" class="form-label">Obrázky</label>
                                    <input type="file" class="form-control" id="images" name="images[]" multiple>
                                    <small class="text-muted">Vyberte jeden nebo více obrázků (JPG, PNG, JPEG, WEBP)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nebo přidat URL obrázku (např. Google Drive)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="image_url_input" placeholder="https://...">
                                        <button type="button" class="btn btn-outline-secondary" id="add_image_url_btn">
                                            <i class="fas fa-plus"></i> Přidat URL
                                        </button>
                                    </div>
                                </div>
                                <div id="image-preview-container" class="row mt-3">
                                    <!-- Zde se zobrazí náhled vybraných obrázků -->
                                </div>
                            </div>
                            
                            <!-- Tlačítka -->
                            <div class="form-section">
                                <h5 class="mb-3">Akce</h5>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus-circle me-2"></i> Přidat produkt
                                    </button>
                                    <a href="admin_products.php" class="btn btn-outline-secondary">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>