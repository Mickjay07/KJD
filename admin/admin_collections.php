<?php
require_once 'config.php';
session_start();

// Přidání importů pro PHPMailer
$phpmailer_loaded = false;
$phpmailer_error = null;

$fast_exception = __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
$fast_phpmailer = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
$fast_smtp = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

if (file_exists($fast_exception) && file_exists($fast_phpmailer) && file_exists($fast_smtp)) {
    require_once $fast_exception;
    require_once $fast_phpmailer;
    require_once $fast_smtp;
    $phpmailer_loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

// Zkusíme autoload, pokud rychlá cesta selže
if (!$phpmailer_loaded) {
    $autoload_paths = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        '/www/kubajadesigns.eu/vendor/autoload.php',
        '/www/kubajadesigns.eu/kubajadesigns.eu/vendor/autoload.php'
    ];

    foreach ($autoload_paths as $autoload_path) {
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $phpmailer_loaded = true;
                break;
            }
        }
    }
}

// Poslední možnost – ruční require přes seznam cest
if (!$phpmailer_loaded) {
    $phpmailer_paths = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
        dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/',
        __DIR__ . '/../../vendor/phpmailer/phpmailer/src/',
        '/www/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/',
        '/www/kubajadesigns.eu/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/'
    ];

    foreach ($phpmailer_paths as $basePath) {
        $exceptionPath = $basePath . 'Exception.php';
        $phpMailerPath = $basePath . 'PHPMailer.php';
        $smtpPath = $basePath . 'SMTP.php';

        if (file_exists($exceptionPath) && file_exists($phpMailerPath) && file_exists($smtpPath)) {
            require_once $exceptionPath;
            require_once $phpMailerPath;
            require_once $smtpPath;
            $phpmailer_loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
            if ($phpmailer_loaded) {
                break;
            }
        }
    }
}

if (!$phpmailer_loaded) {
    $phpmailer_error = 'PHPMailer knihovna se nepodařila načíst. Zkontrolujte prosím umístění složky vendor na serveru.';
    error_log('[admin_collections] ' . $phpmailer_error);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Kontrola přihlášení admina
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$successMessage = '';
$errorMessage = $phpmailer_error ?? '';

// Přidání funkce pro vytvoření slugu
function createSlug($string) {
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-z0-9-]/', '-', strtolower($string));
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Zpracování formuláře pro vytvoření nové kolekce produktů
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_product_collection') {
            $name = $_POST['name'];
            $slug = createSlug($name);
            $description = $_POST['description'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Zpracování obrázku - support both file upload and URL (including Google Drive)
            $image_url = '';
            
            // Priority 1: Check if URL was provided
            if (!empty($_POST['image_url'])) {
                $providedUrl = trim($_POST['image_url']);
                
                // Convert Google Drive URLs to direct image URLs
                if (preg_match('/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/', $providedUrl, $matches)) {
                    $image_url = 'https://lh3.googleusercontent.com/d/' . $matches[1];
                } elseif (preg_match('/drive\.google\.com\/uc\?.*[&?]id=([a-zA-Z0-9_-]+)/', $providedUrl, $matches)) {
                    $image_url = 'https://lh3.googleusercontent.com/d/' . $matches[1];
                } else {
                    // Direct URL (e.g., from other sources)
                    $image_url = $providedUrl;
                }
            }
            // Priority 2: If no URL, check for file upload
            elseif (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $target_dir = "uploads/collections/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $file_name = $slug . '-' . time() . '.' . $file_extension;
                $target_file = $target_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = $target_file;
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO product_collections_main 
                                  (name, slug, description, image_url, is_active, created_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $slug, $description, $image_url, $is_active]);
            
            $successMessage = "Kolekce produktů byla úspěšně vytvořena.";
        }
    } catch(Exception $e) {
        $errorMessage = "Chyba: " . $e->getMessage();
    }
}

// Načtení existujících kolekcí produktů
try {
    $stmt = $conn->query("SELECT * FROM product_collections_main ORDER BY created_at DESC");
    $product_collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání kolekcí produktů: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa kolekcí - KJD Admin</title>
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
      
      /* Table styles */
      .table {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
      }
      
      .table th {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: #fff;
        font-weight: 700;
        padding: 1rem;
        border: none;
      }
      
      .table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(202,186,156,0.1);
        vertical-align: middle;
      }
      
      .table tbody tr:hover {
        background: rgba(202,186,156,0.05);
      }
      
      .form-control, .form-select {
        border: 2px solid var(--kjd-earth-green);
        border-radius: 8px;
        padding: 0.75rem;
        font-weight: 600;
      }
      
      .form-control:focus, .form-select:focus {
        border-color: var(--kjd-dark-green);
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .form-label {
        font-weight: 700;
        color: var(--kjd-dark-green);
        margin-bottom: 0.5rem;
      }
      
      .form-check-input:checked {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
      }
      
      .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
      }
      
      .badge.bg-success {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
      }
      
      .badge.bg-danger {
        background: linear-gradient(135deg, #dc3545, #e83e8c) !important;
      }
      
      .alert {
        border-radius: 12px;
        border: 2px solid;
        font-weight: 600;
      }
      
      .alert-success {
        background: rgba(40,167,69,0.1);
        border-color: #28a745;
        color: #155724;
      }
      
      .alert-danger {
        background: rgba(220,53,69,0.1);
        border-color: #dc3545;
        color: #721c24;
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
        
        .btn-kjd-primary, .btn-kjd-secondary {
          padding: 0.8rem 1.5rem;
          font-size: 1rem;
        }
        
        .table-responsive {
          font-size: 0.9rem;
        }
        
        .table th, .table td {
          padding: 0.5rem;
        }
      }
    </style>
</head>
<body class="cart-page">
    <?php include 'admin_sidebar.php'; ?>

    <!-- Admin Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="h2 mb-0" style="color: var(--kjd-dark-green);">
                                <i class="fas fa-layer-group me-2"></i>Správa kolekcí
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Správa kolekcí produktů</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin.php" class="btn btn-kjd-secondary d-flex align-items-center">
                                <i class="fas fa-arrow-left me-2"></i>Zpět na dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Formulář pro novou kolekci -->
        <div class="cart-item mb-4">
            <h3 class="cart-product-name mb-4">
                <i class="fas fa-plus-circle me-2"></i>Nová kolekce produktů
            </h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_product_collection">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Název kolekce</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="image" class="form-label">Nahrát obrázek kolekce</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <div class="form-text">Nahrajte obrázek ze svého počítače</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="image_url" class="form-label">Nebo použijte URL obrázku</label>
                        <input type="text" class="form-control" id="image_url" name="image_url" placeholder="https://...">
                        <div class="form-text">Podporuje Google Drive, přímé URL a další</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Popis kolekce</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                    <label class="form-check-label" for="is_active">Aktivní kolekce</label>
                </div>
                
                <button type="submit" class="btn btn-kjd-primary">
                    <i class="fas fa-check me-2"></i>Vytvořit kolekci
                </button>
            </form>
        </div>

        <!-- Tabulka existujících kolekcí -->
        <div class="cart-item">
            <h3 class="cart-product-name mb-4">
                <i class="fas fa-list me-2"></i>Existující kolekce produktů
            </h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Slug</th>
                            <th>Obrázek</th>
                            <th>Stav</th>
                            <th>Vytvořeno</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($product_collections)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: var(--kjd-beige); margin-bottom: 1rem;"></i>
                                    <h5 style="color: var(--kjd-dark-green);">Žádné kolekce k zobrazení</h5>
                                    <p class="text-muted">Zatím nebyly vytvořeny žádné kolekce produktů.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($product_collections as $collection): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($collection['name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($collection['slug']); ?></code></td>
                                <td>
                                    <?php if ($collection['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($collection['image_url']); ?>" 
                                             alt="Náhled" style="max-height: 60px; border-radius: 8px; border: 2px solid var(--kjd-beige);">
                                    <?php else: ?>
                                        <span class="text-muted">Bez obrázku</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($collection['is_active']): ?>
                                        <span class="badge bg-success">Aktivní</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Neaktivní</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($collection['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="edit_collection.php?id=<?php echo $collection['id']; ?>" 
                                           class="btn btn-kjd-primary btn-sm">
                                            <i class="fas fa-edit"></i> Upravit
                                        </a>
                                        <a href="manage_collection_products.php?id=<?php echo $collection['id']; ?>" 
                                           class="btn btn-kjd-secondary btn-sm">
                                            <i class="fas fa-cog"></i> Spravovat produkty
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 