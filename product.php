<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Maintenance check moved below DB connection

// Database connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";
try { 
    $conn = new PDO($dsn, $username, $password); 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
    $conn->query("SET NAMES utf8"); 
} catch (PDOException $e) { 
    echo "Connection failed: ".$e->getMessage(); 
    exit; 
}

// Maintenance Mode Check
$maintenance_mode = false;
try {
    $stmt = $conn->query("SELECT maintenance_mode FROM settings WHERE id = 1");
    $sett = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sett && $sett['maintenance_mode'] == 1) {
        $maintenance_mode = true;
    }
} catch (Exception $e) {}

if ($maintenance_mode && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    header('Location: maintenance.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Check if product has 3D model for AR viewing
$has3DModel = false;
$model3DPath = '';

// Helper function to follow URL redirects (for shorturl.at, bit.ly, etc.)
function followRedirect($url, $maxRedirects = 5) {
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'follow_location' => 1,
            'max_redirects' => $maxRedirects,
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
        ]
    ]);
    
    // Try to get headers
    $headers = @get_headers($url, 1, $context);
    
    if ($headers === false) {
        // If get_headers fails, try file_get_contents which might work better
        $finalUrl = @file_get_contents($url, false, $context);
        if ($finalUrl !== false) {
            // Get the actual URL after redirects from context
            $redirectUrl = $http_response_header[0] ?? '';
            if (preg_match('/Location:\s*(.+)/', implode("\n", $http_response_header ?? []), $matches)) {
                return trim($matches[1]);
            }
        }
        return $url; // Return original if all failed
    }
    
    // Check if there was a redirect
    if (isset($headers['Location'])) {
        $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
        // Make sure it's an absolute URL
        if (strpos($location, 'http') !== 0) {
            $parts = parse_url($url);
            $location = $parts['scheme'] . '://' . $parts['host'] . $location;
        }
        return $location;
    }
    
    return $url;
}

function imgSrcFromProduct($p) {
  if (empty($p) || !is_array($p)) {
    return 'images/product-thumb-11.jpg';
  }
  
  $images = [];
  if (!empty($p['image_url'])) {
    $images = explode(',', $p['image_url']);
  }
  
  $first = '';
  if (!empty($images) && isset($images[0])) {
    $first = trim($images[0]);
  }
  
  if ($first === '') {
    return 'images/product-thumb-11.jpg';
  }
  
  // External URLs
  if (preg_match('~^https?://~i', $first)) {
    // Handle URL shorteners (shorturl.at, bit.ly, etc.) - follow redirect to get actual URL
    if (preg_match('/(shorturl\.at|bit\.ly|tinyurl\.com|goo\.gl)/', $first)) {
        $actualUrl = followRedirect($first);
        if ($actualUrl && $actualUrl !== $first) {
            $first = $actualUrl;
        }
    }
    
    // Convert Google Drive URL if needed - use direct image URL
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $first, $matches)) {
        return 'https://lh3.googleusercontent.com/d/' . $matches[1];
    }
    // Also handle /uc format and convert it
    if (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $first, $matches)) {
        return 'https://lh3.googleusercontent.com/d/' . $matches[1];
    }
    return $first;
  }
  
  // Normalize path - remove leading slashes and dots
  $normalized = ltrim($first, './');
  $normalized = ltrim($normalized, '/');
  
  // If path starts with 'admin/', it's already correct from root
  if (strpos($normalized, 'admin/') === 0) {
    return $normalized;
  }
  
  // If path starts with 'uploads/', it could be in admin/ or root
  // Check if file exists in admin/uploads/ first (newer structure)
  // If not, use direct uploads/ path (older structure)
  if (strpos($normalized, 'uploads/') === 0) {
    $adminPath = 'admin/' . $normalized;
    $directPath = $normalized;
    
    // Check if file exists in admin/uploads/ directory
    if (file_exists(__DIR__ . '/' . $adminPath)) {
      return $adminPath;
    }
    // If not in admin/, try direct path (for older uploads)
    if (file_exists(__DIR__ . '/' . $directPath)) {
      return $directPath;
    }
    // Default to admin path (for new uploads)
    return $adminPath;
  }
  
  // Fallback: try relative path
  return '../' . $normalized;
}

try {
  $stmt = $conn->prepare('SELECT * FROM product WHERE id = ? LIMIT 1');
  $stmt->execute([$id]);
  $product = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$product || !is_array($product)) { 
    header('Location: index.php'); 
    exit; 
  }
  // Allow access to hidden products via direct link
  // Hidden products are only accessible through direct URL

  // Check for 3D model
  if (!empty($product['model_3d_path'])) {
    $model3DPath = trim($product['model_3d_path']);
    // Check if file exists
    if (file_exists(__DIR__ . '/' . $model3DPath)) {
      $has3DModel = true;
    }
  }
  
  // Helper function to convert Google Drive URLs to direct image URLs
  function convertGoogleDriveUrl($url) {
    if (empty($url)) return $url;
    
    // If already converted, return as is
    if (strpos($url, 'drive.google.com/thumbnail') !== false) {
      return $url;
    }
    
    // Don't convert if it's not a Google Drive URL
    if (strpos($url, 'drive.google.com') === false) {
      return $url;
    }
    
    // Extract file ID from Google Drive URLs
    // Handle multiple formats:
    // 1. /file/d/{ID}/view?usp=drive_link
    // 2. /file/d/{ID}/view
    // 3. /file/d/{ID}
    // 4. /uc?id={ID}
    if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
      $fileId = $matches[1];
      // Use uc?export=view format - works better for public files
      return 'https://drive.google.com/uc?export=view&id=' . $fileId;
    } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
      $fileId = $matches[1];
      return 'https://drive.google.com/uc?export=view&id=' . $fileId;
    } elseif (preg_match('/drive\.google\.com\/thumbnail\?id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
      // Already thumbnail format, convert to uc format
      $fileId = $matches[1];
      return 'https://drive.google.com/uc?export=view&id=' . $fileId;
    }
    
    return $url;
  }
  
  // Load component images for configurator
  $componentImages = [];
  if (!empty($product['component_images'])) {
    $componentImages = json_decode($product['component_images'], true) ?: [];
    
    // Convert Google Drive URLs in component images
    foreach ($componentImages as $compType => &$variants) {
      foreach ($variants as $idx => &$variant) {
        if (!empty($variant['image'])) {
          $variant['image'] = convertGoogleDriveUrl($variant['image']);
        }
        
        if (!empty($variant['colors'])) {
          foreach ($variant['colors'] as &$color) {
            if (!empty($color['image'])) {
              $color['image'] = convertGoogleDriveUrl($color['image']);
            }
          }
          unset($color); // Clear reference after loop
        }
      }
      unset($variant); // Clear reference after loop
    }
    unset($variants); // Clear reference after loop
  }
} catch (PDOException $e) { 
  error_log('Product.php error: ' . $e->getMessage());
  header('Location: index.php'); 
  exit; 
}

// Load product collections (supports new schema with linking table)
$productCollections = [];
try {
  $stmt = $conn->prepare('SELECT pcm.id, pcm.name, pcm.slug FROM product_collection_items pci JOIN product_collections_main pcm ON pcm.id = pci.collection_id WHERE pci.product_id = ? AND (pcm.is_active IS NULL OR pcm.is_active = 1) ORDER BY pci.position ASC, pcm.name ASC');
  $stmt->execute([$id]);
  $productCollections = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
  // Fallbacks for older schemas
  $collectionName = '';
  if (!empty($product['collection'])) { $collectionName = (string)$product['collection']; }
  elseif (!empty($product['collection_name'])) { $collectionName = (string)$product['collection_name']; }
  elseif (!empty($product['kolekce'])) { $collectionName = (string)$product['kolekce']; }
  elseif (!empty($product['collection_id']) && is_numeric($product['collection_id'])) {
    try {
      $stmt2 = $conn->prepare('SELECT name FROM collections WHERE id = ? LIMIT 1');
      $stmt2->execute([$product['collection_id']]);
      $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
      if (!empty($row2['name'])) { $collectionName = (string)$row2['name']; }
    } catch (PDOException $e2) {
      try {
        $stmt3 = $conn->prepare('SELECT name FROM kolekce WHERE id = ? LIMIT 1');
        $stmt3->execute([$product['collection_id']]);
        $row3 = $stmt3->fetch(PDO::FETCH_ASSOC);
        if (!empty($row3['name'])) { $collectionName = (string)$row3['name']; }
      } catch (PDOException $e3) { /* ignore */ }
    }
  }
  if (!empty($collectionName)) {
    $productCollections = [['id' => null, 'name' => $collectionName, 'slug' => null]];
  }
}

// Check product availability
$isAvailable = true;
$availabilityMessage = '';
$availableFromDate = null;

// Check available_from date
if (!empty($product['available_from'])) {
  $availableFromTimestamp = strtotime($product['available_from']);
  $currentTimestamp = time();
  
  if ($availableFromTimestamp && $availableFromTimestamp > $currentTimestamp) {
    // Product is not yet available
    $isAvailable = false;
    $availableFromDate = date('d.m.Y', $availableFromTimestamp);
    $availabilityMessage = 'Produkt bude dostupný od ' . $availableFromDate;
  }
}

// Check availability field (availability column)
if ($isAvailable && !empty($product['availability'])) {
  $availability = strtolower(trim($product['availability']));
  $unavailableKeywords = ['nedostupné', 'vyprodáno', 'out of stock', 'unavailable', 'na objednávku'];
  
  foreach ($unavailableKeywords as $keyword) {
    if (stripos($availability, $keyword) !== false) {
      $isAvailable = false;
      $availabilityMessage = htmlspecialchars($product['availability']);
      break;
    }
  }
}
// Fetch related products (same collection, random fallback)
$relatedProducts = [];
$relatedLimit = 4;

// 1. Try to get products from the same collection
if (!empty($productCollections)) {
    $collectionIds = array_column($productCollections, 'id');
    // Filter out null IDs
    $collectionIds = array_filter($collectionIds);
    
    if (!empty($collectionIds)) {
        $inQuery = implode(',', array_fill(0, count($collectionIds), '?'));
        $sql = "SELECT p.* FROM product p 
                JOIN product_collection_items pci ON p.id = pci.product_id 
                WHERE pci.collection_id IN ($inQuery) 
                AND p.id != ? 
                AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
                GROUP BY p.id
                LIMIT $relatedLimit";
        
        $params = array_merge($collectionIds, [$id]);
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Ignore error, fallback to random
        }
    }
}

// 2. Fallback: Random products if we don't have enough
if (count($relatedProducts) < $relatedLimit) {
    $needed = $relatedLimit - count($relatedProducts);
    $excludeIds = [$id];
    foreach ($relatedProducts as $rp) {
        $excludeIds[] = $rp['id'];
    }
    
    $inQuery = implode(',', array_fill(0, count($excludeIds), '?'));
    $sql = "SELECT * FROM product 
            WHERE id NOT IN ($inQuery) 
            AND (is_hidden = 0 OR is_hidden IS NULL)
            ORDER BY RAND() 
            LIMIT $needed";
            
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($excludeIds);
        $randomProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $relatedProducts = array_merge($relatedProducts, $randomProducts);
    } catch (PDOException $e) {
        // Ignore error
    }
}

// Check stock_status if exists
if ($isAvailable && isset($product['stock_status'])) {
  $stockStatus = strtolower(trim($product['stock_status']));
  if (in_array($stockStatus, ['out_of_stock', 'preorder'])) {
    $isAvailable = false;
    if ($stockStatus === 'preorder') {
      $availabilityMessage = 'Předobjednávka';
    } else {
      $availabilityMessage = 'Vyprodáno';
    }
  }
}

// Precompute sale info for use across the template (gallery ribbon, price block)
$base = (float)($product['price'] ?? 0);
$saleActive = false;
$saleUpcoming = false; // Initialize
$salePrice = 0;
// Explicitly set timezone to avoid UTC mismatches
date_default_timezone_set('Europe/Prague');

if (!empty($product['sale_enabled']) && (int)$product['sale_enabled']===1 && !empty($product['sale_price']) && (float)$product['sale_price']>0 && (float)$product['sale_price']<$base) {
    $currentTime = time();
    $saleStartTs = !empty($product['sale_start']) ? strtotime($product['sale_start']) : 0;
    $saleEndTs = !empty($product['sale_end']) ? strtotime($product['sale_end']) : 0;

    // debug: error_log("Product ID {$product['id']}: Sale Start: " . $product['sale_start'] . " ($saleStartTs), Now: $currentTime");

    if ($saleStartTs > 0 && $currentTime < $saleStartTs) {
        // Sale is upcoming (Start time is in the future)
        $saleUpcoming = true;
        $saleStartDate = $product['sale_start'];
    } else {
        // Sale startup time is past (or not set) -> Check if it has ended
        if ($saleEndTs > 0) {
             if ($currentTime < $saleEndTs) {
                 // Active: Started and not yet encoded
                 $saleActive = true;
                 $salePrice = (float)$product['sale_price'];
             }
             // Else: Ended
        } else {
             // No end time -> Active indefinitely
             $saleActive = true;
             $salePrice = (float)$product['sale_price'];
        }
    }
}
$discountPct = 0;
$saleEndIso = '';
if (($saleActive || $saleUpcoming) && $base > 0) {
  // Calculate discount based on sale price (which is set for both active and upcoming)
  $discountPct = max(1, (int)round((1 - ($product['sale_price'] / max(0.01, $base))) * 100));
  
  if ($saleActive && !empty($product['sale_end'])) {
    $saleEndTs = strtotime($product['sale_end']);
    if ($saleEndTs) { $saleEndIso = date('c', $saleEndTs); }
  }
}
?>
<!doctype html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($product['name'] ?? 'Produkt') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">
    



<?php
// SEO Meta tagy - Enhanced
$pageTitle = htmlspecialchars($product['name']) . ' | KubaJaDesigns';
$cleanDesc = mb_substr(strip_tags($product['description']), 0, 160) . '...';
$mainImage = 'https://kubajadesigns.eu/' . imgSrcFromProduct($product);
$pageUrl = 'https://kubajadesigns.eu/product.php?id=' . $product['id'];

echo '<title>' . $pageTitle . '</title>' . "\n";
echo '<meta name="description" content="' . htmlspecialchars($cleanDesc) . '">' . "\n";
echo '<link rel="canonical" href="' . $pageUrl . '">' . "\n";

// Open Graph / Facebook
echo '<meta property="og:type" content="product">' . "\n";
echo '<meta property="og:url" content="' . $pageUrl . '">' . "\n";
echo '<meta property="og:title" content="' . htmlspecialchars($product['name']) . '">' . "\n";
echo '<meta property="og:description" content="' . htmlspecialchars($cleanDesc) . '">' . "\n";
echo '<meta property="og:image" content="' . $mainImage . '">' . "\n";
echo '<meta property="og:site_name" content="KubaJaDesigns">' . "\n";
echo '<meta property="product:price:amount" content="' . htmlspecialchars($product['price']) . '">' . "\n";
echo '<meta property="product:price:currency" content="CZK">' . "\n";

// Twitter
echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
echo '<meta name="twitter:title" content="' . htmlspecialchars($product['name']) . '">' . "\n";
echo '<meta name="twitter:description" content="' . htmlspecialchars($cleanDesc) . '">' . "\n";
echo '<meta name="twitter:image" content="' . $mainImage . '">' . "\n";
?>

<!-- JSON-LD strukturovaná data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": "<?php echo htmlspecialchars($product['name']); ?>",
  "description": "<?php echo htmlspecialchars($product['description']); ?>",
  "image": "https://kubajadesigns.eu/<?php echo htmlspecialchars(imgSrcFromProduct($product)); ?>",
  "sku": "<?php echo isset($product['sku']) ? htmlspecialchars($product['sku']) : 'KJD-' . $product['id']; ?>",
  "mpn": "<?php echo $product['id']; ?>",
  "brand": {
    "@type": "Brand",
    "name": "KubaJaDesigns"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://kubajadesigns.eu/product.php?id=<?php echo $product['id']; ?>",
    "priceCurrency": "CZK",
    "price": "<?php echo htmlspecialchars($product['price']); ?>",
    "priceValidUntil": "<?php echo date('Y-m-d', strtotime('+1 year')); ?>",
    "itemCondition": "https://schema.org/NewCondition",
    "availability": "https://schema.org/InStock",
    "seller": {
      "@type": "Organization",
      "name": "KubaJaDesigns"
    }
  }
}
</script>



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
      
      body { background:#f8f9fa; color: var(--kjd-dark-green); min-height: 100vh; }
      a { color: var(--kjd-earth-green); }
      a:hover { color: var(--kjd-gold-brown); }
      
      /* Product page specific styles */
      .product-page { background: #f8f9fa; min-height: 100vh; }
      .product-header { 
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
        padding: 1rem 0; 
        margin-bottom: 1rem; 
        border-bottom: 2px solid var(--kjd-earth-green);
        box-shadow: 0 2px 10px rgba(16,40,32,0.1);
      }
      
      .product-card { 
        background: #fff; 
        border-radius: 12px; 
        padding: 1.5rem; 
        box-shadow: 0 4px 15px rgba(16,40,32,0.1);
        border: 1px solid var(--kjd-beige);
        margin-bottom: 1rem;
      }
      
      .btn-kjd { 
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
        color:#fff; 
        border:none; 
        padding: 1rem 2rem;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(77,45,24,0.3);
      }
      .btn-kjd:hover { 
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(77,45,24,0.4);
      }
      
      .price { color: var(--kjd-dark-green); font-weight:700; font-size:1.5rem; }
      .price .old { color:#888; text-decoration:line-through; margin-right:8px; font-weight:600; }
      .badge-preorder { background: var(--kjd-beige); color: var(--kjd-dark-brown); padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.9rem; }
      .gallery { position: relative; }
      .gallery img { width:100%; height:400px; object-fit:cover; border-radius:12px; box-shadow: 0 4px 15px rgba(16,40,32,0.1); cursor: pointer; transition: all 0.3s ease; }
      .gallery img:hover { transform: scale(1.02); box-shadow: 0 8px 25px rgba(16,40,32,0.2); }
      .thumbs img { width:100%; height:80px; object-fit:cover; border-radius:8px; cursor:pointer; opacity:.8; transition: all 0.2s ease; }
      .thumbs img:hover { opacity: 1; transform: scale(1.05); }
      .thumbs img.active { outline:3px solid var(--kjd-earth-green); opacity:1; }
      .content h2 { color: var(--kjd-dark-brown); font-size:1.3rem; margin:1rem 0 0.8rem; font-weight: 700; }
      .content ul { padding-left:1.2rem; }
      
      /* SALE styling */
      .sale-ribbon {
        position: absolute;
        top: 12px;
        left: 12px;
        background: linear-gradient(135deg, #c62828, #ff7043);
        color: #fff;
        padding: 0.4rem 0.75rem;
        border-radius: 10px;
        font-weight: 800;
        box-shadow: 0 6px 18px rgba(198,40,40,0.35);
        z-index: 2;
      }
      .price-sale {
        background: rgba(198,40,40,0.08);
        border: 2px solid rgba(198,40,40,0.25);
        border-radius: 12px;
        padding: 0.6rem 0.8rem;
        display: inline-flex;
        align-items: baseline;
        gap: 10px;
      }
      .price-sale .new {
        color: #c62828;
        font-size: 1.7rem;
        font-weight: 900;
      }
      .discount-chip {
        background: #c62828;
        color: #fff;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-weight: 800;
        font-size: .9rem;
        box-shadow: 0 4px 12px rgba(198,40,40,0.35);
      }
      .sale-countdown {
        display: inline-block;
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
        color: var(--kjd-dark-brown);
        border: 2px solid var(--kjd-earth-green);
        padding: 0.4rem 0.8rem;
        border-radius: 10px;
        font-weight: 800;
        margin-left: 8px;
        box-shadow: 0 4px 12px rgba(76,100,68,0.2);
        font-size: 0.9rem;
      }
      
      .qty-controls { 
        display: flex; 
        align-items: center; 
        gap: 0.5rem; 
        background: var(--kjd-beige);
        padding: 0.4rem;
        border-radius: 8px;
        border: 1px solid var(--kjd-earth-green);
        max-width: 180px;
      }
      .qty-btn { 
        width: 32px; 
        height: 32px; 
        border-radius: 50%; 
        border: 1px solid var(--kjd-earth-green); 
        background: #fff; 
        color: var(--kjd-earth-green); 
        display: flex; 
        align-items: center; 
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.2s ease;
      }
      .qty-btn:hover { 
        background: var(--kjd-earth-green); 
        color: #fff;
        transform: scale(1.1);
      }
      .qty-input { 
        width: 60px; 
        text-align: center; 
        border: 1px solid var(--kjd-earth-green); 
        border-radius: 6px; 
        padding: 0.5rem; 
        font-weight: 700;
        font-size: 1rem;
        background: #fff;
      }
      /* Mobile sticky add-to-cart bar */
      @media (max-width: 576px) {
        .mobile-addbar { 
          position: fixed; 
          left: 0; right: 0; bottom: 0; 
          background: #fff; 
          border-top: 2px solid var(--kjd-beige); 
          box-shadow: 0 -6px 20px rgba(16,40,32,0.12); 
          padding: 0.75rem 1rem; 
          z-index: 10000; 
        }
        .mobile-addbar .btn { width: 100%; }
        body { padding-bottom: 84px; }
      }
      
      /* Variants & colors */
      .variant-group { 
        background: rgba(202,186,156,0.1); 
        padding: 1rem; 
        border-radius: 8px; 
        margin-bottom: 1rem;
        border: 1px solid var(--kjd-beige);
      }
      .variant-group label { font-weight:700; color:var(--kjd-dark-brown); margin-bottom:.3rem; font-size: 1rem; }
      /* Variant chips */
      .variant-radio { display: none; }
      .variant-chip { display:inline-block; margin:4px 8px 6px 0; padding:8px 12px; border:2px solid var(--kjd-earth-green); border-radius:999px; cursor:pointer; font-weight:700; font-size:.95rem; color:var(--kjd-dark-green); background:#fff; transition:all .2s ease; }
      .variant-radio:disabled + .variant-chip { opacity:.5; cursor:not-allowed; }
      .variant-radio:checked + .variant-chip { background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); color:#fff; }
      .color-swatch { width:32px; height:32px; border-radius:50%; border:2px solid #fff; box-shadow:0 0 0 2px var(--kjd-beige); cursor:pointer; display:inline-block; margin-right:8px; position:relative; transition: all 0.2s ease; }
      .color-swatch:hover { transform: scale(1.1); box-shadow:0 0 0 4px var(--kjd-earth-green); }
      .color-swatch.unavailable { cursor: pointer; opacity: 0.6; }
      .color-swatch.variant-blocked { cursor:not-allowed; opacity:.4; filter: grayscale(40%); }
      .color-swatch.unavailable::after { content:''; position:absolute; left:50%; top:50%; width:20px; height:2px; background:#c62828; transform:translate(-50%,-50%) rotate(45deg); }
      .color-swatch.unavailable::before { content:''; position:absolute; left:50%; top:50%; width:20px; height:2px; background:#c62828; transform:translate(-50%,-50%) rotate(-45deg); }
      .color-swatch.unavailable:hover { opacity: 0.8; transform: scale(1.1); }
      .color-swatch.selected { box-shadow:0 0 0 4px var(--kjd-earth-green); }
      .color-swatch[title]:hover::after { content: attr(title); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: var(--kjd-dark-green); color: white; padding: 6px 12px; border-radius: 6px; font-size: 12px; white-space: nowrap; z-index: 1000; margin-bottom: 8px; }
      .color-swatch[title]:hover::before { content: ''; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); border: 6px solid transparent; border-top-color: var(--kjd-dark-green); z-index: 1000; }
      .variant-select { border-color: var(--kjd-beige); }
      
      /* Tables inside specs */
      .content table { width:100%; border-collapse:collapse; margin:12px 0; background:#fff; border-radius: 8px; overflow: hidden; }
      .content table th, .content table td { border:1px solid rgba(16,40,32,0.15); padding:12px 16px; vertical-align:top; }
      .content table th { background: rgba(202,186,156,0.35); color: var(--kjd-dark-brown); font-weight:700; width:32%; }
      .content table tr:nth-child(even) td { background: rgba(16,40,32,0.02); }
      .content .note { background: rgba(202,186,156,0.55); border-left:4px solid var(--kjd-gold-brown); padding:12px; border-radius:8px; }
      
      /* Lightbox styles */
      .lightbox { 
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        background-color: rgba(0,0,0,0.9); 
        cursor: pointer;
      }
      .lightbox-content { 
        position: absolute; 
        top: 50%; 
        left: 50%; 
        transform: translate(-50%, -50%); 
        max-width: 90%; 
        max-height: 90%; 
        object-fit: contain;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      }
      .lightbox-close { 
        position: absolute; 
        top: 20px; 
        right: 35px; 
        color: #fff; 
        font-size: 40px; 
        font-weight: bold; 
        cursor: pointer; 
        z-index: 10000;
        background: rgba(0,0,0,0.5);
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
      }
      .lightbox-close:hover { 
        background: rgba(0,0,0,0.8); 
        transform: scale(1.1);
      }
      
      /* Configurator color buttons */
      .configurator-color-btn {
        cursor: pointer;
        border: 2px solid var(--kjd-earth-green) !important;
      }
      .configurator-color-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(16,40,32,0.2);
        border-color: var(--kjd-dark-green) !important;
      }
      .configurator-color-btn.active {
        border-color: var(--kjd-dark-green) !important;
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8) !important;
        box-shadow: 0 0 0 3px rgba(16,40,32,0.15), 0 4px 15px rgba(16,40,32,0.2);
        transform: scale(1.05);
      }
      
      /* Configurator nav buttons */
      .configurator-nav:hover {
        background: var(--kjd-earth-green) !important;
        color: #fff !important;
        transform: translateY(-50%) scale(1.1);
      }
      
      /* Selected colors display */
      .selected-colors-display {
        box-shadow: 0 4px 15px rgba(16,40,32,0.1);
      }
      
      /* Mobile responsive styles */
      @media (max-width: 768px) {
        /* Product header - mobilní */
        .product-header {
          padding: 1rem 0 !important;
          margin-bottom: 0.5rem !important;
        }
        
        .product-header h1 {
          font-size: 1.5rem !important;
        }
        
        .product-header .btn {
          padding: 0.5rem 1rem !important;
          font-size: 0.85rem !important;
        }
        
        /* Product card - mobilní */
        .product-card {
          padding: 1rem !important;
          margin-bottom: 0.75rem !important;
        }
        
        /* Gallery - mobilní */
        .gallery img {
          height: 300px !important;
        }
        
        .thumbs {
          margin-top: 0.75rem !important;
        }
        
        .thumbs img {
          height: 60px !important;
        }
        
        /* Price - mobilní */
        .price {
          font-size: 1.3rem !important;
        }
        
        .price-sale .new {
          font-size: 1.5rem !important;
        }
        
        .sale-ribbon {
          font-size: 0.85rem !important;
          padding: 0.3rem 0.6rem !important;
        }
        
        /* Variant groups - mobilní */
        .variant-group {
          padding: 0.75rem !important;
        }
        
        .variant-group label {
          font-size: 0.9rem !important;
        }
        
        .variant-chip {
          font-size: 0.85rem !important;
          padding: 6px 10px !important;
          margin: 3px 6px 4px 0 !important;
        }
        
        /* Color swatches - mobilní */
        .color-swatch {
          width: 36px !important;
          height: 36px !important;
          margin-right: 6px !important;
        }
        
        /* Final lamp preview - mobilní */
        .final-lamp-preview {
          padding: 1.5rem 1rem !important;
        }
        
        .final-lamp-container {
          height: 400px !important;
        }
        
        /* Přepínač způsobů výběru - mobilní */
        .color-selection-mode-btn {
          font-size: 0.85rem !important;
          padding: 0.6rem 1rem !important;
        }
        
        /* Konfigurátor modal - mobilní optimalizace */
        #configuratorModal .modal-dialog {
          margin: 0;
          max-width: 100%;
          height: 100vh;
        }
        
        #configuratorModal .modal-content {
          border-radius: 0;
          height: 100vh;
          display: flex;
          flex-direction: column;
        }
        
        #configuratorModal .modal-header {
          padding: 1rem 1.5rem;
          flex-shrink: 0;
        }
        
        #configuratorModal .modal-header h3 {
          font-size: 1.4rem;
        }
        
        #configuratorModal .modal-body {
          padding: 1rem;
          flex: 1;
          overflow-y: auto;
          -webkit-overflow-scrolling: touch;
        }
        
        /* Kompletní náhled lampy - mobilní */
        .full-lamp-preview {
          min-height: 400px !important;
          padding: 0.5rem !important;
        }
        
        .lamp-preview-container {
          height: 380px !important;
          max-width: 100% !important;
        }
        
        #fullLampPreviewTop {
          height: 75% !important;
          top: 0% !important;
        }
        
        #fullLampPreviewBottom {
          height: 70% !important;
          top: 27% !important;
          transform: translateX(-50%) translateY(-35%) !important;
        }
        
        /* Zobrazení vybraných barev - mobilní */
        .selected-colors-display {
          padding: 0.75rem !important;
          font-size: 0.9rem;
        }
        
        .selected-colors-display .row {
          flex-direction: column;
        }
        
        .selected-colors-display .col-md-6 {
          margin-bottom: 0.5rem;
        }
        
        /* Konfigurátor komponent - mobilní */
        .component-configurator h4 {
          font-size: 1.1rem !important;
        }
        
        .configurator-image-wrapper {
          min-height: 250px !important;
          padding: 1rem !important;
        }
        
        .configurator-image {
          max-height: 220px !important;
        }
        
        .configurator-nav {
          width: 35px !important;
          height: 35px !important;
          font-size: 1rem !important;
        }
        
        /* Barevná tlačítka - mobilní */
        .configurator-color-btn {
          min-width: 70px !important;
          padding: 0.5rem !important;
        }
        
        .configurator-color-btn img {
          width: 45px !important;
          height: 45px !important;
        }
        
        .configurator-color-btn small,
        .configurator-color-btn div {
          font-size: 0.75rem !important;
        }
        
        /* Inline barevná tlačítka - mobilní */
        .inline-color-btn {
          min-width: 65px !important;
          padding: 0.4rem !important;
        }
        
        .inline-color-btn img {
          width: 40px !important;
          height: 40px !important;
        }
        
        /* Tlačítko otevřít konfigurátor - mobilní */
        #openConfiguratorBtn {
          padding: 0.75rem 1.5rem !important;
          font-size: 0.95rem !important;
          width: 100%;
          margin-top: 0.5rem;
        }
        
        /* Přidat do košíku tlačítko - mobilní */
        #addBtn {
          padding: 1rem 2rem !important;
          font-size: 1rem !important;
          min-width: 100% !important;
        }
        
        /* Quantity controls - mobilní */
        .qty-controls {
          max-width: 100% !important;
          justify-content: center;
        }
        
        /* Modal footer - mobilní */
        #configuratorModal .modal-footer {
          padding: 1rem !important;
          flex-direction: column;
          gap: 0.75rem;
          flex-shrink: 0;
          background: #f8f9fa;
          border-top: 2px solid var(--kjd-beige);
          box-shadow: 0 -4px 15px rgba(16,40,32,0.1);
          position: relative;
          z-index: 10;
        }
        
        #configuratorModal .modal-footer .btn {
          width: 100%;
          padding: 1rem !important;
          font-size: 1rem;
          font-weight: 600;
          border-radius: 10px;
        }
        
        #configuratorModal .modal-footer .btn-kjd-secondary {
          border: 2px solid var(--kjd-earth-green);
          background: #fff;
          color: var(--kjd-dark-green);
        }
        
        #configuratorModal .modal-footer .btn-kjd-primary {
          background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green));
          border: none;
          color: #fff;
          box-shadow: 0 4px 15px rgba(16,40,32,0.3);
        }
        
        /* Varianty obrázků komponent - mobilní layout */
        .component-configurator .row {
          flex-direction: column;
        }
        
        .component-configurator .col-md-6 {
          width: 100%;
          margin-bottom: 1.5rem;
        }
        
        /* Content - mobilní */
        .content h2 {
          font-size: 1.1rem !important;
        }
        
        .content table {
          font-size: 0.85rem !important;
        }
        
        .content table th,
        .content table td {
          padding: 8px 12px !important;
        }
        
        /* Lightbox - mobilní */
        .lightbox-close {
          top: 10px !important;
          right: 15px !important;
          width: 40px !important;
          height: 40px !important;
          font-size: 30px !important;
        }
        
        .lightbox-content {
          max-width: 95% !important;
          max-height: 85% !important;
        }
      }
      
      @media (max-width: 480px) {
        /* Extra malé obrazovky */
        .product-header h1 {
          font-size: 1.3rem !important;
        }
        
        .product-header .btn {
          padding: 0.4rem 0.8rem !important;
          font-size: 0.75rem !important;
        }
        
        .product-card {
          padding: 0.75rem !important;
        }
        
        .gallery img {
          height: 250px !important;
        }
        
        .thumbs img {
          height: 50px !important;
        }
        
        .price {
          font-size: 1.2rem !important;
        }
        
        .price-sale .new {
          font-size: 1.3rem !important;
        }
        
        .variant-chip {
          font-size: 0.8rem !important;
          padding: 5px 8px !important;
        }
        
        .color-swatch {
          width: 32px !important;
          height: 32px !important;
        }
        
        .final-lamp-preview {
          padding: 1rem 0.75rem !important;
        }
        
        .final-lamp-container {
          height: 350px !important;
        }
        
        .full-lamp-preview {
          min-height: 350px !important;
        }
        
        .lamp-preview-container {
          height: 330px !important;
        }
        
        #fullLampPreviewTop {
          height: 75% !important;
          top: 0% !important;
        }
        
        #fullLampPreviewBottom {
          height: 70% !important;
          top: 27% !important;
          transform: translateX(-50%) translateY(-35%) !important;
        }
        
        #configuratorModal .modal-body {
          max-height: calc(100vh - 180px);
        }
        
        #configuratorModal .modal-header h3 {
          font-size: 1.2rem !important;
        }
        
        .configurator-image-wrapper {
          min-height: 200px !important;
          padding: 0.75rem !important;
        }
        
        .configurator-image {
          max-height: 180px !important;
        }
        
        .configurator-nav {
          width: 30px !important;
          height: 30px !important;
          font-size: 0.9rem !important;
        }
        
        .configurator-color-btn {
          min-width: 60px !important;
          padding: 0.4rem !important;
        }
        
        .configurator-color-btn img {
          width: 35px !important;
          height: 35px !important;
        }
        
        .configurator-color-btn div {
          font-size: 0.7rem !important;
        }
        
        #openConfiguratorBtn {
          padding: 0.6rem 1rem !important;
          font-size: 0.85rem !important;
        }
        
        #addBtn {
          padding: 0.9rem 1.5rem !important;
          font-size: 0.95rem !important;
        }
        
        .qty-controls {
          padding: 0.3rem !important;
        }
        
        .qty-btn {
          width: 28px !important;
          height: 28px !important;
          font-size: 0.9rem !important;
        }
        
        .qty-input {
          width: 50px !important;
          font-size: 0.9rem !important;
        }
        
        .content h2 {
          font-size: 1rem !important;
        }
        
        .content table {
          font-size: 0.8rem !important;
        }
        
        .content table th,
        .content table td {
          padding: 6px 10px !important;
        }
        
        /* Lightbox - extra malé obrazovky */
        .lightbox-close {
          top: 8px !important;
          right: 12px !important;
          width: 35px !important;
          height: 35px !important;
          font-size: 25px !important;
        }
        
        .lightbox-content {
          max-width: 98% !important;
          max-height: 80% !important;
        }
      }
    </style>
    
    <?php if ($has3DModel): ?>
    <!-- Google Model Viewer for AR/3D -->
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
    <?php endif; ?>
  </head>
  <body class="product-page">

    <?php include 'includes/icons.php'; ?>

    <?php include 'includes/navbar.php'; ?>

    <!-- Product Header -->
    <div class="product-header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Detail produktu</p>
                
              </div>
              <button onclick="history.back(); return false;" class="btn btn-kjd-secondary d-flex align-items-center" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); color: var(--kjd-dark-green); border: 2px solid var(--kjd-earth-green); padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 700; text-decoration: none; cursor: pointer;">
                <svg width="20" height="20" class="me-2"><use xlink:href="#arrow-left"></use></svg>
                Zpět
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
      <div class="row g-4">
        <div class="col-md-7">
          <div class="product-card">
            <?php $images = !empty($product['image_url']) ? array_map('trim', explode(',', $product['image_url'])) : []; ?>
            <div class="gallery mb-3">
              <?php if ($saleActive): ?>
                <div class="sale-ribbon">-<?= $discountPct ?>%</div>
              <?php endif; ?>
              <img id="mainImg" src="<?= htmlspecialchars(imgSrcFromProduct($product)) ?>" alt="<?= htmlspecialchars($product['name']) ?>" referrerpolicy="no-referrer">
            </div>
            <?php if (!empty($images) && count($images) > 1): ?>
            <div class="row row-cols-5 g-2 thumbs">
              <?php foreach ($images as $i => $img): 
                if (empty($img)) continue;
                // Normalize image path for thumbnails
                $normalized = trim($img);
                $normalized = ltrim($normalized, './');
                $normalized = ltrim($normalized, '/');
                
                if (preg_match('~^https?://~', $normalized)) {
                  $src = $normalized;
                } elseif (strpos($normalized, 'admin/') === 0) {
                  $src = $normalized;
                } elseif (strpos($normalized, 'uploads/') === 0) {
                  // Check if file exists in admin/uploads/ or direct uploads/
                  $adminPath = 'admin/' . $normalized;
                  $directPath = $normalized;
                  if (file_exists(__DIR__ . '/' . $adminPath)) {
                    $src = $adminPath;
                  } elseif (file_exists(__DIR__ . '/' . $directPath)) {
                    $src = $directPath;
                  } else {
                    // Default to admin path for new uploads
                    $src = $adminPath;
                  }
                } else {
                  $src = '../' . ltrim($normalized, '/');
                }
              ?>
              <div class="col"><img src="<?= htmlspecialchars($src) ?>" data-src="<?= htmlspecialchars($src) ?>" class="<?= $i===0?'active':'' ?>" referrerpolicy="no-referrer"></div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($has3DModel): ?>
            <!-- 3D Model Toggle Button -->
            <div class="mt-4">
              <button id="toggle3DViewer" class="btn btn-kjd-primary w-100" style="padding: 1.25rem; font-size: 1.2rem; font-weight: 700; border-radius: 12px; box-shadow: 0 4px 15px rgba(77,45,24,0.3);">
                <i class="fas fa-cube me-2"></i>Zobrazit 3D Model
                <i class="fas fa-chevron-down ms-2" id="toggle3DIcon"></i>
              </button>
            </div>
            
            <!-- 3D Viewer (collapsible) -->
            <div id="ar3DViewerContainer" class="ar-viewer-container mt-3" style="display: none; border-radius: 16px; overflow: hidden; border: 2px solid var(--kjd-earth-green); background: #f8f9fa;">
              <model-viewer
                id="productModelViewer"
                alt="3D model of <?= htmlspecialchars($product['name']) ?>"
                camera-controls
                shadow-intensity="1"
                style="width: 100%; height: 500px; background-color: #f8f9fa;"
                loading="eager">
                
                <!-- Loading indicator -->
                <div slot="poster" style="display: flex; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);">
                  <div style="text-align: center;">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem; color: var(--kjd-earth-green) !important;">
                      <span class="visually-hidden">Načítání 3D modelu...</span>
                    </div>
                    <p style="margin-top: 1rem; color: var(--kjd-dark-green); font-weight: 600;">Načítání 3D modelu...</p>
                  </div>
                </div>
                <!-- Controls -->
                <div class="controls" style="position: absolute; top: 10px; right: 10px; z-index: 100; display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
                  <div class="bg-white p-2 rounded border shadow-sm d-flex align-items-center">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="showDimensions" style="cursor: pointer;">
                        <label class="form-check-label" for="showDimensions" style="font-weight: 600; color: var(--kjd-dark-green); cursor: pointer; font-size: 0.9rem; margin-left: 8px;">Zobrazit rozměry</label>
                    </div>
                  </div>
                  <button type="button" id="rotateModelBtn" class="btn btn-sm btn-light border shadow-sm" style="color: var(--kjd-dark-green); font-weight: 600;">
                    <i class="fas fa-sync-alt me-2"></i>Otočit
                  </button>
                </div>

                <!-- Dimension Hotspots (Dynamic) -->
                <!-- Dimension Hotspot (Single Consolidated) -->
                <button slot="hotspot-dim" class="dim-hotspot" data-position="0 0 0" data-normal="0 1 0" style="display: none;">
                    <div class="dim-label" id="dim-label-combined"></div>
                </button>

                <style>
                    .dim-hotspot {
                        background: transparent;
                        border: none;
                        padding: 0;
                        pointer-events: none;
                    }
                    .dim-label {
                        background: rgba(255, 255, 255, 0.9);
                        border: 1px solid var(--kjd-earth-green);
                        border-radius: 4px;
                        padding: 4px 8px;
                        font-size: 12px;
                        font-weight: bold;
                        color: var(--kjd-dark-green);
                        white-space: nowrap;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                </style>
              </model-viewer>
              
              <!-- Instructions -->
              <div style="padding: 1rem; background: white; border-top: 2px solid var(--kjd-earth-green);">
                <div style="display: flex; align-items: center; justify-content: center; text-align: center;">
                  <div>
                    <i class="fas fa-mouse-pointer" style="font-size: 1.5rem; color: var(--kjd-gold-brown); margin-bottom: 0.5rem;"></i>
                    <p style="margin: 0; font-size: 0.9rem; color: var(--kjd-dark-brown); font-weight: 600;">
                      Otáčejte modelem tažením myši nebo prstem. Přibližujte kolečkem nebo gestem.
                    </p>
                  </div>
                </div>
              </div>
            </div>
            
            <script>
            document.getElementById('toggle3DViewer').addEventListener('click', function() {
              const container = document.getElementById('ar3DViewerContainer');
              const icon = document.getElementById('toggle3DIcon');
              const viewer = document.getElementById('productModelViewer');
              
              if (container.style.display === 'none') {
                container.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                this.innerHTML = '<i class="fas fa-cube me-2"></i>Skrýt 3D Model<i class="fas fa-chevron-up ms-2"></i>';
                
                // Load and decrypt model if not already loaded
                if (!viewer.src) {
                    const modelPath = '<?= htmlspecialchars($model3DPath) ?>';
                    fetch('serve_model.php?file=' + encodeURIComponent(modelPath))
                        .then(response => {
                            if (!response.ok) throw new Error('Network response was not ok');
                            return response.arrayBuffer();
                        })
                        .then(buffer => {
                            // Decrypt XOR 0x42
                            const view = new Uint8Array(buffer);
                            for (let i = 0; i < view.length; i++) {
                                view[i] ^= 0x42;
                            }
                            // Create Blob URL
                            const blob = new Blob([view], { type: 'model/gltf-binary' });
                            const url = URL.createObjectURL(blob);
                            viewer.src = url;
                        })
                        .catch(error => {
                            console.error('Error loading 3D model:', error);
                        });
                }
                
                // Scroll to viewer after a brief delay
                setTimeout(() => {
                  container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
              } else {
                container.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                this.innerHTML = '<i class="fas fa-cube me-2"></i>Zobrazit 3D Model<i class="fas fa-chevron-down ms-2"></i>';
              }
            });

            // Dimensions Logic
            const viewer = document.getElementById('productModelViewer');
            const checkbox = document.getElementById('showDimensions');
            const rotateBtn = document.getElementById('rotateModelBtn');
            const hotspot = viewer.querySelector('button[slot="hotspot-dim"]');
            const label = document.getElementById('dim-label-combined');

            // Rotation Logic
            let currentRotationIndex = 0;
            // Cycle through Pitch (X-axis) rotations to fix Z-up models
            // model-viewer orientation = "roll pitch yaw" (Z X Y)
            const rotations = [
                '0deg 0deg 0deg',      // Default
                '0deg 90deg 0deg',     // Pitch 90 (X-axis) - Likely fix for Z-up
                '0deg 180deg 0deg',    // Pitch 180
                '0deg 270deg 0deg',    // Pitch 270 (-90)
                '90deg 0deg 0deg',     // Roll 90 (Z-axis)
                '0deg 0deg 90deg'      // Yaw 90 (Y-axis)
            ];
            
            rotateBtn.addEventListener('click', () => {
                currentRotationIndex = (currentRotationIndex + 1) % rotations.length;
                viewer.orientation = rotations[currentRotationIndex];
                // Update dimensions after rotation
                setTimeout(updateDimensions, 100);
            });

            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    updateDimensions();
                    hotspot.style.display = 'block';
                } else {
                    hotspot.style.display = 'none';
                }
            });

            function updateDimensions() {
                if (!viewer.loaded) return;
                
                const size = viewer.getDimensions();
                
                // Convert meters to cm
                let widthCm = size.x * 100;
                let heightCm = size.y * 100;
                let depthCm = size.z * 100;
                
                // Auto-correction for huge models
                let isAutoCorrected = false;
                if (widthCm > 1000 || heightCm > 1000 || depthCm > 1000) {
                    widthCm /= 1000;
                    heightCm /= 1000;
                    depthCm /= 1000;
                    isAutoCorrected = true;
                }

                // Update consolidated label
                // Format: Š: W cm | V: H cm | H: D cm
                label.textContent = `Š: ${widthCm.toFixed(1)} cm | V: ${heightCm.toFixed(1)} cm | H: ${depthCm.toFixed(1)} cm`;

                // Position hotspot at the bottom center of the model
                // We use the bounding box center for X/Z and bottom for Y
                // Note: getDimensions is AABB size. We assume model is centered at 0,0,0 locally.
                // If not, we might need getBoundingBoxCenter().
                // Let's try placing it slightly below the model.
                
                // Since we rotate the model, the "bottom" changes relative to the camera if we used world coords,
                // but hotspots move WITH the model. 
                // So we want to place it at the "bottom" in the MODEL'S coordinate system.
                // If the model is Z-up, "bottom" is min-Z. If Y-up, min-Y.
                // We'll try placing it at (0, -Y/2, 0) which is standard bottom for Y-up.
                hotspot.dataset.position = `0 ${-size.y/2} ${size.z/2}`;
            }

            viewer.addEventListener('load', () => {
                if (checkbox.checked) {
                    updateDimensions();
                }
            });
            </script>
            <?php endif; ?>
            
            <?php if (!empty($product['html_tech_specs'])): ?>
            <div class="content mt-4">
              <h2>Technické specifikace</h2>
              <?= $product['html_tech_specs'] ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-5">
          <div class="product-card">
        <h1 class="h3" style="color:var(--kjd-dark-brown)"><?= htmlspecialchars($product['name']) ?></h1>
        <?php
          // Sale logic is precomputed at the top of the file
          // Variables: $saleActive, $saleUpcoming, $salePrice, $base
          // No need to recalculate here (which was causing the overwrite bug)

        $discountPct = 0;
        $saleEndIso = '';
        if ($saleActive && $base > 0) {
          $discountPct = max(1, (int)round((1 - ($salePrice / max(0.01, $base))) * 100));
          if (!empty($product['sale_end'])) {
            $saleEndTs = strtotime($product['sale_end']);
            if ($saleEndTs) { $saleEndIso = date('c', $saleEndTs); }
          }
        }
        ?>
        <div class="price mb-2" id="priceDisplay" data-base-price="<?= $base ?>" data-sale-price="<?= $salePrice ?>" data-sale-active="<?= $saleActive ? '1' : '0' ?>">
          <?php if ($saleActive): ?>
            <span class="price-sale">
              <span class="old" id="priceOld"><?= number_format($base, 0, ',', ' ') ?> Kč</span>
              <span class="new" id="priceNew"><?= number_format($salePrice, 0, ',', ' ') ?> Kč</span>
              <span class="discount-chip" id="priceDiscount">-<?= $discountPct ?>%</span>
            </span>
            <?php if (!empty($saleEndIso)): ?>
              <span class="sale-countdown" id="saleCountdown" data-end="<?= $saleEndIso ?>">--:--:--</span>
            <?php endif; ?>
          <?php elseif ($saleUpcoming): ?>
             <span id="priceRegular"><?= number_format($base, 0, ',', ' ') ?> Kč</span>
             <div class="upcoming-sale-badge mt-2" style="background-color: var(--kjd-beige); border: 2px solid var(--kjd-gold-brown); border-radius: 8px; padding: 0.5rem 1rem; display: inline-block; max-width: 100%;">
                <div class="d-flex align-items-center mb-0">
                    <i class="fas fa-clock me-2" style="color: var(--kjd-dark-brown); font-size: 1rem;"></i>
                    <strong style="color: var(--kjd-dark-brown); font-size: 0.95rem; margin-right: 8px;">Akce začíná za:</strong>
                    <span class="sale-countdown fw-bold" id="upcomingSaleCountdown" data-end="<?= date('c', strtotime($saleStartDate)) ?>" style="font-size: 1.1rem; font-family: monospace; color: var(--kjd-dark-green); letter-spacing: 0.5px;">
                        --:--:--
                    </span>
                </div>
             </div>
          <?php else: ?>
            <span id="priceRegular"><?= number_format($base, 0, ',', ' ') ?> Kč</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($product['is_preorder']) && (int)$product['is_preorder']===1): ?>
          <span class="badge badge-preorder mb-3">Předobjednávka</span>
        <?php endif; ?>
        
        <?php if ($isAvailable && !empty($product['availability'])): ?>
          <div class="mb-3" style="color: var(--kjd-earth-green); font-weight: 600; font-size: 0.95rem;">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($product['availability']) ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($productCollections)): ?>
          <div class="mb-3" style="font-weight:700; color: var(--kjd-dark-brown); display:flex; gap:.5rem; flex-wrap:wrap;">
            <?php foreach ($productCollections as $c): 
              $cname = htmlspecialchars($c['name'] ?? '');
              $slug = $c['slug'] ?? '';
              $cid = $c['id'] ?? '';
              $href = 'index.php?collection=' . ($slug !== '' ? urlencode($slug) : urlencode((string)$cid));
            ?>
              <a href="<?= $href ?>" class="badge" style="text-decoration:none; background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); color: var(--kjd-dark-green); border: 2px solid var(--kjd-earth-green); border-radius: 999px; padding: .35rem .75rem;">
                <i class="fas fa-layer-group me-1"></i> Kolekce: <?= $cname ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        
        <div class="content mb-3">
          <?= $product['description'] ?? '' ?>
        </div>
        <?php
          // Variants (support both formats):
          // 1) { "Velikost": {"S": {"price":0}, "M": {"price":50}}, "Typ": ["DIY","Sestavený"] }
          // 2) [ {"name":"Velikost", "options":["S","M"]} ]
          $variants = [];
          if (!empty($product['variants'])) {
            $decoded = json_decode($product['variants'], true);
            if (is_array($decoded)) {
              if (array_keys($decoded) !== range(0, count($decoded)-1)) {
                foreach ($decoded as $k => $opts) {
                  // Normalize options -> list of [label, price]
                  $norm = [];
                  if (is_array($opts)) {
                    foreach ($opts as $label => $data) {
                      if (is_array($opts) && array_keys($opts) === range(0, count($opts)-1)) {
                        // Pure list e.g. ["DIY","Sestavený"]
                        $norm = array_map(function($o){ return ['label'=>$o,'price'=>0]; }, $opts);
                        break;
                      }
                      if (is_array($data)) { $norm[] = ['label'=>$label,'price'=>(float)($data['price'] ?? 0), 'stock'=>$data['stock'] ?? null, 'colors'=>($data['colors'] ?? null)]; }
                      else { $norm[] = ['label'=>$label,'price'=>0]; }
                    }
                  }
                  $variants[] = ['name'=>$k, 'options'=>$norm];
                }
              } else {
                foreach ($decoded as $row) {
                  if (isset($row['name']) && isset($row['options'])) {
                    $norm = [];
                    foreach ((array)$row['options'] as $op) {
                      if (is_array($op)) { $norm[] = ['label'=>$op['label'] ?? ($op['value'] ?? ''),'price'=>(float)($op['price'] ?? 0), 'stock'=>$op['stock'] ?? null, 'colors'=>($op['colors'] ?? null)]; }
                      else { $norm[] = ['label'=>$op,'price'=>0]; }
                    }
                    $variants[] = ['name'=>$row['name'], 'options'=>$norm];
                  }
                }
              }
            }
          }
          // Colors CSV and unavailable list
          $colors = [];
          if (!empty($product['colors'])) { $colors = array_filter(array_map('trim', explode(',', $product['colors']))); }
          $unavailable = [];
          if (!empty($product['unavailable_colors'])) { $unavailable = array_filter(array_map('trim', explode(',', $product['unavailable_colors']))); }
          
          // Combine all colors (both available and unavailable) and remove duplicates
          $allColors = array_unique(array_merge($colors, $unavailable));
        ?>
        <?php if (!empty($variants)): ?>
        <div class="variant-group mb-3">
          <?php foreach ($variants as $v): $vname = htmlspecialchars($v['name']); $opts = (array)$v['options']; ?>
          <div class="mb-2">
            <label><?= $vname ?></label>
            <div>
              <?php foreach ($opts as $op): $label = htmlspecialchars($op['label'] ?? (string)$op); $padj = (float)($op['price'] ?? 0); $variantId = strtolower(preg_replace('/[^a-z0-9]+/i','-', $vname.'-'.$label)); $disabled = isset($op['stock']) && $op['stock']!==null && $op['stock']<=0; $colorsAttr = '';
                if (isset($op['colors'])) { $colorsAttr = htmlspecialchars(implode(',', (array)$op['colors'])); }
              ?>
                <input class="variant-radio variant-select" type="radio" name="variant_<?= $vname ?>" id="<?= $variantId ?>" value="<?= $label ?>" data-variant="<?= $vname ?>" data-price="<?= $padj ?>" data-colors="<?= $colorsAttr ?>" <?= $disabled?'disabled':'' ?>>
                <label class="variant-chip" for="<?= $variantId ?>"><?= $label ?><?= $padj!=0?' ('.($padj>0?'+':'').number_format($padj,0,',',' ').' Kč)':'' ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($allColors)): ?>
        <div class="variant-group mb-3">
          <label>Barva</label>
          <div id="colorGroup">
            <?php
              require_once __DIR__ . '/getColorName.php';
              foreach ($allColors as $c):
                $isUn = in_array($c, $unavailable, true);
                $hex = getColorHexByName($c);
                $colorName = getColorName($c);
            ?>
              <?php if ($isUn): ?>
                <span class="color-swatch unavailable" 
                      title="<?= htmlspecialchars($colorName) ?> - Nedostupné" 
                      data-color="<?= htmlspecialchars($c) ?>" 
                      data-color-name="<?= htmlspecialchars($colorName) ?>"
                      style="background: <?= htmlspecialchars($hex) ?>;"
                      onclick="showUnavailableModal('<?= htmlspecialchars($c) ?>', '<?= htmlspecialchars($colorName) ?>')">
                </span>
              <?php else: ?>
                <span class="color-swatch" 
                      title="<?= htmlspecialchars($colorName) ?>" 
                      data-color="<?= htmlspecialchars($c) ?>" 
                      data-color-name="<?= htmlspecialchars($colorName) ?>"
                      style="background: <?= htmlspecialchars($hex) ?>;">
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        
        <?php 
        // Konfigurátor variant obrázků (Nožičky, Vršek)
        $hasComponentImages = !empty($componentImages) && (isset($componentImages['nozicky']) || isset($componentImages['vrsek']));
        $nozickyVariants = [];
        $vrsekVariants = [];
        
        if ($hasComponentImages) {
          $nozickyVariants = $componentImages['nozicky'] ?? [];
          $vrsekVariants = $componentImages['vrsek'] ?? [];
        }
        
        // Barevné komponenty (např. Nožičky + Vršek)
        $colorComponents = [];
        if (!empty($product['color_components'])) {
          $colorComponents = json_decode($product['color_components'], true) ?: [];
        }
        
        // Pokud existuje konfigurátor, zobrazíme přepínač, jinak zobrazíme jen původní sekci
        if ($hasComponentImages && (!empty($nozickyVariants) || !empty($vrsekVariants)) && !empty($colorComponents)):
          require_once __DIR__ . '/getColorName.php';
        ?>
        <div class="mb-4">
          <h5 class="mb-3" style="color: var(--kjd-dark-brown); font-weight: 700;">Výběr barev:</h5>
          
          <!-- Přepínač mezi způsoby výběru -->
          <div class="mb-3" style="display: flex; gap: 1rem; border-bottom: 2px solid var(--kjd-beige); padding-bottom: 1rem;">
            <button type="button" class="btn color-selection-mode-btn active" data-mode="swatches" style="flex: 1; padding: 0.75rem 1.5rem; background: var(--kjd-dark-green); color: #fff; border: 2px solid var(--kjd-dark-green); border-radius: 10px; font-weight: 600; transition: all 0.2s ease;">
              <i class="fas fa-palette me-2"></i>Výběr z barev
            </button>
            <button type="button" class="btn color-selection-mode-btn" data-mode="configurator" style="flex: 1; padding: 0.75rem 1.5rem; background: #fff; color: var(--kjd-dark-green); border: 2px solid var(--kjd-earth-green); border-radius: 10px; font-weight: 600; transition: all 0.2s ease;">
              <i class="fas fa-sliders-h me-2"></i>Výběr z konfigurátoru
            </button>
          </div>
          
          <!-- Sekce pro výběr z barev -->
          <div id="colorSwatchesSection" class="color-selection-section">
            <h5 class="mb-3" style="color: var(--kjd-dark-brown); font-weight: 700;">Výběr barev</h5>
            <?php foreach ($colorComponents as $compIdx => $component): ?>
            <div class="variant-group mb-3">
              <label>
                <?= htmlspecialchars($component['name']) ?>
                <?php if (!empty($component['required'])): ?>
                  <span style="color: red;">*</span>
                <?php endif; ?>
              </label>
              <div class="color-component-group" data-component="<?= htmlspecialchars($component['name']) ?>">
                <?php foreach ($component['colors'] as $colorName): 
                  $hex = getColorHexByName($colorName);
                  $displayName = getColorName($colorName);
                ?>
                  <span class="color-swatch component-color" 
                        title="<?= htmlspecialchars($displayName) ?>" 
                        data-component="<?= htmlspecialchars($component['name']) ?>"
                        data-color="<?= htmlspecialchars($colorName) ?>" 
                        data-color-name="<?= htmlspecialchars($displayName) ?>"
                        data-required="<?= !empty($component['required']) ? '1' : '0' ?>"
                        style="background: <?= htmlspecialchars($hex) ?>;">
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          
          <!-- Sekce pro výběr z konfigurátoru -->
          <div id="configuratorSection" class="color-selection-section" style="display: none;">
            <!-- Finální náhled lampy -->
            <div class="final-lamp-preview mb-4" style="background: linear-gradient(135deg, #f8f9fa, #fff); border-radius: 20px; padding: 3rem 2rem; border: 2px solid var(--kjd-beige); box-shadow: 0 4px 20px rgba(16,40,32,0.08);">
              <!-- Náhled lampy -->
              <div class="final-lamp-container mb-4" style="position: relative; width: 100%; max-width: 100%; height: 500px; display: flex; align-items: center; justify-content: center; margin-bottom: 2rem;">
                <?php 
                // Zobrazíme první kombinaci jako výchozí
                $firstNozicky = $nozickyVariants[0] ?? null;
                $firstVrsek = $vrsekVariants[0] ?? null;
                
                $nozickyImg = '';
                $vrsekImg = '';
                
                if ($firstNozicky && !empty($firstNozicky['colors'][0]['image'])) {
                  $nozickyImg = $firstNozicky['colors'][0]['image'];
                } elseif ($firstNozicky && !empty($firstNozicky['image'])) {
                  $nozickyImg = $firstNozicky['image'];
                }
                
                if ($firstVrsek && !empty($firstVrsek['colors'][0]['image'])) {
                  $vrsekImg = $firstVrsek['colors'][0]['image'];
                } elseif ($firstVrsek && !empty($firstVrsek['image'])) {
                  $vrsekImg = $firstVrsek['image'];
                }
                
                // Normalize image paths
                function normalizeImgPathFinal($path) {
                  if (preg_match('~^https?://~', $path)) return $path;
                  if (strpos($path, 'uploads/') === 0 || strpos($path, '/uploads/') === 0) return ltrim($path, '/');
                  if (strpos($path, 'admin/uploads/') === 0) return $path;
                  return $path;
                }
                
                $nozickyDisplayUrl = $nozickyImg ? normalizeImgPathFinal($nozickyImg) : '';
                $vrsekDisplayUrl = $vrsekImg ? normalizeImgPathFinal($vrsekImg) : '';
                ?>
                <!-- Kombinovaný náhled - vršek nahoře, nožičky dole, navazují na sebe -->
                <div style="position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                  <?php if ($vrsekDisplayUrl): ?>
                  <img id="finalLampPreviewTop" class="lamp-preview-layer" data-component="vrsek" src="<?= htmlspecialchars($vrsekDisplayUrl) ?>" alt="Vršek" style="position: absolute; top: 0%; left: 50%; transform: translateX(-50%); width: auto; height: 75%; max-width: none; max-height: none; object-fit: contain; z-index: 2;" referrerpolicy="no-referrer">
                  <?php endif; ?>
                  <?php if ($nozickyDisplayUrl): ?>
                  <img id="finalLampPreviewBottom" class="lamp-preview-layer" data-component="nozicky" src="<?= htmlspecialchars($nozickyDisplayUrl) ?>" alt="Nožičky" style="position: absolute; top: 27%; left: 50%; transform: translateX(-50%) translateY(-35%); width: auto; height: 70%; max-width: none; max-height: none; object-fit: contain; z-index: 1;" referrerpolicy="no-referrer">
                  <?php endif; ?>
                </div>
              </div>
              
              <!-- Tlačítko a informace pod náhledem -->
              <div class="text-center">
                <!-- Zobrazení aktuálně vybraných barev -->
                <div class="selected-colors-display mb-4 p-4" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border-radius: 16px; border: 2px solid var(--kjd-earth-green); display: inline-block; min-width: 350px; box-shadow: 0 2px 10px rgba(16,40,32,0.1);">
                  <div class="row text-center g-4">
                    <?php if (!empty($vrsekVariants)): ?>
                    <div class="col-12 col-md-6">
                      <div style="font-size: 0.95rem; color: var(--kjd-dark-green); margin-bottom: 8px; font-weight: 600;">Horní část:</div>
                      <div class="selected-color-name" data-component="vrsek" style="color: var(--kjd-dark-brown); font-weight: 700; font-size: 1.2rem; padding: 0.5rem 1rem; background: #fff; border-radius: 8px; display: inline-block; border: 1px solid var(--kjd-earth-green);">
                        <?= !empty($firstVrsek['colors'][0]) ? htmlspecialchars($firstVrsek['colors'][0]['color'] ?? '') : htmlspecialchars($firstVrsek['name'] ?? '') ?>
                      </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($nozickyVariants)): ?>
                    <div class="col-12 col-md-6">
                      <div style="font-size: 0.95rem; color: var(--kjd-dark-green); margin-bottom: 8px; font-weight: 600;">Spodní část:</div>
                      <div class="selected-color-name" data-component="nozicky" style="color: var(--kjd-dark-brown); font-weight: 700; font-size: 1.2rem; padding: 0.5rem 1rem; background: #fff; border-radius: 8px; display: inline-block; border: 1px solid var(--kjd-earth-green);">
                        <?= !empty($firstNozicky['colors'][0]) ? htmlspecialchars($firstNozicky['colors'][0]['color'] ?? '') : htmlspecialchars($firstNozicky['name'] ?? '') ?>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                
                <!-- Tlačítko -->
                <button type="button" class="btn btn-kjd-primary" id="openConfiguratorBtn" style="padding: 1.25rem 3rem; font-size: 1.2rem; font-weight: 700; border-radius: 14px; box-shadow: 0 6px 20px rgba(138,98,64,0.3); transition: all 0.3s ease; background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); border: none; text-transform: uppercase; letter-spacing: 0.5px; color: #fff;">
                  <i class="fas fa-sliders-h me-2"></i>Otevřít konfigurátor
                </button>
              </div>
            </div>
            
            <!-- Hidden inputs pro hlavní formulář -->
            <?php if (!empty($nozickyVariants)): ?>
            <input type="hidden" id="selected_nozicky" name="selected_nozicky" value="0">
            <input type="hidden" id="selected_nozicky_name" name="selected_nozicky_name" value="<?= htmlspecialchars($firstNozicky['name'] ?? '') ?>">
            <input type="hidden" id="selected_nozicky_color" name="selected_nozicky_color" value="<?= !empty($firstNozicky['colors'][0]) ? htmlspecialchars($firstNozicky['colors'][0]['color'] ?? '') : '' ?>">
            <input type="hidden" id="selected_nozicky_color_image" name="selected_nozicky_color_image" value="<?= !empty($firstNozicky['colors'][0]) ? htmlspecialchars($firstNozicky['colors'][0]['image'] ?? '') : '' ?>">
            <?php endif; ?>
            <?php if (!empty($vrsekVariants)): ?>
            <input type="hidden" id="selected_vrsek" name="selected_vrsek" value="0">
            <input type="hidden" id="selected_vrsek_name" name="selected_vrsek_name" value="<?= htmlspecialchars($firstVrsek['name'] ?? '') ?>">
            <input type="hidden" id="selected_vrsek_color" name="selected_vrsek_color" value="<?= !empty($firstVrsek['colors'][0]) ? htmlspecialchars($firstVrsek['colors'][0]['color'] ?? '') : '' ?>">
            <input type="hidden" id="selected_vrsek_color_image" name="selected_vrsek_color_image" value="<?= !empty($firstVrsek['colors'][0]) ? htmlspecialchars($firstVrsek['colors'][0]['image'] ?? '') : '' ?>">
            <?php endif; ?>
          </div>
        </div>
        <?php elseif (!empty($colorComponents)): 
          // Pokud není konfigurátor, zobrazíme jen původní sekci
          require_once __DIR__ . '/getColorName.php';
        ?>
        <div class="mb-4">
          <h5 class="mb-3" style="color: var(--kjd-dark-brown); font-weight: 700;">Výběr barev</h5>
          <?php foreach ($colorComponents as $compIdx => $component): ?>
          <div class="variant-group mb-3">
            <label>
              <?= htmlspecialchars($component['name']) ?>
              <?php if (!empty($component['required'])): ?>
                <span style="color: red;">*</span>
              <?php endif; ?>
            </label>
            <div class="color-component-group" data-component="<?= htmlspecialchars($component['name']) ?>">
              <?php foreach ($component['colors'] as $colorName): 
                $hex = getColorHexByName($colorName);
                $displayName = getColorName($colorName);
              ?>
                <span class="color-swatch component-color" 
                      title="<?= htmlspecialchars($displayName) ?>" 
                      data-component="<?= htmlspecialchars($component['name']) ?>"
                      data-color="<?= htmlspecialchars($colorName) ?>" 
                      data-color-name="<?= htmlspecialchars($displayName) ?>"
                      data-required="<?= !empty($component['required']) ? '1' : '0' ?>"
                      style="background: <?= htmlspecialchars($hex) ?>;">
                </span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php 
        // Konfigurátor v modalu (zobrazí se jen pokud existuje konfigurátor)
        if ($hasComponentImages && (!empty($nozickyVariants) || !empty($vrsekVariants))):
        ?>
          <!-- Konfigurátor v modalu -->
          <div class="modal fade" id="configuratorModal" tabindex="-1" aria-labelledby="configuratorModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
              <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 40px rgba(16,40,32,0.2);">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border-bottom: 3px solid var(--kjd-earth-green); border-radius: 16px 16px 0 0; padding: 1.5rem 2rem;">
                  <h3 class="modal-title" id="configuratorModalLabel" style="color: var(--kjd-dark-green); font-weight: 700; font-size: 1.8rem;">
                    Konfigurátor Produktu
                  </h3>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 1.5rem;"></button>
                </div>
                <div class="modal-body p-4">
                  <!-- Zobrazení aktuálně vybraných barev -->
                  <div class="selected-colors-display mb-4 p-3" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border-radius: 12px; border: 2px solid var(--kjd-earth-green);">
                    <div class="row text-center">
                      <?php if (!empty($vrsekVariants)): ?>
                      <div class="col-md-6 mb-2 mb-md-0">
                        <strong style="color: var(--kjd-dark-green);">Horní část:</strong>
                        <span class="selected-color-name-modal" data-component="vrsek" style="color: var(--kjd-dark-brown); font-weight: 600; margin-left: 8px;">
                          <?= !empty($firstVrsek['colors'][0]) ? htmlspecialchars($firstVrsek['colors'][0]['color'] ?? '') : htmlspecialchars($firstVrsek['name'] ?? '') ?>
                        </span>
                      </div>
                      <?php endif; ?>
                      <?php if (!empty($nozickyVariants)): ?>
                      <div class="col-md-6">
                        <strong style="color: var(--kjd-dark-green);">Spodní část:</strong>
                        <span class="selected-color-name-modal" data-component="nozicky" style="color: var(--kjd-dark-brown); font-weight: 600; margin-left: 8px;">
                          <?= !empty($firstNozicky['colors'][0]) ? htmlspecialchars($firstNozicky['colors'][0]['color'] ?? '') : htmlspecialchars($firstNozicky['name'] ?? '') ?>
                        </span>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                  <!-- Kompletní náhled lampy -->
                  <div class="full-lamp-preview mb-4" style="background: linear-gradient(135deg, #f8f9fa, #fff); border-radius: 16px; padding: 0.5rem 1rem 0.5rem 1rem; border: 2px solid var(--kjd-beige); min-height: 700px; display: flex; align-items: flex-start; justify-content: center; position: relative;">
                    <div class="lamp-preview-container" style="position: relative; width: 100%; max-width: 100%; height: 680px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start;">
                      <?php 
                      // Zobrazíme první kombinaci jako výchozí
                      $firstNozicky = $nozickyVariants[0] ?? null;
                      $firstVrsek = $vrsekVariants[0] ?? null;
                      
                      $nozickyImg = '';
                      $vrsekImg = '';
                      
                      if ($firstNozicky && !empty($firstNozicky['colors'][0]['image'])) {
                        $nozickyImg = $firstNozicky['colors'][0]['image'];
                      } elseif ($firstNozicky && !empty($firstNozicky['image'])) {
                        $nozickyImg = $firstNozicky['image'];
                      }
                      
                      if ($firstVrsek && !empty($firstVrsek['colors'][0]['image'])) {
                        $vrsekImg = $firstVrsek['colors'][0]['image'];
                      } elseif ($firstVrsek && !empty($firstVrsek['image'])) {
                        $vrsekImg = $firstVrsek['image'];
                      }
                      
                      // Normalize image paths
                      function normalizeImgPath($path) {
                        if (preg_match('~^https?://~', $path)) return $path;
                        if (strpos($path, 'uploads/') === 0 || strpos($path, '/uploads/') === 0) return ltrim($path, '/');
                        if (strpos($path, 'admin/uploads/') === 0) return $path;
                        return $path;
                      }
                      
                      $nozickyDisplayUrl = $nozickyImg ? normalizeImgPath($nozickyImg) : '';
                      $vrsekDisplayUrl = $vrsekImg ? normalizeImgPath($vrsekImg) : '';
                      ?>
                      <!-- Kombinovaný náhled - vršek nahoře, nožičky dole, navazují na sebe -->
                      <div style="position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <?php if ($vrsekDisplayUrl): ?>
                        <img id="fullLampPreviewTop" class="lamp-preview-layer" data-component="vrsek" src="<?= htmlspecialchars($vrsekDisplayUrl) ?>" alt="Vršek" style="position: absolute; top: 0%; left: 50%; transform: translateX(-50%); width: auto; height: 75%; max-width: none; max-height: none; object-fit: contain; z-index: 2;">
                        <?php endif; ?>
                        <?php if ($nozickyDisplayUrl): ?>
                        <img id="fullLampPreviewBottom" class="lamp-preview-layer" data-component="nozicky" src="<?= htmlspecialchars($nozickyDisplayUrl) ?>" alt="Nožičky" style="position: absolute; top: 27%; left: 50%; transform: translateX(-50%) translateY(-35%); width: auto; height: 70%; max-width: none; max-height: none; object-fit: contain; z-index: 1;">
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row g-4">
            <?php if (!empty($nozickyVariants)): ?>
            <div class="col-md-6">
              <div class="component-configurator" data-component="vrsek">
                <h4 class="mb-3" style="font-weight: 700; color: var(--kjd-dark-green); font-size: 1.3rem;">
                  Horní část:
                </h4>
                
                <!-- Obrázek s šipkami -->
                <div class="configurator-image-wrapper position-relative mb-3" style="background: #fff; border-radius: 12px; padding: 1.5rem; border: 2px solid var(--kjd-beige); min-height: 300px; display: flex; align-items: center; justify-content: center;">
                  <?php if (count($vrsekVariants) > 1): ?>
                  <button type="button" class="configurator-nav configurator-prev" data-component="vrsek" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: #fff; border: 2px solid var(--kjd-earth-green); border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; z-index: 2; cursor: pointer; transition: all 0.2s ease; font-size: 1.2rem; color: var(--kjd-dark-green);">
                    ‹
                  </button>
                  <?php endif; ?>
                  <div class="configurator-image-container" style="text-align: center; width: 100%;">
                    <?php 
                    $firstVrsek = $vrsekVariants[0] ?? null;
                    if ($firstVrsek):
                      $imgPath = trim($firstVrsek['image'] ?? '');
                      if (preg_match('~^https?://~', $imgPath)) {
                        $displayUrl = $imgPath;
                      } elseif (strpos($imgPath, 'uploads/') === 0 || strpos($imgPath, '/uploads/') === 0) {
                        $displayUrl = ltrim($imgPath, '/');
                      } elseif (strpos($imgPath, 'admin/uploads/') === 0) {
                        $displayUrl = $imgPath;
                      } else {
                        $displayUrl = $imgPath;
                      }
                    ?>
                    <img src="<?= htmlspecialchars($displayUrl) ?>" alt="Horní část" class="configurator-image" data-component="vrsek" style="max-width: 100%; max-height: 280px; object-fit: contain; border-radius: 8px;" referrerpolicy="no-referrer">
                    <?php else: ?>
                    <div style="color: var(--kjd-dark-green); padding: 2rem;">Žádné varianty nejsou k dispozici</div>
                    <?php endif; ?>
                  </div>
                  <?php if (count($vrsekVariants) > 1): ?>
                  <button type="button" class="configurator-nav configurator-next" data-component="vrsek" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #fff; border: 2px solid var(--kjd-earth-green); border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; z-index: 2; cursor: pointer; transition: all 0.2s ease; font-size: 1.2rem; color: var(--kjd-dark-green);">
                    ›
                  </button>
                  <?php endif; ?>
                </div>
                
                <!-- Barevné varianty -->
                <?php if (!empty($firstVrsek['colors'])): ?>
                <div class="configurator-colors" data-component="vrsek">
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($firstVrsek['colors'] as $colorIdx => $colorVariant): ?>
                      <?php
                      $colorImgPath = trim($colorVariant['image'] ?? '');
                      if (preg_match('~^https?://~', $colorImgPath)) {
                        $colorDisplayUrl = $colorImgPath;
                      } elseif (strpos($colorImgPath, 'uploads/') === 0 || strpos($colorImgPath, '/uploads/') === 0) {
                        $colorDisplayUrl = ltrim($colorImgPath, '/');
                      } elseif (strpos($colorImgPath, 'admin/uploads/') === 0) {
                        $colorDisplayUrl = $colorImgPath;
                      } else {
                        $colorDisplayUrl = $colorImgPath;
                      }
                      ?>
                      <button type="button" class="configurator-color-btn <?= $colorIdx === 0 ? 'active' : '' ?>" 
                              data-component="vrsek" 
                              data-color-index="<?= $colorIdx ?>"
                              data-color-name="<?= htmlspecialchars($colorVariant['color'] ?? '') ?>"
                              data-color-image="<?= htmlspecialchars($colorDisplayUrl) ?>"
                              style="border: 2px solid var(--kjd-earth-green); border-radius: 10px; padding: 0.75rem; background: #fff; transition: all 0.2s ease; min-width: 90px; text-align: center;">
                        <img src="<?= htmlspecialchars($colorDisplayUrl) ?>" alt="<?= htmlspecialchars($colorVariant['color'] ?? '') ?>" 
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; display: block; margin: 0 auto 8px; border: 1px solid rgba(0,0,0,0.1);">
                        <div style="font-weight: 600; color: var(--kjd-dark-green); font-size: 0.9rem;"><?= htmlspecialchars($colorVariant['color'] ?? '') ?></div>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
                
                <input type="hidden" name="selected_nozicky_modal" id="selected_nozicky_modal" value="0">
                <input type="hidden" name="selected_nozicky_name_modal" id="selected_nozicky_name_modal" value="<?= htmlspecialchars($firstNozicky['name'] ?? '') ?>">
                <input type="hidden" name="selected_nozicky_color_modal" id="selected_nozicky_color_modal" value="<?= !empty($firstNozicky['colors'][0]) ? htmlspecialchars($firstNozicky['colors'][0]['color'] ?? '') : '' ?>">
                <input type="hidden" name="selected_nozicky_color_image_modal" id="selected_nozicky_color_image_modal" value="<?= !empty($firstNozicky['colors'][0]) ? htmlspecialchars($firstNozicky['colors'][0]['image'] ?? '') : '' ?>">
              </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($nozickyVariants)): ?>
            <div class="col-md-6">
              <div class="component-configurator" data-component="nozicky">
                <h4 class="mb-3" style="font-weight: 700; color: var(--kjd-dark-green); font-size: 1.3rem;">
                  Spodní část:
                </h4>
                
                <!-- Obrázek s šipkami -->
                <div class="configurator-image-wrapper position-relative mb-3" style="background: #fff; border-radius: 12px; padding: 1.5rem; border: 2px solid var(--kjd-beige); min-height: 300px; display: flex; align-items: center; justify-content: center;">
                  <?php if (count($nozickyVariants) > 1): ?>
                  <button type="button" class="configurator-nav configurator-prev" data-component="nozicky" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); background: #fff; border: 2px solid var(--kjd-earth-green); border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; z-index: 2; cursor: pointer; transition: all 0.2s ease; font-size: 1.2rem; color: var(--kjd-dark-green);">
                    ‹
                  </button>
                  <?php endif; ?>
                  <div class="configurator-image-container" style="text-align: center; width: 100%;">
                    <?php 
                    $firstNozicky = $nozickyVariants[0] ?? null;
                    if ($firstNozicky):
                      $imgPath = trim($firstNozicky['image'] ?? '');
                      if (preg_match('~^https?://~', $imgPath)) {
                        $displayUrl = $imgPath;
                      } elseif (strpos($imgPath, 'uploads/') === 0 || strpos($imgPath, '/uploads/') === 0) {
                        $displayUrl = ltrim($imgPath, '/');
                      } elseif (strpos($imgPath, 'admin/uploads/') === 0) {
                        $displayUrl = $imgPath;
                      } else {
                        $displayUrl = $imgPath;
                      }
                    ?>
                    <img src="<?= htmlspecialchars($displayUrl) ?>" alt="Spodní část" class="configurator-image" data-component="nozicky" style="max-width: 100%; max-height: 280px; object-fit: contain; border-radius: 8px;" referrerpolicy="no-referrer">
                    <?php else: ?>
                    <div style="color: var(--kjd-dark-green); padding: 2rem;">Žádné varianty nejsou k dispozici</div>
                    <?php endif; ?>
                  </div>
                  <?php if (count($nozickyVariants) > 1): ?>
                  <button type="button" class="configurator-nav configurator-next" data-component="nozicky" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #fff; border: 2px solid var(--kjd-earth-green); border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; z-index: 2; cursor: pointer; transition: all 0.2s ease; font-size: 1.2rem; color: var(--kjd-dark-green);">
                    ›
                  </button>
                  <?php endif; ?>
                </div>
                
                <!-- Barevné varianty -->
                <?php if (!empty($firstNozicky['colors'])): ?>
                <div class="configurator-colors" data-component="nozicky">
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($firstNozicky['colors'] as $colorIdx => $colorVariant): ?>
                      <?php
                      $colorImgPath = trim($colorVariant['image'] ?? '');
                      if (preg_match('~^https?://~', $colorImgPath)) {
                        $colorDisplayUrl = $colorImgPath;
                      } elseif (strpos($colorImgPath, 'uploads/') === 0 || strpos($colorImgPath, '/uploads/') === 0) {
                        $colorDisplayUrl = ltrim($colorImgPath, '/');
                      } elseif (strpos($colorImgPath, 'admin/uploads/') === 0) {
                        $colorDisplayUrl = $colorImgPath;
                      } else {
                        $colorDisplayUrl = $colorImgPath;
                      }
                      ?>
                      <button type="button" class="configurator-color-btn <?= $colorIdx === 0 ? 'active' : '' ?>" 
                              data-component="nozicky" 
                              data-color-index="<?= $colorIdx ?>"
                              data-color-name="<?= htmlspecialchars($colorVariant['color'] ?? '') ?>"
                              data-color-image="<?= htmlspecialchars($colorDisplayUrl) ?>"
                              style="border: 2px solid var(--kjd-earth-green); border-radius: 10px; padding: 0.75rem; background: #fff; transition: all 0.2s ease; min-width: 90px; text-align: center;">
                        <img src="<?= htmlspecialchars($colorDisplayUrl) ?>" alt="<?= htmlspecialchars($colorVariant['color'] ?? '') ?>" 
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; display: block; margin: 0 auto 8px; border: 1px solid rgba(0,0,0,0.1);">
                        <div style="font-weight: 600; color: var(--kjd-dark-green); font-size: 0.9rem;"><?= htmlspecialchars($colorVariant['color'] ?? '') ?></div>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
                
                <input type="hidden" name="selected_vrsek_modal" id="selected_vrsek_modal" value="0">
                <input type="hidden" name="selected_vrsek_name_modal" id="selected_vrsek_name_modal" value="<?= htmlspecialchars($firstVrsek['name'] ?? '') ?>">
                <input type="hidden" name="selected_vrsek_color_modal" id="selected_vrsek_color_modal" value="<?= !empty($firstVrsek['colors'][0]) ? htmlspecialchars($firstVrsek['colors'][0]['color'] ?? '') : '' ?>">
                <input type="hidden" name="selected_vrsek_color_image_modal" id="selected_vrsek_color_image_modal" value="<?= !empty($firstVrsek['colors'][0]) ? htmlspecialchars($firstVrsek['colors'][0]['image'] ?? '') : '' ?>">
              </div>
            </div>
            <?php endif; ?>
                  </div>
                  
                  <!-- Tlačítko pro uložení výběru -->
                  <div class="modal-footer" style="border-top: 2px solid var(--kjd-beige); padding: 1.5rem 2rem; background: #f8f9fa; border-radius: 0 0 16px 16px; box-shadow: 0 -4px 15px rgba(16,40,32,0.1);">
                    <button type="button" class="btn btn-kjd-secondary" data-bs-dismiss="modal" style="padding: 0.75rem 1.5rem; border: 2px solid var(--kjd-earth-green); background: #fff; color: var(--kjd-dark-green); font-weight: 600; border-radius: 10px; transition: all 0.2s ease;">
                      <i class="fas fa-times me-2"></i>Zrušit
                    </button>
                    <button type="button" class="btn btn-kjd-primary" id="saveConfiguratorSelection" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green)); border: none; color: #fff; font-weight: 600; border-radius: 10px; box-shadow: 0 4px 15px rgba(16,40,32,0.3); transition: all 0.2s ease;">
                      <i class="fas fa-check me-2"></i>Použít výběr
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <script>
          // Store component variants data
          window.componentVariants = {
            nozicky: <?= json_encode($nozickyVariants, JSON_UNESCAPED_UNICODE) ?>,
            vrsek: <?= json_encode($vrsekVariants, JSON_UNESCAPED_UNICODE) ?>
          };
          console.log('Loaded componentVariants:', window.componentVariants);
          console.log('nozicky variants:', window.componentVariants.nozicky);
          console.log('vrsek variants:', window.componentVariants.vrsek);
          if (window.componentVariants.nozicky && window.componentVariants.nozicky[0]) {
            console.log('First nozicky variant:', window.componentVariants.nozicky[0]);
            console.log('First nozicky variant colors:', window.componentVariants.nozicky[0].colors);
          }
          if (window.componentVariants.vrsek && window.componentVariants.vrsek[0]) {
            console.log('First vrsek variant:', window.componentVariants.vrsek[0]);
            console.log('First vrsek variant colors:', window.componentVariants.vrsek[0].colors);
          }
        </script>
        <?php endif; ?>




        
        <?php if ($isAvailable): ?>
        <div class="text-center mb-3">
          <div class="qty-controls" style="display: inline-flex; align-items: center; justify-content: center;">
            <button class="qty-btn" type="button" id="qtyMinus">-</button>
            <input type="text" class="qty-input" id="qty" value="1">
            <button class="qty-btn" type="button" id="qtyPlus">+</button>
          </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$isAvailable): ?>
        <div class="alert alert-warning mb-3" style="background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,193,7,0.05)); border: 2px solid #ffc107; border-radius: 12px; padding: 1rem; color: #856404; font-weight: 600;">
          <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($availabilityMessage) ?>
        </div>
        <div class="text-center">
          <button class="btn btn-kjd" id="addBtn" disabled style="opacity: 0.5; cursor: not-allowed; padding: 1.25rem 3rem; font-size: 1.2rem; font-weight: 700; border-radius: 14px; min-width: 280px;">
            <i class="fas fa-ban me-2"></i>Nedostupné
          </button>
        </div>
        <?php else: ?>
        <div class="text-center">
          <button class="btn btn-kjd" id="addBtn" style="padding: 1.25rem 3rem; font-size: 1.2rem; font-weight: 700; border-radius: 14px; box-shadow: 0 6px 20px rgba(138,98,64,0.3); transition: all 0.3s ease; background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); border: none; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; min-width: 280px;">
            <i class="fas fa-shopping-cart me-2"></i>Přidat do košíku
          </button>
        </div>
        <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile sticky add-to-cart bar -->
    <?php if ($isAvailable): ?>
    <div class="mobile-addbar d-sm-none d-block">
      <button class="btn btn-kjd" id="addBtnMobile"><i class="fas fa-shopping-cart me-2"></i>Přidat do košíku</button>
    </div>
    <?php else: ?>
    <div class="mobile-addbar d-sm-none d-block" style="background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,193,7,0.05)); border-top: 2px solid #ffc107;">
      <div style="padding: 0.75rem 1rem; text-align: center; color: #856404; font-weight: 600;">
        <i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($availabilityMessage) ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Related Products Section -->
    <section class="py-5" style="background: #fff; border-top: 1px solid var(--kjd-beige); margin-top: 3rem;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div class="section-header d-flex justify-content-between align-items-center mb-4">
              <h2 class="section-title" style="color: var(--kjd-dark-green); font-weight: 700; font-size: 1.8rem;">Mohlo by se vám líbit</h2>
              <a href="index.php" class="btn btn-outline-dark rounded-pill px-4">Zobrazit vše</a>
            </div>
            
            <div class="product-grid row row-cols-2 row-cols-sm-3 row-cols-md-4 g-4">
              <?php foreach ($relatedProducts as $rp): ?>
                <?php
                  $rpImg = imgSrcFromProduct($rp);
                  $rpName = htmlspecialchars($rp['name'] ?? 'Product');
                  $rpPrice = isset($rp['price']) ? (float)$rp['price'] : 0;
                  $rpIsPreorder = !empty($rp['is_preorder']) && (int)$rp['is_preorder'] === 1;
                  $rpIsNew = !empty($rp['is_new']) && (int)$rp['is_new'] === 1;
                  $rpSaleActive = false; $rpSalePrice = 0;
                  if (!empty($rp['sale_enabled']) && (int)$rp['sale_enabled'] === 1) {
                    $sp = isset($rp['sale_price']) ? (float)$rp['sale_price'] : 0;
                    if ($sp > 0 && $sp < $rpPrice) {
                      if (!empty($rp['sale_end'])) {
                        $endTs = strtotime($rp['sale_end']);
                        if ($endTs && time() < $endTs) {
                          $rpSaleActive = true; $rpSalePrice = $sp;
                        }
                      } else { 
                        $rpSaleActive = true; $rpSalePrice = $sp; 
                      }
                    }
                  }
                ?>
                <div class="col">
                  <div class="product-item position-relative h-100" style="background: #fff; border: 1px solid var(--kjd-beige); border-radius: 16px; padding: 1rem; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(16,40,32,0.05);">
                    <?php if ($rpSaleActive): ?>
                      <span class="badge position-absolute m-2" style="top:0; left:0; background: linear-gradient(135deg, #c62828, #ff7043); color: #fff; z-index: 5; border-radius: 8px;">-<?= max(1, (int)round((1 - ($rpSalePrice / max(0.01, $rpPrice))) * 100)) ?>%</span>
                    <?php elseif ($rpIsPreorder): ?>
                      <span class="badge bg-warning position-absolute m-2" style="top:0; left:0; z-index: 5; border-radius: 8px;">Preorder</span>
                    <?php elseif ($rpIsNew): ?>
                      <span class="badge bg-primary position-absolute m-2" style="top:0; left:0; z-index: 5; border-radius: 8px;">New</span>
                    <?php endif; ?>
                    
                    <a href="product.php?id=<?= (int)$rp['id'] ?>" class="text-decoration-none d-block h-100 d-flex flex-column">
                      <figure style="margin:0; overflow:hidden; border-radius:12px; position: relative; padding-top: 100%;">
                        <img src="<?= htmlspecialchars($rpImg) ?>" class="img-fluid position-absolute top-0 start-0 w-100 h-100" alt="<?= $rpName ?>" style="object-fit:cover; transition: transform 0.3s ease;">
                      </figure>
                      <h3 style="font-size: 1.1rem; margin: 1rem 0 0.5rem; color: var(--kjd-dark-green); font-weight: 700; line-height: 1.3;"><?= $rpName ?></h3>
                      <div class="mt-auto">
                        <div class="price mb-3" style="font-size: 1.2rem; font-weight: 800; color: var(--kjd-gold-brown);">
                          <?php if ($rpSaleActive): ?>
                            <span style="text-decoration:line-through;color:#888;margin-right:6px;font-size:0.9rem;font-weight:500;"><?= number_format($rpPrice, 0, ',', ' ') ?> Kč</span>
                            <span style="color:#c62828;"><?= number_format($rpSalePrice, 0, ',', ' ') ?> Kč</span>
                          <?php else: ?>
                            <?= number_format($rpPrice, 0, ',', ' ') ?> Kč
                          <?php endif; ?>
                        </div>
                        <div class="btn btn-sm btn-outline-dark w-100" style="border-radius: 8px; font-weight: 600; border-color: var(--kjd-earth-green); color: var(--kjd-dark-green);">Zobrazit</div>
                      </div>
                    </a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Image Lightbox -->
    <div id="lightbox" class="lightbox">
      <span class="lightbox-close">&times;</span>
      <img class="lightbox-content" id="lightboxImg">
    </div>

    <!-- Unavailable Color Modal -->
    <div class="modal fade" id="unavailableModal" tabindex="-1" aria-labelledby="unavailableModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header" style="background: var(--kjd-beige); border-bottom: 2px solid var(--kjd-earth-green);">
            <h5 class="modal-title" id="unavailableModalLabel" style="color: var(--kjd-dark-green);">
              <i class="fas fa-bell me-2"></i>Upozornění na dostupnost
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="text-center mb-3">
              <div class="color-preview mb-3" id="modalColorPreview" style="width: 60px; height: 60px; border-radius: 50%; margin: 0 auto; border: 3px solid var(--kjd-beige);"></div>
              <h6 id="modalColorName" style="color: var(--kjd-dark-brown);"></h6>
              <p class="text-muted">Tato barva je momentálně nedostupná. Chcete být upozorněni, až bude znovu k dispozici?</p>
            </div>
            <form id="notificationForm">
              <div class="mb-3">
                <label for="notificationEmail" class="form-label" style="color: var(--kjd-dark-green); font-weight: 600;">Váš email</label>
                <input type="email" class="form-control" id="notificationEmail" required 
                       style="border-color: var(--kjd-beige);" 
                       placeholder="vas@email.cz">
              </div>
              <div class="mb-3">
                <label for="notificationName" class="form-label" style="color: var(--kjd-dark-green); font-weight: 600;">Vaše jméno (volitelné)</label>
                <input type="text" class="form-control" id="notificationName" 
                       style="border-color: var(--kjd-beige);" 
                       placeholder="Vaše jméno">
              </div>
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-kjd">
                  <i class="fas fa-bell me-2"></i>Upozornit mě
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Sale countdown (if present)
      (function(){
        const el = document.getElementById('saleCountdown');
        if (!el) return;
        const end = new Date(el.dataset.end).getTime();
        function tick(){
          const now = Date.now();
          const diff = Math.max(0, end - now);
          const d = Math.floor(diff/86400000);
          const h = Math.floor((diff%86400000)/3600000);
          const m = Math.floor((diff%3600000)/60000);
          const s = Math.floor((diff%60000)/1000);
          el.textContent = (d>0?d+'d ':'') + String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
          if (diff>0) requestAnimationFrame(()=>setTimeout(tick,250));
        }
        tick();
      })();
      // Mobile add bar button forwards to main add button
      document.getElementById('addBtnMobile')?.addEventListener('click', function(){
        document.getElementById('addBtn')?.click();
      });
      // Lightbox functionality
      const lightbox = document.getElementById('lightbox');
      const lightboxImg = document.getElementById('lightboxImg');
      const lightboxClose = document.querySelector('.lightbox-close');
      
      // Open lightbox when clicking on main image or thumbnails
      document.getElementById('mainImg')?.addEventListener('click', function() {
        lightboxImg.src = this.src;
        lightbox.style.display = 'block';
        document.body.style.overflow = 'hidden';
      });
      
      document.querySelectorAll('.thumbs img')?.forEach(function(el){
        el.addEventListener('click',function(){
          document.getElementById('mainImg').src=this.dataset.src;
          document.querySelectorAll('.thumbs img').forEach(t=>t.classList.remove('active'));
          this.classList.add('active');
        })
      })
      
      // Close lightbox
      function closeLightbox() {
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto';
      }
      
      lightboxClose?.addEventListener('click', closeLightbox);
      lightbox?.addEventListener('click', function(e) {
        if (e.target === lightbox) {
          closeLightbox();
        }
      });
      
      // Close with Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightbox.style.display === 'block') {
          closeLightbox();
        }
      });
      document.getElementById('qtyMinus')?.addEventListener('click',()=>{ const i=document.getElementById('qty'); i.value=Math.max(1,parseInt(i.value||'1')-1); });
      document.getElementById('qtyPlus')?.addEventListener('click',()=>{ const i=document.getElementById('qty'); i.value=parseInt(i.value||'1')+1; });
      // Color selection
      const colorEls = document.querySelectorAll('#colorGroup .color-swatch');
      let selectedColor = null;
      colorEls.forEach(el=>{
        if(el.classList.contains('unavailable') || el.classList.contains('variant-blocked')) return;
        el.addEventListener('click',()=>{
          if (el.classList.contains('variant-blocked')) return;
          colorEls.forEach(e=>e.classList.remove('selected'));
          el.classList.add('selected'); selectedColor = el.dataset.color;
        });
      });
      
      // Barevné komponenty - výběr barev
      const componentColors = {};
      
      // Aktuální způsob výběru (swatches nebo configurator)
      let currentSelectionMode = 'swatches';
      
      // Přepínač mezi způsoby výběru
      document.querySelectorAll('.color-selection-mode-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const mode = this.dataset.mode;
          currentSelectionMode = mode;
          
          // Aktualizuj tlačítka
          document.querySelectorAll('.color-selection-mode-btn').forEach(b => {
            if (b.dataset.mode === mode) {
              b.style.background = 'var(--kjd-dark-green)';
              b.style.color = '#fff';
              b.classList.add('active');
            } else {
              b.style.background = '#fff';
              b.style.color = 'var(--kjd-dark-green)';
              b.classList.remove('active');
            }
          });
          
          // Zobraz/skryj sekce
          if (mode === 'swatches') {
            document.getElementById('colorSwatchesSection').style.display = 'block';
            document.getElementById('configuratorSection').style.display = 'none';
          } else {
            document.getElementById('colorSwatchesSection').style.display = 'none';
            document.getElementById('configuratorSection').style.display = 'block';
          }
        });
      });
      
      // Event listenery pro barevné swatche jsou už přidané v PHP, takže není potřeba je kopírovat
      
      // Mapování názvů komponent na klíče v componentImages
      const componentNameMap = {
        'Vršek': 'vrsek',
        'Nožičky': 'nozicky',
        'vrsek': 'vrsek',
        'nozicky': 'nozicky'
      };
      
      // Funkce pro aktualizaci konfigurátoru na základě výběru barvy
      function updateConfiguratorFromColorSelection(componentName, colorName) {
        const componentKey = componentNameMap[componentName] || componentName.toLowerCase();
        
        if (!window.componentVariants || !window.componentVariants[componentKey]) {
          return;
        }
        
        const variants = window.componentVariants[componentKey];
        
        // Najít variantu a barvu, která odpovídá vybrané barvě
        let foundVariantIndex = -1;
        let foundColorIndex = -1;
        
        for (let vIdx = 0; vIdx < variants.length; vIdx++) {
          const variant = variants[vIdx];
          if (variant.colors && variant.colors.length > 0) {
            for (let cIdx = 0; cIdx < variant.colors.length; cIdx++) {
              const colorVariant = variant.colors[cIdx];
              // Porovnat název barvy (case-insensitive)
              if (colorVariant.color && colorVariant.color.toLowerCase() === colorName.toLowerCase()) {
                foundVariantIndex = vIdx;
                foundColorIndex = cIdx;
                break;
              }
            }
            if (foundVariantIndex >= 0) break;
          }
        }
        
        // Pokud jsme našli odpovídající variantu a barvu, aktualizuj konfigurátor
        if (foundVariantIndex >= 0) {
          // Aktualizuj inline barevná tlačítka
          const inlineBtn = document.querySelector(`.inline-color-btn[data-component="${componentKey}"][data-variant-index="${foundVariantIndex}"][data-color-index="${foundColorIndex}"]`);
          if (inlineBtn) {
            document.querySelectorAll(`.inline-color-btn[data-component="${componentKey}"]`).forEach(b => b.classList.remove('active'));
            inlineBtn.classList.add('active');
            inlineBtn.click(); // Spustí event handler pro aktualizaci hidden inputs
          }
          
          // Aktualizuj modální konfigurátor (pokud je otevřený)
          const modalConfigurator = document.querySelector(`#configuratorModal .configurator-color-btn[data-component="${componentKey}"][data-color-index="${foundColorIndex}"]`);
          if (modalConfigurator) {
            // Najít správnou variantu v modalu
            const modalVariantIndex = parseInt(document.getElementById(`selected_${componentKey}_modal`)?.value || '0');
            if (modalVariantIndex === foundVariantIndex) {
              // Pokud je vybraná správná varianta, aktualizuj barvu
              document.querySelectorAll(`#configuratorModal .configurator-color-btn[data-component="${componentKey}"]`).forEach(b => b.classList.remove('active'));
              modalConfigurator.classList.add('active');
              modalConfigurator.click();
            } else {
              // Pokud není vybraná správná varianta, nejprve změň variantu
              const updateFunc = window['updateConfigurator_' + componentKey];
              if (updateFunc) {
                updateFunc(foundVariantIndex, foundColorIndex);
              }
            }
          }
          
          // Aktualizuj zobrazení vybrané barvy
          const selectedColorDisplay = document.querySelector(`.selected-color-name[data-component="${componentKey}"]`);
          if (selectedColorDisplay && variants[foundVariantIndex] && variants[foundVariantIndex].colors[foundColorIndex]) {
            selectedColorDisplay.textContent = variants[foundVariantIndex].colors[foundColorIndex].color || '';
          }
          
          // Aktualizuj kompletní náhled lampy
          updateFullLampPreview();
        }
      }
      
      document.querySelectorAll('.component-color').forEach(el => {
        el.addEventListener('click', () => {
          const component = el.dataset.component;
          const color = el.dataset.color;
          const colorName = el.dataset.colorName || color;
          
          // Odznač ostatní barvy ve stejné komponentě
          document.querySelectorAll(`.component-color[data-component="${component}"]`).forEach(e => {
            e.classList.remove('selected');
          });
          
          // Označ vybranou barvu
          el.classList.add('selected');
          componentColors[component] = color;
          
          // Aktualizuj konfigurátor
          updateConfiguratorFromColorSelection(component, colorName);
          
          console.log('Selected component colors:', componentColors);
        });
      });

      // Filter colors by selected variant options (intersection of allowed sets)
      function updateAllowedColors(){
        const selected = Array.from(document.querySelectorAll('.variant-radio:checked'));
        // Collect allowed sets
        let allowedSet = null; // null => no restriction
        selected.forEach(r=>{
          const csv = (r.getAttribute('data-colors')||'').trim();
          if (csv){
            const set = new Set(csv.split(',').map(s=>s.trim().toLowerCase()).filter(Boolean));
            if (allowedSet === null){ allowedSet = set; }
            else {
              // intersect
              allowedSet = new Set(Array.from(allowedSet).filter(x=>set.has(x)));
            }
          }
        });
        // Apply to swatches
        colorEls.forEach(el=>{
          const name = (el.getAttribute('data-color-name')||'').trim().toLowerCase();
          if (allowedSet && allowedSet.size>0){
            if (!allowedSet.has(name)) { el.classList.add('variant-blocked'); if (el.classList.contains('selected')) { el.classList.remove('selected'); if (selectedColor===el.dataset.color) selectedColor=null; } }
            else { el.classList.remove('variant-blocked'); }
          } else {
            el.classList.remove('variant-blocked');
          }
        });
      }
      
      // Update price based on selected variants
      function updatePrice() {
        const priceDisplay = document.getElementById('priceDisplay');
        if (!priceDisplay) return;
        
        const basePrice = parseFloat(priceDisplay.dataset.basePrice) || 0;
        const salePrice = parseFloat(priceDisplay.dataset.salePrice) || 0;
        const saleActive = priceDisplay.dataset.saleActive === '1';
        
        // Calculate total variant price adjustment
        let variantAdjustment = 0;
        document.querySelectorAll('.variant-radio:checked').forEach(radio => {
          const priceAdj = parseFloat(radio.dataset.price) || 0;
          variantAdjustment += priceAdj;
        });
        
        // Calculate final prices
        // Calculate final prices
        const finalBasePrice = basePrice + variantAdjustment;
        // Apply sale percentage factor to variants if sale is active (Proportional Discount)
        const discountFactor = (basePrice > 0) ? (salePrice / basePrice) : 1;
        const finalSalePrice = saleActive ? (finalBasePrice * discountFactor) : 0;
        
        // Format price with thousands separator
        const formatPrice = (price) => {
          return Math.round(price).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        };
        
        // Update display
        if (saleActive && finalSalePrice > 0) {
          const oldEl = document.getElementById('priceOld');
          const newEl = document.getElementById('priceNew');
          const discountEl = document.getElementById('priceDiscount');
          
          if (oldEl) oldEl.textContent = formatPrice(finalBasePrice) + ' Kč';
          if (newEl) newEl.textContent = formatPrice(finalSalePrice) + ' Kč';
          
          if (discountEl && finalBasePrice > 0) {
            const discountPct = Math.max(1, Math.round((1 - (finalSalePrice / finalBasePrice)) * 100));
            discountEl.textContent = '-' + discountPct + '%';
          }
        } else {
          const regularEl = document.getElementById('priceRegular');
          if (regularEl) regularEl.textContent = formatPrice(finalBasePrice) + ' Kč';
        }
      }
      
      document.querySelectorAll('.variant-radio').forEach(r=>{
        r.addEventListener('change', () => {
          updateAllowedColors();
          updatePrice();
        });
      });
      updateAllowedColors();
      updatePrice();

      // Unavailable color modal
      function showUnavailableModal(colorCode, colorName) {
        const modal = new bootstrap.Modal(document.getElementById('unavailableModal'));
        document.getElementById('modalColorPreview').style.backgroundColor = colorCode;
        document.getElementById('modalColorName').textContent = colorName;
        document.getElementById('notificationForm').dataset.colorCode = colorCode;
        document.getElementById('notificationForm').dataset.colorName = colorName;
        modal.show();
      }

      // Handle notification form submission
      document.getElementById('notificationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('notificationEmail').value;
        const name = document.getElementById('notificationName').value;
        const colorCode = this.dataset.colorCode;
        const colorName = this.dataset.colorName;
        const productId = <?= (int)$id ?>;
        
        fetch('register_notification.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            email: email,
            name: name,
            product_id: productId,
            color_code: colorCode,
            color_name: colorName,
            type: 'color_availability'
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Děkujeme! Budete upozorněni, až bude barva dostupná.');
            bootstrap.Modal.getInstance(document.getElementById('unavailableModal')).hide();
            document.getElementById('notificationForm').reset();
          } else {
            alert(data.message || 'Nastala chyba při odesílání žádosti.');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Nastala chyba při odesílání žádosti.');
        });
      });
      
      // Open configurator modal
      document.getElementById('openConfiguratorBtn')?.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('configuratorModal'));
        modal.show();
        
        // Inicializuj konfigurátor pro všechny komponenty při otevření modalu
        if (window.componentVariants) {
          Object.keys(window.componentVariants).forEach(component => {
            const updateFunc = window['updateConfigurator_' + component];
            if (updateFunc) {
              // Zkontroluj, jestli už jsou vybrané hodnoty v hlavních hidden inputs
              const mainIndex = document.getElementById(`selected_${component}`);
              const mainColor = document.getElementById(`selected_${component}_color`);
              
              let initIndex = 0;
              let initColorIndex = 0;
              
              if (mainIndex && mainColor && mainColor.value) {
                // Pokud už jsou vybrané hodnoty, použij je
                initIndex = parseInt(mainIndex.value) || 0;
                const variant = window.componentVariants[component][initIndex];
                if (variant && variant.colors) {
                  const colorIdx = variant.colors.findIndex(c => c.color && c.color.toLowerCase() === mainColor.value.toLowerCase());
                  if (colorIdx >= 0) {
                    initColorIndex = colorIdx;
                  }
                }
              }
              
              updateFunc(initIndex, initColorIndex);
            }
          });
          // Aktualizuj kompletní náhled lampy
          updateFullLampPreview();
        }
      });
      
      // Funkce pro aktualizaci barevných swatchů v sekci "Výběr barev"
      function updateColorSwatchesFromConfigurator(componentKey, colorName) {
        // Mapování klíčů zpět na názvy komponent
        const reverseComponentMap = {
          'vrsek': 'Vršek',
          'nozicky': 'Nožičky'
        };
        
        const componentName = reverseComponentMap[componentKey] || componentKey;
        
        // Najít odpovídající barevný swatch
        document.querySelectorAll(`.component-color[data-component="${componentName}"]`).forEach(swatch => {
          const swatchColorName = swatch.dataset.colorName || swatch.dataset.color;
          if (swatchColorName && swatchColorName.toLowerCase() === colorName.toLowerCase()) {
            // Odznač ostatní swatche
            document.querySelectorAll(`.component-color[data-component="${componentName}"]`).forEach(s => {
              s.classList.remove('selected');
            });
            // Označ odpovídající swatch
            swatch.classList.add('selected');
          }
        });
      }
      
      // Inline color button selection
      document.querySelectorAll('.inline-color-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const component = this.dataset.component;
          const variantIndex = parseInt(this.dataset.variantIndex) || 0;
          const colorIndex = parseInt(this.dataset.colorIndex) || 0;
          const colorName = this.dataset.colorName || '';
          const colorImage = this.dataset.colorImage || '';
          
          // Update active state
          document.querySelectorAll(`.inline-color-btn[data-component="${component}"]`).forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          
          // Update hidden inputs
          const hiddenIndex = document.getElementById(`selected_${component}`);
          const hiddenName = document.getElementById(`selected_${component}_name`);
          const hiddenColor = document.getElementById(`selected_${component}_color`);
          const hiddenColorImage = document.getElementById(`selected_${component}_color_image`);
          
          if (hiddenIndex) hiddenIndex.value = variantIndex;
          if (hiddenName && window.componentVariants[component] && window.componentVariants[component][variantIndex]) {
            hiddenName.value = window.componentVariants[component][variantIndex].name || '';
          }
          if (hiddenColor) hiddenColor.value = colorName;
          if (hiddenColorImage) hiddenColorImage.value = colorImage;
          
          // Update display
          const selectedColorDisplay = document.querySelector(`.selected-color-name[data-component="${component}"]`);
          if (selectedColorDisplay) {
            selectedColorDisplay.textContent = colorName;
          }
          
          // Aktualizuj barevné swatche v sekci "Výběr barev"
          updateColorSwatchesFromConfigurator(component, colorName);
          
          // Aktualizuj kompletní náhled lampy
          updateFullLampPreview();
        });
      });
      
      // Update full lamp preview (both modal and final preview)
      function updateFullLampPreview() {
        const nozickyIndex = parseInt(document.getElementById('selected_nozicky_modal')?.value || document.getElementById('selected_nozicky')?.value || '0') || 0;
        const vrsekIndex = parseInt(document.getElementById('selected_vrsek_modal')?.value || document.getElementById('selected_vrsek')?.value || '0') || 0;
        
        // Get current color values from hidden inputs
        let nozickyColor = (document.getElementById('selected_nozicky_color_modal')?.value || document.getElementById('selected_nozicky_color')?.value || '').trim();
        let vrsekColor = (document.getElementById('selected_vrsek_color_modal')?.value || document.getElementById('selected_vrsek_color')?.value || '').trim();
        
        // If colors are empty, try to get from active buttons or variant names
        if (!nozickyColor && window.componentVariants?.nozicky?.[nozickyIndex]) {
          const variant = window.componentVariants.nozicky[nozickyIndex];
          const activeBtn = document.querySelector(`#configuratorModal .configurator-color-btn[data-component="nozicky"].active`);
          if (activeBtn) {
            nozickyColor = (activeBtn.dataset.colorName || '').trim();
          } else if (variant.colors && variant.colors.length > 0) {
            nozickyColor = variant.colors[0].color || variant.name || '';
          } else {
            nozickyColor = variant.name || '';
          }
        }
        
        if (!vrsekColor && window.componentVariants?.vrsek?.[vrsekIndex]) {
          const variant = window.componentVariants.vrsek[vrsekIndex];
          const activeBtn = document.querySelector(`#configuratorModal .configurator-color-btn[data-component="vrsek"].active`);
          if (activeBtn) {
            vrsekColor = (activeBtn.dataset.colorName || '').trim();
          } else if (variant.colors && variant.colors.length > 0) {
            vrsekColor = variant.colors[0].color || variant.name || '';
          } else {
            vrsekColor = variant.name || '';
          }
        }
        
        // Update color name displays
        const selectedNozickyDisplay = document.querySelector('.selected-color-name[data-component="nozicky"]');
        const selectedVrsekDisplay = document.querySelector('.selected-color-name[data-component="vrsek"]');
        const selectedNozickyDisplayModal = document.querySelector('#configuratorModal .selected-color-name-modal[data-component="nozicky"]');
        const selectedVrsekDisplayModal = document.querySelector('#configuratorModal .selected-color-name-modal[data-component="vrsek"]');
        
        if (selectedNozickyDisplay && nozickyColor) {
          selectedNozickyDisplay.textContent = nozickyColor;
        }
        if (selectedVrsekDisplay && vrsekColor) {
          selectedVrsekDisplay.textContent = vrsekColor;
        }
        if (selectedNozickyDisplayModal && nozickyColor) {
          selectedNozickyDisplayModal.textContent = nozickyColor;
        }
        if (selectedVrsekDisplayModal && vrsekColor) {
          selectedVrsekDisplayModal.textContent = vrsekColor;
        }
        
        let nozickyColorIndex = 0;
        let vrsekColorIndex = 0;
        
        // Find active color button
        const activeNozickyBtn = document.querySelector('#configuratorModal .configurator-color-btn[data-component="nozicky"].active');
        if (activeNozickyBtn) {
          nozickyColorIndex = parseInt(activeNozickyBtn.dataset.colorIndex) || 0;
        }
        
        const activeVrsekBtn = document.querySelector('#configuratorModal .configurator-color-btn[data-component="vrsek"].active');
        if (activeVrsekBtn) {
          vrsekColorIndex = parseInt(activeVrsekBtn.dataset.colorIndex) || 0;
        }
        
        let nozickyImg = '';
        let vrsekImg = '';
        
        if (window.componentVariants.nozicky && window.componentVariants.nozicky[nozickyIndex]) {
          const variant = window.componentVariants.nozicky[nozickyIndex];
          if (variant.colors && variant.colors[nozickyColorIndex]) {
            nozickyImg = variant.colors[nozickyColorIndex].image || variant.image || '';
          } else {
            nozickyImg = variant.image || '';
          }
        }
        
        if (window.componentVariants.vrsek && window.componentVariants.vrsek[vrsekIndex]) {
          const variant = window.componentVariants.vrsek[vrsekIndex];
          if (variant.colors && variant.colors[vrsekColorIndex]) {
            vrsekImg = variant.colors[vrsekColorIndex].image || variant.image || '';
          } else {
            vrsekImg = variant.image || '';
          }
        }
        
        // Normalize paths
        function normalizePath(path) {
          if (!path) return '';
          if (/^https?:\/\//.test(path)) return path;
          if (path.startsWith('uploads/') || path.startsWith('/uploads/')) return path.replace(/^\/+/, '');
          if (path.startsWith('admin/uploads/')) return path;
          return path;
        }
        
        const nozickyEl = document.getElementById('fullLampPreviewBottom');
        const vrsekEl = document.getElementById('fullLampPreviewTop');
        
        if (nozickyEl && nozickyImg) {
          nozickyEl.src = normalizePath(nozickyImg);
          nozickyEl.style.display = 'block';
          // Posunout nožičky výš, aby překrývaly vršek - použít top positioning
          nozickyEl.style.position = 'absolute';
          nozickyEl.style.top = '27%';
          nozickyEl.style.left = '50%';
          nozickyEl.style.transform = 'translateX(-50%) translateY(-35%)';
          nozickyEl.style.width = 'auto';
          // Pro mobil použít stejné hodnoty jako na PC
          if (window.innerWidth <= 768) {
            nozickyEl.style.height = '70%';
          } else {
            nozickyEl.style.height = '70%';
          }
          nozickyEl.style.maxWidth = 'none';
          nozickyEl.style.maxHeight = 'none';
          nozickyEl.style.zIndex = '1';
        } else if (nozickyEl) {
          nozickyEl.style.display = 'none';
        }
        
        if (vrsekEl && vrsekImg) {
          vrsekEl.src = normalizePath(vrsekImg);
          vrsekEl.style.display = 'block';
          // Vršek nahoře
          vrsekEl.style.position = 'absolute';
          vrsekEl.style.top = '0%';
          vrsekEl.style.left = '50%';
          vrsekEl.style.transform = 'translateX(-50%)';
          vrsekEl.style.width = 'auto';
          // Pro mobil použít stejné hodnoty jako na PC
          if (window.innerWidth <= 768) {
            vrsekEl.style.height = '75%';
          } else {
            vrsekEl.style.height = '75%';
          }
          vrsekEl.style.maxWidth = 'none';
          vrsekEl.style.maxHeight = 'none';
          vrsekEl.style.zIndex = '2';
        } else if (vrsekEl) {
          vrsekEl.style.display = 'none';
        }
        
        // Aktualizuj také finální náhled lampy (v sekci konfigurátoru)
        const finalNozickyEl = document.getElementById('finalLampPreviewBottom');
        const finalVrsekEl = document.getElementById('finalLampPreviewTop');
        
        if (finalNozickyEl && nozickyImg) {
          finalNozickyEl.src = normalizePath(nozickyImg);
          finalNozickyEl.style.display = 'block';
          finalNozickyEl.style.position = 'absolute';
          finalNozickyEl.style.top = '27%';
          finalNozickyEl.style.left = '50%';
          finalNozickyEl.style.transform = 'translateX(-50%) translateY(-35%)';
          finalNozickyEl.style.width = 'auto';
          finalNozickyEl.style.height = '70%';
          finalNozickyEl.style.maxWidth = 'none';
          finalNozickyEl.style.maxHeight = 'none';
          finalNozickyEl.style.zIndex = '1';
        } else if (finalNozickyEl) {
          finalNozickyEl.style.display = 'none';
        }
        
        if (finalVrsekEl && vrsekImg) {
          finalVrsekEl.src = normalizePath(vrsekImg);
          finalVrsekEl.style.display = 'block';
          finalVrsekEl.style.position = 'absolute';
          finalVrsekEl.style.top = '0%';
          finalVrsekEl.style.left = '50%';
          finalVrsekEl.style.transform = 'translateX(-50%)';
          finalVrsekEl.style.width = 'auto';
          finalVrsekEl.style.height = '75%';
          finalVrsekEl.style.maxWidth = 'none';
          finalVrsekEl.style.maxHeight = 'none';
          finalVrsekEl.style.zIndex = '2';
        } else if (finalVrsekEl) {
          finalVrsekEl.style.display = 'none';
        }
      }
      
      // Save configurator selection
      document.getElementById('saveConfiguratorSelection')?.addEventListener('click', function() {
        // Copy modal selections to main form
        // Zkus najít aktivní barevné tlačítko v modalu a získat hodnoty z něj
        const activeNozickyBtn = document.querySelector('#configuratorModal .configurator-color-btn[data-component="nozicky"].active');
        const activeVrsekBtn = document.querySelector('#configuratorModal .configurator-color-btn[data-component="vrsek"].active');
        
        let nozickyIndex = document.getElementById('selected_nozicky_modal')?.value || '0';
        let nozickyName = document.getElementById('selected_nozicky_name_modal')?.value || '';
        let nozickyColor = (document.getElementById('selected_nozicky_color_modal')?.value || '').trim();
        let nozickyColorImage = (document.getElementById('selected_nozicky_color_image_modal')?.value || '').trim();
        
        // Pokud jsou prázdné, zkus získat z aktivního tlačítka nebo použij název varianty
        if (!nozickyColor) {
          if (activeNozickyBtn) {
            nozickyColor = (activeNozickyBtn.dataset.colorName || '').trim();
            nozickyColorImage = (activeNozickyBtn.dataset.colorImage || '').trim();
          }
          // Pokud stále není barva, použij název varianty jako barvu
          if (!nozickyColor) {
            const variantIdx = parseInt(document.getElementById('selected_nozicky_modal')?.value || '0');
            if (window.componentVariants.nozicky && window.componentVariants.nozicky[variantIdx]) {
              const variant = window.componentVariants.nozicky[variantIdx];
              nozickyColor = variant.name || '';
              nozickyColorImage = variant.image || '';
            }
          }
          const variantIdx = parseInt(document.getElementById('selected_nozicky_modal')?.value || '0');
          if (window.componentVariants.nozicky && window.componentVariants.nozicky[variantIdx]) {
            nozickyName = window.componentVariants.nozicky[variantIdx].name || '';
          }
        }
        
        let vrsekIndex = document.getElementById('selected_vrsek_modal')?.value || '0';
        let vrsekName = document.getElementById('selected_vrsek_name_modal')?.value || '';
        let vrsekColor = (document.getElementById('selected_vrsek_color_modal')?.value || '').trim();
        let vrsekColorImage = (document.getElementById('selected_vrsek_color_image_modal')?.value || '').trim();
        
        // Pokud jsou prázdné, zkus získat z aktivního tlačítka nebo použij název varianty
        if (!vrsekColor) {
          if (activeVrsekBtn) {
            vrsekColor = (activeVrsekBtn.dataset.colorName || '').trim();
            vrsekColorImage = (activeVrsekBtn.dataset.colorImage || '').trim();
          }
          // Pokud stále není barva, použij název varianty jako barvu
          if (!vrsekColor) {
            const variantIdx = parseInt(document.getElementById('selected_vrsek_modal')?.value || '0');
            if (window.componentVariants.vrsek && window.componentVariants.vrsek[variantIdx]) {
              const variant = window.componentVariants.vrsek[variantIdx];
              vrsekColor = variant.name || '';
              vrsekColorImage = variant.image || '';
            }
          }
          const variantIdx = parseInt(document.getElementById('selected_vrsek_modal')?.value || '0');
          if (window.componentVariants.vrsek && window.componentVariants.vrsek[variantIdx]) {
            vrsekName = window.componentVariants.vrsek[variantIdx].name || '';
          }
        }
        
        console.log('Saving configurator selection:', {
          nozicky: { index: nozickyIndex, name: nozickyName, color: nozickyColor, colorImage: nozickyColorImage, activeBtn: !!activeNozickyBtn },
          vrsek: { index: vrsekIndex, name: vrsekName, color: vrsekColor, colorImage: vrsekColorImage, activeBtn: !!activeVrsekBtn }
        });
        
        // Update main form hidden inputs
        const mainNozickyIndex = document.getElementById('selected_nozicky');
        const mainNozickyName = document.getElementById('selected_nozicky_name');
        const mainNozickyColor = document.getElementById('selected_nozicky_color');
        const mainNozickyColorImage = document.getElementById('selected_nozicky_color_image');
        
        const mainVrsekIndex = document.getElementById('selected_vrsek');
        const mainVrsekName = document.getElementById('selected_vrsek_name');
        const mainVrsekColor = document.getElementById('selected_vrsek_color');
        const mainVrsekColorImage = document.getElementById('selected_vrsek_color_image');
        
        if (mainNozickyIndex) {
          mainNozickyIndex.value = nozickyIndex;
          console.log('Updated selected_nozicky:', mainNozickyIndex.value);
        }
        if (mainNozickyName) {
          mainNozickyName.value = nozickyName;
          console.log('Updated selected_nozicky_name:', mainNozickyName.value);
        }
        if (mainNozickyColor) {
          mainNozickyColor.value = nozickyColor;
          console.log('Updated selected_nozicky_color:', mainNozickyColor.value);
        }
        if (mainNozickyColorImage) {
          mainNozickyColorImage.value = nozickyColorImage;
          console.log('Updated selected_nozicky_color_image:', mainNozickyColorImage.value);
        }
        
        if (mainVrsekIndex) {
          mainVrsekIndex.value = vrsekIndex;
          console.log('Updated selected_vrsek:', mainVrsekIndex.value);
        }
        if (mainVrsekName) {
          mainVrsekName.value = vrsekName;
          console.log('Updated selected_vrsek_name:', mainVrsekName.value);
        }
        if (mainVrsekColor) {
          mainVrsekColor.value = vrsekColor;
          console.log('Updated selected_vrsek_color:', mainVrsekColor.value);
        }
        if (mainVrsekColorImage) {
          mainVrsekColorImage.value = vrsekColorImage;
          console.log('Updated selected_vrsek_color_image:', mainVrsekColorImage.value);
        }
        
        // Update display
        // Update color name displays
        const selectedNozickyDisplay = document.querySelector('.selected-color-name[data-component="nozicky"]');
        const selectedVrsekDisplay = document.querySelector('.selected-color-name[data-component="vrsek"]');
        const selectedNozickyDisplayModal = document.querySelector('#configuratorModal .selected-color-name-modal[data-component="nozicky"]');
        const selectedVrsekDisplayModal = document.querySelector('#configuratorModal .selected-color-name-modal[data-component="vrsek"]');
        
        if (selectedNozickyDisplay) {
          selectedNozickyDisplay.textContent = nozickyColor || nozickyName;
        }
        if (selectedVrsekDisplay) {
          selectedVrsekDisplay.textContent = vrsekColor || vrsekName;
        }
        if (selectedNozickyDisplayModal) {
          selectedNozickyDisplayModal.textContent = nozickyColor || nozickyName;
        }
        if (selectedVrsekDisplayModal) {
          selectedVrsekDisplayModal.textContent = vrsekColor || vrsekName;
        }
        
        // Aktualizuj barevné swatche v sekci "Výběr barev"
        if (typeof updateColorSwatchesFromConfigurator === 'function') {
          if (nozickyColor) {
            updateColorSwatchesFromConfigurator('nozicky', nozickyColor);
          }
          if (vrsekColor) {
            updateColorSwatchesFromConfigurator('vrsek', vrsekColor);
          }
        }
        
        // Aktualizuj inline barevná tlačítka - najít a aktivovat odpovídající tlačítka
        // Funkce pro aktualizaci inline tlačítek pro komponentu
        function updateInlineButtonsForComponent(componentKey, variantIndex, colorName) {
          const variant = window.componentVariants?.[componentKey]?.[parseInt(variantIndex)];
          if (!variant || !variant.colors || !colorName) return;
          
          // Najít index barvy v této variantě
          const colorIdx = variant.colors.findIndex(c => c.color && c.color.toLowerCase() === colorName.toLowerCase());
          if (colorIdx < 0) return;
          
          // Najít kontejner pro inline tlačítka tohoto komponentu
          const componentLabel = componentKey === 'nozicky' ? 'Horní část - Barva:' : 'Spodní část - Barva:';
          const inlineContainer = Array.from(document.querySelectorAll('.inline-color-selection label')).find(l => l.textContent.includes(componentLabel))?.nextElementSibling;
          
          if (inlineContainer) {
            // Odstranit existující tlačítka pro tento komponent
            inlineContainer.querySelectorAll('.inline-color-btn[data-component="' + componentKey + '"]').forEach(b => b.remove());
            
            // Vytvořit nová tlačítka pro vybranou variantu
            variant.colors.forEach((colorVariant, idx) => {
              let colorImgPath = colorVariant.image || '';
              if (colorImgPath && !/^https?:\/\//.test(colorImgPath)) {
                if (colorImgPath.startsWith('uploads/') || colorImgPath.startsWith('/uploads/')) {
                  colorImgPath = colorImgPath.replace(/^\/+/, '');
                }
              }
              
              const btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'inline-color-btn btn btn-sm' + (idx === colorIdx ? ' active' : '');
              btn.setAttribute('data-component', componentKey);
              btn.setAttribute('data-variant-index', variantIndex);
              btn.setAttribute('data-color-index', idx);
              btn.setAttribute('data-color-name', colorVariant.color || '');
              btn.setAttribute('data-color-image', colorImgPath);
              btn.style.cssText = 'border: 2px solid var(--kjd-earth-green); border-radius: 10px; padding: 0.4rem; background: #fff; transition: all 0.2s ease; min-width: 65px; text-align: center;';
              
              btn.innerHTML = `
                <img src="${colorImgPath}" alt="${(colorVariant.color || '').replace(/"/g, '&quot;')}" 
                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; display: block; margin: 0 auto 6px; border: 1px solid rgba(0,0,0,0.1);">
                <div style="font-weight: 600; color: var(--kjd-dark-green); font-size: 0.85rem;">${(colorVariant.color || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
              `;
              
              // Přidat event listener
              btn.addEventListener('click', function() {
                const comp = this.dataset.component;
                const vIdx = parseInt(this.dataset.variantIndex) || 0;
                const cIdx = parseInt(this.dataset.colorIndex) || 0;
                const cName = this.dataset.colorName || '';
                const cImage = this.dataset.colorImage || '';
                
                document.querySelectorAll(`.inline-color-btn[data-component="${comp}"]`).forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const hiddenIndex = document.getElementById(`selected_${comp}`);
                const hiddenName = document.getElementById(`selected_${comp}_name`);
                const hiddenColor = document.getElementById(`selected_${comp}_color`);
                const hiddenColorImage = document.getElementById(`selected_${comp}_color_image`);
                
                if (hiddenIndex) hiddenIndex.value = vIdx;
                if (hiddenName && window.componentVariants[comp] && window.componentVariants[comp][vIdx]) {
                  hiddenName.value = window.componentVariants[comp][vIdx].name || '';
                }
                if (hiddenColor) hiddenColor.value = cName;
                if (hiddenColorImage) hiddenColorImage.value = cImage;
                
                const selectedColorDisplay = document.querySelector(`.selected-color-name[data-component="${comp}"]`);
                if (selectedColorDisplay) {
                  selectedColorDisplay.textContent = cName;
                }
                
                if (typeof updateColorSwatchesFromConfigurator === 'function') {
                  updateColorSwatchesFromConfigurator(comp, cName);
                }
                updateFullLampPreview();
              });
              
              inlineContainer.appendChild(btn);
            });
            
            // Aktivovat správné tlačítko
            const activeBtn = inlineContainer.querySelector(`.inline-color-btn[data-component="${componentKey}"][data-variant-index="${variantIndex}"][data-color-index="${colorIdx}"]`);
            if (activeBtn) {
              activeBtn.click();
            }
          }
        }
        
        // Aktualizuj inline tlačítka pro oba komponenty
        if (nozickyColor) {
          updateInlineButtonsForComponent('nozicky', nozickyIndex, nozickyColor);
          // Naplň componentColors pro validaci - použij správné názvy z colorComponents
          // Najdi všechny možné názvy komponentů a naplň je všechny
          document.querySelectorAll('.component-color[data-component]').forEach(el => {
            const compName = el.dataset.component || '';
            if (compName === 'Nožičky' || compName === 'Horní část' || 
                compName.toLowerCase().includes('nožič') || compName.toLowerCase().includes('horní')) {
              componentColors[compName] = nozickyColor;
            }
          });
        }
        if (vrsekColor) {
          updateInlineButtonsForComponent('vrsek', vrsekIndex, vrsekColor);
          // Naplň componentColors pro validaci - použij správné názvy z colorComponents
          // Najdi všechny možné názvy komponentů a naplň je všechny
          document.querySelectorAll('.component-color[data-component]').forEach(el => {
            const compName = el.dataset.component || '';
            if (compName === 'Vršek' || compName === 'Spodní část' || 
                compName.toLowerCase().includes('vršek') || compName.toLowerCase().includes('spodní')) {
              componentColors[compName] = vrsekColor;
            }
          });
        }
        
        console.log('Component colors after configurator save:', componentColors);
        console.log('Hidden inputs after save:', {
          nozicky: {
            index: document.getElementById('selected_nozicky')?.value,
            name: document.getElementById('selected_nozicky_name')?.value,
            color: document.getElementById('selected_nozicky_color')?.value,
            colorImage: document.getElementById('selected_nozicky_color_image')?.value
          },
          vrsek: {
            index: document.getElementById('selected_vrsek')?.value,
            name: document.getElementById('selected_vrsek_name')?.value,
            color: document.getElementById('selected_vrsek_color')?.value,
            colorImage: document.getElementById('selected_vrsek_color_image')?.value
          }
        });
        
        // Aktualizuj kompletní náhled lampy
        updateFullLampPreview();
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('configuratorModal'));
        if (modal) modal.hide();
      });
      
      // Component configurator navigation (for modal)
      if (window.componentVariants) {
        const configurators = {};
        
        Object.keys(window.componentVariants).forEach(component => {
          const variants = window.componentVariants[component];
          if (variants.length === 0) return;
          
          configurators[component] = {
            variants: variants,
            currentIndex: 0
          };
          
          // Modal elements
          const prevBtn = document.querySelector(`#configuratorModal .configurator-prev[data-component="${component}"]`);
          const nextBtn = document.querySelector(`#configuratorModal .configurator-next[data-component="${component}"]`);
          const imgEl = document.querySelector(`#configuratorModal .configurator-image[data-component="${component}"]`);
          const hiddenIndex = document.getElementById(`selected_${component}_modal`);
          const hiddenName = document.getElementById(`selected_${component}_name_modal`);
          const hiddenColor = document.getElementById(`selected_${component}_color_modal`);
          const hiddenColorImage = document.getElementById(`selected_${component}_color_image_modal`);
          
          const updateConfigurator = (index, colorIndex = 0) => {
            if (index < 0 || index >= variants.length) return;
            
            configurators[component].currentIndex = index;
            const variant = variants[index];
            const colorVariants = variant.colors || [];
            
            console.log(`updateConfigurator for ${component}:`, {
              index,
              colorIndex,
              variant: variant,
              colorVariants: colorVariants,
              colorVariantsLength: colorVariants.length
            });
            
            // Determine which image to show (color variant or base variant)
            let imgPath = variant.image || '';
            if (colorVariants.length > 0 && colorIndex >= 0 && colorIndex < colorVariants.length) {
              imgPath = colorVariants[colorIndex].image || imgPath;
            }
            
            if (imgPath && !/^https?:\/\//.test(imgPath)) {
              if (imgPath.startsWith('uploads/') || imgPath.startsWith('/uploads/')) {
                imgPath = imgPath.replace(/^\/+/, '');
              }
            }
            if (imgEl) imgEl.src = imgPath;
            
            // Store update function globally for modal initialization
            window['updateConfigurator_' + component] = updateConfigurator;
            
            // Update color variants display
            const colorsContainer = document.querySelector(`.configurator-colors[data-component="${component}"]`);
            if (colorsContainer && colorVariants.length > 0) {
              colorsContainer.style.display = 'block';
              colorsContainer.innerHTML = `
                <div class="d-flex flex-wrap gap-2">
                  ${colorVariants.map((colorVariant, idx) => {
                    let colorImgPath = colorVariant.image || '';
                    if (colorImgPath && !/^https?:\/\//.test(colorImgPath)) {
                      if (colorImgPath.startsWith('uploads/') || colorImgPath.startsWith('/uploads/')) {
                        colorImgPath = colorImgPath.replace(/^\/+/, '');
                      }
                    }
                    return `
                      <button type="button" class="configurator-color-btn ${idx === colorIndex ? 'active' : ''}" 
                              data-component="${component}" 
                              data-color-index="${idx}"
                              data-color-name="${(colorVariant.color || '').replace(/"/g, '&quot;')}"
                              data-color-image="${colorImgPath.replace(/"/g, '&quot;')}"
                              style="border: 2px solid var(--kjd-earth-green); border-radius: 10px; padding: 0.75rem; background: ${idx === colorIndex ? 'linear-gradient(135deg, var(--kjd-beige), #f5f0e8)' : '#fff'}; transition: all 0.2s ease; min-width: 90px; text-align: center;">
                        <img src="${colorImgPath.replace(/"/g, '&quot;')}" alt="${(colorVariant.color || '').replace(/"/g, '&quot;')}" 
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px; display: block; margin: 0 auto 8px; border: 1px solid rgba(0,0,0,0.1);">
                        <div style="font-weight: 600; color: var(--kjd-dark-green); font-size: 0.9rem;">${(colorVariant.color || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                      </button>
                    `;
                  }).join('')}
                </div>
              `;
              
              // Add click handlers for color buttons
              colorsContainer.querySelectorAll('.configurator-color-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                  // Remove active from all buttons in this component
                  colorsContainer.querySelectorAll('.configurator-color-btn').forEach(b => b.classList.remove('active'));
                  // Add active to clicked button
                  this.classList.add('active');
                  
                  const newColorIndex = parseInt(this.dataset.colorIndex) || 0;
                  updateConfigurator(index, newColorIndex);
                });
              });
            } else if (colorsContainer) {
              colorsContainer.style.display = 'none';
            }
            
            // Update selected color display in modal
            const selectedColorDisplayModal = document.querySelector(`#configuratorModal .selected-color-name-modal[data-component="${component}"]`);
            if (selectedColorDisplayModal) {
              if (colorVariants.length > 0 && colorIndex >= 0 && colorIndex < colorVariants.length) {
                selectedColorDisplayModal.textContent = colorVariants[colorIndex].color || variant.name || '';
              } else {
                // Pokud nejsou barvy, použij název varianty
                selectedColorDisplayModal.textContent = variant.name || '';
              }
            }
            
            // Update hidden inputs (modal versions)
            if (hiddenIndex) hiddenIndex.value = index;
            if (hiddenName) hiddenName.value = variant.name || '';
            
            if (hiddenColor) {
              if (colorVariants.length > 0 && colorIndex >= 0 && colorIndex < colorVariants.length) {
                hiddenColor.value = colorVariants[colorIndex].color || '';
              } else if (variant.name) {
                // Pokud nejsou barvy, použij název varianty jako barvu
                hiddenColor.value = variant.name;
              } else {
                hiddenColor.value = '';
              }
              console.log(`Updated modal hidden input selected_${component}_color_modal:`, hiddenColor.value);
            }
            if (hiddenColorImage) {
              if (colorVariants.length > 0 && colorIndex >= 0 && colorIndex < colorVariants.length) {
                hiddenColorImage.value = colorVariants[colorIndex].image || '';
              } else if (variant.image) {
                // Pokud nejsou barvy, použij obrázek varianty
                hiddenColorImage.value = variant.image;
              } else {
                hiddenColorImage.value = '';
              }
              console.log(`Updated modal hidden input selected_${component}_color_image_modal:`, hiddenColorImage.value);
            }
            
            console.log(`updateConfigurator called for ${component}: index=${index}, colorIndex=${colorIndex}, colorVariants.length=${colorVariants.length}`);
            
            // Update full lamp preview
            updateFullLampPreview();
            
            // Aktualizuj barevné swatche v sekci "Výběr barev" (pokud existuje funkce)
            if (typeof updateColorSwatchesFromConfigurator === 'function' && colorVariants.length > 0 && colorIndex >= 0 && colorIndex < colorVariants.length) {
              updateColorSwatchesFromConfigurator(component, colorVariants[colorIndex].color || '');
            }
            
            // Update button states - zobrazit šipky jen když je možné navigovat
            if (prevBtn) {
              if (index === 0) {
                prevBtn.style.opacity = '0.3';
                prevBtn.style.cursor = 'not-allowed';
                prevBtn.disabled = true;
              } else {
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
                prevBtn.disabled = false;
              }
            }
            if (nextBtn) {
              if (index === variants.length - 1) {
                nextBtn.style.opacity = '0.3';
                nextBtn.style.cursor = 'not-allowed';
                nextBtn.disabled = true;
              } else {
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
                nextBtn.disabled = false;
              }
            }
          };
          
          if (prevBtn) {
            prevBtn.addEventListener('click', () => {
              // Omezená navigace - nelze jít dál než na začátek
              if (configurators[component].currentIndex > 0) {
                const newIndex = configurators[component].currentIndex - 1;
                updateConfigurator(newIndex, 0); // Reset to first color when changing variant
              }
            });
            prevBtn.addEventListener('mouseenter', function() {
              if (!this.disabled) {
                this.style.background = 'var(--kjd-earth-green)';
                this.style.color = '#fff';
              }
            });
            prevBtn.addEventListener('mouseleave', function() {
              if (!this.disabled) {
                this.style.background = '#fff';
                this.style.color = 'var(--kjd-dark-green)';
              }
            });
          }
          
          if (nextBtn) {
            nextBtn.addEventListener('click', () => {
              // Omezená navigace - nelze jít dál než na konec
              if (configurators[component].currentIndex < variants.length - 1) {
                const newIndex = configurators[component].currentIndex + 1;
                updateConfigurator(newIndex, 0); // Reset to first color when changing variant
              }
            });
            nextBtn.addEventListener('mouseenter', function() {
              if (!this.disabled) {
                this.style.background = 'var(--kjd-earth-green)';
                this.style.color = '#fff';
              }
            });
            nextBtn.addEventListener('mouseleave', function() {
              if (!this.disabled) {
                this.style.background = '#fff';
                this.style.color = 'var(--kjd-dark-green)';
              }
            });
          }
          
          // Initialize
          updateConfigurator(0);
        });
        
        // Initialize on modal open (once for all components)
        const modalEl = document.getElementById('configuratorModal');
        if (modalEl && !modalEl.dataset.initialized) {
          modalEl.dataset.initialized = 'true';
          modalEl.addEventListener('shown.bs.modal', function() {
            // Initialize all configurators
            Object.keys(configurators).forEach(comp => {
              const config = configurators[comp];
              const updateFunc = window['updateConfigurator_' + comp];
              if (updateFunc) {
                updateFunc(0, 0);
              }
            });
            updateFullLampPreview();
          });
        }
      }
      
      // Product availability check
      const isProductAvailable = <?= $isAvailable ? 'true' : 'false' ?>;
      const availabilityMsg = <?= json_encode($availabilityMessage) ?>;
      
      document.getElementById('addBtn')?.addEventListener('click',()=>{
        // Check if product is available
        if (!isProductAvailable) {
          alert(availabilityMsg || 'Produkt není momentálně dostupný.');
          return;
        }
        
        const q=parseInt(document.getElementById('qty').value||'1');
        const variantSelections = {};
        const variantPrices = {};
        // collect radio selections with prices
        document.querySelectorAll('.variant-select:checked').forEach(r=>{ 
          variantSelections[r.dataset.variant] = r.value;
          variantPrices[r.dataset.variant] = parseFloat(r.dataset.price) || 0;
        });
        // Simple validation: require color if colors exist and not selected (only for available colors)
        const colorExists = document.querySelector('#colorGroup');
        const availableColors = document.querySelectorAll('#colorGroup .color-swatch:not(.unavailable):not(.variant-blocked)');
        const noColorRequired = <?= !empty($product['no_color_required']) ? 'true' : 'false' ?>;
        if (!noColorRequired && colorExists && availableColors.length > 0 && !selectedColor) {
          alert('Vyberte prosím barvu.');
          return;
        }
        
        // NAČTI componentColors z obou zdrojů - nejprve z vybraných swatches
        const componentColorsFromSwatches = {};
        document.querySelectorAll('.component-color.selected').forEach(el => {
          const component = el.dataset.component || '';
          const color = el.dataset.color || '';
          if (component && color) {
            componentColorsFromSwatches[component] = color;
          }
        });
        
        // Pak naplň z hidden inputs (konfigurátor)
        const componentColorsFromConfigurator = {};
        if (window.componentVariants) {
          Object.keys(window.componentVariants).forEach(component => {
            const hiddenColor = document.getElementById(`selected_${component}_color`);
            if (hiddenColor && hiddenColor.value && hiddenColor.value.trim()) {
              const selectedColor = hiddenColor.value.trim();
              // Mapování komponentů na jejich možné názvy
              const componentToNames = {
                'vrsek': ['Vršek', 'Spodní část'],
                'nozicky': ['Nožičky', 'Horní část']
              };
              
              if (componentToNames[component]) {
                componentToNames[component].forEach(name => {
                  componentColorsFromConfigurator[name] = selectedColor;
                });
              }
            }
          });
        }
        
        // Sjednoť componentColors - konfigurátor má prioritu, pak swatches
        Object.keys(componentColors).forEach(key => delete componentColors[key]);
        Object.assign(componentColors, componentColorsFromSwatches);
        Object.assign(componentColors, componentColorsFromConfigurator);
        
        // Get selected component image variants FIRST (before validation)
        const componentImageSelections = {};
        if (window.componentVariants) {
          Object.keys(window.componentVariants).forEach(component => {
            const hiddenIndex = document.getElementById(`selected_${component}`);
            const hiddenName = document.getElementById(`selected_${component}_name`);
            const hiddenColor = document.getElementById(`selected_${component}_color`);
            const hiddenColorImage = document.getElementById(`selected_${component}_color_image`);
            
            // Zkontroluj, zda existují hidden inputs
            if (hiddenIndex) {
              const index = parseInt(hiddenIndex.value) || 0;
              const variant = window.componentVariants[component] && window.componentVariants[component][index] ? window.componentVariants[component][index] : null;
              
              if (variant) {
                const selectedColor = hiddenColor ? hiddenColor.value.trim() : '';
                componentImageSelections[component] = {
                  index: index,
                  name: (hiddenName ? hiddenName.value : '') || variant.name || '',
                  image: variant.image || '',
                  color: selectedColor,
                  colorImage: hiddenColorImage ? hiddenColorImage.value.trim() : ''
                };
              } else if (hiddenColor && hiddenColor.value.trim()) {
                // Pokud není varianta, ale je vybraná barva, použij ji
                const selectedColor = hiddenColor.value.trim();
                componentImageSelections[component] = {
                  index: index,
                  name: hiddenName ? hiddenName.value : '',
                  image: '',
                  color: selectedColor,
                  colorImage: hiddenColorImage ? hiddenColorImage.value.trim() : ''
                };
              }
            }
          });
        }
        
        console.log('Component image selections before validation:', componentImageSelections);
        console.log('Component colors before validation (from swatches):', componentColorsFromSwatches);
        console.log('Component colors before validation (from configurator):', componentColorsFromConfigurator);
        console.log('Component colors before validation (final):', componentColors);
        
        // Validace barevných komponentů - kontroluj podle aktuálního způsobu výběru
        const requiredComponents = document.querySelectorAll('.component-color[data-required="1"]');
        const componentNames = new Set();
        requiredComponents.forEach(el => componentNames.add(el.dataset.component));
        
        // Mapování názvů komponent (různé varianty názvů)
        const componentNameMap = {
          'Vršek': 'vrsek',
          'Nožičky': 'nozicky',
          'Horní část': 'nozicky',
          'Spodní část': 'vrsek',
          'vrsek': 'vrsek',
          'nozicky': 'nozicky'
        };
        
        // Reverzní mapování pro kontrolu componentColors
        const reverseComponentMap = {
          'vrsek': ['Vršek', 'Spodní část'],
          'nozicky': ['Nožičky', 'Horní část']
        };
        
        // Validace podle aktuálního způsobu výběru
        if (currentSelectionMode === 'swatches') {
          // Validace pro výběr z barev
          for (const compName of componentNames) {
            let hasColorInSwatches = componentColors[compName];
            const compKey = componentNameMap[compName] || compName.toLowerCase();
            
            if (!hasColorInSwatches && compKey in reverseComponentMap) {
              for (const possibleName of reverseComponentMap[compKey]) {
                if (componentColors[possibleName]) {
                  hasColorInSwatches = true;
                  break;
                }
              }
            }
            
            if (!hasColorInSwatches) {
              alert(`Vyberte prosím barvu pro: ${compName}`);
              return;
            }
          }
        } else {
          // Validace pro výběr z konfigurátoru
          for (const compName of componentNames) {
            const compKey = componentNameMap[compName] || compName.toLowerCase();
            
            // Zkontroluj hidden inputs přímo - toto je hlavní kontrola
            const hiddenColor = document.getElementById(`selected_${compKey}_color`);
            const hasColorInHiddenInput = hiddenColor && hiddenColor.value && hiddenColor.value.trim() !== '';
            
            // Zkontroluj componentImageSelections (fallback)
            const hasColorInConfigurator = componentImageSelections[compKey] && componentImageSelections[compKey].color && componentImageSelections[compKey].color.trim() !== '';
            
            // Zkontroluj také modal hidden inputs jako poslední možnost
            const modalColor = document.getElementById(`selected_${compKey}_color_modal`);
            const hasColorInModal = modalColor && modalColor.value && modalColor.value.trim() !== '';
            
            console.log(`Validating ${compName} (key: ${compKey}):`, {
              hasColorInHiddenInput,
              hasColorInConfigurator,
              hasColorInModal,
              hiddenValue: hiddenColor ? hiddenColor.value : 'N/A',
              modalValue: modalColor ? modalColor.value : 'N/A',
              componentImageValue: componentImageSelections[compKey] ? componentImageSelections[compKey].color : 'N/A'
            });
            
            if (!hasColorInHiddenInput && !hasColorInConfigurator && !hasColorInModal) {
              alert(`Vyberte prosím barvu pro: ${compName} v konfigurátoru`);
              return;
            }
          }
        }
        
        // Require selections for all variant groups (at least one checked per group)
        let missing = false;
        const groups = new Set();
        document.querySelectorAll('.variant-select').forEach(r=>groups.add(r.dataset.variant));
        groups.forEach(g=>{ if(!document.querySelector('.variant-select[data-variant="'+g+'"]:checked')) missing = true; });
        if (missing) { alert('Vyberte prosím všechny varianty.'); return; }
        
        const payload = { 
          product_id: <?= (int)$id ?>, 
          quantity: q, 
          color: selectedColor, 
          variants: variantSelections, 
          variant_prices: variantPrices,
          component_colors: componentColors,
          component_images: componentImageSelections,

        };
        console.log('Sending to cart:', payload);
        
        // Try JSON first, fallback to FormData
        fetch('add-to-cart.php',{ 
          method:'POST', 
          headers:{'Content-Type':'application/json'}, 
          body: JSON.stringify(payload) 
        })
          .then(r=>{
            console.log('Response status:', r.status);
            return r.text();
          })
          .then(text=>{
            console.log('Raw response:', text);
            try {
              const d = JSON.parse(text);
              console.log('Cart response:', d);
              if(d.success) {
                alert('Přidáno do košíku');
                // Update cart count in navbar if exists
                const cartBadge = document.querySelector('.cart-count');
                if(cartBadge && d.cart_count) cartBadge.textContent = d.cart_count;
              } else {
                alert(d.message||'Chyba při přidávání do košíku');
              }
            } catch(e) {
              console.error('JSON parse error:', e);
              alert('Chyba: ' + text.substring(0, 100));
            }
          })
          .catch(err=>{
            console.error('Cart error:', err);
            alert('Chyba při přidávání do košíku: ' + err.message);
          })
      });
      
      // Sale Countdown Logic
       setInterval(function() {
           const now = new Date().getTime();
           
           // Active sale countdown (ends in)
           const endEl = document.getElementById('saleCountdown');
           if (endEl && endEl.dataset.end) {
               const diff = new Date(endEl.dataset.end).getTime() - now;
               if (diff > 0) {
                   const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                   const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                   const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                   const s = Math.floor((diff % (1000 * 60)) / 1000);
                   endEl.textContent = (d>0 ? d + 'd ' : '') + 
                       (h<10 ? '0'+h : h) + ':' + 
                       (m<10 ? '0'+m : m) + ':' + 
                       (s<10 ? '0'+s : s);
               } else {
                   endEl.textContent = '00:00:00';
               }
           }
           
           // Upcoming sale countdown (starts in)
           const startEl = document.getElementById('upcomingSaleCountdown');
           if (startEl && startEl.dataset.end) {
               const diff = new Date(startEl.dataset.end).getTime() - now;
               if (diff > 0) {
                   const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                   const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                   const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                   const s = Math.floor((diff % (1000 * 60)) / 1000);
                   startEl.textContent = (d>0 ? d + 'd ' : '') + 
                       (h<10 ? '0'+h : h) + ':' + 
                       (m<10 ? '0'+m : m) + ':' + 
                       (s<10 ? '0'+s : s);
               } else {
                   startEl.textContent = '00:00:00';
                   // Reload page when sale starts to show sale prices
                   setTimeout(() => location.reload(), 1000);
               }
           }
       }, 1000);
    </script>

  </body>
</html>