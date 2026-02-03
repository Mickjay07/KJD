<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Language handling (cs/sk/en)
$supportedLangs = ['cs', 'sk', 'en'];
if (isset($_GET['lang'])) {
    $reqLang = strtolower(trim($_GET['lang']));
    if (in_array($reqLang, $supportedLangs, true)) {
        $_SESSION['lang'] = $reqLang;
    }
}
$lang = $_SESSION['lang'] ?? 'cs';

// Basic translations
$translations = [
    'cs' => [
        'collections_title' => 'Kolekce',
        'collections_subtitle' => 'Prohlédněte si naše produkty podle kolekcí',
        'view_collection' => 'Zobrazit kolekci',
        'no_collections' => 'Aktuálně nejsou k dispozici žádné kolekce.',
        'search_placeholder' => 'Hledat kolekce...',
        'all_collections' => 'Všechny kolekce'
    ],
    'sk' => [
        'collections_title' => 'Kolekcie',
        'collections_subtitle' => 'Prehliadnite si naše produkty podľa kolekcií',
        'view_collection' => 'Zobraziť kolekciu',
        'no_collections' => 'Aktuálne nie sú k dispozícii žiadne kolekcie.',
        'search_placeholder' => 'Hľadať kolekcie...',
        'all_collections' => 'Všetky kolekcie'
    ],
    'en' => [
        'collections_title' => 'Collections',
        'collections_subtitle' => 'Browse our products by collections',
        'view_collection' => 'View collection',
        'no_collections' => 'No collections available at the moment.',
        'search_placeholder' => 'Search collections...',
        'all_collections' => 'All collections'
    ],
];

function t(string $key): string {
    global $translations, $lang;
    return $translations[$lang][$key] ?? ($translations['cs'][$key] ?? $key);
}

// DB connection
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
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Helper function to get product image
function getProductImageSrc(array $product): string {
  $images = [];
  if (!empty($product['image_url'])) {
    $images = explode(',', $product['image_url']);
  }
  $firstImage = trim($images[0] ?? '');
  if ($firstImage === '') {
    return 'images/product-thumb-11.jpg';
  }
  
  // Absolute URL already
  if (preg_match('~^https?://~i', $firstImage)) {
    return $firstImage;
  }
  
  // Paths that already start with uploads/... stored in DB
  if (strpos($firstImage, 'uploads/') === 0 || strpos($firstImage, '/uploads/') === 0) {
    $normalized = ltrim($firstImage, '/');
    $webPath = '../' . $normalized;
    return $webPath;
  }
  
  // Otherwise treat DB value as a filename (e.g., 6.JPG) stored under uploads/products/
  $webPath = '../uploads/products/' . $firstImage;
  $fsPath = __DIR__ . '/../uploads/products/' . $firstImage;
  if (file_exists($fsPath)) {
    return $webPath;
  }
  
  return 'images/product-thumb-11.jpg';
}

// Search/filter handling
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Collection parameter handling
$activeCollectionId = 0;
$activeCollectionName = '';
$activeCollectionSlug = '';
$collectionParam = isset($_GET['collection']) ? trim((string)$_GET['collection']) : '';

// Load active collections
$collections = [];
try {
    if ($searchQuery !== '') {
        $stmt = $conn->prepare("SELECT id, name, slug, description, image_url, icon_url FROM product_collections_main WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?) ORDER BY name ASC");
        $searchTerm = '%' . $searchQuery . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
  $stmt = $conn->query("SELECT id, name, slug, description, image_url, icon_url FROM product_collections_main WHERE is_active = 1 ORDER BY name ASC");
    }
  $collections = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Resolve active collection
    if ($collectionParam !== '') {
        if (ctype_digit($collectionParam)) {
            $activeCollectionId = (int)$collectionParam;
            foreach ($collections as $col) {
                if ((int)$col['id'] === $activeCollectionId) {
                    $activeCollectionName = $col['name'];
                    $activeCollectionSlug = (string)($col['slug'] ?? '');
                    break;
                }
            }
        } else {
            $activeCollectionSlug = $collectionParam;
            foreach ($collections as $col) {
                if (!empty($col['slug']) && strtolower($col['slug']) === strtolower($activeCollectionSlug)) {
                    $activeCollectionId = (int)$col['id'];
                    $activeCollectionName = $col['name'];
                    break;
                }
            }
            if ($activeCollectionId === 0) {
                $s = $conn->prepare("SELECT id, name, slug FROM product_collections_main WHERE slug = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
                $s->execute([$collectionParam]);
                if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                    $activeCollectionId = (int)$row['id'];
                    $activeCollectionName = (string)$row['name'];
                    $activeCollectionSlug = (string)($row['slug'] ?? '');
                }
            }
        }
    }
} catch (PDOException $e) {
  $collections = [];
}

// Get product count for each collection
foreach ($collections as &$col) {
    try {
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM product_collection_items WHERE collection_id = ?");
        $countStmt->execute([$col['id']]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $col['product_count'] = (int)($countResult['count'] ?? 0);
    } catch (PDOException $e) {
        $col['product_count'] = 0;
    }
}
unset($col);

// Load products for active collection
$collectionProducts = [];
if ($activeCollectionId > 0) {
    try {
        $stmt = $conn->prepare(
            "SELECT DISTINCT p.* 
             FROM product p
             JOIN product_collection_items pci ON p.id = pci.product_id
             WHERE pci.collection_id = ? AND (p.is_hidden IS NULL OR p.is_hidden = 0)
             ORDER BY pci.position ASC, p.id DESC"
        );
        $stmt->execute([$activeCollectionId]);
        $collectionProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $collectionProducts = [];
    }
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>
<!DOCTYPE html>
<html lang="cs">
  <head>
    <title>Kolekce – KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="author" content="">
    <meta name="keywords" content="">
    <meta name="description" content="">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="fonts/sf-pro.css">

    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      body {
        background: #f8f9fa;
        color: var(--kjd-dark-green);
      }
      
      /* Header section */
      .collections-header {
        background: transparent;
        padding: 3rem 0;
        border-bottom: none;
        box-shadow: none;
        margin-bottom: 2rem;
      }
      
      .collections-header h1 {
        color: var(--kjd-dark-green);
        font-weight: 800;
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
      }
      
      .collections-header p {
        color: var(--kjd-gold-brown);
        font-weight: 600;
        font-size: 1.1rem;
        margin: 0;
      }
      
      /* Search bar */
      .search-section {
        margin-bottom: 3rem;
      }
      
      .search-input-wrapper {
        position: relative;
        max-width: 600px;
        margin: 0 auto;
      }
      
      .search-input-wrapper input {
        width: 100%;
        padding: 1rem 1rem 1rem 3rem;
        border: 2px solid var(--kjd-earth-green);
        border-radius: 12px;
        font-size: 1rem;
        background: #fff;
        color: var(--kjd-dark-green);
        transition: all 0.3s ease;
      }
      
      .search-input-wrapper input:focus {
        outline: none;
        border-color: var(--kjd-dark-green);
        box-shadow: 0 0 0 3px rgba(16, 40, 32, 0.1);
      }
      
      .search-input-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--kjd-earth-green);
        font-size: 1.1rem;
      }
      
      /* Collection cards */
      .collection-grid {
        gap: 2rem;
        --bs-gutter-x: 2rem;
        --bs-gutter-y: 2rem;
        justify-content: center;
      }
      
      .collection-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        border: 2px solid var(--kjd-earth-green);
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
      }
      
      .collection-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.15);
        border-color: var(--kjd-dark-green);
      }
      
      .collection-card-image {
        width: 100%;
        height: 280px;
        object-fit: cover;
        border-bottom: 2px solid var(--kjd-earth-green);
      }
      
      .collection-card-content {
        padding: 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
      }
      
      .collection-card-title {
        color: var(--kjd-dark-green);
        font-weight: 800;
        font-size: 1.4rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .collection-card-title i {
        color: var(--kjd-earth-green);
        font-size: 1.2rem;
      }
      
      .collection-card-description {
        color: var(--kjd-gold-brown);
        font-weight: 600;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 1rem;
        flex: 1;
      }
      
      .collection-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid rgba(76, 100, 68, 0.2);
      }
      
      .collection-product-count {
        color: var(--kjd-earth-green);
        font-weight: 700;
        font-size: 0.9rem;
      }
      
      .btn-view-collection {
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
        color: #fff;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(77,45,24,0.3);
      }
      
      .btn-view-collection:hover {
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(77,45,24,0.4);
      }
      
      /* Empty state */
      .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: #fff;
        border-radius: 16px;
        border: 2px solid var(--kjd-earth-green);
      }
      
      .empty-state i {
        font-size: 4rem;
        color: var(--kjd-beige);
        margin-bottom: 1.5rem;
      }
      
      .empty-state h3 {
        color: var(--kjd-dark-green);
        font-weight: 800;
        margin-bottom: 1rem;
      }
      
      .empty-state p {
        color: var(--kjd-gold-brown);
        font-weight: 600;
        font-size: 1.1rem;
      }
      
      /* Mobile responsive */
      @media (max-width: 768px) {
        /* Header - mobilní */
        .collections-header {
          padding: 1.5rem 0 !important;
          margin-bottom: 1.5rem !important;
        }
        
        .collections-header .d-flex {
          flex-direction: column !important;
          gap: 1rem !important;
          align-items: stretch !important;
        }
        
        .collections-header .text-center {
          text-align: center !important;
          margin-bottom: 0.5rem;
        }
        
        .collections-header h1 {
          font-size: 1.75rem !important;
          margin-bottom: 0.5rem !important;
        }
        
        .collections-header p {
          font-size: 0.95rem !important;
        }
        
        .btn-back-to-collections {
          width: 100% !important;
          justify-content: center !important;
          padding: 0.875rem 1.25rem !important;
          font-size: 0.95rem !important;
        }
        
        /* Search - mobilní */
        .search-section {
          margin-bottom: 1.5rem !important;
          padding: 0 1rem;
        }
        
        .search-input-wrapper {
          max-width: 100% !important;
        }
        
        .search-input-wrapper input {
          font-size: 1rem !important;
          padding: 0.875rem 1rem 0.875rem 3rem !important;
        }
        
        .search-input-wrapper i {
          left: 1rem !important;
          font-size: 1.1rem !important;
        }
        
        /* Collection grid - mobilní */
        .collection-grid {
          gap: 1.25rem !important;
          --bs-gutter-x: 1.25rem !important;
          --bs-gutter-y: 1.25rem !important;
          padding: 0 1rem;
        }
        
        .collection-card {
          border-radius: 12px !important;
          overflow: hidden;
        }
        
        .collection-card-image {
          height: 200px !important;
        }
        
        .collection-card-content {
          padding: 1rem !important;
        }
        
        .collection-card-title {
          font-size: 1.1rem !important;
          margin-bottom: 0.5rem !important;
        }
        
        .collection-card-description {
          font-size: 0.9rem !important;
          line-height: 1.5 !important;
          margin-bottom: 1rem !important;
        }
        
        .collection-card-footer {
          flex-direction: column !important;
          gap: 0.75rem !important;
          align-items: stretch !important;
        }
        
        .collection-product-count {
          font-size: 0.9rem !important;
          text-align: center !important;
        }
        
        .btn-view-collection {
          width: 100% !important;
          text-align: center !important;
          padding: 0.75rem 1rem !important;
          font-size: 0.95rem !important;
        }
        
        /* Products section - mobilní */
        .products-section {
          margin-top: 1.5rem !important;
          padding-top: 1.5rem !important;
          border-top: 2px solid var(--kjd-earth-green) !important;
        }
        
        .products-section .collections-header {
          padding: 1.25rem 0 !important;
          margin-bottom: 1.25rem !important;
        }
        
        .products-section .collections-header h1 {
          font-size: 1.5rem !important;
        }
        
        .products-section .collections-header p {
          font-size: 0.9rem !important;
        }
        
        .product-grid {
          gap: 1rem !important;
          --bs-gutter-x: 1rem !important;
          --bs-gutter-y: 1rem !important;
          padding: 0 1rem;
        }
        
        .product-grid.row-cols-2 > * {
          flex: 0 0 auto !important;
          width: calc(50% - 0.5rem) !important;
          max-width: calc(50% - 0.5rem) !important;
        }
        
        .product-item {
          padding: 1rem !important;
          border-radius: 12px !important;
        }
        
        .product-item .tab-image {
          height: 200px !important;
          border-radius: 10px !important;
        }
        
        .product-item h3 {
          font-size: 1rem !important;
          margin: 0.5rem 0 !important;
          line-height: 1.3 !important;
        }
        
        .product-item .price {
          font-size: 1.1rem !important;
          margin: 0.5rem 0 !important;
        }
        
        .btn-kjd-primary {
          padding: 0.75rem 1rem !important;
          font-size: 0.9rem !important;
          width: 100% !important;
        }
        
        .share-buttons-compact {
          gap: 0.4rem !important;
          margin-top: 0.5rem !important;
        }
        
        .share-btn-compact {
          width: 28px !important;
          height: 28px !important;
          font-size: 0.8rem !important;
        }
        
        .btn-wishlist {
          width: 36px !important;
          height: 36px !important;
          top: 8px !important;
          right: 8px !important;
        }
        
        .product-item .badge {
          font-size: 0.75rem !important;
          padding: 0.3rem 0.6rem !important;
          top: 8px !important;
          left: 8px !important;
        }
        
        /* Empty state - mobilní */
        .empty-state {
          padding: 2rem 1rem !important;
        }
        
        .empty-state i {
          font-size: 3rem !important;
        }
        
        .empty-state h3 {
          font-size: 1.25rem !important;
        }
        
        .empty-state p {
          font-size: 0.95rem !important;
        }
        
        /* Container padding - mobilní */
        .container-fluid {
          padding-left: 0.5rem !important;
          padding-right: 0.5rem !important;
        }
      }
      
      @media (max-width: 576px) {
        /* Extra malé obrazovky */
        .collections-header {
          padding: 1.25rem 0 !important;
        }
        
        .collections-header h1 {
          font-size: 1.5rem !important;
        }
        
        .collections-header p {
          font-size: 0.85rem !important;
        }
        
        .btn-back-to-collections {
          padding: 0.75rem 1rem !important;
          font-size: 0.9rem !important;
        }
        
        .search-input-wrapper input {
          font-size: 0.95rem !important;
          padding: 0.75rem 0.875rem 0.75rem 2.75rem !important;
        }
        
        .collection-grid {
          gap: 1rem !important;
          --bs-gutter-x: 1rem !important;
          --bs-gutter-y: 1rem !important;
        }
        
        .collection-card-image {
          height: 180px !important;
        }
        
        .collection-card-content {
          padding: 0.875rem !important;
        }
        
        .collection-card-title {
          font-size: 1rem !important;
        }
        
        .collection-card-description {
          font-size: 0.85rem !important;
        }
        
        .collection-product-count {
          font-size: 0.85rem !important;
        }
        
        .btn-view-collection {
          padding: 0.7rem 0.875rem !important;
          font-size: 0.9rem !important;
        }
        
        .products-section .collections-header h1 {
          font-size: 1.35rem !important;
        }
        
        .product-grid {
          gap: 0.875rem !important;
          --bs-gutter-x: 0.875rem !important;
          --bs-gutter-y: 0.875rem !important;
        }
        
        .product-grid.row-cols-2 > * {
          width: calc(50% - 0.4375rem) !important;
          max-width: calc(50% - 0.4375rem) !important;
        }
        
        .product-item {
          padding: 0.875rem !important;
        }
        
        .product-item .tab-image {
          height: 180px !important;
        }
        
        .product-item h3 {
          font-size: 0.95rem !important;
        }
        
        .product-item .price {
          font-size: 1rem !important;
        }
        
        .btn-kjd-primary {
          padding: 0.7rem 0.875rem !important;
          font-size: 0.85rem !important;
        }
        
        .share-btn-compact {
          width: 26px !important;
          height: 26px !important;
          font-size: 0.75rem !important;
        }
      }
      
      @media (max-width: 480px) {
        /* Velmi malé obrazovky */
        .collections-header h1 {
          font-size: 1.35rem !important;
        }
        
        .collection-card-image {
          height: 160px !important;
        }
        
        .product-item .tab-image {
          height: 160px !important;
        }
        
        .product-grid {
          row-gap: 0.75rem !important;
          --bs-gutter-y: 0.75rem !important;
          column-gap: 0.75rem !important;
          --bs-gutter-x: 0.75rem !important;
        }
        
        .product-grid.row-cols-2 > * {
          width: calc(50% - 0.375rem) !important;
          max-width: calc(50% - 0.375rem) !important;
        }
        
        .empty-state {
          padding: 1.5rem 0.75rem !important;
        }
        
        .empty-state i {
          font-size: 2.5rem !important;
        }
        
        .empty-state h3 {
          font-size: 1.1rem !important;
        }
      }
    
    /* Products section with animation */
    .products-section {
      margin-top: 3rem;
      padding-top: 3rem;
      border-top: 3px solid var(--kjd-earth-green);
    }
    
    .products-section.hidden {
      display: none;
    }
    
    .products-section.animate-in {
      animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .product-grid {
      gap: 2rem;
      --bs-gutter-x: 2rem;
      --bs-gutter-y: 2rem;
      justify-content: center;
    }
    
    .product-item {
      background: #fff;
      border-radius: 16px;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(16,40,32,0.08);
      border: 2px solid var(--kjd-earth-green);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      height: 100%;
      position: relative;
      opacity: 0;
      animation: fadeInScale 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
    
    .product-item:nth-child(1) { animation-delay: 0.1s; }
    .product-item:nth-child(2) { animation-delay: 0.2s; }
    .product-item:nth-child(3) { animation-delay: 0.3s; }
    .product-item:nth-child(4) { animation-delay: 0.4s; }
    .product-item:nth-child(5) { animation-delay: 0.5s; }
    .product-item:nth-child(6) { animation-delay: 0.6s; }
    .product-item:nth-child(n+7) { animation-delay: 0.7s; }
    
    @keyframes fadeInScale {
      from {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }
    
    .product-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      border-color: transparent;
    }
    
    .product-item .tab-image {
      width: 100%;
      height: 260px;
      object-fit: cover;
      border-radius: 12px;
      border: 2px solid var(--kjd-beige);
      transition: all 0.2s ease;
    }
    
    .product-item .tab-image:hover {
      border-color: var(--kjd-earth-green);
      transform: scale(1.02);
    }
    
    .product-item h3 {
      color: var(--kjd-dark-green);
      font-weight: 600;
      font-size: 1.1rem;
      margin: 0.75rem 0 0.5rem;
      line-height: 1.3;
    }
    
    .product-item .price {
      color: var(--kjd-gold-brown);
      font-weight: 700;
      font-size: 1.2rem;
      margin: 0.5rem 0;
    }
    
    .btn-kjd-primary {
      background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
      color: #fff;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 10px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(77,45,24,0.3);
    }
    
    .btn-kjd-primary:hover {
      background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
      color: #fff;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(77,45,24,0.4);
    }
    
    .btn-back-to-collections {
      background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
      color: var(--kjd-dark-green);
      border: 2px solid var(--kjd-earth-green);
      padding: 0.75rem 1.5rem;
      border-radius: 12px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 12px rgba(16,40,32,0.15);
      position: relative;
      overflow: hidden;
      animation: fadeInLeft 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .btn-back-to-collections::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
      transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 0;
    }
    
    .btn-back-to-collections i,
    .btn-back-to-collections span {
      position: relative;
      z-index: 1;
      transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .btn-back-to-collections:hover {
      color: #fff;
      transform: translateX(-8px) scale(1.02);
      box-shadow: 0 6px 20px rgba(16,40,32,0.25);
      border-color: var(--kjd-dark-green);
    }
    
    .btn-back-to-collections:hover::before {
      left: 0;
    }
    
    .btn-back-to-collections:hover i {
      transform: translateX(-3px);
    }
    
    .btn-back-to-collections:active {
      transform: translateX(-6px) scale(0.98);
      box-shadow: 0 3px 10px rgba(16,40,32,0.2);
    }
    
    @keyframes fadeInLeft {
      from {
        opacity: 0;
        transform: translateX(-20px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    .share-buttons-compact {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      align-items: center;
      margin-top: 0.5rem;
    }
    
    .share-btn-compact {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 1.5px solid var(--kjd-earth-green);
      background: #fff;
      color: var(--kjd-dark-green);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
      text-decoration: none;
      font-size: 0.9rem;
      cursor: pointer;
    }
    
    .share-btn-compact:hover {
      transform: scale(1.1);
      border-width: 2px;
    }
    
    .share-btn-compact.facebook:hover { background: #1877f2; border-color: #1877f2; color: #fff; }
    .share-btn-compact.twitter:hover { background: #1da1f2; border-color: #1da1f2; color: #fff; }
    .share-btn-compact.whatsapp:hover { background: #25d366; border-color: #25d366; color: #fff; }
    .share-btn-compact.email:hover { background: var(--kjd-gold-brown); border-color: var(--kjd-gold-brown); color: #fff; }
    .share-btn-compact.copy-link:hover { background: var(--kjd-earth-green); border-color: var(--kjd-earth-green); color: #fff; }
    
    .btn-wishlist {
      position: absolute;
      top: 10px;
      right: 10px;
      width: 40px;
      height: 40px;
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid var(--kjd-earth-green);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 11;
      transition: all 0.3s ease;
    }
    
    .btn-wishlist:hover {
      background: var(--kjd-earth-green);
      color: #fff;
    }
    </style>
  </head>
  <body>
    <?php include 'includes/icons.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Header (hidden when collection is selected) -->
    <?php if ($activeCollectionId === 0): ?>
    <section class="collections-header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
              <div class="text-center" style="flex: 1;">
                <h1><?= t('collections_title') ?></h1>
                <p><?= t('collections_subtitle') ?></p>
              </div>
              <a href="index.php" class="btn-back-to-collections">
                <i class="fas fa-arrow-left"></i>
                <span>Zpět na hlavní stránku</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Search Section (hidden when collection is selected) -->
    <?php if ($activeCollectionId === 0): ?>
    <section class="search-section">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <form method="GET" action="collections.php" class="search-input-wrapper">
              <i class="fas fa-search"></i>
              <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="<?= t('search_placeholder') ?>" autocomplete="off">
            </form>
          </div>
      </div>
    </div>
    </section>
    <?php endif; ?>

    <!-- Collections Grid (hidden when collection is selected) -->
    <?php if ($activeCollectionId === 0): ?>
    <section class="py-3">
    <div class="container-fluid">
      <?php if (!empty($collections)): ?>
          <div class="collection-grid row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4">
          <?php foreach ($collections as $col): 
            $name = htmlspecialchars($col['name'] ?? '');
            $slug = $col['slug'] ?? '';
              $cid  = (int)($col['id'] ?? 0);
              $description = htmlspecialchars($col['description'] ?? '');
            $img  = trim((string)($col['image_url'] ?? ''));
              $productCount = (int)($col['product_count'] ?? 0);
              $href = 'collections.php?collection=' . ($slug !== '' ? urlencode($slug) : urlencode((string)$cid));
            if ($img === '') { $img = 'images/product-thumb-11.jpg'; }
          ?>
              <div class="col">
                <div class="collection-card">
                  <a href="<?= $href ?>" style="text-decoration: none; display: block;">
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= $name ?>" class="collection-card-image" onerror="this.src='images/product-thumb-11.jpg'">
                  </a>
                  <div class="collection-card-content">
                    <h3 class="collection-card-title">
                      <i class="fas fa-layer-group"></i>
                      <?= $name ?>
                    </h3>
                    <?php if ($description !== ''): ?>
                      <p class="collection-card-description"><?= nl2br($description) ?></p>
                <?php endif; ?>
                    <div class="collection-card-footer">
                      <span class="collection-product-count">
                        <i class="fas fa-box me-1"></i><?= $productCount ?> <?= $productCount === 1 ? 'produkt' : ($productCount < 5 ? 'produkty' : 'produktů') ?>
                      </span>
                      <a href="<?= $href ?>" class="btn-view-collection">
                        <i class="fas fa-eye me-1"></i><?= t('view_collection') ?>
                      </a>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3><?= t('no_collections') ?></h3>
            <?php if ($searchQuery !== ''): ?>
              <p>Zkuste změnit vyhledávací dotaz nebo <a href="collections.php" style="color: var(--kjd-earth-green); font-weight: 700;">zobrazit všechny kolekce</a>.</p>
            <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    </section>
    <?php endif; ?>

    <!-- Products Section (shown when collection is selected) -->
    <?php if ($activeCollectionId > 0 && !empty($collectionProducts)): ?>
    <section id="productsSection" class="products-section animate-in">
      <!-- Header for products section -->
      <div class="collections-header" style="margin-bottom: 2rem;">
        <div class="container-fluid">
          <div class="row">
            <div class="col-md-12">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h1 style="color: var(--kjd-dark-green); font-weight: 800; margin-bottom: 0.5rem; font-size: 2.5rem;">
                    <?= htmlspecialchars($activeCollectionName) ?>
                  </h1>
                  <p style="color: var(--kjd-gold-brown); font-weight: 600; margin: 0; font-size: 1.1rem;">
                    <?= count($collectionProducts) ?> <?= count($collectionProducts) === 1 ? 'produkt' : (count($collectionProducts) < 5 ? 'produkty' : 'produktů') ?>
                  </p>
                </div>
                <a href="collections.php<?= $searchQuery !== '' ? '?search=' . urlencode($searchQuery) : '' ?>" class="btn-back-to-collections">
                  <i class="fas fa-arrow-left"></i>
                  <span>Zpět na kolekce</span>
                </a>
              </div>
            </div>
          </div>
        </div>
    </div>
      
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div class="product-grid row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
              <?php foreach ($collectionProducts as $p): ?>
                <?php
                  $img = htmlspecialchars(getProductImageSrc($p));
                  $name = htmlspecialchars($p['name'] ?? 'Product');
                  $price = isset($p['price']) ? (float)$p['price'] : 0;
                  $isPreorder = !empty($p['is_preorder']) && (int)$p['is_preorder'] === 1;
                  $isNew = !empty($p['is_new']) && (int)$p['is_new'] === 1;
                  $saleActive = false; $salePrice = 0;
                  
                  // Timezone fix
                  date_default_timezone_set('Europe/Prague');
                  
                  if (!empty($p['sale_enabled']) && (int)$p['sale_enabled'] === 1) {
                    $sp = isset($p['sale_price']) ? (float)$p['sale_price'] : 0;
                    if ($sp > 0 && $sp < $price) {
                      $now = time();
                      $startTs = !empty($p['sale_start']) ? strtotime($p['sale_start']) : 0;
                      $endTs = !empty($p['sale_end']) ? strtotime($p['sale_end']) : 0;
                      
                      // Check strict logic: 
                      // 1. If start date is set and in future -> Sale is NOT active (it is upcoming)
                      // 2. If start date is past (or not set) -> Check end date
                      
                      $isUpcoming = ($startTs > 0 && $now < $startTs);
                      
                      if (!$isUpcoming) {
                        if ($endTs > 0) {
                          if ($now < $endTs) {
                            $saleActive = true; $salePrice = $sp;
                          }
                        } else { 
                          $saleActive = true; $salePrice = $sp; 
                        }
                      }
                    }
                  }
                ?>
                <div class="col">
                  <div class="product-item position-relative">
                    <?php if ($saleActive): ?>
                      <span class="badge position-absolute m-3" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); color: var(--kjd-dark-brown); border: 2px solid var(--kjd-earth-green); font-weight: 800; z-index: 5;">-<?= max(1, (int)round((1 - ($salePrice / max(0.01, $price))) * 100)) ?>%</span>
                    <?php elseif ($isPreorder): ?>
                      <span class="badge bg-warning position-absolute m-3" style="z-index: 5;">Preorder</span>
                    <?php elseif ($isNew): ?>
                      <span class="badge bg-primary position-absolute m-3" style="z-index: 5;">New</span>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                      <a href="#" class="btn-wishlist" onclick="toggleFavorite(<?= (int)$p['id'] ?>); return false;" 
                         data-product-id="<?= (int)$p['id'] ?>" title="Přidat do oblíbených">
                        <svg width="24" height="24"><use xlink:href="#heart"></use></svg>
                      </a>
                    <?php else: ?>
                      <a href="login.php" class="btn-wishlist" title="Přihlaste se pro přidání do oblíbených">
                        <svg width="24" height="24"><use xlink:href="#heart"></use></svg>
                      </a>
                    <?php endif; ?>
                    <figure>
                      <img src="<?= $img ?>" class="tab-image" alt="<?= $name ?>">
                    </figure>
                    <h3><?= $name ?></h3>
                    <span class="price">
                      <?php if ($saleActive): ?>
                        <span style="text-decoration:line-through;color:#888;margin-right:6px;"><?= number_format($price, 0, ',', ' ') ?> Kč</span>
                        <span style="color:#c62828;font-weight:700;"><?= number_format($salePrice, 0, ',', ' ') ?> Kč</span>
                      <?php else: ?>
                        <?= number_format($price, 0, ',', ' ') ?> Kč
                      <?php endif; ?>
                    </span>
                    <div class="d-flex flex-column align-items-center gap-2 mt-3">
                      <a href="product.php?id=<?= (int)$p['id'] ?>" class="btn btn-kjd-primary">Zobrazit produkt</a>
                      <?php 
                        $productUrl = 'https://kubajadesigns.eu/product.php?id=' . (int)$p['id'];
                        $shareText = urlencode($name . ' - KubaJaDesigns');
                      ?>
                      <div class="share-buttons-compact">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($productUrl) ?>" 
                           target="_blank" class="share-btn-compact facebook" title="Sdílet na Facebooku">
                          <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($productUrl) ?>&text=<?= $shareText ?>" 
                           target="_blank" class="share-btn-compact twitter" title="Sdílet na Twitteru">
                          <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?= urlencode($shareText . ' ' . $productUrl) ?>" 
                           target="_blank" class="share-btn-compact whatsapp" title="Sdílet na WhatsApp">
                          <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="mailto:?subject=<?= urlencode($name) ?>&body=<?= urlencode('Podívej se na tento produkt: ' . $productUrl) ?>" 
                           class="share-btn-compact email" title="Poslat emailem">
                          <i class="fas fa-envelope"></i>
                        </a>
                        <button class="share-btn-compact copy-link" 
                                data-product-url="<?= htmlspecialchars($productUrl) ?>" 
                                title="Kopírovat odkaz">
                          <i class="fas fa-link"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
      // Auto-submit search on input (with debounce)
      let searchTimeout;
      document.querySelector('input[name="search"]')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          if (this.value.length === 0 || this.value.length >= 2) {
            this.form.submit();
          }
        }, 500);
      });
      
      // Smooth scroll to products section when collection is selected
      <?php if ($activeCollectionId > 0 && !empty($collectionProducts)): ?>
      document.addEventListener('DOMContentLoaded', function() {
        const productsSection = document.getElementById('productsSection');
        if (productsSection) {
          setTimeout(() => {
            productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }, 100);
        }
      });
      <?php endif; ?>
      
      // Copy link functionality
      document.addEventListener('click', function(e) {
        if (e.target.closest('.share-btn-compact.copy-link')) {
          e.preventDefault();
          const btn = e.target.closest('.share-btn-compact.copy-link');
          const url = btn.getAttribute('data-product-url');
          
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
              const originalHTML = btn.innerHTML;
              btn.innerHTML = '<i class="fas fa-check"></i>';
              btn.style.background = 'var(--kjd-earth-green)';
              btn.style.borderColor = 'var(--kjd-earth-green)';
              btn.style.color = '#fff';
              
              setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.style.color = '';
              }, 1500);
            }).catch(err => {
              console.error('Failed to copy:', err);
              alert('Odkaz: ' + url);
            });
          } else {
            alert('Odkaz: ' + url);
          }
        }
      });
    </script>
  </body>
</html>
