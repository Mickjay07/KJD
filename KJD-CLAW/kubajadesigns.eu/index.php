<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// Database connection
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

// Language handling (cs/sk/en)
$supportedLangs = ['cs', 'sk', 'en'];
if (isset($_GET['lang'])) {
    $reqLang = strtolower(trim($_GET['lang']));
    if (in_array($reqLang, $supportedLangs, true)) {
        $_SESSION['lang'] = $reqLang;
    }
}
$lang = $_SESSION['lang'] ?? 'cs';

// Basic translations used on this page. Extend as needed.
$translations = [
    'cs' => [
        'all_categories' => 'Zobrazit všechny kategorie →',
        'categories' => 'Kategorie',
        'all' => 'Vše',
        'all_products' => 'Všechny produkty',
        'view_product' => 'Zobrazit produkt',
        'language' => 'Jazyk',
        'lang_cs' => 'Čeština',
        'lang_sk' => 'Slovenčina',
        'lang_en' => 'English',
        'cart_title' => 'Váš košík',
        'cart_button' => 'Košík',
        'continue_shopping' => 'Pokračovat v nákupu',
        'cart_empty' => 'Košík je prázdný',
        'cart_empty_hint' => 'Přidejte si nějaké produkty',
        'shop_now' => 'Nakupovat',
        'buy' => 'Koupit',
        'sale_20' => '20% sleva',
        'sale_15' => '15% sleva',
        'spring_pots' => 'Jarní kolekce květináčů',
        'summer_selection' => 'Letní výběr',
        'reviews_none' => 'Zatím žádné recenze. Buďte první!',
        'reviews_photo_alt' => 'Foto recenze',
        'reviews_title' => 'Naše hodnocení',
        'hero_title' => 'Lampa- STRATA',
        'hero_subtitle' => 'Lampa která je inspirovaná vlny !',
        'all_categories_select' => 'Všechny kategorie',
        'search_placeholder' => 'Hledat produkty...'
    ],
    'sk' => [
        'all_categories' => 'Zobraziť všetky kategórie →',
        'categories' => 'Kategórie',
        'all' => 'Všetko',
        'all_products' => 'Všetky produkty',
        'view_product' => 'Zobraziť produkt',
        'language' => 'Jazyk',
        'lang_cs' => 'Čeština',
        'lang_sk' => 'Slovenčina',
        'lang_en' => 'English',
        'cart_title' => 'Váš košík',
        'cart_button' => 'Košík',
        'continue_shopping' => 'Pokračovať v nákupe',
        'cart_empty' => 'Košík je prázdny',
        'cart_empty_hint' => 'Pridajte si nejaké produkty',
        'shop_now' => 'Nakupovať',
        'buy' => 'Kúpiť',
        'sale_20' => '20% zľava',
        'sale_15' => '15% zľava',
        'spring_pots' => 'Jarná kolekcia kvetináčov',
        'summer_selection' => 'Letný výber',
        'reviews_none' => 'Zatiaľ žiadne recenzie. Buďte prvý!',
        'reviews_photo_alt' => 'Foto recenzie',
        'reviews_title' => 'Naše hodnotenie',
        'hero_title' => 'Lampa- STRATA',
        'hero_subtitle' => 'Lampa, ktorá je inšpirovaná vlny!',
        'all_categories_select' => 'Všetky kategórie',
        'search_placeholder' => 'Hľadať produkty...'
    ],
    'en' => [
        'all_categories' => 'View all categories →',
        'categories' => 'Categories',
        'all' => 'All',
        'all_products' => 'All products',
        'view_product' => 'View product',
        'language' => 'Language',
        'lang_cs' => 'Czech',
        'lang_sk' => 'Slovak',
        'lang_en' => 'English',
        'cart_title' => 'Your cart',
        'cart_button' => 'Cart',
        'continue_shopping' => 'Continue shopping',
        'cart_empty' => 'Cart is empty',
        'cart_empty_hint' => 'Add some products',
        'shop_now' => 'Shop now',
        'buy' => 'Buy',
        'sale_20' => '20% off',
        'sale_15' => '15% off',
        'spring_pots' => 'Spring pots collection',
        'summer_selection' => 'Summer selection',
        'reviews_none' => 'No reviews yet. Be the first!',
        'reviews_photo_alt' => 'Review photo',
        'reviews_title' => 'Our Reviews',
        'hero_title' => 'Lamp - WAVEA',
        'hero_subtitle' => 'A lamp inspired by the forest!',
        'all_categories_select' => 'All categories',
        'search_placeholder' => 'Search products...'
    ],
];

function t(string $key): string {
    global $translations, $lang;
    return $translations[$lang][$key] ?? ($translations['cs'][$key] ?? $key);
}

// Optional: slug-based category name translations (fill in as needed)
$collectionTranslations = [
  // 'slug-key' => ['cs' => 'Česky', 'sk' => 'Slovensky', 'en' => 'English'],
];

function getCollectionDisplayName(array $collection): string {
  global $lang, $collectionTranslations;
  $default = isset($collection['name']) ? (string)$collection['name'] : '';
  if ($lang === 'sk' && !empty($collection['name_sk'])) {
    return (string)$collection['name_sk'];
  }
  if ($lang === 'en' && !empty($collection['name_en'])) {
    return (string)$collection['name_en'];
  }
  $slug = isset($collection['slug']) ? (string)$collection['slug'] : '';
  if ($slug !== '' && isset($collectionTranslations[$slug][$lang])) {
    return (string)$collectionTranslations[$slug][$lang];
  }
  return $default;
}

// Track abandoned cart for logged-in users
if (isset($_SESSION['user_id']) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    include 'track_abandoned_cart.php';
}

// DB connection moved to top

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>KJD</title>
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
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">

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
      
      /* Product grid spacing */
      .product-grid {
        gap: 2rem;
        --bs-gutter-x: 2rem;
        --bs-gutter-y: 2rem;
        justify-content: center;
      }
      
      /* Standardize product image size like main site */
      .product-grid .tab-image { 
        width: 100%; 
        height: 260px; 
        object-fit: cover; 
        border-radius: 12px;
        border: 2px solid var(--kjd-beige);
        transition: all 0.2s ease;
      }
      .product-grid .tab-image:hover {
        border-color: var(--kjd-earth-green);
        transform: scale(1.02);
      }
      
      /* Enhanced product cards - subtle improvements */
      .product-item {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        position: relative;
      }
      .product-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
        border-color: rgba(16, 40, 32, 0);
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
      
      /* Badge improvements */
      .badge {
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
        border-radius: 15px;
      }
      
      /* Make hero banner images cover their blocks nicely */
      .banner-blocks .img-wrapper { position: relative; }
      .banner-blocks .img-wrapper img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover;
        border-radius: 8px;
      }
      
      /* Left big block: ensure min-height so the image can cover nicely */
      .banner-ad.large .row.banner-content { 
        min-height: 420px; 
        background: rgba(202,186,156,0.05);
        border-radius: 12px;
      }
      .banner-ad.large .img-wrapper { height: 100%; }
      
      /* Bottom-right promo block: cover background image by placing an <img> as bg alternative */
      .banner-ad.block-3 { 
        position: relative; 
        overflow: hidden; 
        border-radius: 12px;
      }
      .banner-ad.block-3 .img-cover { 
        position: absolute; 
        right: 0; 
        bottom: 0; 
        width: 65%; 
        height: 100%; 
        object-fit: cover; 
        pointer-events: none;
      }
      
      /* Middle banner enhancement */
      .banner-ad.block-2 {
        border-radius: 12px;
        background: var(--kjd-earth-green) !important;
        position: relative;
        overflow: hidden;
      }
      
      .banner-ad.block-2 .banner-title {
        color: #fff;
        font-weight: 700;
      }
      
      .banner-ad.block-2 .categories.sale {
        color: #fff;
        font-weight: 700;
      }
      
      /* Skrýt automatické "SLEVA" z CSS pro block-2 */
      .banner-ad.block-2 .categories.sale::before,
      .banner-ad.block-2 .categories.sale::after {
        display: none !important;
        content: none !important;
      }
      
      .banner-ad.block-2 .btn-kjd-primary {
        background: #fff;
        color: var(--kjd-earth-green);
        border: 2px solid #fff;
        font-weight: 700;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
      }
      
      .banner-ad.block-2 .btn-kjd-primary:hover {
        background: var(--kjd-beige);
        color: var(--kjd-dark-green);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      }
      
      .banner-ad.block-2 .btn-kjd-primary svg {
        fill: currentColor;
        margin-left: 0.5rem;
      }

      /* SHROOM hero styling to match site accents */
      .banner-ad.large.shroom-hero {
        border: 3px solid var(--kjd-earth-green);
        border-radius: 16px;
        background: linear-gradient(135deg, var(--kjd-beige), #f5efe6);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      .shroom-hero .row.banner-content { min-height: 360px; }
      .shroom-hero .categories { display: none; }
      .shroom-hero .shroom-chip {
        display: inline-block;
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown));
        color: #fff;
        font-weight: 800;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        border: 2px solid rgba(0,0,0,0.05);
        box-shadow: 0 2px 8px rgba(16,40,32,0.15);
      }
      .shroom-hero .display-4 {
        color: var(--kjd-dark-green);
        font-weight: 800;
        text-shadow: 0 2px 0 rgba(16,40,32,0.06);
      }
      .shroom-hero .btn-kjd-primary {
        background: var(--kjd-dark-brown);
        color: #fff;
      }
      .shroom-hero .btn-kjd-primary:hover { background: var(--kjd-gold-brown); }
      
      @media (max-width: 992px) {
        .banner-ad.large .row.banner-content { min-height: 360px; }
        .banner-ad.block-3 .img-cover { width: 70%; }
        .product-grid .tab-image { height: 240px; }
      }
      
      /* Mobile optimizations */
      @media (max-width: 768px) {
        /* Obecné vylepšení pro tablety */
        .container-fluid {
          padding-left: 1rem;
          padding-right: 1rem;
        }
        
        .product-grid {
          gap: 1.5rem;
          --bs-gutter-x: 1.5rem;
          --bs-gutter-y: 1.5rem;
        }
        
        /* Zajistit 2 sloupce na tabletech */
        .product-grid.row-cols-2 > *,
        .product-grid.row-cols-2 > .col {
          flex: 0 0 auto !important;
          width: calc(50% - 0.5rem) !important;
          max-width: calc(50% - 0.5rem) !important;
        }
        
        .product-item {
          padding: 1.25rem;
        }
        
        .banner-ad.large .row.banner-content {
          min-height: 320px;
          padding: 2rem !important;
        }
        
        /* Banner blocks - vertikální layout na tabletech */
        .banner-blocks {
          display: flex !important;
          flex-direction: column !important;
          gap: 1.5rem !important;
        }
        
        .banner-blocks .block-1,
        .banner-blocks .block-2,
        .banner-blocks .block-3 {
          width: 100% !important;
        }
      }
      
      @media (max-width: 576px) {
        body { 
          font-size: 15px; 
          line-height: 1.6;
        }
        
        /* Container spacing */
        .container-fluid {
          padding-left: 0.75rem;
          padding-right: 0.75rem;
        }
        
        /* Spam warning alert - mobile */
        .alert.alert-warning {
          padding: 1rem !important;
          margin-bottom: 1rem !important;
        }
        
        .alert.alert-warning h4 {
          font-size: 1.1rem !important;
        }
        
        .alert.alert-warning p {
          font-size: 0.95rem !important;
        }
        
        .alert.alert-warning i.fa-exclamation-triangle {
          font-size: 1.5rem !important;
        }
        
        section {
          padding-top: 2rem !important;
          padding-bottom: 2rem !important;
        }
        
        /* Hero banner - výrazné vylepšení */
        .banner-ad.large .row.banner-content { 
          min-height: auto !important; 
          padding: 1.5rem 1rem !important; 
          text-align: center;
        }
        
        .banner-ad.large .content-wrapper { 
          margin-bottom: 1.5rem; 
          width: 100% !important;
        }
        
        .banner-ad.large .content-wrapper .categories { 
          font-size: 0.9rem; 
          margin-bottom: 0.5rem !important; 
        }
        
        .banner-ad.large .content-wrapper .display-4 { 
          font-size: 1.6rem !important; 
          line-height: 1.3 !important;
          margin-bottom: 0.75rem;
        }
        
        .banner-ad.large .content-wrapper p { 
          font-size: 0.9rem; 
          margin-bottom: 1rem;
          line-height: 1.5;
        }
        
        .banner-ad.large .content-wrapper .btn { 
          width: 100%; 
          padding: 0.75rem 1.5rem;
          font-size: 0.95rem;
        }
        
        .banner-ad.large .img-wrapper { 
          width: 100% !important;
          margin-top: 1rem;
        }
        
        .banner-ad.large .img-wrapper img { 
          max-height: 220px; 
          object-fit: contain;
          width: 100%;
        }
        
        /* SHROOM hero specifické úpravy */
        .shroom-hero .row.banner-content { 
          min-height: auto !important; 
          padding: 1.5rem 1rem !important; 
        }
        
        .shroom-hero .shroom-chip {
          font-size: 0.85rem;
          padding: 0.3rem 0.6rem;
        }
        
        /* Banner blocks - zajistit vertikální layout na mobilu */
        .banner-blocks {
          display: flex !important;
          flex-direction: column !important;
          grid-template-columns: 1fr !important;
          grid-template-rows: auto !important;
          gap: 1rem !important;
        }
        
        .banner-blocks .block-1,
        .banner-blocks .block-2,
        .banner-blocks .block-3 {
          grid-area: auto !important;
          width: 100% !important;
          margin-bottom: 1rem;
        }
        
        /* Ostatní bannery */
        .banner-ad.block-2, 
        .banner-ad.block-3 { 
          padding: 0 !important; 
          margin-bottom: 1rem !important;
        }
        
        .banner-ad.block-2 .banner-content,
        .banner-ad.block-3 .banner-content {
          padding: 2rem 1.5rem !important;
          min-height: auto !important;
        }
        
        .banner-ad.block-2 .content-wrapper,
        .banner-ad.block-3 .content-wrapper {
          width: 100% !important;
          text-align: center !important;
          padding: 0 !important;
        }
        
        .banner-ad.block-2 .banner-title {
          font-size: 1.4rem !important;
          margin-bottom: 1rem !important;
          color: #fff !important;
          font-weight: 800 !important;
          line-height: 1.3;
        }
        
        .banner-ad.block-2 .categories.sale {
          color: #fff !important;
          font-weight: 800 !important;
          font-size: 1.1rem !important;
          margin-bottom: 0.75rem !important;
          padding-bottom: 0.5rem !important;
          display: block;
        }
        
        .banner-ad.block-2 .btn-kjd-primary {
          background: #fff !important;
          color: var(--kjd-earth-green) !important;
          border: 2px solid #fff !important;
          font-weight: 700 !important;
          padding: 0.875rem 2rem !important;
          border-radius: 10px !important;
          transition: all 0.3s ease;
          width: 100% !important;
          max-width: 280px;
          margin: 0 auto;
          display: flex !important;
          align-items: center;
          justify-content: center;
          font-size: 1rem !important;
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .banner-ad.block-2 .btn-kjd-primary:hover {
          background: var(--kjd-beige) !important;
          color: var(--kjd-dark-green) !important;
          transform: translateY(-2px);
          box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }
        
        .banner-ad.block-2 .btn-kjd-primary svg {
          fill: currentColor;
          width: 18px !important;
          height: 18px !important;
          margin-left: 0.5rem;
        }
        
        /* Skrýt background obrázek na mobilu pro block-2 */
        .banner-ad.block-2 {
          background: var(--kjd-earth-green) !important;
          background-image: none !important;
        }
        
        .banner-ad.block-3 h3 {
          font-size: 1.5rem !important;
          margin-bottom: 1rem !important;
        }
        
        .banner-ad.block-3 p {
          font-size: 1rem !important;
        }
        
        /* Produktové karty - vylepšené */
        .product-item { 
          padding: 1rem; 
          border-radius: 12px;
          margin-bottom: 0.75rem;
        }
        
        .product-grid { 
          gap: 1.25rem;
          margin-bottom: 1rem;
          --bs-gutter-x: 1.25rem;
          --bs-gutter-y: 1.25rem;
        }
        
        .product-grid .tab-image { 
          height: 180px; 
          border-radius: 10px;
          margin-bottom: 0.75rem;
        }
        
        .product-item h3 { 
          font-size: 0.95rem; 
          margin: 0.5rem 0;
          line-height: 1.3;
          min-height: 2.6em;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }
        
        .product-item .price { 
          font-size: 1.1rem; 
          margin: 0.5rem 0;
        }
        
        .product-item .badge {
          font-size: 0.75rem;
          padding: 0.3rem 0.6rem;
        }
        
        /* Tlačítka */
        .btn-kjd-primary { 
          width: 100%; 
          padding: 0.75rem 1.5rem;
          font-size: 0.95rem;
        }
        
        /* Kategorie */
        .category-title { 
          font-size: 1.1rem; 
          margin-bottom: 1rem;
          text-align: center;
        }
        
        .category-item {
          margin-bottom: 1rem;
        }
        
        .category-item img, 
        .category-item svg { 
          width: 80px !important; 
          height: 120px !important; 
        }
        
        /* Recenze sekce */
        .kjd-reviews {
          padding: 1.5rem 1rem !important;
          margin: 1rem 0 !important;
        }
        
        .kjd-review-card {
          padding: 1rem !important;
        }
        
        .kjd-review-name {
          font-size: 1rem !important;
        }
        
        .kjd-review-text {
          font-size: 0.9rem !important;
        }
        
        /* Promo bannery dole */
        .banner-ad.mb-3 {
          margin-bottom: 1rem !important;
        }
        
        .banner-ad.mb-3 .banner-content {
          padding: 1.5rem 1rem !important;
        }
        
        .banner-ad.mb-3 h3 {
          font-size: 1.3rem !important;
        }
        
        .banner-ad.mb-3 p {
          font-size: 0.95rem !important;
        }
        
        /* Tabs a navigace */
        .nav-tabs {
          flex-wrap: nowrap;
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
          scrollbar-width: none;
          -ms-overflow-style: none;
        }
        
        .nav-tabs::-webkit-scrollbar {
          display: none;
        }
        
        .nav-tabs .nav-link {
          white-space: nowrap;
          font-size: 0.9rem;
          padding: 0.5rem 1rem;
        }
        
        /* Language picker spacing */
        #offcanvasNavbar .ms-auto { 
          margin-left: 0 !important; 
        }

        /* Offcanvas cart responsiveness */
        #offcanvasCart .offcanvas-body { 
          padding: 1rem !important; 
        }
        
        #miniCart .list-group-item { 
          flex-wrap: wrap; 
          gap: 0.5rem;
          padding: 1rem !important;
        }
        
        #miniCart .list-group-item > .d-flex { 
          width: 100%; 
        }
        
        #miniCart img { 
          width: 60px !important; 
          height: 60px !important; 
        }
        
        #miniCart .badge { 
          margin-left: auto;
          font-size: 0.9rem;
        }
        
        #offcanvasCart .d-flex.justify-content-between.align-items-center { 
          padding: 1rem !important; 
        }
        
        #offcanvasCart .btn { 
          width: 100%; 
          padding: 0.875rem;
          font-size: 1rem;
        }
        
        /* Maintenance notice */
        .alert {
          padding: 1rem 0.75rem !important;
          font-size: 0.85rem;
        }
        
        .alert .d-flex {
          flex-direction: column;
          text-align: center;
        }
        
        .alert i {
          margin-bottom: 0.5rem;
        }
        
        /* Spacing improvements */
        .py-5 {
          padding-top: 2rem !important;
          padding-bottom: 2rem !important;
        }
        
        .py-3 {
          padding-top: 1rem !important;
          padding-bottom: 1rem !important;
        }
        
        /* Product grid columns - zajistit 2 sloupce na mobilu */
        .product-grid.row-cols-2 {
          --bs-gutter-x: 1.25rem;
          display: flex !important;
          flex-wrap: wrap !important;
        }
        
        .product-grid.row-cols-2 > *,
        .product-grid.row-cols-2 > .col {
          flex: 0 0 auto !important;
          width: calc(50% - 0.625rem) !important;
          max-width: calc(50% - 0.625rem) !important;
        }
      }
      
      @media (max-width: 480px) {
        /* Extra malé obrazovky - ještě kompaktnější */
        body {
          font-size: 14px;
        }
        
        .banner-ad.large .content-wrapper .display-4 {
          font-size: 1.4rem !important;
        }
        
        .product-grid .tab-image {
          height: 160px;
        }
        
        .product-item h3 {
          font-size: 0.9rem;
        }
        
        .product-item .price {
          font-size: 1rem;
        }
        
        .category-item img,
        .category-item svg {
          width: 70px !important;
          height: 105px !important;
        }
        
        .banner-ad.block-3 h3 {
          font-size: 1.3rem !important;
        }
        
        /* Block-2 na extra malých obrazovkách */
        .banner-ad.block-2 .banner-content {
          padding: 1.5rem 1rem !important;
        }
        
        .banner-ad.block-2 .banner-title {
          font-size: 1.2rem !important;
        }
        
        .banner-ad.block-2 .categories.sale {
          font-size: 1rem !important;
        }
        
        .banner-ad.block-2 .btn-kjd-primary {
          padding: 0.75rem 1.5rem !important;
          font-size: 0.95rem !important;
          max-width: 100%;
        }
        
        /* Zajistit 2 sloupce i na extra malých obrazovkách */
        .product-grid.row-cols-2 > *,
        .product-grid.row-cols-2 > .col {
          width: calc(50% - 0.625rem) !important;
          max-width: calc(50% - 0.625rem) !important;
        }
      }
      
      /* Touch-friendly improvements */
      @media (max-width: 768px) {
        /* Zvětšit touch targety */
        .btn, .nav-link, .category-item {
          min-height: 44px;
          min-width: 44px;
        }
        
        /* Smooth scrolling */
        html {
          scroll-behavior: smooth;
        }
        
        /* Better tap highlights */
        a, button {
          -webkit-tap-highlight-color: rgba(76, 100, 68, 0.2);
        }
        
        /* Prevent text selection on buttons */
        .btn, .nav-link {
          -webkit-user-select: none;
          -moz-user-select: none;
          user-select: none;
        }
        
        /* Vylepšení kategorií na mobilu */
        .category-carousel {
          padding-bottom: 1.5rem;
        }
        
        .category-item {
          padding: 0.75rem;
          border-radius: 12px;
          transition: transform 0.2s ease;
        }
        
        .category-item:active {
          transform: scale(0.95);
        }
        
        /* Vylepšení produktových karet - lepší hover/touch feedback */
        .product-item {
          transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .product-item:active {
          transform: scale(0.98);
        }
        
        /* Vylepšení sekcí - lepší spacing */
        section {
          scroll-margin-top: 80px;
        }
        
        /* Vylepšení footeru na mobilu */
        footer {
          font-size: 0.9rem;
        }
        
        footer .row {
          text-align: center;
        }
        
        footer .col-md-3,
        footer .col-md-4 {
          margin-bottom: 2rem;
        }
        
        /* Vylepšení search na mobilu */
        .offcanvas-body form {
          margin-bottom: 1rem;
        }
        
        /* Vylepšení section headers */
        .section-title {
          font-size: 1.5rem !important;
          margin-bottom: 1.5rem;
          text-align: center;
        }
        
        /* Vylepšení swiper pagination na mobilu */
        .swiper-pagination-bullet {
          width: 10px !important;
          height: 10px !important;
        }
        
        /* Vylepšení badge na mobilu */
        .badge {
          font-size: 0.75rem;
          padding: 0.35rem 0.65rem;
        }
        
        /* Vylepšení recenzí na mobilu */
        .kjd-reviews {
          margin: 1.5rem 0 !important;
        }
        
        .reviews-swiper {
          padding-bottom: 2rem;
        }
        
        /* Vylepšení promo bannerů dole */
        .banner-ad.mb-3 {
          margin-bottom: 1.5rem !important;
        }
        
        /* Vylepšení hlavního menu na mobilu */
        .navbar-toggler {
          border: 2px solid var(--kjd-earth-green);
          padding: 0.5rem 0.75rem;
        }
        
        /* Vylepšení offcanvas menu */
        .offcanvas {
          max-width: 85%;
        }
        
        /* Vylepšení cart badge */
        .cart-count,
        .badge.rounded-pill {
          font-size: 0.7rem;
          min-width: 20px;
          height: 20px;
          padding: 0.2rem 0.4rem;
        }
        
        /* Vylepšení loading states */
        .preloader-wrapper {
          z-index: 99999;
        }
        
        /* Vylepšení scrollbar na mobilu (pokud je viditelný) */
        ::-webkit-scrollbar {
          width: 6px;
        }
        
        ::-webkit-scrollbar-track {
          background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
          background: var(--kjd-earth-green);
          border-radius: 3px;
        }
        
        /* Vylepšení focus states pro přístupnost */
        .btn:focus,
        .nav-link:focus,
        a:focus {
          outline: 3px solid var(--kjd-earth-green);
          outline-offset: 2px;
        }
      }
      
      /* Force single slide (hide others and pagination now) */
      .main-swiper .swiper-slide:not(:first-child) { display: none !important; }
      .main-swiper .swiper-pagination { display: none !important; }
      
      /* Zpět nahoru tlačítko */
      .scroll-to-top {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green));
        color: #fff;
        border: none;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        z-index: 1050;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(16,40,32,0.3);
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(20px);
      }
      
      .scroll-to-top.show {
        display: flex;
        opacity: 1;
        transform: translateY(0);
      }
      
      .scroll-to-top:hover {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown));
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(16,40,32,0.4);
      }
      
      .scroll-to-top:active {
        transform: translateY(-1px);
      }
      
      /* Share buttons compact */
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
      
      .share-btn-compact.facebook:hover {
        background: #1877f2;
        border-color: #1877f2;
        color: #fff;
      }
      
      .share-btn-compact.twitter:hover {
        background: #1da1f2;
        border-color: #1da1f2;
        color: #fff;
      }
      
      .share-btn-compact.whatsapp:hover {
        background: #25d366;
        border-color: #25d366;
        color: #fff;
      }
      
      .share-btn-compact.email:hover {
        background: var(--kjd-gold-brown);
        border-color: var(--kjd-gold-brown);
        color: #fff;
      }
      
      .share-btn-compact.copy-link:hover {
        background: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
        color: #fff;
      }
      
      .share-btn-compact.copy-link {
        border: 1.5px solid var(--kjd-earth-green);
        background: #fff;
        padding: 0;
      }
      
      /* Zajistit, aby wishlist tlačítko bylo vpravo nahoře */
      .btn-wishlist {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 11 !important;
      }
      
      /* Badge pozice - vlevo nahoře */
      .product-item .badge.position-absolute {
        top: 10px;
        left: 10px;
        z-index: 5;
      }
      
      @media (max-width: 768px) {
        .scroll-to-top {
          width: 45px;
          height: 45px;
          bottom: 15px;
          right: 15px;
          font-size: 1.3rem;
        }
        
        .share-buttons-compact {
          gap: 0.4rem;
        }
        
        .share-btn-compact {
          width: 30px;
          height: 30px;
          font-size: 0.85rem;
        }
      }
      
      /* Enhanced buttons */
      .btn-kjd-primary { 
        background: var(--kjd-dark-brown); 
        color: #fff; 
        border: none; 
        padding: 0.6rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s ease;
      }
      .btn-kjd-primary:hover { 
        background: var(--kjd-gold-brown); 
        color: #fff;
        transform: translateY(-1px);
      }
      
      .kjd-chip { color: var(--kjd-dark-green); }
      
      /* Review strip - subtle improvements */
      .kjd-reviews { 
        background: var(--kjd-beige); 
        border-radius: 16px; 
        padding: 2rem; 
        margin: 1.5rem 0 2rem;
        border: 2px solid var(--kjd-earth-green);
      }
      .reviews-swiper { padding: 0.25rem 0.25rem 2.25rem; position: relative; }
      .reviews-swiper .swiper-pagination { bottom: 0 !important; }
      .reviews-controls .btn { background: #fff; }
      .reviews-controls .btn:hover { background: var(--kjd-beige); }
      .reviews-swiper .swiper-slide { height: auto; }
      .kjd-review-card { 
        background: #fff; 
        border-radius: 12px; 
        padding: 1.5rem; 
        box-shadow: 0 2px 10px rgba(16,40,32,0.08); 
        border-left: 4px solid var(--kjd-earth-green); 
        height: 100%;
        transition: all 0.2s ease;
      }
      .kjd-review-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(16,40,32,0.12);
      }
      .kjd-review-name { 
        color: var(--kjd-dark-brown); 
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
      }
      .kjd-review-stars { 
        color: #f0b938; 
        font-size: 1.2rem;
        margin-bottom: 0.75rem;
      }
      .kjd-review-text {
        color: #555;
        line-height: 1.5;
        font-weight: 500;
      }
      
      /* Review image hover effect */
      .review-image-clickable:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      }
      
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
        animation: fadeIn 0.3s ease;
      }
      
      .lightbox-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        max-width: 90%;
        max-height: 90%;
        animation: zoomIn 0.3s ease;
      }
      
      .lightbox-image {
        width: 100%;
        height: auto;
        border-radius: 8px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.5);
      }
      
      .lightbox-close {
        position: absolute;
        top: 20px;
        right: 30px;
        color: #fff;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        z-index: 10000;
        transition: color 0.2s ease;
      }
      
      .lightbox-close:hover {
        color: #ff6b6b;
      }
      
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      
      @keyframes zoomIn {
        from { transform: translate(-50%, -50%) scale(0.8); }
        to { transform: translate(-50%, -50%) scale(1); }
      }
      
      /* Enhanced category tabs */
      .nav-tabs .nav-link {
        color: var(--kjd-dark-green);
        font-weight: 600;
        border: 1px solid transparent;
        border-radius: 8px;
        margin: 0 0.2rem;
        transition: all 0.2s ease;
      }
      .nav-tabs .nav-link:hover {
        border-color: var(--kjd-earth-green);
        background: rgba(202,186,156,0.1);
      }
      .nav-tabs .nav-link.active {
        background: var(--kjd-earth-green);
        color: #fff;
        border-color: var(--kjd-earth-green);
        font-weight: 700;
      }
      
      /* Section title enhancements */
      .section-title {
        color: var(--kjd-dark-green);
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 1rem;
      }
      
      /* Hide "Newly Arrived Brands" */
      section.kjd-hide { display: none !important; }
      
      
      /* View product link */
      .nav-link[href*="product.php"] {
        color: var(--kjd-earth-green);
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
      }
      .nav-link[href*="product.php"]:hover {
        color: var(--kjd-dark-brown);
        text-decoration: underline;
      }
      
      /* KJD Custom Preloader */
      .preloader-wrapper {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8) !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
      }
      
      .preloader-wrapper .preloader {
        margin: 0;
        transform: none;
        position: relative;
        width: auto;
        height: auto;
        background: none !important;
        animation: none !important;
        border-radius: 0;
      }
      
      .preloader:before,
      .preloader:after {
        display: none !important;
      }
      
      .preloader-text {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--kjd-dark-green);
        margin-bottom: 2rem;
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        animation: textFadeIn 1s ease-out;
      }
      
      .preloader-progress {
        width: 300px;
        height: 6px;
        background: rgba(202,186,156,0.3);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        margin-bottom: 1rem;
      }
      
      .preloader-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--kjd-earth-green), var(--kjd-dark-green));
        border-radius: 10px;
        width: 0%;
        animation: progressLoad 3s ease-out forwards;
        box-shadow: 0 2px 8px rgba(76,100,68,0.3);
      }
      
      .preloader-percentage {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--kjd-gold-brown);
        animation: percentageCount 3s ease-out forwards;
      }
      
      @keyframes textFadeIn {
        0% { opacity: 0; transform: translateY(20px); }
        100% { opacity: 1; transform: translateY(0); }
      }
      
      @keyframes progressLoad {
        0% { width: 0%; }
        100% { width: 100%; }
      }
      
      @keyframes percentageCount {
        0% { content: "0%"; }
        25% { content: "25%"; }
        50% { content: "50%"; }
        75% { content: "75%"; }
        100% { content: "100%"; }
      }
      
      @keyframes zoomOut {
        0% { transform: scale(1); opacity: 1; }
        100% { transform: scale(1.2); opacity: 0; }
      }
      
      .preloader-wrapper.fade-out {
        animation: zoomOut 0.8s ease-in-out forwards;
      }
    </style>
  </head>
  <body>
    <!-- Hanging Christmas Lights (Global Top) -->
    <style>
      .lightrope {
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        position: absolute;
        z-index: 9999;
        margin: -15px 0 0 0;
        padding: 0;
        pointer-events: none;
        width: 100%;
        top: 0;
        left: 0;
      }
      .lightrope li {
        position: relative;
        animation-fill-mode: both;
        animation-iteration-count: infinite;
        list-style: none;
        margin: 0;
        padding: 0;
        display: inline-block;
        width: 12px;
        height: 28px;
        border-radius: 50%;
        margin: 20px;
        background: #ff0000;
        box-shadow: 0px 4.6666666667px 24px 3px #ff0000;
        animation-name: flash-1;
        animation-duration: 2s;
      }
      .lightrope li:nth-child(2n+1) {
        background: #ffd700;
        box-shadow: 0px 4.6666666667px 24px 3px rgba(255, 215, 0, 0.5);
        animation-name: flash-2;
        animation-duration: 0.4s;
      }
      .lightrope li:nth-child(4n+2) {
        background: #00ff00;
        box-shadow: 0px 4.6666666667px 24px 3px #00ff00;
        animation-name: flash-3;
        animation-duration: 1.1s;
      }
      .lightrope li:nth-child(odd) {
        animation-duration: 1.8s;
      }
      .lightrope li:nth-child(3n+1) {
        animation-duration: 1.4s;
      }
      .lightrope li:before {
        content: "";
        position: absolute;
        background: #222;
        width: 10px;
        height: 9.3333333333px;
        border-radius: 3px;
        top: -4.6666666667px;
        left: 1px;
      }
      .lightrope li:after {
        content: "";
        top: -14px;
        left: 9px;
        position: absolute;
        width: 52px;
        height: 18.6666666667px;
        border-bottom: solid #222 2px;
        border-radius: 50%;
      }
      .lightrope li:last-child:after {
        content: none;
      }
      .lightrope li:first-child {
        margin-left: -40px;
      }
      @keyframes flash-1 {
        0%, 100% { background: #ff0000; box-shadow: 0px 4.6666666667px 24px 3px #ff0000; }
        50% { background: rgba(255, 0, 0, 0.4); box-shadow: 0px 4.6666666667px 24px 3px rgba(255, 0, 0, 0.2); }
      }
      @keyframes flash-2 {
        0%, 100% { background: #ffd700; box-shadow: 0px 4.6666666667px 24px 3px #ffd700; }
        50% { background: rgba(255, 215, 0, 0.4); box-shadow: 0px 4.6666666667px 24px 3px rgba(255, 215, 0, 0.2); }
      }
      @keyframes flash-3 {
        0%, 100% { background: #00ff00; box-shadow: 0px 4.6666666667px 24px 3px #00ff00; }
        50% { background: rgba(0, 255, 0, 0.4); box-shadow: 0px 4.6666666667px 24px 3px rgba(0, 255, 0, 0.2); }
      }
    </style>
    <ul class="lightrope">
      <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
      <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
      <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
      <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
      <li></li><li></li>
    </ul>

    <?php include 'includes/icons.php'; ?>
    <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
      <defs>
        <symbol xmlns="http://www.w3.org/2000/svg" id="link" viewBox="0 0 24 24">
          <path fill="currentColor" d="M12 19a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm5 0a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm0-4a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm-5 0a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm7-12h-1V2a1 1 0 0 0-2 0v1H8V2a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V6a3 3 0 0 0-3-3Zm1 17a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9h16Zm0-11H4V6a1 1 0 0 1 1-1h1v1a1 1 0 0 0 2 0V5h8v1a1 1 0 0 0 2 0V5h1a1 1 0 0 1 1 1ZM7 15a1 1 0 1 0-1-1a1 1 0 0 0 1 1Zm0 4a1 1 0 1 0-1-1a1 1 0 0 0 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="arrow-right" viewBox="0 0 24 24">
          <path fill="currentColor" d="M17.92 11.62a1 1 0 0 0-.21-.33l-5-5a1 1 0 0 0-1.42 1.42l3.3 3.29H7a1 1 0 0 0 0 2h7.59l-3.3 3.29a1 1 0 0 0 0 1.42a1 1 0 0 0 1.42 0l5-5a1 1 0 0 0 .21-.33a1 1 0 0 0 0-.76Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="category" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 5.5h-6.28l-.32-1a3 3 0 0 0-2.84-2H5a3 3 0 0 0-3 3v13a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-10a3 3 0 0 0-3-3Zm1 13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1h4.56a1 1 0 0 1 .95.68l.54 1.64a1 1 0 0 0 .95.68h7a1 1 0 0 1 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="calendar" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 4h-2V3a1 1 0 0 0-2 0v1H9V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3Zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-7h16Zm0-9H4V7a1 1 0 0 1 1-1h2v1a1 1 0 0 0 2 0V6h6v1a1 1 0 0 0 2 0V6h2a1 1 0 0 1 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="heart" viewBox="0 0 24 24">
          <path fill="currentColor" d="M20.16 4.61A6.27 6.27 0 0 0 12 4a6.27 6.27 0 0 0-8.16 9.48l7.45 7.45a1 1 0 0 0 1.42 0l7.45-7.45a6.27 6.27 0 0 0 0-8.87Zm-1.41 7.46L12 18.81l-6.75-6.74a4.28 4.28 0 0 1 3-7.3a4.25 4.25 0 0 1 3 1.25a1 1 0 0 0 1.42 0a4.27 4.27 0 0 1 6 6.05Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="plus" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 11h-6V5a1 1 0 0 0-2 0v6H5a1 1 0 0 0 0 2h6v6a1 1 0 0 0 2 0v-6h6a1 1 0 0 0 0-2Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="minus" viewBox="0 0 24 24">
          <path fill="currentColor" d="M19 11H5a1 1 0 0 0 0 2h14a1 1 0 0 0 0-2Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="cart" viewBox="0 0 24 24">
          <path fill="currentColor" d="M8.5 19a1.5 1.5 0 1 0 1.5 1.5A1.5 1.5 0 0 0 8.5 19ZM19 16H7a1 1 0 0 1 0-2h8.491a3.013 3.013 0 0 0 2.885-2.176l1.585-5.55A1 1 0 0 0 19 5H6.74a3.007 3.007 0 0 0-2.82-2H3a1 1 0 0 0 0 2h.921a1.005 1.005 0 0 1 .962.725l.155.545v.005l1.641 5.742A3 3 0 0 0 7 18h12a1 1 0 0 0 0-2Zm-1.326-9l-1.22 4.274a1.005 1.005 0 0 1-.963.726H8.754l-.255-.892L7.326 7ZM16.5 19a1.5 1.5 0 1 0 1.5 1.5a1.5 1.5 0 0 0-1.5-1.5Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="check" viewBox="0 0 24 24">
          <path fill="currentColor" d="M18.71 7.21a1 1 0 0 0-1.42 0l-7.45 7.46l-3.13-3.14A1 1 0 1 0 5.29 13l3.84 3.84a1 1 0 0 0 1.42 0l8.16-8.16a1 1 0 0 0 0-1.47Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="trash" viewBox="0 0 24 24">
          <path fill="currentColor" d="M10 18a1 1 0 0 0 1-1v-6a1 1 0 0 0-2 0v6a1 1 0 0 0 1 1ZM20 6h-4V5a3 3 0 0 0-3-3h-2a3 3 0 0 0-3 3v1H4a1 1 0 0 0 0 2h1v11a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3V8h1a1 1 0 0 0 0-2ZM10 5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1h-4Zm7 14a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V8h10Zm-3-1a1 1 0 0 0 1-1v-6a1 1 0 0 0-2 0v6a1 1 0 0 0 1 1Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="star-outline" viewBox="0 0 15 15">
          <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M7.5 9.804L5.337 11l.413-2.533L4 6.674l2.418-.37L7.5 4l1.082 2.304l2.418.37l-1.75 1.793L9.663 11L7.5 9.804Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="star-solid" viewBox="0 0 15 15">
          <path fill="currentColor" d="M7.953 3.788a.5.5 0 0 0-.906 0L6.08 5.85l-2.154.33a.5.5 0 0 0-.283.843l1.574 1.613l-.373 2.284a.5.5 0 0 0 .736.518l1.92-1.063l1.921 1.063a.5.5 0 0 0 .736-.519l-.373-2.283l1.574-1.613a.5.5 0 0 0-.283-.844L8.921 5.85l-.968-2.062Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="search" viewBox="0 0 24 24">
          <path fill="currentColor" d="M21.71 20.29L18 16.61A9 9 0 1 0 16.61 18l3.68 3.68a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.39ZM11 18a7 7 0 1 1 7-7a7 7 0 0 1-7 7Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="user" viewBox="0 0 24 24">
          <path fill="currentColor" d="M15.71 12.71a6 6 0 1 0-7.42 0a10 10 0 0 0-6.22 8.18a1 1 0 0 0 2 .22a8 8 0 0 1 15.9 0a1 1 0 0 0 1 .89h.11a1 1 0 0 0 .88-1.1a10 10 0 0 0-6.25-8.19ZM12 12a4 4 0 1 1 4-4a4 4 0 0 1-4 4Z"/>
        </symbol>
        <symbol xmlns="http://www.w3.org/2000/svg" id="close" viewBox="0 0 15 15">
          <path fill="currentColor" d="M7.953 3.788a.5.5 0 0 0-.906 0L6.08 5.85l-2.154.33a.5.5 0 0 0-.283.843l1.574 1.613l-.373 2.284a.5.5 0 0 0 .736.518l1.92-1.063l1.921 1.063a.5.5 0 0 0 .736-.519l-.373-2.283l1.574-1.613a.5.5 0 0 0-.283-.844L8.921 5.85l-.968-2.062Z"/>
        </symbol>
      </defs>
    </svg>

    <div class="preloader-wrapper">
      <div class="preloader">
        <div class="preloader-text">kubajadesigns.eu</div>
        <div class="preloader-progress">
          <div class="preloader-progress-bar"></div>
        </div>
        <div class="preloader-percentage">0%</div>
      </div>
    </div>

    <?php
    // Helper to get first product image path for this subdirectory
// Helper function to follow URL redirects (for shorturl.at, bit.ly, etc.)
function followRedirect($url, $maxRedirects = 5) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, $maxRedirects);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    return $finalUrl ?: $url;
}

function getProductImageSrc(array $product): string {
  $images = [];
  if (!empty($product['image_url'])) {
    $images = explode(',', $product['image_url']);
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
  return 'uploads/products/' . $normalized;
}

// Compute effective price respecting active sale
function computeEffectivePrice(array $p): float {
  if (isset($p['final_price'])) {
    return (float)$p['final_price'];
  }
  $base = (float)($p['price'] ?? 0);
  $saleActive = false; $salePrice = 0;
  if (!empty($p['sale_enabled']) && (int)$p['sale_enabled'] === 1) {
    $sp = isset($p['sale_price']) ? (float)$p['sale_price'] : 0;
    if ($sp > 0 && $sp < $base) {
      if (!empty($p['sale_end'])) {
        $endTs = strtotime((string)$p['sale_end']);
        if ($endTs && time() < $endTs) { $saleActive = true; $salePrice = $sp; }
      } else { $saleActive = true; $salePrice = $sp; }
    }
  }
  return $saleActive ? $salePrice : $base;
}

// Helper to resolve a category/collection icon for this subdirectory
function getCollectionIconSrc(array $collection): string {
  // 1) DB-provided fields
  $candidates = [];
  foreach (['icon_url', 'icon_path', 'icon'] as $field) {
    if (!empty($collection[$field]) && is_string($collection[$field])) {
      $candidates[] = trim((string)$collection[$field]);
    }
  }

  // 2) Conventional locations by collection id
  $id = isset($collection['id']) ? (string)$collection['id'] : '';
  if ($id !== '') {
    $byIdBases = [
      __DIR__ . '/../uploads/collection_icons/',   // web: ../uploads/collection_icons/
      __DIR__ . '/images/categories/',             // web: images/categories/
    ];
    $exts = ['.svg', '.png', '.jpg', '.jpeg', '.webp'];
    foreach ($byIdBases as $baseFs) {
      foreach ($exts as $ext) {
        $fs = $baseFs . $id . $ext;
        if (file_exists($fs)) {
          // Map fs -> web path relative to /02/
          if (strpos($fs, __DIR__ . '/../') === 0) {
            return '../' . ltrim(substr($fs, strlen(__DIR__ . '/../')), '/');
          }
          if (strpos($fs, __DIR__ . '/') === 0) {
            return substr($fs, strlen(__DIR__ . '/') );
          }
        }
      }
    }
  }

  // 3) Evaluate DB-provided candidates (uploads or absolute URLs)
  foreach ($candidates as $cand) {
    // Absolute URL
    if (preg_match('~^https?://~i', $cand)) {
      return $cand;
    }
    // Stored like uploads/... in DB
    if (strpos($cand, 'uploads/') === 0 || strpos($cand, '/uploads/') === 0) {
      $normalized = ltrim($cand, '/');
      $fs = __DIR__ . '/../' . $normalized;
      if (file_exists($fs)) {
        return '../' . $normalized;
      }
    }
    // Try relative to this /02/ directory
    $fsLocal = __DIR__ . '/' . ltrim($cand, '/');
    if (file_exists($fsLocal)) {
      return ltrim($cand, '/');
    }
  }

  // 4) No icon found -> return empty string, caller will fallback to default SVG
  return '';
}

    // Load collections (categories)
    $collections = [];
    $activeCollectionId = 0;
    $activeCollectionName = '';
    $activeCollectionSlug = '';
    $collectionParam = isset($_GET['collection']) ? trim((string)$_GET['collection']) : '';
    try {
        $cstmt = $conn->query("SELECT id, name, slug, icon_url FROM product_collections_main WHERE is_active = 1 ORDER BY name");
        $collections = $cstmt->fetchAll(PDO::FETCH_ASSOC);
        // Resolve active collection (supports id or slug)
        if ($collectionParam !== '') {
            if (ctype_digit($collectionParam)) {
                $activeCollectionId = (int)$collectionParam;
                foreach ($collections as $col) {
                    if ((int)$col['id'] === $activeCollectionId) { $activeCollectionName = $col['name']; $activeCollectionSlug = (string)($col['slug'] ?? ''); break; }
                }
            } else {
                // treat as slug
                $activeCollectionSlug = $collectionParam;
                foreach ($collections as $col) {
                    if (!empty($col['slug']) && strtolower($col['slug']) === strtolower($activeCollectionSlug)) {
                        $activeCollectionId = (int)$col['id'];
                        $activeCollectionName = $col['name'];
                        break;
                    }
                }
                if ($activeCollectionId === 0) {
                    // fallback direct DB lookup by slug
                    $s = $conn->prepare("SELECT id, name, slug FROM product_collections_main WHERE slug = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
                    $s->execute([$collectionParam]);
                    if ($row = $s->fetch(PDO::FETCH_ASSOC)) { $activeCollectionId = (int)$row['id']; $activeCollectionName = (string)$row['name']; $activeCollectionSlug = (string)($row['slug'] ?? ''); }
                }
            }
        }
    } catch (PDOException $e) {
        $collections = [];
    }

    // Fetch products - only 6 newest for homepage OR search results
    $products = [];
    $searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    try {
        if ($searchQ !== '') {
            $stmt = $conn->prepare("SELECT * FROM product WHERE (is_hidden IS NULL OR is_hidden = 0) AND (name LIKE ? OR description LIKE ?) ORDER BY id DESC");
            $like = '%' . $searchQ . '%';
            $stmt->execute([$like, $like]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->query("SELECT * FROM product WHERE (is_hidden IS NULL OR is_hidden = 0) ORDER BY id DESC LIMIT 6");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $products = [];
    }
    ?>

    <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasCart" aria-labelledby="My Cart">
      <div class="offcanvas-header" style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown)); color: #fff; border-bottom: 2px solid var(--kjd-beige);">
        <h5 class="offcanvas-title" style="font-weight: 800;">
          <?= t('cart_title') ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body" style="background: #f8f9fa;">
        <div class="order-md-last">
          <ul class="list-group mb-3" id="miniCart" style="border-radius: 12px; overflow: hidden;">
            <?php if (!empty($_SESSION['cart'])): ?>
              <?php foreach ($_SESSION['cart'] as $productId => $productData): ?>
                <?php
                $quantity = (int)($productData['quantity'] ?? 0);
                $price = computeEffectivePrice($productData);
                $name = htmlspecialchars($productData['name'] ?? 'Product');
                $img = getProductImageSrc($productData);
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center" style="border: 1px solid rgba(202,186,156,0.2); background: #fff;">
                  <div class="d-flex align-items-center">
                    <img src="<?= $img ?>" alt="<?= $name ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 12px; margin-right: 12px; border: 2px solid var(--kjd-beige);">
                    <div>
                      <div style="font-weight: 700; color: var(--kjd-dark-green); font-size: 0.9rem; line-height: 1.2;"><?= $name ?></div>
                      <small style="color: var(--kjd-gold-brown); font-weight: 600;">
                        <?php
                          $baseP = (float)($productData['price'] ?? $price);
                          if ($price < $baseP) {
                            echo $quantity . 'x ' . '<span style="text-decoration:line-through;color:#888; margin-right:6px;">' . number_format($baseP, 0, ',', ' ') . ' Kč</span>' . number_format($price, 0, ',', ' ') . ' Kč';
                          } else {
                            echo $quantity . 'x ' . number_format($price, 0, ',', ' ') . ' Kč';
                          }
                        ?>
                      </small>
                    </div>
                  </div>
                  <span class="badge" style="background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); color: #fff; font-weight: 700; padding: 0.5rem 0.75rem; border-radius: 8px;"><?= number_format($price * $quantity, 0, ',', ' ') ?> Kč</span>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="list-group-item text-center text-muted" style="border: 1px solid rgba(202,186,156,0.2); background: #fff; padding: 2rem;">
                <i class="fas fa-shopping-cart me-2" style="font-size: 2rem; color: var(--kjd-beige);"></i>
                <div style="font-weight: 600; color: var(--kjd-dark-green); margin-top: 0.5rem;"><?= t('cart_empty') ?></div>
                <small style="color: #666;"><?= t('cart_empty_hint') ?></small>
              </li>
            <?php endif; ?>
          </ul>
          <?php if (!empty($_SESSION['cart'])): ?>
            <?php
            $cartTotal = 0;
            foreach ($_SESSION['cart'] as $productData) {
              $quantity = (int)($productData['quantity'] ?? 0);
              $price = computeEffectivePrice($productData);
              $cartTotal += $price * $quantity;
            }
            ?>
            <div class="d-flex justify-content-between align-items-center mb-3" style="background: var(--kjd-beige); padding: 1rem; border-radius: 12px; border: 2px solid var(--kjd-earth-green);">
              <span style="font-weight: 800; color: var(--kjd-dark-green); font-size: 1.1rem;">Celkem:</span>
              <span style="font-weight: 800; color: var(--kjd-dark-brown); font-size: 1.2rem;"><?= number_format($cartTotal, 0, ',', ' ') ?> Kč</span>
            </div>
          <?php endif; ?>
          <button class="w-100 btn btn-lg mb-3" type="button" onclick="window.location.href='cart.php'" 
                  style="background: var(--kjd-earth-green); color: #fff; border: none; border-radius: 8px; font-weight: 700; padding: 1rem; font-size: 1.1rem;">
            <?= t('cart_button') ?>
          </button>
          <button class="w-100 btn btn-lg" type="button" onclick="window.location.href='index.php'" 
                  style="background: var(--kjd-beige); color: var(--kjd-dark-green); border: 2px solid var(--kjd-earth-green); border-radius: 8px; font-weight: 700; padding: 1rem; font-size: 1.1rem;">
            <i class="fas fa-arrow-left me-2"></i><?= t('continue_shopping') ?>
          </button>
        </div>
      </div>
    </div>

    <div class="offcanvas offcanvas-end" data-bs-scroll="true" tabindex="-1" id="offcanvasSearch" aria-labelledby="Search">
      <div class="offcanvas-header justify-content-center">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <div class="order-md-last">
          <h4 class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-primary">Search</span>
          </h4>
          <form role="search" action="index.php" method="get" class="d-flex mt-3 gap-0">
            <input class="form-control rounded-start rounded-0 bg-light" name="q" value="<?= htmlspecialchars($searchQ ?? '') ?>" placeholder="What are you looking for?" aria-label="What are you looking for?">
            <button class="btn btn-dark rounded-end rounded-0" type="submit">Search</button>
          </form>
        </div>
      </div>
    </div>

    <?php include 'includes/navbar-index.php'; ?>
    
      <div class="container-fluid">
        <div class="row py-3">
          <div class="d-flex  justify-content-center justify-content-sm-between align-items-center">
            <nav class="main-menu d-flex navbar navbar-expand-lg">

              <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar"
                aria-controls="offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
              </button>

              <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">

                <div class="offcanvas-header justify-content-center">
                  <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>

              <div class="offcanvas-body">
                <div class="ms-auto d-flex align-items-center gap-2 mb-3" style="display: none !important;">
                  <form method="get" action="index.php" class="d-flex align-items-center">
                    <label for="lang" class="me-2" style="font-weight:600; color: var(--kjd-dark-green);"><?= t('language') ?>:</label>
                    <select id="lang" name="lang" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
                      <option value="cs" <?= ($lang==='cs'?'selected':'') ?>><?= t('lang_cs') ?></option>
                      <option value="sk" <?= ($lang==='sk'?'selected':'') ?>><?= t('lang_sk') ?></option>
                      <option value="en" <?= ($lang==='en'?'selected':'') ?>><?= t('lang_en') ?></option>
                    </select>
                  </form>
                </div>
                <!-- Navigation content removed per request -->
                </div>

              </div>
          </div>
        </div>
      </div>
    </header>
    
    <?php // Keep hero/banner sections exactly as template (unchanged) ?>
    <section class="py-3" style="background-image: url('images/background-pattern.jpg');background-repeat: no-repeat;background-size: cover;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="banner-blocks">
            
              <div class="banner-ad large shroom-hero block-1">

                <div class="swiper main-swiper">
                  <div class="swiper-wrapper">
                    
                    <div class="swiper-slide">
                      <div class="row banner-content p-5">
                        <div class="content-wrapper col-md-7">
                          <div class="my-3"><span class="shroom-chip">13.1.</span></div>
                          <h3 class="display-4"><?= t('hero_title') ?></h3>
                          <p><?= t('hero_subtitle') ?></p>
                          <a href="https://kubajadesigns.eu/product.php?id=110" class="btn btn-kjd-primary btn-lg text-uppercase fs-6 rounded-1 px-4 py-3 mt-3"><?= t('buy') ?></a>
                        </div>
                        <div class="img-wrapper col-md-5">
                          <img src="https://lh3.googleusercontent.com/d/12nyEqGhNIA7pgXqDMWsbaTbS9YDciXf3" class="img-fluid" alt="WAVEA" />
                        </div>
                      </div>
                    </div>
                  
                  </div>  
                  
                  <div class="swiper-pagination"></div>

                </div>
              </div>
              
              <div class="banner-ad bg-success-subtle block-2" style="background:url('images/ad-image-1.png') no-repeat;background-position: right bottom">
                <div class="row banner-content p-5">

                  <div class="content-wrapper col-md-7">
                    <div class="categories sale mb-3 pb-3"><?= t('sale_20') ?></div>
                    <h3 class="banner-title"><?= t('spring_pots') ?></h3>
                    <a href="index.php?collection=1" class="btn btn-kjd-primary mt-3 d-inline-flex align-items-center">
                      <?= t('shop_now') ?> 
                      <svg width="20" height="20" class="ms-2"><use xlink:href="#arrow-right"></use></svg>
                    </a>
                  </div>

                </div>
              </div>

              <div class="banner-ad block-3" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border: 3px solid var(--kjd-earth-green); border-radius: 16px; box-shadow: 0 8px 30px rgba(16,40,32,0.12);">
                <div class="row banner-content p-5">
                  <div class="content-wrapper col-12 text-center">
                    <h3 class="item-title" style="color: var(--kjd-dark-green); font-weight: 800; font-size: 2rem; margin-bottom: 1.5rem;">Dárek zdarma</h3>
                    <p style="color: var(--kjd-dark-brown); font-size: 1.1rem; margin-bottom: 0; font-weight: 600; line-height: 1.6;">
                      <i class="fas fa-gift me-2" style="color: var(--kjd-gold-brown);"></i>Ke každé objednávce dostanete menší dárek!
                    </p>
                  </div>
                </div>
              </div>

            </div>
            <!-- / Banner Blocks -->
              
          </div>
        </div>
      </div>
    </section>


    <!-- Category section moved below reviews -->

    <section class="py-5 overflow-hidden kjd-hide">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">

            <div class="section-header d-flex flex-wrap flex-wrap justify-content-between mb-5">
              
              <h2 class="section-title">Newly Arrived Brands</h2>

              <div class="d-flex align-items-center">
                <a href="#" class="btn-link text-decoration-none">View All Categories →</a>
                <div class="swiper-buttons">
                  <button class="swiper-prev brand-carousel-prev btn btn-yellow">❮</button>
                  <button class="swiper-next brand-carousel-next btn btn-yellow">❯</button>
                </div>  
              </div>
            </div>
            
          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="brand-carousel swiper">
              <div class="swiper-wrapper">
                <!-- Keep static demo cards -->
                <div class="swiper-slide">
                  <div class="card mb-3 p-3 rounded-4 shadow border-0">
                    <div class="row g-0">
                      <div class="col-md-4">
                        <img src="images/product-thumb-11.jpg" class="img-fluid rounded" alt="Card title">
                      </div>
                      <div class="col-md-8">
                        <div class="card-body py-0">
                          <p class="text-muted mb-0">Amber Jar</p>
                          <h5 class="card-title">Honey best nectar you wish to get</h5>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

    <!-- O nás sekce -->
    <section class="py-5" style="background: #fff;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            
            <div style="background: var(--kjd-beige); border-radius: 16px; padding: 3rem 2rem; margin: 1.5rem 0 2rem; border: 2px solid var(--kjd-earth-green); box-shadow: 0 4px 20px rgba(16,40,32,0.08);">
              <div class="text-center mb-4">
                <div style="font-size: 3.5rem; color: var(--kjd-earth-green); margin-bottom: 1rem;">
                  <i class="fas fa-lightbulb"></i>
                </div>
                <h2 style="color: var(--kjd-dark-green); font-weight: 800; margin-bottom: 1rem; font-size: 2.5rem;">O nás</h2>
                <div style="width: 80px; height: 4px; background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown)); margin: 0 auto 2rem; border-radius: 2px;"></div>
              </div>
              <div class="row justify-content-center">
                <div class="col-md-10 col-lg-9">
                  <div style="background: #fff; padding: 2.5rem; border-radius: 16px; border: 2px solid var(--kjd-earth-green); box-shadow: 0 4px 20px rgba(16,40,32,0.08); position: relative;">
                    <div style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px; background: var(--kjd-beige); border: 2px solid var(--kjd-earth-green); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                      <i class="fas fa-quote-left" style="color: var(--kjd-earth-green); font-size: 0.9rem;"></i>
                    </div>
                    <p style="color: var(--kjd-dark-brown); font-size: 1.15rem; line-height: 1.9; margin: 1rem 0 0 0; text-align: center; font-weight: 500;">
                      <strong style="color: var(--kjd-dark-green);">KJD</strong> je malá značka zaměřená na originální 3D tištěné dekorace a lampy. Každý produkt navrhuji a vyrábím sám, aby byl jedinečný a kvalitní. Dbám na čistý design, příjemné světlo a detaily, které dělají domov útulnější.
                    </p>
                    <p style="color: var(--kjd-dark-brown); font-size: 1.15rem; line-height: 1.9; margin: 1.5rem 0 0 0; text-align: center; font-weight: 500;">
                      Mojí vizí je tvořit dostupné, krásné a osobní kousky, které dávají smysl a dělají radost.
                    </p>
                    <div style="text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--kjd-beige);">
                      <div style="display: inline-flex; align-items: center; gap: 0.75rem; color: var(--kjd-gold-brown); font-weight: 700; font-size: 1rem;">
                        <i class="fas fa-heart" style="color: var(--kjd-earth-green);"></i>
                        <span>Vytvořeno s láskou v České republice</span>
                        <i class="fas fa-heart" style="color: var(--kjd-earth-green);"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-5">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div class="bootstrap-tabs product-tabs">
              <div class="tabs-header d-flex justify-content-between border-bottom my-5">
                <h3><?= $searchQ !== '' ? 'Výsledky hledání: "' . htmlspecialchars($searchQ) . '"' : 'Nejnovější produkty' ?></h3>
              </div>
              <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active" id="nav-all" role="tabpanel" aria-labelledby="nav-all-tab">

                  <div class="product-grid row row-cols-2 row-cols-sm-3 row-cols-md-3 row-cols-lg-4 row-cols-xl-5">
                    <?php if (!empty($products)): ?>
                      <?php foreach ($products as $p): ?>
                        <?php
                          $img = htmlspecialchars(getProductImageSrc($p));
                          $name = htmlspecialchars($p['name'] ?? 'Product');
                          $price = isset($p['price']) ? (float)$p['price'] : 0;
                          $isPreorder = !empty($p['is_preorder']) && (int)$p['is_preorder'] === 1;
                          $isNew = !empty($p['is_new']) && (int)$p['is_new'] === 1;
                          $saleActive = false; $salePrice = 0; $saleEndIso = '';
                          
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
                                    $saleActive = true; $salePrice = $sp; $saleEndIso = date('c', $endTs);
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
                                 data-product-id="<?= (int)$p['id'] ?>" title="Přidat do oblíbených" style="z-index: 5;">
                                <svg width="24" height="24"><use xlink:href="#heart"></use></svg>
                              </a>
                            <?php else: ?>
                              <a href="login.php" class="btn-wishlist" title="Přihlaste se pro přidání do oblíbených" style="z-index: 5;">
                                <svg width="24" height="24"><use xlink:href="#heart"></use></svg>
                              </a>
                            <?php endif; ?>
                            <figure>
                              <img src="<?= $img ?>" class="tab-image" alt="<?= $name ?>" referrerpolicy="no-referrer">
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
                              <a href="product.php?id=<?= (int)$p['id'] ?>" class="btn btn-kjd-primary"><?= t('view_product') ?></a>
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
                    <?php else: ?>
                      <div class="col-12"><p>No products found.</p></div>
                    <?php endif; ?>
                  </div>
                  <!-- / product-grid -->
                  
                  <div class="text-center mt-5">
                    <a href="collections.php" class="btn btn-kjd-primary" style="padding: 1rem 2.5rem; font-size: 1.1rem; font-weight: 700;">
                      Zobrazit více
                    </a>
                  </div>
                  
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

    <!-- Doprava sekce -->
    <section class="py-5" style="background: #fff;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); padding: 2.5rem; border-radius: 16px; text-align: center; box-shadow: 0 4px 20px rgba(16,40,32,0.12);">
              <h3 style="color: #fff; font-weight: 800; margin-bottom: 1rem;">
                <i class="fas fa-shipping-fast me-2"></i>Doprava
              </h3>
              <p style="color: #fff; font-size: 1.2rem; font-weight: 600; margin: 0;">
                Expedice do 48h. Doprava od 99 Kč.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Proč naše lampy sekce -->
    <section class="py-5" style="background: #fff;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div style="background: var(--kjd-beige); border-radius: 16px; padding: 2rem; margin: 1.5rem 0 2rem; border: 2px solid var(--kjd-earth-green);">
              <div class="text-center mb-5">
                <h2 style="color: var(--kjd-dark-green); font-weight: 800; margin-bottom: 1rem;">Proč naše lampy</h2>
              </div>
              <div class="row">
              <div class="col-md-6 col-lg-3 mb-4">
                <div style="background: #fff; padding: 2rem; border-radius: 16px; border: 2px solid var(--kjd-earth-green); text-align: center; height: 100%; box-shadow: 0 4px 20px rgba(16,40,32,0.08); transition: transform 0.3s ease;">
                  <div style="font-size: 3rem; color: var(--kjd-earth-green); margin-bottom: 1rem;">
                    <i class="fas fa-flag"></i>
                  </div>
                  <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">Design vyrobený v ČR</h4>
                </div>
              </div>
              <div class="col-md-6 col-lg-3 mb-4">
                <div style="background: #fff; padding: 2rem; border-radius: 16px; border: 2px solid var(--kjd-earth-green); text-align: center; height: 100%; box-shadow: 0 4px 20px rgba(16,40,32,0.08); transition: transform 0.3s ease;">
                  <div style="font-size: 3rem; color: var(--kjd-earth-green); margin-bottom: 1rem;">
                    <i class="fas fa-hand-paper"></i>
                  </div>
                  <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">Ruční kompletace</h4>
                </div>
              </div>
              <div class="col-md-6 col-lg-3 mb-4">
                <div style="background: #fff; padding: 2rem; border-radius: 16px; border: 2px solid var(--kjd-earth-green); text-align: center; height: 100%; box-shadow: 0 4px 20px rgba(16,40,32,0.08); transition: transform 0.3s ease;">
                  <div style="font-size: 3rem; color: var(--kjd-earth-green); margin-bottom: 1rem;">
                    <i class="fas fa-leaf"></i>
                  </div>
                  <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">Šetrné materiály</h4>
                </div>
              </div>
              <div class="col-md-6 col-lg-3 mb-4">
                <div style="background: #fff; padding: 2rem; border-radius: 16px; border: 2px solid var(--kjd-earth-green); text-align: center; height: 100%; box-shadow: 0 4px 20px rgba(16,40,32,0.08); transition: transform 0.3s ease;">
                  <div style="font-size: 3rem; color: var(--kjd-earth-green); margin-bottom: 1rem;">
                    <i class="fas fa-shapes"></i>
                  </div>
                  <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 0.5rem;">Unikátní tvary</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Reviews sekce - přesunuta úplně dolů -->
    <section class="py-5" style="background: #fff;">
      <div class="container-fluid">
        <!-- Reviews strip -->
        <div class="kjd-reviews">
          <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 style="margin:0; font-weight:800; color: var(--kjd-dark-green);">
                <?= t('reviews_title') ?>
              </h4>
            </div>
            <div class="reviews-swiper swiper">
              <div class="swiper-wrapper">
                <?php
                try {
                  $rstmt = $conn->query("SELECT jmeno, prijmeni, text_recenze, hodnoceni, obrazek FROM recenze ORDER BY datum DESC LIMIT 12");
                  $revs = $rstmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) { $revs = []; }
                if ($revs):
                  foreach ($revs as $rev):
                ?>
                <div class="swiper-slide">
                  <div class="kjd-review-card">
                    <?php if (!empty($rev['obrazek'])): ?>
                      <div class="kjd-review-image">
                        <img src="../<?= htmlspecialchars($rev['obrazek']) ?>" 
                             alt="<?= t('reviews_photo_alt') ?>" 
                             class="review-image-clickable"
                             data-full-image="../<?= htmlspecialchars($rev['obrazek']) ?>"
                             style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem; cursor: pointer; transition: transform 0.2s ease;">
                      </div>
                    <?php endif; ?>
                    <div class="kjd-review-name"><?= htmlspecialchars(($rev['jmeno']??'').' '.($rev['prijmeni']??'')) ?></div>
                    <div class="kjd-review-stars">
                      <?php $s = (int)($rev['hodnoceni']??5); for ($i=0;$i<$s;$i++) echo '★'; ?>
                    </div>
                    <div class="kjd-review-text"><?= nl2br(htmlspecialchars($rev['text_recenze']??'')) ?></div>
                  </div>
                </div>
                <?php endforeach; else: ?>
                <div class="swiper-slide"><div class="kjd-review-card"><?= t('reviews_none') ?></div></div>
                <?php endif; ?>
              </div>
              <div class="swiper-pagination"></div>
            </div>
            <div class="reviews-controls d-flex justify-content-center align-items-center gap-3 mt-3">
              <button class="btn btn-sm" id="reviewsPrev" style="border:2px solid var(--kjd-earth-green); border-radius:8px; color: var(--kjd-dark-green); background:#fff;">❮</button>
              <span style="font-size: 0.9rem; color: var(--kjd-dark-brown); opacity: 0.8;">Swipe →</span>
              <button class="btn btn-sm" id="reviewsNext" style="border:2px solid var(--kjd-earth-green); border-radius:8px; color: var(--kjd-dark-green); background:#fff;">❯</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Promo banners - přesunuty pod recenze -->
    <section class="py-5" style="background: #fff;">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6">
            <div class="banner-ad mb-3" style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border: 3px solid var(--kjd-earth-green); border-radius: 16px; padding: 2rem; box-shadow: 0 8px 30px rgba(16,40,32,0.12);">
              <div class="banner-content text-center">
                <div class="categories" style="color: var(--kjd-earth-green); font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">
                  <i class="fas fa-info-circle me-2"></i>Důležité upozornění
                </div>
                <h3 class="banner-title" style="color: var(--kjd-dark-green); font-weight: 800; margin-bottom: 1rem;">Všechny lampy jsou na E14</h3>
                <p style="color: var(--kjd-dark-brown); font-size: 1.1rem; margin-bottom: 0; font-weight: 600; line-height: 1.6;">
                  <i class="fas fa-lightbulb me-2" style="color: var(--kjd-gold-brown);"></i>Lampy se prodávají bez žárovek!
                </p>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="banner-ad" style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); border-radius: 16px; padding: 2rem; box-shadow: 0 8px 30px rgba(16,40,32,0.12);">
              <div class="banner-content">
                <div class="categories" style="color: var(--kjd-beige); font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Novinky</div>
                <h3 class="banner-title" style="color: #fff; font-weight: 800; margin-bottom: 1rem;">Jarní kolekce</h3>
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 1.5rem;">Čerstvé novinky pro váš domov a zahradu.</p>
                <a href="index.php" class="btn" style="background: var(--kjd-beige); color: var(--kjd-dark-green); border: none; font-weight: 700; text-uppercase;">Nakupovat</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>


    
    <?php include 'includes/footer.php'; ?>

    <!-- Lightbox for review images -->
    <div id="lightbox" class="lightbox">
      <span class="lightbox-close">&times;</span>
      <div class="lightbox-content">
        <img id="lightbox-image" class="lightbox-image" src="" alt="">
      </div>
    </div>

    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <!-- Lightbox JavaScript -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // Reviews slider (Swiper)
        try {
          const reviewsSwiper = new Swiper('.reviews-swiper', {
            slidesPerView: 1,
            spaceBetween: 16,
            pagination: { el: '.reviews-swiper .swiper-pagination', clickable: true },
            breakpoints: {
              576: { slidesPerView: 2 },
              992: { slidesPerView: 3 }
            }
          });
          const prevBtn = document.getElementById('reviewsPrev');
          const nextBtn = document.getElementById('reviewsNext');
          if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', () => reviewsSwiper.slidePrev());
            nextBtn.addEventListener('click', () => reviewsSwiper.slideNext());
          }
        } catch (e) { console.warn('Swiper init failed', e); }

        const lightbox = document.getElementById('lightbox');
        const lightboxImage = document.getElementById('lightbox-image');
        const lightboxClose = document.querySelector('.lightbox-close');
        const reviewImages = document.querySelectorAll('.review-image-clickable');
        
        // Open lightbox when clicking on review images
        reviewImages.forEach(function(img) {
          img.addEventListener('click', function() {
            const fullImageSrc = this.getAttribute('data-full-image');
            lightboxImage.src = fullImageSrc;
            lightboxImage.alt = this.alt;
            lightbox.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
          });
        });
        
        // Close lightbox when clicking close button
        lightboxClose.addEventListener('click', function() {
          lightbox.style.display = 'none';
          document.body.style.overflow = 'auto'; // Restore scrolling
        });
        
        // Close lightbox when clicking outside the image
        lightbox.addEventListener('click', function(e) {
          if (e.target === lightbox) {
            lightbox.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
          }
        });
        
        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && lightbox.style.display === 'block') {
            lightbox.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
          }
        });
      });
    </script>
    
    <!-- Newsletter Popup Modal -->
    <div class="modal fade" id="newsletterModal" tabindex="-1" aria-labelledby="newsletterModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: 3px solid var(--kjd-earth-green);">
          <div class="modal-header" style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown)); color: #fff; border-radius: 17px 17px 0 0;">
            <h5 class="modal-title" id="newsletterModalLabel" style="font-weight: 800;">
              <i class="fas fa-gift me-2"></i>Vítejte v KJD!
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center p-4">
            <div class="mb-3">
              <i class="fas fa-envelope-open-text" style="font-size: 3rem; color: var(--kjd-earth-green);"></i>
            </div>
            <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1rem;">
              Získejte 10% slevu!
            </h4>
            <p style="color: #666; margin-bottom: 2rem;">
              Zaregistrujte se do našeho newsletteru a získejte okamžitě 10% slevu na první nákup. 
              Slevový kód vám pošleme na email.
            </p>
            <div class="alert alert-warning mb-3" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 1rem; text-align: left;">
              <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                <i class="fas fa-exclamation-triangle" style="color: #856404; font-size: 1.25rem; margin-top: 0.1rem;"></i>
                <div>
                  <strong style="color: #856404; font-size: 0.95rem;">Důležité upozornění:</strong>
                  <p style="color: #856404; font-size: 0.85rem; margin: 0.5rem 0 0 0; line-height: 1.5;">
                    Všechny naše emaily často končí ve složce Spam. Prosím, zkontrolujte si po registraci složku Spam/Promo, aby vám slevový kód neunikl!
                  </p>
                </div>
              </div>
            </div>
            <form id="newsletterForm">
              <div class="mb-3">
                <input type="email" class="form-control" id="newsletterEmail" placeholder="Váš email" 
                       style="border: 2px solid var(--kjd-earth-green); border-radius: 12px; padding: 0.75rem; font-weight: 600;" required>
              </div>
              <button type="submit" class="btn btn-kjd-primary w-100" style="padding: 0.75rem 2rem; font-size: 1.1rem;">
                <i class="fas fa-gift me-2"></i>Získat 10% slevu
              </button>
            </form>
            <div id="newsletterMessage" class="mt-3" style="display: none;"></div>
          </div>
        </div>
      </div>
    </div>

    <script>
      function addToCart(productId) {
        fetch('add-to-cart.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ product_id: productId })
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            alert('Produkt byl přidán do košíku');
          } else {
            alert(data.message || 'Nastala chyba při přidávání do košíku');
          }
        })
        .catch(err => {
          console.error(err);
          alert('Nastala chyba při přidávání do košíku');
        });
      }

      function toggleFavorite(productId) {
        fetch('ajax/toggle_favorite.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ product_id: productId })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const heartIcon = document.querySelector(`[data-product-id="${productId}"]`);
            if (heartIcon) {
              if (data.action === 'added') {
                heartIcon.style.color = '#dc3545';
                heartIcon.title = 'Odebrat z oblíbených';
              } else {
                heartIcon.style.color = '#666';
                heartIcon.title = 'Přidat do oblíbených';
              }
            }
          } else {
            alert('Chyba: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Chyba při přidávání do oblíbených');
        });
      }

      // Newsletter popup
      document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, checking newsletter popup');
        console.log('newsletterShown in localStorage:', localStorage.getItem('newsletterShown'));
        
        // Show popup after 3 seconds if not shown before
        if (!localStorage.getItem('newsletterShown')) {
          console.log('Newsletter not shown before, will show in 3 seconds');
          setTimeout(() => {
            console.log('Showing newsletter modal');
            const modal = new bootstrap.Modal(document.getElementById('newsletterModal'));
            modal.show();
          }, 3000);
        } else {
          console.log('Newsletter already shown, skipping popup');
        }

        // Newsletter form submission
        document.getElementById('newsletterForm').addEventListener('submit', function(e) {
          e.preventDefault();
          console.log('Newsletter form submitted');
          
          const email = document.getElementById('newsletterEmail').value;
          const messageDiv = document.getElementById('newsletterMessage');
          
          console.log('Email:', email);
          console.log('Sending request to ajax/newsletter_signup.php');
          
          fetch('ajax/newsletter_signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
          })
          .then(response => {
            console.log('Response status:', response.status);
            return response.json();
          })
          .then(data => {
            console.log('Response data:', JSON.stringify(data));
            if (data.success) {
              messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + data.message + '</div>';
              localStorage.setItem('newsletterShown', 'true');
              setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('newsletterModal')).hide();
              }, 2000);
            } else {
              messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + data.message + '</div>';
            }
            messageDiv.style.display = 'block';
          })
          .catch(error => {
            console.error('Error:', error);
            messageDiv.innerHTML = '<div class="alert alert-danger">Chyba při odesílání: ' + error.message + '</div>';
            messageDiv.style.display = 'block';
          });
        });

        // Mark as shown when modal is closed
        document.getElementById('newsletterModal').addEventListener('hidden.bs.modal', function() {
          localStorage.setItem('newsletterShown', 'true');
        });
      });
    </script>
    
    <!-- Custom Preloader Script -->
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const preloaderWrapper = document.querySelector('.preloader-wrapper');
        const percentageElement = document.querySelector('.preloader-percentage');
        let progress = 0;
        
        // Simulate loading progress with smooth increments
        const progressInterval = setInterval(() => {
          // Smaller, more frequent increments for smoother animation
          progress += Math.random() * 8 + 2; // Random increment between 2-10%
          if (progress > 100) progress = 100;
          
          percentageElement.textContent = Math.round(progress) + '%';
          
          if (progress >= 100) {
            clearInterval(progressInterval);
            
            // Add zoom out effect after a short delay
            setTimeout(() => {
              preloaderWrapper.classList.add('fade-out');
              
              // Hide preloader after animation completes
              setTimeout(() => {
                preloaderWrapper.style.display = 'none';
              }, 800);
            }, 500);
          }
        }, 150); // Faster interval for smoother animation
        
        // Fallback: hide preloader after 4 seconds regardless
        setTimeout(() => {
          if (preloaderWrapper.style.display !== 'none') {
            clearInterval(progressInterval);
            percentageElement.textContent = '100%';
            preloaderWrapper.classList.add('fade-out');
            setTimeout(() => {
              preloaderWrapper.style.display = 'none';
            }, 800);
          }
        }, 4000);
      });
      
      // Scroll to top button
      const scrollToTopBtn = document.createElement('button');
      scrollToTopBtn.className = 'scroll-to-top';
      scrollToTopBtn.innerHTML = '↑';
      scrollToTopBtn.setAttribute('aria-label', 'Zpět nahoru');
      scrollToTopBtn.onclick = () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      };
      document.body.appendChild(scrollToTopBtn);
      
      window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
          scrollToTopBtn.classList.add('show');
        } else {
          scrollToTopBtn.classList.remove('show');
        }
      });
      
      // Copy link functionality - use event delegation for dynamically loaded content
      document.addEventListener('click', function(e) {
        if (e.target.closest('.share-btn-compact.copy-link')) {
          e.preventDefault();
          const btn = e.target.closest('.share-btn-compact.copy-link');
          const url = btn.getAttribute('data-product-url');
          
          // Try modern clipboard API first
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
              // Show feedback
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
              fallbackCopyTextToClipboard(url, btn);
            });
          } else {
            // Fallback for older browsers
            fallbackCopyTextToClipboard(url, btn);
          }
        }
      });
      
      // Fallback copy function
      function fallbackCopyTextToClipboard(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
          const successful = document.execCommand('copy');
          if (successful) {
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.style.background = 'var(--kjd-earth-green)';
            button.style.borderColor = 'var(--kjd-earth-green)';
            button.style.color = '#fff';
            
            setTimeout(() => {
              button.innerHTML = originalHTML;
              button.style.background = '';
              button.style.borderColor = '';
              button.style.color = '';
            }, 1500);
          } else {
            alert('Odkaz: ' + text);
          }
        } catch (err) {
          console.error('Fallback copy failed:', err);
          alert('Odkaz: ' + text);
        }
        
        document.body.removeChild(textArea);
      }
      
    </script>
    
    <!-- Scroll to top button -->
    <!-- Button is created dynamically in JavaScript above -->
  </body>
</html>


