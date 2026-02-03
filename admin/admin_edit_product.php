<?php
// Start session and check admin login
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Include database connection
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../functions.php';

// Set page title and initialize variables
$pageTitle = 'Upravit produkt';
$product = [];
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        
        $name = $_POST['name'] ?? '';
        $model = $_POST['model'] ?? '';

        $price = isset($_POST['price']) ? (float)str_replace([' ', ','], ['', '.'], $_POST['price']) : 0;
        $description = $_POST['description'] ?? '';
        $html_tech_specs = $_POST['html_tech_specs'] ?? '';
        $availability = $_POST['availability'] ?? 'Skladem';
        $colors = !empty($_POST['colors']) ? $_POST['colors'] : null;
        $unavailable_colors = !empty($_POST['unavailable_colors']) ? $_POST['unavailable_colors'] : null;
        $no_color_required = isset($_POST['no_color_required']) ? 1 : 0;
        $is_lamp_config = isset($_POST['is_lamp_config']) ? 1 : 0;
        
        // Zpracování barevných komponentů
        $colorComponents = [];
        if (!empty($_POST['component_names'])) {
            $componentNames = $_POST['component_names'];
            $componentColors = $_POST['component_colors'];
            
            for ($i = 0; $i < count($componentNames); $i++) {
                if (!empty($componentNames[$i]) && !empty($componentColors[$i])) {
                    // Všechny komponenty jsou vždy povinné
                    $colorComponents[] = [
                        'name' => trim($componentNames[$i]),
                        'colors' => array_map('trim', explode(',', $componentColors[$i])),
                        'required' => true
                    ];
                }
            }
        }
        // Prefer JSON posted from hidden field to avoid issues with dynamic inputs
        $postedComponentsJson = isset($_POST['color_components_json']) ? trim($_POST['color_components_json']) : '';
        $colorComponentsJson = null;
        if ($postedComponentsJson !== '') {
            $decoded = json_decode($postedComponentsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $colorComponentsJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }
        if ($colorComponentsJson === null) {
            $colorComponentsJson = !empty($colorComponents) ? json_encode($colorComponents, JSON_UNESCAPED_UNICODE) : null;
        }
        
        
        $stock_status = $_POST['stock_status'] ?? 'in_stock';
        $is_preorder = isset($_POST['is_preorder']) ? 1 : 0;
        $sale_enabled = isset($_POST['sale_enabled']) ? 1 : 0;
        $sale_price = !empty($_POST['sale_price']) ? (float)str_replace([' ', ','], ['', '.'], $_POST['sale_price']) : null;
        $sale_end = !empty($_POST['sale_end']) ? $_POST['sale_end'] : null;
        $sale_start = !empty($_POST['sale_start']) ? $_POST['sale_start'] : null;
        $release_date = !empty($_POST['release_date']) ? $_POST['release_date'] : null;
        $available_from = !empty($_POST['available_from']) ? $_POST['available_from'] : date('Y-m-d H:i:s');
        $is_hidden = isset($_POST['is_hidden']) ? 1 : 0;
        
        // Build variants JSON from dynamic form inputs
        $variants = [];
        $variantTypes = $_POST['variant_types'] ?? [];
        $variantOptions = $_POST['variant_options'] ?? [];
        $variantPrices = $_POST['variant_prices'] ?? [];
        $variantStocks = $_POST['variant_stocks'] ?? [];
        $variantColors = $_POST['variant_colors'] ?? [];

        $rowsCount = max(count($variantTypes), count($variantOptions), count($variantPrices), count($variantStocks));
        for ($i = 0; $i < $rowsCount; $i++) {
            $type = trim($variantTypes[$i] ?? '');
            $opt = trim($variantOptions[$i] ?? '');
            if ($type === '' && $opt === '') { continue; }
            if (!isset($variants[$type])) { $variants[$type] = []; }
            if ($opt !== '') {
                $entry = [
                    'price' => (float)($variantPrices[$i] ?? 0),
                    'stock' => (int)($variantStocks[$i] ?? 0),
                ];
                // Optional per-option color restriction (CSV)
                $colorsCsv = trim($variantColors[$i] ?? '');
                if ($colorsCsv !== '') {
                    $entry['colors'] = array_values(array_filter(array_map('trim', explode(',', $colorsCsv))));
                }
                $variants[$type][$opt] = $entry;
            }
        }
        $variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE);

        // Handle file uploads (save to root uploads/products)
        $uploadedImages = [];
        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        // Save DB path relative to web root
                        $dbImagePath = "uploads/products/" . $fileName;
                        $uploadedImages[] = $dbImagePath;
                        error_log("Successfully uploaded image: " . $targetPath . " (DB path: " . $dbImagePath . ")");
                    }
                }
            }
        }
        
        // Combine existing, new uploaded, and new URL images
        $existingImages = $_POST['existing_images'] ?? [];
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
                        // Convert Google Drive URLs to thumbnail API
                if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
                    return 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
                    return 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                }          return $url;
        }, $newImageUrls);
        
        $allImages = array_merge($existingImages, $uploadedImages, $newImageUrls);
        $image_url = !empty($allImages) ? implode(',', $allImages) : null;
        
        // Handle component images (Nožičky, Vršek variants)
        $componentImages = [];
        $componentImageTypes = ['nozicky', 'vrsek']; // Types: nozicky, vrsek
        
        foreach ($componentImageTypes as $type) {
            $componentImages[$type] = [];
            
            // Get existing images for this component
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
                    
                    // Handle new color image uploads for existing variants (File Upload)
                    if (!empty($_FILES['component_color_images_' . $type]['name'][$idx])) {
                        $uploadDir = __DIR__ . '/../uploads/products/components/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        foreach ($_FILES['component_color_images_' . $type]['tmp_name'][$idx] as $colorKey => $colorTmpName) {
                            if ($_FILES['component_color_images_' . $type]['error'][$idx][$colorKey] === UPLOAD_ERR_OK) {
                                $colorFileName = uniqid() . '_' . basename($_FILES['component_color_images_' . $type]['name'][$idx][$colorKey]);
                                $colorTargetPath = $uploadDir . $colorFileName;
                                
                                if (move_uploaded_file($colorTmpName, $colorTargetPath)) {
                                    $colorDbPath = "uploads/products/components/" . $colorFileName;
                                    $colorName = $_POST['new_component_color_names_' . $type][$idx][$colorKey] ?? 'Barva ' . (count($variant['colors']) + 1);
                                    $variant['colors'][] = [
                                        'color' => trim($colorName),
                                        'image' => $colorDbPath
                                    ];
                                }
                            }
                        }
                    }
                    
                    // Handle bulk color URL addition for existing variants (NEW SIMPLE SYSTEM)
                    if (!empty($_POST['component_color_images_urls_bulk_' . $type][$idx])) {
                        $urlsText = trim($_POST['component_color_images_urls_bulk_' . $type][$idx]);
                        $urls = array_filter(array_map('trim', explode("\n", $urlsText)));
                        
                        foreach ($urls as $colorIdx => $colorUrl) {
                            if (!empty($colorUrl)) {
                                // Convert Google Drive URLs to thumbnail API
                                $processedColorUrl = trim($colorUrl);
                                if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $processedColorUrl, $matches)) {
                                    $processedColorUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                                } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $processedColorUrl, $matches)) {
                                    $processedColorUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                                }
                                
                                $variant['colors'][] = [
                                    'color' => 'Barva ' . ($colorIdx + 1),
                                    'image' => $processedColorUrl
                                ];
                            }
                        }
                    }

                    // Handle new color image uploads for existing variants (URL) - OLD SYSTEM
                    if (!empty($_POST['component_color_images_url_' . $type][$idx])) {
                        foreach ($_POST['component_color_images_url_' . $type][$idx] as $colorKey => $colorUrl) {
                            if (!empty($colorUrl)) {
                                $colorName = $_POST['new_component_color_names_' . $type][$idx][$colorKey] ?? 'Barva ' . (count($variant['colors']) + 1);
                                
                                // Convert Google Drive URLs to thumbnail API
                                $processedColorUrl = trim($colorUrl);
                                if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $processedColorUrl, $matches)) {
                                    $processedColorUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                                } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $processedColorUrl, $matches)) {
                                    $processedColorUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                                }
                                
                                $variant['colors'][] = [
                                    'color' => trim($colorName),
                                    'image' => $processedColorUrl
                                ];
                            }
                        }
                    }
                    
                    $componentImages[$type][] = $variant;
                }
            }
            
            // Handle new uploads for this component (File Upload)
            if (!empty($_FILES['component_images_' . $type]['name'][0])) {
                $uploadDir = __DIR__ . '/../uploads/products/components/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                foreach ($_FILES['component_images_' . $type]['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['component_images_' . $type]['error'][$key] === UPLOAD_ERR_OK) {
                        $fileName = uniqid() . '_' . basename($_FILES['component_images_' . $type]['name'][$key]);
                        $targetPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $dbImagePath = "uploads/products/components/" . $fileName;
                            $newName = $_POST['new_component_image_names_' . $type][$key] ?? 'Varianta ' . (count($componentImages[$type]) + 1);
                            
                            $variant = [
                                'image' => $dbImagePath,
                                'name' => trim($newName),
                                'colors' => []
                            ];
                            
                            // Handle color images for this new variant (File Upload)
                            if (!empty($_FILES['component_color_images_' . $type]['name'][$key])) {
                                foreach ($_FILES['component_color_images_' . $type]['tmp_name'][$key] as $colorKey => $colorTmpName) {
                                    if ($_FILES['component_color_images_' . $type]['error'][$key][$colorKey] === UPLOAD_ERR_OK) {
                                        $colorFileName = uniqid() . '_' . basename($_FILES['component_color_images_' . $type]['name'][$key][$colorKey]);
                                        $colorTargetPath = $uploadDir . $colorFileName;
                                        
                                        if (move_uploaded_file($colorTmpName, $colorTargetPath)) {
                                            $colorDbPath = "uploads/products/components/" . $colorFileName;
                                            $colorName = $_POST['new_component_color_names_' . $type][$key][$colorKey] ?? 'Barva ' . (count($variant['colors']) + 1);
                                            $variant['colors'][] = [
                                                'color' => trim($colorName),
                                                'image' => $colorDbPath
                                            ];
                                        }
                                    }
                                }
                            }

                            // Handle color images for this new variant (URL)
                            if (!empty($_POST['component_color_images_url_' . $type][$key])) {
                                foreach ($_POST['component_color_images_url_' . $type][$key] as $colorKey => $colorUrl) {
                                    if (!empty($colorUrl)) {
                                        $colorName = $_POST['new_component_color_names_' . $type][$key][$colorKey] ?? 'Barva ' . (count($variant['colors']) + 1);
                                        $variant['colors'][] = [
                                            'color' => trim($colorName),
                                            'image' => trim($colorUrl)
                                        ];
                                    }
                                }
                            }
                            
                            $componentImages[$type][] = $variant;
                        }
                    }
                }
            }
            
            // Handle bulk URL variants (NEW SIMPLE SYSTEM)
            if (!empty($_POST['component_images_urls_bulk_' . $type])) {
                $urlsText = trim($_POST['component_images_urls_bulk_' . $type]);
                $urls = array_filter(array_map('trim', explode("\n", $urlsText)));
                
                $successMessage .= "<br><strong>✓ Zpracovávám " . count($urls) . " URL pro typ '$type'</strong>";
                
                foreach ($urls as $idx => $url) {
                    if (!empty($url)) {
                        // Convert Google Drive URLs to thumbnail API URLs
                        $processedUrl = trim($url);
                        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $processedUrl, $matches)) {
                            $processedUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                        } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $processedUrl, $matches)) {
                            $processedUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                        }
                        
                        $variant = [
                            'image' => $processedUrl,
                            'name' => 'Varianta ' . ($idx + 1),
                            'colors' => []
                        ];
                        
                        $componentImages[$type][] = $variant;
                    }
                }
            }

            // Handle new variants via URL (OLD SYSTEM - keep for compatibility)
            if (!empty($_POST['component_images_url_' . $type])) {
                $urlCount = count($_POST['component_images_url_' . $type]);
                $successMessage .= "<br><strong>✓ Přidáno $urlCount URL variant(y) pro typ '$type'</strong>";
                
                foreach ($_POST['component_images_url_' . $type] as $key => $url) {
                    if (!empty($url)) {
                        $newName = $_POST['new_component_image_names_url_' . $type][$key] ?? 'Varianta ' . (count($componentImages[$type]) + 1);
                        
                        // Convert Google Drive URLs to thumbnail API
                        $processedUrl = trim($url);
                        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $processedUrl, $matches)) {
                            $processedUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                        } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $processedUrl, $matches)) {
                            $processedUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                        }
                        
                        $variant = [
                            'image' => $processedUrl,
                            'name' => trim($newName),
                            'colors' => []
                        ];

                        // Handle color images for this new variant (URL)
                        if (!empty($_POST['component_color_images_url_url_' . $type][$key])) {
                            foreach ($_POST['component_color_images_url_url_' . $type][$key] as $colorKey => $colorUrl) {
                                if (!empty($colorUrl)) {
                                    $colorName = $_POST['new_component_color_names_url_' . $type][$key][$colorKey] ?? 'Barva ' . (count($variant['colors']) + 1);
                                                                        // Convert Google Drive URLs to thumbnail API
                                        $processedColorUrl = trim($colorUrl);
                                        if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $processedColorUrl, $matches)) {
                                            $processedColorUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                                        } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $processedColorUrl, $matches)) {
                                            $processedColorUrl = 'https://drive.google.com/thumbnail?id=' . $matches[1] . '&sz=w1000';
                                        }                                  
                                    $variant['colors'][] = [
                                        'color' => trim($colorName),
                                        'image' => $processedColorUrl
                                    ];
                                }
                            }
                        }
                        
                        $componentImages[$type][] = $variant;
                    }
                }
            }
        }
        
        $componentImagesJson = !empty($componentImages) ? json_encode($componentImages, JSON_UNESCAPED_UNICODE) : null;
        
        // Prepare SQL based on add/edit mode
        if ($productId > 0) {
            // Update existing product
            
            // Try to update with component_images, fallback if column doesn't exist
            try {
                $stmt = $conn->prepare("UPDATE product SET 
                    name = ?, model = ?, price = ?, description = ?, 
                    html_tech_specs = ?, availability = ?, colors = ?, 
                    unavailable_colors = ?, no_color_required = ?, color_components = ?, image_url = ?, stock_status = ?, 
                    is_preorder = ?, sale_enabled = ?, sale_price = ?, 
                    sale_end = ?, release_date = ?, available_from = ?,
                    variants = ?, is_hidden = ?, component_images = ?, is_lamp_config = ?, sale_start = ?
                    WHERE id = ?");
                    
                $result = $stmt->execute([
                    $name, $model, $price, $description,
                    $html_tech_specs, $availability, $colors,
                    $unavailable_colors, $no_color_required, $colorComponentsJson, $image_url, $stock_status,
                    $is_preorder, $sale_enabled, $sale_price,
                    $sale_end, $release_date, $available_from,
                    $variantsJson, $is_hidden, $componentImagesJson, $is_lamp_config,
                    $sale_start,
                    $productId
                ]);
            } catch (PDOException $e) {
                // If component_images column doesn't exist, update without it
                if (strpos($e->getMessage(), 'component_images') !== false) {
                    $stmt = $conn->prepare("UPDATE product SET 
                        name = ?, model = ?, price = ?, description = ?, 
                        html_tech_specs = ?, availability = ?, colors = ?, 
                        unavailable_colors = ?, no_color_required = ?, color_components = ?, image_url = ?, stock_status = ?, 
                        is_preorder = ?, sale_enabled = ?, sale_price = ?, 
                        sale_end = ?, release_date = ?, available_from = ?,
                        variants = ?, is_hidden = ?, is_lamp_config = ?, sale_start = ?
                        WHERE id = ?");
                        
                    $result = $stmt->execute([
                        $name, $model, $price, $description,
                        $html_tech_specs, $availability, $colors,
                        $unavailable_colors, $no_color_required, $colorComponentsJson, $image_url, $stock_status,
                        $is_preorder, $sale_enabled, $sale_price,
                        $sale_end, $release_date, $available_from,
                        $variantsJson, $is_hidden, $is_lamp_config,
                        $sale_start,
                        $productId
                    ]);
                } else {
                    throw $e;
                }
            }
            $_SESSION['success_message'] = 'Produkt byl úspěšně aktualizován.';
        } else {
            // Insert new product
            try {

                $stmt = $conn->prepare("INSERT INTO product (
                    name, model, price, description, html_tech_specs, 
                    availability, colors, unavailable_colors, no_color_required, color_components, image_url, 
                    stock_status, is_preorder, sale_enabled, sale_price, 
                    sale_end, release_date, available_from, variants, is_hidden, component_images, is_lamp_config, sale_start
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                $stmt->execute([
                    $name, $model, $price, $description, $html_tech_specs,
                    $availability, $colors, $unavailable_colors, $no_color_required, $colorComponentsJson, $image_url,
                    $stock_status, $is_preorder, $sale_enabled, $sale_price,
                    $sale_end, $release_date, $available_from, $variantsJson, $is_hidden, $componentImagesJson, $is_lamp_config, $sale_start
                ]);
            } catch (PDOException $e) {
                // If component_images column doesn't exist, insert without it
                if (strpos($e->getMessage(), 'component_images') !== false) {
                    $stmt = $conn->prepare("INSERT INTO product (
                        name, model, price, description, html_tech_specs, 
                        availability, colors, unavailable_colors, no_color_required, color_components, image_url, 
                        stock_status, is_preorder, sale_enabled, sale_price, 
                        sale_end, release_date, available_from, variants, is_hidden, is_lamp_config, sale_start
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                    $stmt->execute([
                        $name, $model, $price, $description, $html_tech_specs,
                        $availability, $colors, $unavailable_colors, $no_color_required, $colorComponentsJson, $image_url,
                        $stock_status, $is_preorder, $sale_enabled, $sale_price,
                        $sale_end, $release_date, $available_from, $variantsJson, $is_hidden, $is_lamp_config, $sale_start
                    ]);
                } else {
                    throw $e;
                }
            }
            
            $productId = $conn->lastInsertId();
            $_SESSION['success_message'] = 'Produkt byl úspěšně přidán.';
        }
        
        // Redirect to prevent form resubmission
        header("Location: admin_edit_product.php?id=" . $productId);
        exit;
        
    } catch (PDOException $e) {
        $error = 'Chyba při ukládání produktu: ' . $e->getMessage();
        $_SESSION['error_message'] = $error;
        error_log('[admin_edit_product] PDO Error: ' . $e->getMessage());
        error_log('[admin_edit_product] SQL State: ' . $e->getCode());
        
        // Pokud je chyba kvůli neexistujícímu sloupci, zobraz specifickou zprávu
        if (strpos($e->getMessage(), 'color_components') !== false) {
            $_SESSION['error_message'] = 'Chyba: Sloupec color_components neexistuje v databázi. Spusť prosím SQL příkaz z admin/add_color_components.sql v phpMyAdmin!';
        }
    }
}

// Load product data if editing
if ($productId > 0) {
    $stmt = $conn->prepare("SELECT * FROM product WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error_message'] = 'Produkt nebyl nalezen.';
        header('Location: admin_products.php');
        exit;
    }
    
    // Prepare image URLs
    $product['image_urls'] = !empty($product['image_url']) ? explode(',', $product['image_url']) : [];
    $product['variants'] = !empty($product['variants']) ? json_decode($product['variants'], true) : [];
    $product['component_images'] = !empty($product['component_images']) ? json_decode($product['component_images'], true) : [];
    $pageTitle = 'Upravit produkt: ' . htmlspecialchars($product['name']);
} else {
    $pageTitle = 'Přidat nový produkt';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo htmlspecialchars($pageTitle); ?> - KJD Administrace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="../css/vendor.css">
    <link rel="stylesheet" type="text/css" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <link rel="stylesheet" href="css/admin_edit_product.css">

</head>
<body class="kjd-admin-page">
    <?php include '../includes/icons.php'; ?>
    
    <!-- Preloader removed by user request -->

    <!-- Page Content -->
    <div id="page-content-wrapper" class="w-100">
        <!-- Sidebar (Header) -->
        <?php include 'admin_sidebar.php'; ?>

    <div class="page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="page-title">
                        <i class="fas fa-edit" style="color: var(--kjd-earth-green);"></i>
                        <?php echo htmlspecialchars($pageTitle); ?>
                    </h1>
                    <p class="page-subtitle mb-0">Upravujte produktové informace v jednotném KJD stylu</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a href="admin_products.php" class="btn btn-kjd-secondary">
                            <i class="fas fa-arrow-left"></i> Zpět na přehled
                        </a>
                        <a href="admin_novy_produkt.php" class="btn btn-kjd-primary">
                            <i class="fas fa-plus"></i> Nový produkt
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid pb-5">
        <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="productForm">
            <input type="hidden" name="product_id" value="<?php echo (int)$productId; ?>">

            <div class="row g-4">
                <?php if (isset($_SESSION['error_message']) || isset($_SESSION['success_message']) || isset($error) || isset($errorMessage)): ?>
                    <div class="col-12">
                        <div class="d-flex flex-column gap-3">
                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show cart-alert" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show cart-alert" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger alert-dismissible fade show cart-alert" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($errorMessage)): ?>
                                <div class="alert alert-danger alert-dismissible fade show cart-alert" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-12 col-lg-9">
                    <!-- Tab Navigation -->
                    <div class="kjd-tabs-nav">
                        <button type="button" class="kjd-tab-btn active" data-target="#tab-basic">
                            <i class="fas fa-info-circle"></i> Základní info
                        </button>
                        <button type="button" class="kjd-tab-btn" data-target="#tab-variants">
                            <i class="fas fa-cogs"></i> Varianty
                        </button>
                        <button type="button" class="kjd-tab-btn" data-target="#tab-images">
                            <i class="fas fa-image"></i> Obrázky
                        </button>
                        <button type="button" class="kjd-tab-btn" data-target="#tab-colors">
                            <i class="fas fa-palette"></i> Barvy
                        </button>
                        <button type="button" class="kjd-tab-btn" data-target="#tab-configurator">
                            <i class="fas fa-tools"></i> Konfigurátor
                        </button>
                    </div>

                    <div class="tab-content-wrapper">
                        <!-- Tab 1: Basic Info -->
                        <div id="tab-basic" class="tab-content-section active">
                        <!-- Sekce 1: Základní informace -->
                        <div class="kjd-card">
                            <div class="kjd-card-header">
                                <h3 class="kjd-card-title">
                                    <i class="fas fa-info-circle" style="color: var(--primary-600);"></i> Základní informace
                                </h3>
                            </div>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label for="name" class="form-label">Název produktu <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>">
                                    <div class="invalid-feedback">Zadejte prosím název produktu.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($product['model'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6">
                                    <label for="price" class="form-label">Cena (Kč) *</label>
                                    <input type="text" class="form-control" id="price" name="price" required value="<?php echo isset($product['price']) ? number_format($product['price'], 2, ',', ' ') : '0,00'; ?>">
                                </div>
                                <div class="col-12">
                                    <label for="description" class="form-label">Popis produktu</label>
                                    <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label for="html_tech_specs" class="form-label">Technické specifikace (HTML povoleno)</label>
                                    <textarea class="form-control" id="html_tech_specs" name="html_tech_specs" rows="10"><?php echo htmlspecialchars($product['html_tech_specs'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        </div>

                        <!-- Tab 2: Variants -->
                        <div id="tab-variants" class="tab-content-section">
                            <div class="kjd-card">
                                <div class="kjd-card-header">
                                    <h3 class="kjd-card-title">
                                        <i class="fas fa-cogs me-2"></i> Varianty produktu
                                    </h3>
                                </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted">Přidejte varianty jako velikost, materiál apod.</span>
                                        <button type="button" class="btn btn-kjd-secondary" id="add-variant-row">
                                            <i class="fas fa-plus me-2"></i>Přidat variantu
                                        </button>
                                    </div>
                            <div id="variants-container" class="d-flex flex-column gap-3">
                                <?php if (!empty($product['variants'])): ?>
                                    <?php foreach ($product['variants'] as $type => $options): ?>
                                        <?php foreach ($options as $optName => $optData): ?>
                                            <div class="row g-3 align-items-end variant-row">
                                                <div class="col-md-3">
                                                    <label class="form-label">Typ</label>
                                                    <input type="text" class="form-control" name="variant_types[]" value="<?= htmlspecialchars($type) ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Možnost</label>
                                                    <input type="text" class="form-control" name="variant_options[]" value="<?= htmlspecialchars($optName) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Příplatek</label>
                                                    <input type="number" class="form-control" name="variant_prices[]" value="<?= (float)($optData['price'] ?? 0) ?>" step="0.01">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Skladem</label>
                                                    <input type="number" class="form-control" name="variant_stocks[]" value="<?= (int)($optData['stock'] ?? 0) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Barvy pro tuto možnost (CSV, prázdné = všechny)</label>
                                                    <input type="text" class="form-control" name="variant_colors[]" value="<?= htmlspecialchars(isset($optData['colors']) ? implode(', ', (array)$optData['colors']) : '') ?>" placeholder="např. Černá, Bílá, Modrá">
                                                </div>
                                                <div class="col-md-1 d-grid gap-2">
                                                    <button type="button" class="btn btn-success add-variant-same-type">+</button>
                                                    <button type="button" class="btn btn-danger remove-variant">&times;</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-muted">Zatím nejsou definovány žádné varianty. Přidejte první variantu tlačítkem výše.</div>
                                <?php endif; ?>
                            </div>
                            <?php $existingTypes = array_keys($product['variants'] ?? []); ?>
                            <?php if (!empty($existingTypes)): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-8">
                                            <label class="form-label">Přidat možnost k existujícímu typu</label>
                                            <select id="variantTypeSelect" class="form-select">
                                                <?php foreach ($existingTypes as $t): ?>
                                                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-grid">
                                            <button type="button" class="btn btn-outline-secondary" id="add-variant-option-global">
                                                <i class="fas fa-plus me-2"></i>Přidat možnost
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Images -->
                        <div id="tab-images" class="tab-content-section">
                            <div class="kjd-card">
                                <div class="kjd-card-header">
                                    <h3 class="kjd-card-title">
                                        <i class="fas fa-image me-2"></i> Obrázky produktu
                                    </h3>
                                </div>
                                <div class="kjd-card-body">
                                    <div class="mb-3">
                                        <label for="images" class="form-label">Nahrát obrázky</label>
                                        <input class="form-control" type="file" id="images" name="images[]" multiple accept="image/*">
                                        <div class="form-text">Můžete nahrát více obrázků najednou.</div>
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
                                    <div id="image_preview" class="image-preview">
                                        <?php if (!empty($product['image_urls'])): ?>
                                            <?php foreach ($product['image_urls'] as $index => $imageUrl): ?>
                                                <?php
                                                    $disp = trim($imageUrl);
                                                    if (preg_match('~^https?://~', $disp)) {
                                                        $displayUrl = $disp;
                                                    } elseif (strpos($disp, 'uploads/') === 0 || strpos($disp, '/uploads/') === 0) {
                                                        $displayUrl = '../' . ltrim($disp, '/');
                                                    } elseif (strpos($disp, 'admin/uploads/') === 0) {
                                                        $displayUrl = '../' . ltrim($disp, '/');
                                                    } else {
                                                        $displayUrl = '../' . ltrim($disp, '/');
                                                    }
                                                ?>
                                                <div class="image-preview-item" data-image="<?php echo htmlspecialchars($imageUrl); ?>">
                                                    <?php if ($index === 0): ?>
                                                        <span class="badge bg-primary">Hlavní</span>
                                                    <?php endif; ?>
                                                    <img src="<?php echo htmlspecialchars($displayUrl); ?>" alt="Náhled obrázku <?php echo $index + 1; ?>">
                                                    <div class="image-preview-actions">
                                                        <button type="button" class="btn-remove-image" title="Odstranit obrázek">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    <input type="hidden" name="existing_images[]" value="<?php echo htmlspecialchars($imageUrl); ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-muted py-4">Žádné obrázky nebyly nahrány</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Colors -->
                        <div id="tab-colors" class="tab-content-section">
                            <div class="kjd-card">
                            <div class="kjd-card-header mb-4">
                                <h3 class="kjd-card-title mb-0">
                                    <i class="fas fa-palette me-2"></i>Barvy
                                </h3>
                            </div>
                            
                            <!-- Podsekce 1: Základní barvy produktu -->
                            <div class="mb-4 pb-4 border-bottom">
                                <h5 class="text-primary fw-bold mb-3">
                                    <i class="fas fa-paint-brush me-2"></i>Základní barvy produktu
                                </h5>
                                
                                <?php
                                    $existingColorsCsv = trim($product['colors'] ?? '');
                                    $existingColorsArr = array_filter(array_map('trim', explode(',', $existingColorsCsv)));
                                    $colorMap = function_exists('getColorMap') ? getColorMap() : [];
                                    ksort($colorMap);
                                ?>
                                
                                <div class="mb-3">
                                    <label for="colors" class="form-label">Dostupné barvy</label>
                                    <input type="text" class="form-control" id="colors" name="colors" value="<?php echo htmlspecialchars($product['colors'] ?? ''); ?>" placeholder="Černá, Bílá, Modrá">
                                    <div class="form-text">Oddělujte čárkou. Můžete použít výběr z palety níže.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Výběr z palety barev</label>
                                    <input type="text" id="colorSearch" class="form-control mb-2" placeholder="Hledat barvu...">
                                    <div id="colorChecklist" class="kjd-checklist">
                                        <div class="row">
                                            <?php if (!empty($colorMap)): $i = 0; foreach ($colorMap as $hex => $name): $i++; $checked = in_array($name, $existingColorsArr, true) ? 'checked' : ''; ?>
                                                <div class="col-md-4 col-sm-6 mb-1 color-item-row" data-name="<?php echo htmlspecialchars(mb_strtolower($name)); ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input color-check" type="checkbox" value="<?php echo htmlspecialchars($name); ?>" id="color-<?php echo $i; ?>" <?php echo $checked; ?>>
                                                        <label class="form-check-label" for="color-<?php echo $i; ?>"><?php echo htmlspecialchars($name); ?> <small class="text-muted"><?php echo htmlspecialchars($hex); ?></small></label>
                                                    </div>
                                                </div>
                                            <?php endforeach; else: ?>
                                                <div class="col-12 text-muted">Paleta barev není dostupná.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="form-text">Zaškrtnuté barvy se automaticky vyplní do pole výše.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="unavailable_colors" class="form-label">Nedostupné barvy</label>
                                    <textarea class="form-control" id="unavailable_colors" name="unavailable_colors" rows="2" placeholder="Každou barvu na nový řádek"><?php echo htmlspecialchars($product['unavailable_colors'] ?? ''); ?></textarea>
                                    <div class="form-text">Každou barvu na nový řádek</div>
                                </div>
                                
                                <div class="mb-3">
                                    </div>
                                </div>
                                

                            </div>
                            

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 5: Configurator -->
                    <div id="tab-configurator" class="tab-content-section">
                        <div class="kjd-card">
                            <div class="kjd-card-header mb-4">
                                <h3 class="kjd-card-title mb-0">
                                    <i class="fas fa-tools me-2"></i>Konfigurátor lamp
                                </h3>
                            </div>

                            <div class="mb-4 pb-4 border-bottom">
                                <div class="form-check form-switch p-3 border rounded kjd-bg-light">
                                    <input class="form-check-input" type="checkbox" id="is_lamp_config" name="is_lamp_config" <?php echo !empty($product['is_lamp_config']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="is_lamp_config">
                                        Aktivovat konfigurátor lamp
                                    </label>
                                    <div class="form-text mt-1">
                                        Pokud je aktivní, na stránce produktu se zobrazí interaktivní konfigurátor pro výběr podstavce a stínidla.
                                    </div>
                                </div>
                            </div>

                            <!-- Podsekce 2: Barevné komponenty (Moved) -->
                            <div class="mb-4 pb-4 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="text-primary fw-bold mb-0">
                                        <i class="fas fa-layer-group me-2"></i>Barevné komponenty
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-kjd-primary" id="addColorComponentBtn">
                                        <i class="fas fa-plus me-1"></i> Přidat komponent
                                    </button>
                                </div>
                                <p class="text-muted small mb-3">Definujte části produktu, pro které si zákazník může vybrat barvu (např. Nožičky, Vršek).</p>
                                
                                <div id="colorComponentsContainer" class="d-flex flex-column gap-3">
                                    <?php
                                    $existingComponents = [];
                                    if (!empty($product['color_components'])) {
                                        $existingComponents = json_decode($product['color_components'], true) ?: [];
                                    }

                                    foreach ($existingComponents as $idx => $comp):
                                    ?>
                                        <div class="kjd-card color-component-card" data-index="<?= $idx ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="text-primary fw-bold mb-0">Komponent #<?= $idx + 1 ?></h6>
                                                    <button type="button" class="btn btn-sm btn-danger remove-component-btn">
                                                        <i class="fas fa-trash me-1"></i> Odstranit
                                                    </button>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Název komponenty *</label>
                                                        <input type="text" class="form-control" name="component_names[]" value="<?= htmlspecialchars($comp['name'] ?? '') ?>" placeholder="např. Nožičky, Vršek">
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="form-label">Dostupné barvy *</label>
                                                        <input type="text" class="form-control" name="component_colors[]" value="<?= htmlspecialchars(implode(', ', $comp['colors'] ?? [])) ?>" placeholder="Černá, Bílá, Přírodní">
                                                        <small class="text-muted">Oddělujte čárkou</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($existingComponents)): ?>
                                        <div class="kjd-dashed-box text-center py-4 text-muted">
                                            <i class="fas fa-info-circle me-2"></i>Zatím nejsou přidány žádné komponenty.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="color_components_json" id="color_components_json" value="">
                            </div>
                            
                            <!-- Podsekce 3: Varianty obrázků komponent (Moved) -->
                            <div class="mb-4">
                                <h5 class="text-primary fw-bold mb-3">
                                    <i class="fas fa-images me-2"></i>Varianty a jejich obrázky
                                </h5>
                                <p class="text-muted small mb-4">Zde nahrajte obrázky pro jednotlivé varianty komponent (např. různé tvary nožiček) a jejich barevné verze.</p>
                                
                                <?php
                                $componentImageTypes = [
                                    'nozicky' => ['label' => 'Horní část (Nožičky)', 'icon' => 'fa-arrow-up'],
                                    'vrsek' => ['label' => 'Spodní část (Vršek)', 'icon' => 'fa-arrow-down']
                                ];
                                
                                foreach ($componentImageTypes as $type => $typeData):
                                    $existingVariants = $product['component_images'][$type] ?? [];
                                ?>
                                <div class="kjd-card mb-4 component-image-variants" data-type="<?= $type ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="text-primary fw-bold mb-0">
                                                <i class="fas <?= $typeData['icon'] ?> me-2"></i><?= htmlspecialchars($typeData['label']) ?>
                                            </h6>
                                        </div>
                                        
                                        <!-- Existing variants -->
                                        <div class="existing-variants mb-4" data-type="<?= $type ?>">
                                            <?php if (!empty($existingVariants)): ?>
                                                <?php foreach ($existingVariants as $idx => $variant): ?>
                                                    <div class="variant-item mb-3 p-3 border rounded kjd-bg-light" data-index="<?= $idx ?>">
                                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                                            <div class="d-flex align-items-center gap-3">
                                                                <?php
                                                                $imgPath = trim($variant['image'] ?? '');
                                                                if (preg_match('~^https?://~', $imgPath)) {
                                                                    $displayUrl = $imgPath;
                                                                } elseif (strpos($imgPath, 'uploads/') === 0 || strpos($imgPath, '/uploads/') === 0) {
                                                                    $displayUrl = '../' . ltrim($imgPath, '/');
                                                                } elseif (strpos($imgPath, 'admin/uploads/') === 0) {
                                                                    $displayUrl = '../' . ltrim($imgPath, '/');
                                                                } else {
                                                                    $displayUrl = '../' . ltrim($imgPath, '/');
                                                                }
                                                                ?>
                                                                <img src="<?= htmlspecialchars($displayUrl) ?>" alt="Varianta" class="img-thumbnail kjd-img-thumbnail" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                                                                <div>
                                                                    <label class="form-label small mb-1">Název varianty</label>
                                                                    <input type="text" class="form-control" 
                                                                           name="component_image_names_<?= $type ?>[]" 
                                                                           value="<?= htmlspecialchars($variant['name'] ?? '') ?>" 
                                                                           placeholder="např. Varianta 1">
                                                                    <input type="hidden" name="existing_component_images_<?= $type ?>[]" value="<?= htmlspecialchars($variant['image'] ?? '') ?>">
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn btn-sm btn-danger remove-variant-btn">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- Barevné varianty -->
                                                        <div class="color-variants-section mt-3 pt-3 border-top">
                                                            <label class="form-label mb-2 fw-bold small">
                                                                <i class="fas fa-palette me-1"></i>Barevné varianty
                                                            </label>
                                                            <div class="existing-color-variants mb-3" data-variant-index="<?= $idx ?>">
                                                                <?php 
                                                                $colorVariants = $variant['colors'] ?? [];
                                                                if (!empty($colorVariants)): 
                                                                    foreach ($colorVariants as $colorIdx => $colorVariant):
                                                                ?>
                                                                    <div class="color-variant-item mb-2 p-2 rounded d-flex align-items-center gap-3 kjd-bg-white border" data-color-index="<?= $colorIdx ?>">
                                                                        <?php
                                                                        $colorImgPath = trim($colorVariant['image'] ?? '');
                                                                        if (preg_match('~^https?://~', $colorImgPath)) {
                                                                            $colorDisplayUrl = $colorImgPath;
                                                                        } elseif (strpos($colorImgPath, 'uploads/') === 0 || strpos($colorImgPath, '/uploads/') === 0) {
                                                                            $colorDisplayUrl = '../' . ltrim($colorImgPath, '/');
                                                                        } elseif (strpos($colorImgPath, 'admin/uploads/') === 0) {
                                                                            $colorDisplayUrl = '../' . ltrim($colorImgPath, '/');
                                                                        } else {
                                                                            $colorDisplayUrl = '../' . ltrim($colorImgPath, '/');
                                                                        }
                                                                        ?>
                                                                        <img src="<?= htmlspecialchars($colorDisplayUrl) ?>" alt="Barva" class="img-thumbnail kjd-img-thumbnail" style="max-width: 60px; max-height: 60px; object-fit: cover;">
                                                                        <div class="flex-grow-1">
                                                                            <input type="text" class="form-control form-control-sm" 
                                                                                   name="existing_component_colors_<?= $type ?>[<?= $idx ?>][<?= $colorIdx ?>][name]" 
                                                                                   value="<?= htmlspecialchars($colorVariant['color'] ?? '') ?>" 
                                                                                   placeholder="Název barvy">
                                                                            <input type="hidden" name="existing_component_colors_<?= $type ?>[<?= $idx ?>][<?= $colorIdx ?>][image]" value="<?= htmlspecialchars($colorVariant['image'] ?? '') ?>">
                                                                        </div>
                                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-color-variant-btn">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                <?php 
                                                                    endforeach;
                                                                endif; 
                                                                ?>
                                                            </div>
                                                            <div class="mb-2" style="background: #f8f9fa; padding: 0.75rem; border-radius: 8px;">
                                                                <label class="form-label small fw-bold mb-2">
                                                                    <i class="fas fa-upload me-1"></i>Nahrát fotky dalších barev
                                                                </label>
                                                                <input type="file" class="form-control form-control-sm variant-color-images-input" 
                                                                       data-variant-index="<?= $idx ?>"
                                                                       data-component-type="<?= $type ?>"
                                                                       multiple 
                                                                       accept="image/*"
                                                                       style="border: 1px dashed var(--kjd-earth-green);">
                                                                <div class="form-text small">
                                                                    <i class="fas fa-info-circle me-1"></i>Podporované formáty: JPG, PNG, WebP
                                                                </div>
                                                            </div>
                                                            <div class="mb-2" style="background: #fff; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--kjd-beige);">
                                                                <label class="form-label small fw-bold mb-2">
                                                                    <i class="fas fa-link me-1"></i>Nebo URL barev (každé na nový řádek)
                                                                </label>
                                                                <textarea class="form-control form-control-sm" 
                                                                          name="component_color_images_urls_bulk_<?= $type ?>[<?= $idx ?>]"
                                                                          rows="3"
                                                                          placeholder="Vložte URL barev, každé na nový řádek&#10;https://drive.google.com/file/d/...&#10;https://..."
                                                                          style="border: 1px solid var(--kjd-earth-green); font-family: monospace; font-size: 0.85rem;"></textarea>
                                                                <div class="form-text small mt-1">
                                                                    <i class="fas fa-magic me-1" style="color: var(--kjd-earth-green);"></i>
                                                                    <strong>Jednoduché!</strong> Vložte URL a uložte.
                                                                </div>
                                                            </div>
                                                            <div class="new-color-variant-names" data-variant-index="<?= $idx ?>" data-component-type="<?= $type ?>"></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="kjd-dashed-box text-center py-3 text-muted">
                                                    Zatím nejsou přidány žádné varianty.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Add new variants -->
                                        <div class="border-top pt-4 mt-3" style="background: linear-gradient(135deg, #f8f9fa, #fff); padding: 1.5rem; border-radius: 12px; border: 2px solid var(--kjd-beige);">
                                            <h6 class="mb-3" style="color: var(--kjd-dark-green); font-weight: 700;">
                                                <i class="fas fa-plus-circle me-2"></i>Přidat nové varianty
                                            </h6>
                                            
                                            <!-- File Upload Section -->
                                            <div class="mb-4">
                                                <label class="form-label mb-2 fw-bold" style="color: var(--kjd-dark-brown);">
                                                    <i class="fas fa-upload me-1"></i>Nahrát ze souboru
                                                </label>
                                                <input type="file" class="form-control" 
                                                       name="component_images_<?= $type ?>[]" 
                                                       multiple 
                                                       accept="image/*"
                                                       id="component_images_<?= $type ?>"
                                                       data-component-type="<?= $type ?>"
                                                       style="border: 2px dashed var(--kjd-earth-green); padding: 1rem;">
                                                <div class="form-text">
                                                    <i class="fas fa-info-circle me-1"></i>Můžete nahrát více obrázků najednou (JPG, PNG, WebP)
                                                </div>
                                            </div>
                                            
                                            <!-- URL Section - SIMPLIFIED -->
                                            <div class="mt-3" style="background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid var(--kjd-beige);">
                                                <label class="form-label small fw-bold mb-2" style="color: var(--kjd-dark-brown);">
                                                    <i class="fas fa-link me-1"></i>Nebo přidat URL obrázky (každé URL na nový řádek)
                                                </label>
                                                <textarea class="form-control" 
                                                          name="component_images_urls_bulk_<?= $type ?>" 
                                                          rows="5"
                                                          placeholder="Vložte Google Drive nebo přímé URL&#10;Každé URL na nový řádek:&#10;&#10;https://drive.google.com/file/d/...&#10;https://drive.google.com/file/d/...&#10;https://imgur.com/..."
                                                          style="border: 2px solid var(--kjd-earth-green); font-family: monospace; font-size: 0.9rem;"></textarea>
                                                <div class="form-text mt-2">
                                                    <i class="fas fa-check-circle me-1" style="color: var(--kjd-earth-green);"></i>
                                                    <strong>✨ JEDNODUCHÉ!</strong> Prostě vložte URL (každé na nový řádek) a klikněte na Uložit změny. 
                                                    <strong>Automatická podpora Google Drive!</strong>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Names for new uploads -->
                                        <div class="new-variant-names mt-3" data-type="<?= $type ?>"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="available_from" class="form-label">Dostupné od</label>
                                <input type="datetime-local" class="form-control" id="available_from" name="available_from" value="<?php echo !empty($product['available_from']) ? date('Y-m-d\\TH:i', strtotime($product['available_from'])) : ''; ?>">
                            </div>
                        </div>
                        </div>
                    </div>


                <div class="col-12 col-lg-3" style="z-index: 100;">
                    <div class="sticky-sidebar d-flex flex-column gap-4">
                        <?php
                        $primaryImageUrl = $product['image_urls'][0] ?? null;
                        $primaryDisplayUrl = null;
                        if ($primaryImageUrl) {
                            $trimmed = trim($primaryImageUrl);
                            if (preg_match('~^https?://~', $trimmed)) {
                                $primaryDisplayUrl = $trimmed;
                            } elseif (strpos($trimmed, 'uploads/') === 0 || strpos($trimmed, '/uploads/') === 0) {
                                $primaryDisplayUrl = '../' . ltrim($trimmed, '/');
                            } elseif (strpos($trimmed, 'admin/uploads/') === 0) {
                                $primaryDisplayUrl = '../' . ltrim($trimmed, '/');
                            } else {
                                $primaryDisplayUrl = '../' . ltrim($trimmed, '/');
                            }
                        }
                        ?>
                        <div class="kjd-card">
                            <div class="kjd-card-header">
                                <h3 class="kjd-card-title">
                                    <i class="fas fa-image me-2"></i>Hlavní náhled
                                </h3>
                            </div>
                            <div id="sidebarPrimaryPreview">
                                <?php if ($primaryDisplayUrl): ?>
                                    <img src="<?php echo htmlspecialchars($primaryDisplayUrl); ?>" alt="Hlavní obrázek produktu" class="sidebar-preview-img">
                                <?php else: ?>
                                    <div class="sidebar-preview-empty">
                                        <span><i class="fas fa-image me-2"></i>Žádný hlavní obrázek</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-text mt-2" id="sidebarPrimaryHint">
                                <?php if ($primaryDisplayUrl): ?>
                                    Tento obrázek vidí zákazníci jako první.
                                <?php else: ?>
                                    Nahrajte obrázky v sekci níže pro zobrazení náhledu.
                                <?php endif; ?>
                            </div>
                            <div class="d-grid gap-2 mt-3">
                                <button type="button" class="btn btn-kjd-secondary" id="sidebarUploadTrigger">
                                    <i class="fas fa-upload me-2"></i>Nahrát obrázek
                                </button>
                            </div>
                        </div>

                        <div class="kjd-card">
                            <div class="kjd-card-header">
                                <h3 class="kjd-card-title">
                                    <i class="fas fa-check-circle me-2"></i>Stav a dostupnost
                                </h3>
                            </div>
                            <div class="mb-3">
                                <label for="stock_status" class="form-label">Stav skladu</label>
                                <select class="form-select" id="stock_status" name="stock_status">
                                    <option value="in_stock" <?php echo ($product['stock_status'] ?? '') === 'in_stock' ? 'selected' : ''; ?>>Skladem</option>
                                    <option value="out_of_stock" <?php echo ($product['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : ''; ?>>Nedostupné</option>
                                    <option value="preorder" <?php echo ($product['stock_status'] ?? '') === 'preorder' ? 'selected' : ''; ?>>Předobjednávka</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="availability" class="form-label">Dostupnost</label>
                                <input type="text" class="form-control" id="availability" name="availability" value="<?php echo htmlspecialchars($product['availability'] ?? 'Skladem'); ?>">
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_preorder" name="is_preorder" value="1" <?php echo !empty($product['is_preorder']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_preorder">Předobjednávka</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="sale_enabled" name="sale_enabled" value="1" <?php echo !empty($product['sale_enabled']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sale_enabled">Sleva</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is_hidden" name="is_hidden" value="1" <?php echo !empty($product['is_hidden']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_hidden">Skrýt produkt (pouze s přímým odkazem)</label>
                                <div class="form-text">Produkt nebude veřejně vylistován, ale zůstane dostupný přes přímý odkaz.</div>
                            </div>

                            <div class="mb-3" id="sale_price_container" style="display: <?php echo !empty($product['sale_enabled']) ? 'block' : 'none'; ?>;">
                                <label for="sale_price" class="form-label">Slevová cena (Kč)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sale_price" name="sale_price" value="<?php echo isset($product['sale_price']) ? number_format($product['sale_price'], 2, ',', ' ') : ''; ?>">
                                    <span class="input-group-text">nebo</span>
                                    <input type="number" class="form-control" id="sale_percentage" placeholder="%" min="0" max="100" step="1">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Zadejte cenu NEBO procenta (auto-přepočet z hlavní ceny).</div>
                            </div>
                            <div class="mb-3" id="sale_end_container" style="display: <?php echo !empty($product['sale_enabled']) ? 'block' : 'none'; ?>;">
                                <label for="sale_start" class="form-label">Začátek slevy (volitelné)</label>
                                <input type="datetime-local" class="form-control mb-2" id="sale_start" name="sale_start" value="<?php echo !empty($product['sale_start']) ? date('Y-m-d\\TH:i', strtotime($product['sale_start'])) : ''; ?>">
                                
                                <label for="sale_end" class="form-label">Konec slevy (volitelné)</label>
                                <input type="datetime-local" class="form-control" id="sale_end" name="sale_end" value="<?php echo !empty($product['sale_end']) ? date('Y-m-d\\TH:i', strtotime($product['sale_end'])) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="release_date" class="form-label">Datum vydání</label>
                                <input type="date" class="form-control" id="release_date" name="release_date" value="<?php echo !empty($product['release_date']) ? $product['release_date'] : ''; ?>">
                            </div>
                        </div>

                        <div class="kjd-card">
                            <div class="kjd-card-header">
                                <h3 class="kjd-card-title">
                                    <i class="fas fa-save me-2"></i>Akce
                                </h3>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-kjd-primary">
                                    <i class="fas fa-save me-2"></i>Uložit změny
                                </button>
                                <a href="admin_products.php" class="btn btn-kjd-secondary">
                                    <i class="fas fa-list me-2"></i>Zpět na seznam produktů
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tiny.cloud/1/01xxy8vo5nbpsts18sbtttzawt4lcx1xl2js0l72x2siwprx/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        if (typeof tinymce !== 'undefined') {
            tinymce.init({
                selector: '#description, #html_tech_specs',
                plugins: 'link lists table image code',
                toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table',
                height: 300,
                menubar: false,
                statusbar: false,
                content_style: 'body { font-family: "Montserrat", Arial, sans-serif; font-size: 14px; }',
                images_upload_url: 'upload_image.php',
                images_upload_credentials: true,
                relative_urls: false,
                remove_script_host: false
            });
        }
        
        // Toggle sale price fields
        const saleCheckbox = document.getElementById('sale_enabled');
        if (saleCheckbox) {
            saleCheckbox.addEventListener('change', function() {
                const saleFields = document.querySelectorAll('#sale_price_container, #sale_end_container');
                saleFields.forEach(field => {
                    field.style.display = this.checked ? 'block' : 'none';
                });
            });
        }
        

      
      // Variants dynamic rows
      document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-variant')) {
          const row = e.target.closest('.variant-row');
          if (row) row.remove();
        }
        if (e.target && e.target.classList.contains('add-variant-same-type')) {
          const row = e.target.closest('.variant-row');
          if (!row) return;
          const container = document.getElementById('variants-container');
          if (!container) return;
          const typeInput = row.querySelector('input[name="variant_types[]"]');
          const typeVal = typeInput ? typeInput.value : '';
          const tpl = document.createElement('div');
          tpl.className = 'row g-2 align-items-end mb-2 variant-row';
          tpl.innerHTML = `
            <div class="col-md-3">
              <label class="form-label">Typ</label>
              <input type="text" class="form-control" name="variant_types[]" value="${typeVal}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Možnost</label>
              <input type="text" class="form-control" name="variant_options[]" placeholder="např. L">
            </div>
            <div class="col-md-2">
              <label class="form-label">Příplatek</label>
              <input type="number" class="form-control" name="variant_prices[]" value="0" step="0.01">
            </div>
            <div class="col-md-2">
              <label class="form-label">Skladem</label>
              <input type="number" class="form-control" name="variant_stocks[]" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Barvy pro tuto možnost (CSV, prázdné = všechny)</label>
              <input type="text" class="form-control" name="variant_colors[]" placeholder="např. Černá, Bílá, Modrá">
            </div>
            <div class="col-md-1 d-grid gap-2">
              <button type="button" class="btn btn-success add-variant-same-type">+</button>
              <button type="button" class="btn btn-danger remove-variant">&times;</button>
            </div>
          `;
          // insert after current row
          row.insertAdjacentElement('afterend', tpl);
        }
      });
      
      document.getElementById('add-variant-row')?.addEventListener('click', function() {
        const container = document.getElementById('variants-container');
        if (!container) return;
        const tpl = document.createElement('div');
        tpl.className = 'row g-2 align-items-end mb-2 variant-row';
        tpl.innerHTML = `
          <div class="col-md-3">
            <label class="form-label">Typ</label>
            <input type="text" class="form-control" name="variant_types[]" placeholder="např. Velikost">
          </div>
          <div class="col-md-4">
            <label class="form-label">Možnost</label>
            <input type="text" class="form-control" name="variant_options[]" placeholder="např. M">
          </div>
          <div class="col-md-2">
            <label class="form-label">Příplatek</label>
            <input type="number" class="form-control" name="variant_prices[]" value="0" step="0.01">
          </div>
          <div class="col-md-2">
            <label class="form-label">Skladem</label>
            <input type="number" class="form-control" name="variant_stocks[]" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Barvy pro tuto možnost (CSV, prázdné = všechny)</label>
            <input type="text" class="form-control" name="variant_colors[]" placeholder="např. Černá, Bílá, Modrá">
          </div>
          <div class="col-md-1 d-grid">
            <button type="button" class="btn btn-danger remove-variant">&times;</button>
          </div>
        `;
        container.appendChild(tpl);
      });

      // Global add option to selected type
      document.getElementById('add-variant-option-global')?.addEventListener('click', function(){
        const sel = document.getElementById('variantTypeSelect');
        const typeVal = sel ? sel.value : '';
        if (!typeVal) return;
        const container = document.getElementById('variants-container');
        if (!container) return;
        const tpl = document.createElement('div');
        tpl.className = 'row g-2 align-items-end mb-2 variant-row';
        tpl.innerHTML = `
          <div class="col-md-3">
            <label class="form-label">Typ</label>
            <input type="text" class="form-control" name="variant_types[]" value="${typeVal}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Možnost</label>
            <input type="text" class="form-control" name="variant_options[]" placeholder="např. varianta">
          </div>
          <div class="col-md-2">
            <label class="form-label">Příplatek</label>
            <input type="number" class="form-control" name="variant_prices[]" value="0" step="0.01">
          </div>
          <div class="col-md-2">
            <label class="form-label">Skladem</label>
            <input type="number" class="form-control" name="variant_stocks[]" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label">Barvy pro tuto možnost (CSV, prázdné = všechny)</label>
            <input type="text" class="form-control" name="variant_colors[]" placeholder="např. Černá, Bílá, Modrá">
          </div>
          <div class="col-md-1 d-grid">
            <button type="button" class="btn btn-danger remove-variant">&times;</button>
          </div>`;
        container.appendChild(tpl);
      });
        
        // Handle image preview and removal
        document.addEventListener('DOMContentLoaded', function() {
            const imagePreview = document.getElementById('image_preview');
            const fileInput = document.getElementById('images');
            const sidebarTrigger = document.getElementById('sidebarUploadTrigger');
            const sidebarPreview = document.getElementById('sidebarPrimaryPreview');
            const sidebarHint = document.getElementById('sidebarPrimaryHint');
            const placeholderMarkup = '<div class="sidebar-preview-empty"><span><i class="fas fa-image me-2"></i>Žádný hlavní obrázek</span></div>';
            const mainBadgeMarkup = '<span class="badge bg-primary">Hlavní</span>';

            const refreshPrimaryDisplay = () => {
                if (!imagePreview) return;
                const items = imagePreview.querySelectorAll('.image-preview-item');
                items.forEach((item, idx) => {
                    const badge = item.querySelector('.badge');
                    if (idx === 0) {
                        if (!badge) {
                            item.insertAdjacentHTML('afterbegin', mainBadgeMarkup);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                });

                if (!sidebarPreview) return;

                if (items.length > 0) {
                    const firstImg = items[0].querySelector('img');
                    if (firstImg) {
                        sidebarPreview.innerHTML = `<img src="${firstImg.src}" alt="Hlavní obrázek produktu" class="sidebar-preview-img">`;
                        if (sidebarHint) sidebarHint.textContent = 'Tento obrázek vidí zákazníci jako první.';
                        return;
                    }
                }

                sidebarPreview.innerHTML = placeholderMarkup;
                if (sidebarHint) sidebarHint.textContent = 'Nahrajte obrázky v sekci níže pro zobrazení náhledu.';
            };

            sidebarTrigger?.addEventListener('click', () => fileInput?.click());

            if (imagePreview) {
                imagePreview.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.btn-remove-image');
                    if (!removeBtn) return;

                    const previewItem = removeBtn.closest('.image-preview-item');
                    if (previewItem) {
                        previewItem.style.opacity = '0';
                        previewItem.style.transform = 'scale(0.8)';

                        setTimeout(() => {
                            previewItem.remove();

                            if (imagePreview.querySelectorAll('.image-preview-item').length === 0) {
                                const noImages = document.createElement('div');
                                noImages.className = 'text-muted py-4';
                                noImages.textContent = 'Žádné obrázky nebyly nahrány';
                                imagePreview.appendChild(noImages);
                            }

                            refreshPrimaryDisplay();
                        }, 200);
                    }
                });
            }

            // Handle URL image addition
            const addUrlBtn = document.getElementById('add_image_url_btn');
            const urlInput = document.getElementById('image_url_input');

            addUrlBtn?.addEventListener('click', function() {
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

                const noImagesMsg = imagePreview.querySelector('.text-muted');
                if (noImagesMsg) {
                    noImagesMsg.remove();
                }

                const div = document.createElement('div');
                div.className = 'image-preview-item';
                div.dataset.image = url;
                
                div.innerHTML = `
                    <img src="${url}" alt="Náhled obrázku" referrerpolicy="no-referrer" onerror="this.src='../assets/images/placeholder.png'">
                    <div class="image-preview-actions">
                        <button type="button" class="btn-remove-image" title="Odstranit obrázek">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <input type="hidden" name="new_image_urls[]" value="${url}">
                `;

                imagePreview.appendChild(div);
                urlInput.value = '';
                refreshPrimaryDisplay();
            });

            if (fileInput && imagePreview) {
                fileInput.addEventListener('change', function(e) {
                    const files = e.target.files;
                    if (!files || files.length === 0) return;

                    const noImagesMsg = imagePreview.querySelector('.text-muted');
                    if (noImagesMsg) {
                        noImagesMsg.remove();
                    }

                    Array.from(files).forEach((file) => {
                        if (!file.type.match('image.*')) return;

                        const reader = new FileReader();
                        reader.onload = function(readerEvent) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'image-preview-item';
                            previewItem.innerHTML = `
                                <img src="${readerEvent.target.result}" alt="Náhled obrázku">
                                <div class="image-preview-actions">
                                    <button type="button" class="btn-remove-image" title="Odstranit obrázek">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="new_images[]" value="${readerEvent.target.result}">
                            `;

                            previewItem.style.opacity = '0';
                            previewItem.style.transform = 'scale(0.8)';
                            imagePreview.appendChild(previewItem);

                            setTimeout(() => {
                                previewItem.style.opacity = '1';
                                previewItem.style.transform = 'scale(1)';
                            }, 10);

                            refreshPrimaryDisplay();
                        };

                        reader.readAsDataURL(file);
                    });
                });
            }

            refreshPrimaryDisplay();
        });
        
        // Sync color checklist with input
        const colorsInput = document.getElementById('colors');
        const checklist = document.getElementById('colorChecklist');
        const searchInput = document.getElementById('colorSearch');
        function syncFromChecks() {
          const values = Array.from(checklist.querySelectorAll('.color-check:checked')).map(i=>i.value);
          colorsInput.value = values.join(', ');
        }
        checklist?.addEventListener('change', function(e){
          if (e.target && e.target.classList.contains('color-check')) { syncFromChecks(); }
        });
        // Initialize from input (in case input edited manually)
        function syncFromInput() {
          const set = new Set(colorsInput.value.split(',').map(s=>s.trim()).filter(Boolean));
          checklist.querySelectorAll('.color-check').forEach(ch=>{ ch.checked = set.has(ch.value); });
        }
        colorsInput?.addEventListener('blur', syncFromInput);
        syncFromInput();
        // Search filter
        searchInput?.addEventListener('input', function(){
          const q = this.value.trim().toLowerCase();
          checklist.querySelectorAll('.color-item-row').forEach(row=>{
            const name = row.dataset.name || '';
            row.style.display = name.indexOf(q) !== -1 ? '' : 'none';
          });
        });
        
        // Form validation
        (function () {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Preloader JS removed
        
        // Barevné komponenty - dynamické přidávání
        let componentIndex = <?= !empty($existingComponents) ? count($existingComponents) : 0 ?>;
        
        // DEBUG: Zobraz debug info
        const debugDiv = document.getElementById('debugComponentCount');
        if (debugDiv) debugDiv.style.display = 'block';
        
        function updateComponentNumbers() {
            const cards = document.querySelectorAll('.color-component-card');
            cards.forEach((card, index) => {
                const heading = card.querySelector('h6');
                if (heading) heading.textContent = `Komponent #${index + 1}`;
            });
        }
        updateComponentNumbers();
        
        document.getElementById('addColorComponentBtn')?.addEventListener('click', function() {
            const container = document.getElementById('colorComponentsContainer');
            const currentCount = container.querySelectorAll('.color-component-card').length;
            
            const componentHtml = `
                <div class="kjd-card mb-3 color-component-card" data-index="${componentIndex}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary fw-bold mb-0">Komponent #${currentCount + 1}</h6>
                            <button type="button" class="btn btn-sm btn-danger remove-component-btn">
                                <i class="fas fa-trash"></i> Odstranit
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Název komponenty *</label>
                                <input type="text" class="form-control" name="component_names[]" 
                                       placeholder="např. Nožičky, Vršek">
                                <small class="text-muted">Zobrazí se zákazníkovi</small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Dostupné barvy *</label>
                                <input type="text" class="form-control" name="component_colors[]" 
                                       placeholder="Černá, Bílá, Přírodní">
                                <small class="text-muted">Oddělujte čárkou (všechny komponenty jsou povinné)</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', componentHtml);
            componentIndex++;
            updateComponentNumbers();
        });
        
        // Odstranění komponenty
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-component-btn')) {
                e.target.closest('.color-component-card').remove();
                updateComponentNumbers();
            }
        });
        
        function serializeComponentsToHiddenField() {
            const container = document.getElementById('colorComponentsContainer');
            const cards = container ? container.querySelectorAll('.color-component-card') : [];
            const components = [];
            cards.forEach(card => {
                const nameInput = card.querySelector('input[name="component_names[]"]');
                const colorsInput = card.querySelector('input[name="component_colors[]"]');
                const name = nameInput ? nameInput.value.trim() : '';
                const colorsRaw = colorsInput ? colorsInput.value.trim() : '';
                if (name !== '' && colorsRaw !== '') {
                    const colors = colorsRaw.split(',').map(c => c.trim()).filter(Boolean);
                    if (colors.length) {
                        components.push({ name, colors, required: true });
                    }
                }
            });
            const hidden = document.getElementById('color_components_json');
            if (hidden) hidden.value = components.length ? JSON.stringify(components) : '';
        }
        // Before submit, serialize components
        document.getElementById('productForm')?.addEventListener('submit', function() {
            serializeComponentsToHiddenField();
        });
        
        // Component image variants management
        document.querySelectorAll('input[type="file"][id^="component_images_"]').forEach(fileInput => {
            fileInput.addEventListener('change', function() {
                const type = this.id.replace('component_images_', '');
                const namesContainer = document.querySelector(`.new-variant-names[data-type="${type}"]`);
                if (!namesContainer) return;
                
                namesContainer.innerHTML = '';
                
                if (this.files && this.files.length > 0) {
                    Array.from(this.files).forEach((file, index) => {
                        if (!file.type.match('image.*')) return;
                        
                        const reader = new FileReader();
                        reader.onload = function(readerEvent) {
                            const variantDiv = document.createElement('div');
                            variantDiv.className = 'variant-item mb-3 p-3 border rounded kjd-bg-light';
                            variantDiv.innerHTML = `
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <img src="${readerEvent.target.result}" alt="Náhled varianty" class="img-thumbnail kjd-img-thumbnail" style="max-width: 120px; max-height: 120px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <label class="form-label small mb-1">Název varianty</label>
                                        <input type="text" class="form-control" 
                                               name="new_component_image_names_${type}[]" 
                                               placeholder="např. Varianta ${index + 1}" 
                                               value="Varianta ${index + 1}">
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-top">
                                    <label class="form-label small mb-2" style="font-weight: 600;">
                                        <i class="fas fa-palette me-1"></i>Barevné varianty (fotky barev) pro tuto variantu
                                    </label>
                                    <input type="file" class="form-control form-control-sm" 
                                           name="component_color_images_${type}[${index}][]" 
                                           multiple 
                                           accept="image/*">
                                    <div class="form-text small">Nahrajte fotky této varianty v různých barvách</div>
                                </div>
                                <div class="new-color-variant-names-new mt-3" data-variant-index="${index}" data-component-type="${type}"></div>
                            `;
                            namesContainer.appendChild(variantDiv);
                            
                            // Add handler for color images
                            const colorInput = variantDiv.querySelector('input[type="file"]');
                            if (colorInput) {
                                colorInput.addEventListener('change', function() {
                                    const variantIdx = this.getAttribute('name').match(/\[(\d+)\]/)[1];
                                    const namesContainer = variantDiv.querySelector('.new-color-variant-names-new');
                                    if (!namesContainer) return;
                                    
                                    namesContainer.innerHTML = '';
                                    
                                    if (this.files && this.files.length > 0) {
                                        Array.from(this.files).forEach((colorFile, colorIdx) => {
                                            if (!colorFile.type.match('image.*')) return;
                                            
                                            const colorReader = new FileReader();
                                            colorReader.onload = function(colorReaderEvent) {
                                                const colorVariantDiv = document.createElement('div');
                                                colorVariantDiv.className = 'color-variant-item mb-2 p-2 rounded d-flex align-items-center gap-3 kjd-bg-white border';
                                                colorVariantDiv.innerHTML = `
                                                    <img src="${colorReaderEvent.target.result}" alt="Náhled barvy" class="img-thumbnail kjd-img-thumbnail" style="max-width: 80px; max-height: 80px; object-fit: cover;">
                                                    <div class="flex-grow-1">
                                                        <label class="form-label small mb-1">Název barvy</label>
                                                        <input type="text" class="form-control form-control-sm" 
                                                               name="new_component_color_names_${type}[${variantIdx}][]" 
                                                               placeholder="např. Barva ${colorIdx + 1}" 
                                                               value="Barva ${colorIdx + 1}">
                                                    </div>
                                                `;
                                                namesContainer.appendChild(colorVariantDiv);
                                            };
                                            colorReader.readAsDataURL(colorFile);
                                        });
                                    }
                                });
                            }
                        };
                        reader.readAsDataURL(file);
                    });
                }
            });
        });

        // Handle URL variant addition
        document.querySelectorAll('.add-variant-url-btn').forEach(btn => {
            const type = btn.dataset.type;
            const input = document.getElementById(`component_images_url_input_${type}`);
            
            // Function to add URL variant
            const addUrlVariant = () => {
                const url = input.value.trim();
                
                if (!url) {
                    input.style.borderColor = '#dc3545';
                    input.placeholder = '⚠️ Prosím vložte URL!';
                    setTimeout(() => {
                        input.style.borderColor = '';
                        input.placeholder = 'https://drive.google.com/file/d/... nebo přímé URL';
                    }, 2000);
                    return;
                }
                
                console.log('🔍 Hledám kontejner pro:', type);
                const namesContainer = document.querySelector(`.new-variant-names[data-type="${type}"]`);
                console.log('📦 Nalezený kontejner:', namesContainer);
                
                if (!namesContainer) {
                    console.error('❌ KONTEJNER NENALEZEN!');
                    console.log('Všechny .new-variant-names:', document.querySelectorAll('.new-variant-names'));
                    alert(`❌ CHYBA: Nenašel jsem kontejner pro typ "${type}"!\n\nNalezené kontejnery: ${Array.from(document.querySelectorAll('.new-variant-names')).map(el => el.dataset.type).join(', ')}`);
                    return;
                }
                
                // Generate a unique index for URL variants
                const index = 'url_' + Date.now();
                
                const variantDiv = document.createElement('div');
                variantDiv.className = 'variant-item mb-3 p-3 border rounded kjd-bg-light';
                variantDiv.style.cssText = 'background: linear-gradient(135deg, #fff, #f8f9fa); border: 2px solid var(--kjd-earth-green) !important; box-shadow: 0 2px 8px rgba(16,40,32,0.08); animation: slideIn 0.3s ease;';
                
                console.log('🎯 Přidávám URL variantu:', {type, url, index});
                
                // Show visible confirmation
                const confirmDiv = document.createElement('div');
                confirmDiv.className = 'alert alert-success';
                confirmDiv.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 9999; max-width: 400px;';
                confirmDiv.innerHTML = `
                    <strong>✅ URL varianta přidána!</strong><br>
                    <small>Typ: ${type}<br>
                    URL: ${url.substring(0, 50)}...<br>
                    Index: ${index}</small>
                `;
                document.body.appendChild(confirmDiv);
                setTimeout(() => confirmDiv.remove(), 3000);
                
                variantDiv.innerHTML = `
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div style="position: relative;">
                            <img src="${url}" alt="Náhled varianty" class="img-thumbnail kjd-img-thumbnail" 
                                 style="max-width: 120px; max-height: 120px; object-fit: cover; border: 2px solid var(--kjd-beige); border-radius: 8px;" 
                                 onerror="this.src='../assets/images/placeholder.png'; this.style.opacity='0.5';"
                                 onload="this.style.opacity='1'; this.nextElementSibling.style.display='none';">
                            <div class="spinner-border spinner-border-sm" role="status" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: var(--kjd-earth-green);">
                                <span class="visually-hidden">Načítání...</span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label small mb-1" style="color: var(--kjd-dark-brown); font-weight: 700;">Název varianty</label>
                            <input type="text" class="form-control" 
                                   name="new_component_image_names_url_${type}[${index}]" 
                                   placeholder="např. Varianta URL" 
                                   value="Varianta URL"
                                   style="border: 2px solid var(--kjd-earth-green);">
                            <input type="hidden" name="component_images_url_${type}[${index}]" value="${url}">
                            <div class="form-text mt-1">
                                <i class="fas fa-check-circle me-1" style="color: var(--kjd-earth-green);"></i>
                                <small>URL přidána úspěšně</small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger remove-variant-btn" style="background: linear-gradient(135deg, #dc3545, #c82333); border: none;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="mt-3 pt-3 border-top" style="background: #f8f9fa; margin: -0.5rem; margin-top: 1rem; padding: 1rem; border-radius: 0 0 8px 8px;">
                        <label class="form-label small mb-2" style="font-weight: 600; color: var(--kjd-dark-green);">
                            <i class="fas fa-palette me-1"></i>Barevné varianty (fotky barev) pro tuto variantu
                        </label>
                        <div class="mb-2">
                             <label class="form-label small">Nahrát soubory</label>
                             <input type="file" class="form-control form-control-sm" 
                                   name="component_color_images_url_${type}[${index}][]" 
                                   multiple 
                                   accept="image/*"
                                   style="border: 1px dashed var(--kjd-earth-green);">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Nebo přidat URL barvy</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control url-color-input" placeholder="https://..." style="border-color: var(--kjd-earth-green);">\n                                <button type="button" class="btn add-color-url-btn" data-variant-index="${index}" data-type="${type}"
                                        style="background: var(--kjd-earth-green); color: #fff; border: none;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="new-color-variant-names-new mt-3" data-variant-index="${index}" data-component-type="${type}"></div>
                `;
                namesContainer.appendChild(variantDiv);
                input.value = ''; // Clear input
                
                // Add handler for color URL button in this new variant
                variantDiv.querySelector('.add-color-url-btn').addEventListener('click', function() {
                    const vIdx = this.dataset.variantIndex;
                    const t = this.dataset.type;
                    const urlInput = this.previousElementSibling;
                    const colorUrl = urlInput.value.trim();
                    
                    if (!colorUrl) return;
                    
                    const colorContainer = variantDiv.querySelector('.new-color-variant-names-new');
                    const colorIdx = 'url_' + Date.now();
                    
                    const colorVariantDiv = document.createElement('div');
                    colorVariantDiv.className = 'color-variant-item mb-2 p-2 rounded d-flex align-items-center gap-3 kjd-bg-white border';
                    colorVariantDiv.innerHTML = `
                        <img src="${colorUrl}" alt="Náhled barvy" class="img-thumbnail kjd-img-thumbnail" style="max-width: 80px; max-height: 80px; object-fit: cover;" onerror="this.src='../assets/images/placeholder.png'">
                        <div class="flex-grow-1">
                            <label class="form-label small mb-1">Název barvy</label>
                            <input type="text" class="form-control form-control-sm" 
                                   name="new_component_color_names_url_${t}[${vIdx}][${colorIdx}]" 
                                   placeholder="např. Barva URL" 
                                   value="Barva URL">
                            <input type="hidden" name="component_color_images_url_url_${t}[${vIdx}][${colorIdx}]" value="${colorUrl}">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-color-variant-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    colorContainer.appendChild(colorVariantDiv);
                    urlInput.value = '';
                });
            };
            
            // Add click event
            btn.addEventListener('click', addUrlVariant);
            
            // Add Enter key support
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addUrlVariant();
                }
            });
            
            // Visual feedback when typing
            input.addEventListener('input', () => {
                if (input.value.trim()) {
                    btn.style.animation = 'pulse 1s infinite';
                } else {
                    btn.style.animation = '';
                }
            });
        });
        
        // Add CSS animation for pulse effect
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
        
        // DEBUG: Log form data when submitting
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const formData = new FormData(this);
            
            console.log('📤 Odesílám formulář');
            
            // Log all component_images_url data to console
            for (let [key, value] of formData.entries()) {
                if (key.includes('component_images_url') || key.includes('component_images_urls_bulk')) {
                    console.log(`  ${key}: ${value}`);
                }
            }
        });
        
        // Color variant images for existing variants
        document.querySelectorAll('.variant-color-images-input').forEach(colorInput => {
            colorInput.addEventListener('change', function() {
                const variantIdx = this.dataset.variantIndex;
                const componentType = this.dataset.componentType;
                const namesContainer = document.querySelector(`.new-color-variant-names[data-variant-index="${variantIdx}"][data-component-type="${componentType}"]`);
                if (!namesContainer) return;
                
                namesContainer.innerHTML = '';
                
                if (this.files && this.files.length > 0) {
                    Array.from(this.files).forEach((file, index) => {
                        if (!file.type.match('image.*')) return;
                        
                        const reader = new FileReader();
                        reader.onload = function(readerEvent) {
                            const colorVariantDiv = document.createElement('div');
                            colorVariantDiv.className = 'color-variant-item mb-2 p-2 rounded d-flex align-items-center gap-3 kjd-bg-white border';
                            colorVariantDiv.innerHTML = `
                                <img src="${readerEvent.target.result}" alt="Náhled barvy" class="img-thumbnail kjd-img-thumbnail" style="max-width: 80px; max-height: 80px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <label class="form-label small mb-1">Název barvy</label>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="new_component_color_names_${componentType}[${variantIdx}][]" 
                                           placeholder="např. Barva ${index + 1}" 
                                           value="Barva ${index + 1}">
                                </div>
                            `;
                            namesContainer.appendChild(colorVariantDiv);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            });
        });

        // Handle URL color variant addition for existing variants
        document.querySelectorAll('.add-color-url-existing-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const variantIdx = this.dataset.variantIndex;
                const componentType = this.dataset.componentType;
                const input = this.previousElementSibling;
                const url = input.value.trim();
                
                if (!url) return;
                
                const namesContainer = document.querySelector(`.new-color-variant-names[data-variant-index="${variantIdx}"][data-component-type="${componentType}"]`);
                if (!namesContainer) return;
                
                const colorIdx = 'url_' + Date.now();
                
                const colorVariantDiv = document.createElement('div');
                colorVariantDiv.className = 'color-variant-item mb-2 p-2 rounded d-flex align-items-center gap-3 kjd-bg-white border';
                colorVariantDiv.innerHTML = `
                    <img src="${url}" alt="Náhled barvy" class="img-thumbnail kjd-img-thumbnail" style="max-width: 80px; max-height: 80px; object-fit: cover;" onerror="this.src='../assets/images/placeholder.png'">
                    <div class="flex-grow-1">
                        <label class="form-label small mb-1">Název barvy</label>
                        <input type="text" class="form-control form-control-sm" 
                               name="new_component_color_names_${componentType}[${variantIdx}][${colorIdx}]" 
                               placeholder="např. Barva URL" 
                               value="Barva URL">
                        <input type="hidden" name="component_color_images_url_${componentType}[${variantIdx}][${colorIdx}]" value="${url}">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-color-variant-btn">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                namesContainer.appendChild(colorVariantDiv);
                input.value = '';
            });
        });

        // Remove variant button
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-variant-btn')) {
                const variantItem = e.target.closest('.variant-item');
                if (variantItem) {
                    variantItem.remove();
                }
            }
            if (e.target.closest('.remove-color-variant-btn')) {
                const colorVariantItem = e.target.closest('.color-variant-item');
                if (colorVariantItem) {
                    colorVariantItem.remove();
                }
            }
        });
        

        
        // Tab switching logic
        document.querySelectorAll('.kjd-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons and contents
                document.querySelectorAll('.kjd-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content-section').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked button
                btn.classList.add('active');
                
                // Show target content
                const targetId = btn.dataset.target;
                const targetContent = document.querySelector(targetId);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });

        // Price <-> Percentage Sync Logic
        const priceInput = document.getElementById('price');
        const salePriceInput = document.getElementById('sale_price');
        const salePercentageInput = document.getElementById('sale_percentage');
        
        function parseLocalFloat(val) {
            if (!val) return 0;
            return parseFloat(val.toString().replace(/\s/g, '').replace(',', '.')) || 0;
        }

        function formatLocalFloat(val) {
            return val.toFixed(2).replace('.', ',');
        }

        function updatePercentage() {
            if (!priceInput || !salePriceInput || !salePercentageInput) return;
            const price = parseLocalFloat(priceInput.value);
            const sale = parseLocalFloat(salePriceInput.value);
            
            if (price > 0 && sale > 0 && sale < price) {
                const pct = Math.round((1 - (sale / price)) * 100);
                if (document.activeElement !== salePercentageInput) {
                    salePercentageInput.value = pct;
                }
            }
        }

        function updateSalePrice() {
            if (!priceInput || !salePriceInput || !salePercentageInput) return;
            const price = parseLocalFloat(priceInput.value);
            const pct = parseFloat(salePercentageInput.value);
            
            if (price > 0 && !isNaN(pct) && pct >= 0 && pct < 100) {
                const sale = price * (1 - (pct / 100));
                salePriceInput.value = formatLocalFloat(sale);
            } else if (price <= 0 && pct > 0) {
                // Warning if price is 0
                alert('⚠️ Pro nastavení procentuální slevy musíte vyplnit "Cenu" produktu (např. cenu nejlevnější varianty).\n\nSystém potřebuje základní cenu pro výpočet slevy.');
                salePercentageInput.value = '';
            }
        }

        if (priceInput && salePriceInput && salePercentageInput) {
            // Calculate percentage on load
            updatePercentage();
            
            // Events
            salePriceInput.addEventListener('input', updatePercentage);
            priceInput.addEventListener('input', updatePercentage);
            salePercentageInput.addEventListener('input', updateSalePrice);
        }
    </script>
</body>
</html>
