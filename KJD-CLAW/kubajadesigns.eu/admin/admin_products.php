<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Získání všech kolekcí pro filtrování
$collections = $conn->query("SELECT * FROM product_collections_main ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Filtrování podle kolekce
$collection_filter = isset($_GET['collection']) ? (int)$_GET['collection'] : null;

// Zpracování mazání produktu
if (isset($_POST['delete_product'])) {
    $id = (int)$_POST['product_id'];
    $table = $_POST['product_table']; // Zjistíme, z jaké tabulky se má produkt smazat
    try {
        $conn->beginTransaction();
        
        // Nejprve smažeme vazby na kolekce
        $stmt = $conn->prepare("DELETE FROM product_collection_items WHERE product_id = ?");
        $stmt->execute([$id]);
        
        // Pak smažeme produkt
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        
        $conn->commit();
        $successMessage = "Produkt byl úspěšně smazán.";
    } catch(PDOException $e) {
        $conn->rollBack();
        $errorMessage = "Chyba při mazání produktu: " . $e->getMessage();
    }
}

// Získání seznamu produktů z tabulky product
try {
    if ($collection_filter) {
        $stmt = $conn->prepare("
            SELECT p.*, GROUP_CONCAT(pcm.name) as collections, 'product' as product_table
            FROM product p
            LEFT JOIN product_collection_items pci ON p.id = pci.product_id
            LEFT JOIN product_collections_main pcm ON pci.collection_id = pcm.id
            WHERE pci.collection_id = ?
            GROUP BY p.id
            ORDER BY p.id DESC
        ");
        $stmt->execute([$collection_filter]);
    } else {
        $stmt = $conn->prepare("
            SELECT p.*, GROUP_CONCAT(pcm.name) as collections, 'product' as product_table
            FROM product p
            LEFT JOIN product_collection_items pci ON p.id = pci.product_id
            LEFT JOIN product_collections_main pcm ON pci.collection_id = pcm.id
            GROUP BY p.id
            ORDER BY p.id DESC
        ");
        $stmt->execute();
    }
    $all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání produktů: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa produktů - KJD Administrace</title>
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
        
        .table-responsive {
          font-size: 0.9rem;
        }
        
        .table th, .table td {
          padding: 0.5rem;
        }
        
        .badge {
          font-size: 0.7rem;
          padding: 0.25rem 0.5rem;
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
        
        .table th, .table td {
          padding: 0.3rem;
          font-size: 0.8rem;
        }
        
        .container-fluid {
          padding-left: 0.5rem;
          padding-right: 0.5rem;
        }
        }
    </style>
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
                    <h1><i class="fas fa-box me-3"></i>Správa produktů</h1>
                    <p>Přehled a správa všech produktů</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- Flash zprávy -->
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show cart-item">
                        <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show cart-item">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Akce -->
                <div class="cart-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="cart-product-name mb-0">Akce</h2>
                        <div>
                            <a href="admin_novy_produkt.php" class="btn btn-kjd-primary me-2">
                                <i class="fas fa-plus"></i> Nový produkt
                            </a>
                            <a href="novy_produkt_product3.php" class="btn btn-kjd-secondary">
                                <i class="fas fa-plus"></i> Nový produkt3
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Filtry -->
                <div class="cart-item">
                    <h3 class="cart-product-name mb-3">
                        <i class="fas fa-filter me-2"></i>Filtry
                    </h3>
                        <form method="get" class="d-flex gap-2">
                        <select name="collection" class="form-select" style="max-width: 200px;">
                                <option value="">Všechny kolekce</option>
                                <?php foreach ($collections as $collection): ?>
                                    <option value="<?php echo $collection['id']; ?>" 
                                            <?php echo $collection_filter == $collection['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($collection['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <button type="submit" class="btn btn-kjd-secondary">Filtrovat</button>
                        </form>
                    </div>

                <!-- Tabulka produktů -->
                <div class="cart-item">
                    <h2 class="cart-product-name mb-4">
                        <i class="fas fa-list me-2"></i>Seznam produktů
                    </h2>
                        <table class="table apple-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Název</th>
                                    <th>Cena</th>
                                    <th>Stav skladu</th>
                                    <th>Kolekce</th>
                                    <th>Dostupné barvy</th>
                                    <th>Nedostupné barvy</th>
                                    <th>Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_products)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Žádné produkty</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_products as $product): ?>
                                        <tr <?php echo !empty($product['is_hidden']) ? 'style="opacity: 0.6; background-color: #f8f9fa;"' : ''; ?>>
                                            <td><?php echo $product['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($product['name']); ?>
                                                <?php if (!empty($product['is_hidden'])): ?>
                                                    <span class="badge bg-warning text-dark ms-2" title="Skrytý produkt - dostupný pouze s přímým odkazem">
                                                        <i class="fas fa-eye-slash"></i> Skrytý
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($product['price'], 0, ',', ' '); ?> Kč</td>
                                            <td>
                                                <span class="badge bg-<?php echo $product['stock_status'] === 'in_stock' ? 'success' : 
                                                    ($product['stock_status'] === 'out_of_stock' ? 'danger' : 'warning'); ?>">
                                                    <?php echo $product['stock_status'] === 'in_stock' ? 'Skladem' : 
                                                        ($product['stock_status'] === 'out_of_stock' ? 'Vyprodáno' : 'Předobjednávky'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['collections'] ?? ''); ?></td>
                                            <td>
                                                <?php 
                                                if (!empty($product['colors'])) {
                                                    $colors = explode(',', $product['colors']);
                                                    foreach ($colors as $color) {
                                                        $color = trim($color);
                                                        echo '<span class="color-preview" style="background-color: ' . $color . ';"></span>';
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if (!empty($product['unavailable_colors'])) {
                                                    $colors = explode(',', $product['unavailable_colors']);
                                                    foreach ($colors as $color) {
                                                        $color = trim($color);
                                                        echo '<span class="color-preview" style="background-color: ' . $color . ';"></span>';
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="admin_edit_product.php?id=<?php echo $product['id']; ?>&type=<?php echo $product['product_table']; ?>" 
                                                       class="btn btn-kjd-primary btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $product['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Modal pro potvrzení smazání -->
                                                <div class="modal fade" id="deleteModal<?php echo $product['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Potvrzení smazání</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Opravdu chcete smazat produkt "<?php echo htmlspecialchars($product['name']); ?>"?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                                                                <form method="post">
                                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                    <input type="hidden" name="product_table" value="<?php echo $product['product_table']; ?>">
                                                                    <button type="submit" name="delete_product" class="btn btn-danger">Smazat</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
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