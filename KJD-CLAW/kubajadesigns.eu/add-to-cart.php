<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Přečtení vstupu (JSON nebo klasický POST)
$productId = null;
$productType = 'product';
$raw = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Zkusit JSON
    $raw = file_get_contents('php://input');
    error_log('[add-to-cart] raw POST input: ' . $raw);
    
    $data = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        error_log('[add-to-cart] JSON decoded successfully: ' . print_r($data, true));
        if (isset($data['product_id'])) {
            $productId = (int)$data['product_id'];
        }
        if (!empty($data['product_type'])) {
            $productType = $data['product_type'];
        }
    } else {
        error_log('[add-to-cart] JSON decode error: ' . json_last_error_msg());
    }
    
    // Fallback na form POST
    if ($productId === null && isset($_POST['product_id'])) {
        $productId = (int)$_POST['product_id'];
        error_log('[add-to-cart] Using form POST product_id: ' . $productId);
        if (!empty($_POST['product_type'])) {
            $productType = $_POST['product_type'];
        }
    }
}

// Fallback na GET (zpětná kompatibilita)
if ($productId === null && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    error_log('[add-to-cart] Using GET id: ' . $productId);
}

// Debug vstupů
error_log('[add-to-cart] FINAL product_id=' . ($productId ?? 'null') . ' product_type=' . ($productType ?? 'null'));

if (!$productId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID produktu nebylo nalezeno']);
    exit;
}

try {
    // Výběr správné tabulky dle typu a fallback, pokud typ není doručen z frontendu
    $tablesByType = [
        'product' => 'product',
        'product2' => 'product2',
        'product3' => 'product3'
    ];
    $orderToTry = [];
    // Heuristika podle refereru, pokud product_type není dodán explicitně
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    error_log('[add-to-cart] referer=' . $referer);
    $guessedType = null;
    if (empty($productType) || !isset($tablesByType[$productType])) {
        if (stripos($referer, 'product2') !== false) {
            $guessedType = 'product2';
        } elseif (stripos($referer, 'product3') !== false) {
            $guessedType = 'product3';
        } elseif (stripos($referer, 'product-detail') !== false || stripos($referer, 'index') !== false) {
            $guessedType = 'product';
        }
    }
    if (isset($tablesByType[$productType])) {
        $orderToTry[] = $tablesByType[$productType];
    } elseif ($guessedType && isset($tablesByType[$guessedType])) {
        $orderToTry[] = $tablesByType[$guessedType];
    }
    // doplň zbylé tabulky jako fallbacky
    foreach ($tablesByType as $t) {
        if (!in_array($t, $orderToTry, true)) {
            $orderToTry[] = $t;
        }
    }

    $product = null;
    $resolvedType = $productType ?: ($guessedType ?: '');
    error_log('[add-to-cart] guessedType=' . ($guessedType ?? 'null') . ' initialResolved=' . $resolvedType);
    foreach ($orderToTry as $t) {
        $stmt = $conn->prepare("SELECT * FROM $t WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $product = $row;
            // nastav skutečný typ dle nalezené tabulky
            $resolvedType = ($t === 'product' ? 'product' : ($t === 'product2' ? 'product2' : 'product3'));
            error_log('[add-to-cart] found product in table=' . $t . ' resolvedType=' . $resolvedType);
            break;
        }
    }

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Produkt nebyl nalezen']);
        exit;
    }

    // Připravit košík
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Získat barvu a varianty z requestu
    $selectedColor = isset($data['color']) ? $data['color'] : '';
    $variants = isset($data['variants']) && is_array($data['variants']) ? $data['variants'] : [];
    $variantPrices = isset($data['variant_prices']) && is_array($data['variant_prices']) ? $data['variant_prices'] : [];
    $componentColors = isset($data['component_colors']) && is_array($data['component_colors']) ? $data['component_colors'] : [];
    $catalogSelection = isset($data['catalog_selection']) && is_array($data['catalog_selection']) ? $data['catalog_selection'] : [];
    $quantity = isset($data['quantity']) ? max(1, (int)$data['quantity']) : 1;
    
    // Vytvoř klíč pro košík (unikátní pro každou kombinaci variant a barevných komponentů)
    $variantKey = !empty($variants) ? md5(json_encode($variants)) : '';
    $componentKey = !empty($componentColors) ? md5(json_encode($componentColors)) : '';
    $cartKey = $productId . '-' . $resolvedType . '-' . $selectedColor . '-' . $variantKey . '-' . $componentKey;

    // Spočítej efektivní cenu se slevou (pokud je aktivní)
    $basePrice = (float)($product['price'] ?? 0);
    $effectivePrice = $basePrice;
    
    // Přidej ceny z variant (použij ceny z frontendu)
    $variantPriceAdjustment = 0;
    if (!empty($variantPrices)) {
        foreach ($variantPrices as $variantName => $price) {
            $variantPriceAdjustment += (float)$price;
        }
        error_log('[add-to-cart] variant prices: ' . print_r($variantPrices, true) . ' total adjustment: ' . $variantPriceAdjustment);
    }
    
    $basePrice += $variantPriceAdjustment;
    $effectivePrice = $basePrice;
    if (!empty($product['sale_enabled']) && (int)$product['sale_enabled'] === 1) {
        $sp = isset($product['sale_price']) ? (float)$product['sale_price'] : 0;
        if ($sp > 0 && $sp < $basePrice) {
            if (!empty($product['sale_end'])) {
                $endTs = strtotime((string)$product['sale_end']);
                if ($endTs && time() < $endTs) { $effectivePrice = $sp; }
            } else { $effectivePrice = $sp; }
        }
    }

    if (isset($_SESSION['cart'][$cartKey])) {
        // Aktualizuj případně cenu (sleva mohla mezitím začít/ skončit)
        $_SESSION['cart'][$cartKey]['final_price'] = $effectivePrice;
        $_SESSION['cart'][$cartKey]['price'] = $basePrice;
        $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
    } else {
        // První obrázek
        $imageUrl = '';
        if (!empty($product['image_url'])) {
            $parts = explode(',', $product['image_url']);
            $imageUrl = $parts[0];
        }

        $_SESSION['cart'][$cartKey] = [
            'id' => $productId,
            'product_type' => $resolvedType,
            'name' => $product['name'] ?? ($product['title'] ?? 'Produkt'),
            'price' => $basePrice,
            'final_price' => $effectivePrice,
            'selected_color' => $selectedColor,
            'color_price' => 0,
            'quantity' => $quantity,
            'image_url' => $imageUrl,
            'variants' => $variants,
            'variant_price_adjustment' => $variantPriceAdjustment,
            'component_colors' => $componentColors,
            'catalog_selection' => $catalogSelection
        ];
    }

    // Spočítat počet kusů v košíku
    $cart_count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int)$item['quantity'];
    }

    error_log('[add-to-cart] success add: id=' . $productId . ' type=' . $resolvedType . ' cart_key=' . $cartKey . ' cart_count=' . $cart_count);
    echo json_encode(['success' => true, 'cart_count' => $cart_count]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chyba serveru: ' . $e->getMessage()]);
    exit;
}
?>
