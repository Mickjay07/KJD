<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$collection_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$successMessage = '';
$errorMessage = '';

// Funkce pro vytvoření slugu
function createSlug($string) {
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-z0-9-]/', '-', strtolower($string));
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Načtení dat kolekce
try {
    $stmt = $conn->prepare("SELECT * FROM product_collections_main WHERE id = ?");
    $stmt->execute([$collection_id]);
    $collection = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$collection) {
        header('Location: admin_collections.php');
        exit;
    }
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání kolekce: " . $e->getMessage();
}

// Zpracování formuláře pro úpravu kolekce
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_collection'])) {
    try {
        $name = $_POST['name'];
        $slug = createSlug($name);
        $description = $_POST['description'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Zpracování nového obrázku - support both file upload and URL (including Google Drive)
        $image_url = $collection['image_url'] ?? '';
        
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
            
            // Delete old uploaded file if it was replaced with a URL
            if (!empty($collection['image_url']) && file_exists($collection['image_url'])) {
                @unlink($collection['image_url']);
            }
        }
        // Priority 2: If no URL, check for file upload
        elseif (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $target_dir = "uploads/collections/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $file_name = $slug . '-' . time() . '.' . $ext;
                $target_file = $target_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    // Smazání starého obrázku, pokud existuje (only delete files, not URLs)
                    if (!empty($collection['image_url']) && file_exists($collection['image_url'])) {
                        @unlink($collection['image_url']);
                    }
                    $image_url = $target_file;
                }
            }
        }

        $stmt = $conn->prepare("
            UPDATE product_collections_main 
            SET name = ?, slug = ?, description = ?, image_url = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $slug, $description, $image_url, $is_active, $collection_id
        ]);
        
        $successMessage = "Kolekce byla úspěšně aktualizována.";
        
        // Aktualizace dat kolekce pro zobrazení
        $collection['name'] = $name;
        $collection['slug'] = $slug;
        $collection['description'] = $description;
        $collection['image_url'] = $image_url;
        $collection['is_active'] = $is_active;
    } catch(PDOException $e) {
        $errorMessage = "Chyba při aktualizaci kolekce: " . $e->getMessage();
    }
}

// Helper funkce pro získání cesty k obrázku
function getImagePath($image_url) {
    if (empty($image_url)) {
        return '';
    }
    
    // Absolute URL
    if (preg_match('~^https?://~i', $image_url)) {
        return $image_url;
    }
    
    // Relative path
    if (strpos($image_url, 'uploads/') === 0) {
        return '../' . $image_url;
    }
    
    return '../' . $image_url;
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upravit kolekci - KJD Admin</title>
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
      
      .form-check-input {
        width: 24px;
        height: 24px;
        border: 2px solid var(--kjd-earth-green);
      }
      
      .form-check-input:checked {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
      }
      
      .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .current-image {
        max-width: 300px;
        max-height: 300px;
        border-radius: 12px;
        border: 3px solid var(--kjd-beige);
        box-shadow: 0 4px 15px rgba(16,40,32,0.1);
        margin-bottom: 1rem;
      }
      
      .image-preview {
        margin-top: 1rem;
      }
      
      .image-preview img {
        max-width: 200px;
        max-height: 200px;
        border-radius: 8px;
        border: 2px solid var(--kjd-earth-green);
        margin-top: 0.5rem;
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
        
        .cart-item {
          padding: 1.5rem;
        }
        
        .btn-kjd-primary, .btn-kjd-secondary {
          padding: 0.8rem 1.5rem;
          font-size: 1rem;
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
                                <i class="fas fa-edit me-2"></i>Upravit kolekci
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">
                                <?php echo htmlspecialchars($collection['name'] ?? ''); ?>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin_collections.php" class="btn btn-kjd-secondary d-flex align-items-center">
                                <i class="fas fa-arrow-left me-2"></i>Zpět na kolekce
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

        <div class="cart-item">
            <h3 class="cart-product-name mb-4">
                <i class="fas fa-cog me-2"></i>Upravit informace o kolekci
            </h3>
            
            <form method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Název kolekce</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($collection['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($collection['slug'] ?? ''); ?>" 
                               readonly style="background-color: #f8f9fa; cursor: not-allowed;">
                        <small class="text-muted">Slug se automaticky generuje z názvu</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Popis kolekce</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($collection['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Současný obrázek</label>
                    <?php if (!empty($collection['image_url'])): 
                        $imagePath = getImagePath($collection['image_url']);
                    ?>
                        <div>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                 alt="Současný obrázek" 
                                 class="current-image"
                                 onerror="this.src='../images/product-thumb-11.jpg';">
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" style="background: var(--kjd-beige); border-color: var(--kjd-earth-green); color: var(--kjd-dark-green);">
                            <i class="fas fa-info-circle me-2"></i>Žádný obrázek není nahrán
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="image" class="form-label">Nahrát nový obrázek</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                        <small class="text-muted">Nahrajte obrázek ze svého počítače</small>
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <label class="form-label mt-2">Náhled nového obrázku:</label>
                            <img id="previewImg" src="" alt="Náhled">
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="image_url" class="form-label">Nebo použijte URL obrázku</label>
                        <input type="text" class="form-control" id="image_url" name="image_url" placeholder="https://...">
                        <small class="text-muted">Podporuje Google Drive, přímé URL a další</small>
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active"
                           <?php echo (!empty($collection['is_active']) && $collection['is_active']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active" style="font-weight: 700; color: var(--kjd-dark-green); font-size: 1.1rem;">
                        Aktivní kolekce
                    </label>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" name="update_collection" class="btn btn-kjd-primary">
                        <i class="fas fa-save me-2"></i>Uložit změny
                    </button>
                    <a href="admin_collections.php" class="btn btn-kjd-secondary">
                        <i class="fas fa-times me-2"></i>Zrušit
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>

