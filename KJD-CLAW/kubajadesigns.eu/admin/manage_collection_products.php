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

// Načtení informací o kolekci
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

// Přidání produktů do kolekce
if (isset($_POST['add_products'])) {
    try {
        $conn->beginTransaction();
        
        // Nejprve smažeme všechny existující vazby
        $stmt = $conn->prepare("DELETE FROM product_collection_items WHERE collection_id = ?");
        $stmt->execute([$collection_id]);
        
        // Poté přidáme nové vazby
        if (!empty($_POST['products'])) {
            $stmt = $conn->prepare("
                INSERT INTO product_collection_items (product_id, collection_id, position) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($_POST['products'] as $index => $product_id) {
                $position = $index + 1;
                $stmt->execute([$product_id, $collection_id, $position]);
            }
        }
        
        $conn->commit();
        $successMessage = "Produkty byly úspěšně aktualizovány.";
    } catch(PDOException $e) {
        $conn->rollBack();
        $errorMessage = "Chyba při aktualizaci produktů: " . $e->getMessage();
    }
}

// Načtení všech produktů a označení těch, které jsou v kolekci
try {
    // Zkontrolujeme, zda existuje tabulka product2
    $tables = [];
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $products = [];
    
    // Načtení produktů z tabulky product
    if (in_array('product', $tables)) {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   CASE WHEN pci.collection_id IS NOT NULL THEN 1 ELSE 0 END as is_in_collection,
                   pci.position,
                   'product' as product_table
            FROM product p
            LEFT JOIN product_collection_items pci ON p.id = pci.product_id AND pci.collection_id = ?
            ORDER BY pci.position ASC, p.name ASC
        ");
        $stmt->execute([$collection_id]);
        $products = array_merge($products, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Načtení produktů z tabulky product2
    if (in_array('product2', $tables)) {
        $stmt = $conn->prepare("
            SELECT p.*, 
                   CASE WHEN pci.collection_id IS NOT NULL THEN 1 ELSE 0 END as is_in_collection,
                   pci.position,
                   'product2' as product_table
            FROM product2 p
            LEFT JOIN product_collection_items pci ON p.id = pci.product_id AND pci.collection_id = ?
            ORDER BY pci.position ASC, p.name ASC
        ");
        $stmt->execute([$collection_id]);
        $products = array_merge($products, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Seřadíme produkty podle pozice (produkty v kolekci první, pak ostatní)
    usort($products, function($a, $b) {
        if ($a['is_in_collection'] != $b['is_in_collection']) {
            return $b['is_in_collection'] - $a['is_in_collection'];
        }
        if ($a['is_in_collection'] && $b['is_in_collection']) {
            return ($a['position'] ?? 999) - ($b['position'] ?? 999);
        }
        return strcmp($a['name'], $b['name']);
    });
    
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání produktů: " . $e->getMessage();
}

// Helper funkce pro získání obrázku produktu
function getProductImageSrc($product) {
    if (empty($product['image_url'])) {
        return '../images/product-thumb-11.jpg';
    }
    
    $images = explode(',', $product['image_url']);
    $firstImage = trim($images[0] ?? '');
    
    if ($firstImage === '') {
        return '../images/product-thumb-11.jpg';
    }
    
    // Absolute URL already
    if (preg_match('~^https?://~i', $firstImage)) {
        return $firstImage;
    }
    
    // Paths that already start with uploads/...
    if (strpos($firstImage, 'uploads/') === 0 || strpos($firstImage, '/uploads/') === 0) {
        $normalized = ltrim($firstImage, '/');
        return '../' . $normalized;
    }
    
    // Otherwise treat as filename
    $webPath = '../uploads/products/' . $firstImage;
    $fsPath = __DIR__ . '/../uploads/products/' . $firstImage;
    if (file_exists($fsPath)) {
        return $webPath;
    }
    
    return '../images/product-thumb-11.jpg';
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa produktů v kolekci - KJD Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    
    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
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
      
      /* Product list */
      .product-list {
        list-style: none;
        padding: 0;
        margin: 0;
      }
      
      .product-item {
        padding: 1rem;
        margin-bottom: 0.75rem;
        background: #fff;
        border: 2px solid var(--kjd-beige);
        border-radius: 12px;
        cursor: move;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
      }
      
      .product-item:hover {
        border-color: var(--kjd-earth-green);
        box-shadow: 0 4px 15px rgba(16,40,32,0.1);
        transform: translateX(5px);
      }
      
      .product-item.selected {
        background: linear-gradient(135deg, rgba(76,100,68,0.1), rgba(202,186,156,0.1));
        border-color: var(--kjd-earth-green);
        border-width: 3px;
      }
      
      .product-item.sortable-ghost {
        opacity: 0.4;
        background: var(--kjd-beige);
      }
      
      .product-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        margin-right: 1rem;
        border: 2px solid var(--kjd-beige);
      }
      
      .product-info {
        flex: 1;
      }
      
      .product-name {
        font-weight: 700;
        color: var(--kjd-dark-green);
        margin-bottom: 0.25rem;
      }
      
      .product-price {
        color: var(--kjd-gold-brown);
        font-weight: 600;
        font-size: 0.9rem;
      }
      
      .product-badge {
        margin-left: 0.5rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
      }
      
      .form-check-input {
        width: 24px;
        height: 24px;
        margin-right: 1rem;
        cursor: pointer;
        border: 2px solid var(--kjd-earth-green);
      }
      
      .form-check-input:checked {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
      }
      
      .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .drag-handle {
        color: var(--kjd-earth-green);
        font-size: 1.2rem;
        margin-left: 0.5rem;
        cursor: grab;
      }
      
      .drag-handle:active {
        cursor: grabbing;
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
        
        .product-item {
          flex-direction: column;
          align-items: flex-start;
        }
        
        .product-image {
          margin-bottom: 0.5rem;
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
                                <i class="fas fa-cog me-2"></i>Správa produktů v kolekci
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">
                                <?php echo htmlspecialchars($collection['name']); ?>
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
                <i class="fas fa-list me-2"></i>Produkty v kolekci
            </h3>
            <p class="mb-4" style="color: #666;">
                <i class="fas fa-info-circle me-2"></i>
                Zaškrtněte produkty, které chcete přidat do kolekce. Přetáhněte produkty pro změnu jejich pořadí.
            </p>
            
            <form method="post" id="productsForm">
                <ul class="product-list" id="sortableProducts">
                    <?php if (empty($products)): ?>
                        <li class="text-center py-5">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--kjd-beige); margin-bottom: 1rem;"></i>
                            <h5 style="color: var(--kjd-dark-green);">Žádné produkty k zobrazení</h5>
                            <p class="text-muted">Zatím nebyly vytvořeny žádné produkty.</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <li class="product-item <?php echo $product['is_in_collection'] ? 'selected' : ''; ?>"
                                data-id="<?php echo $product['id']; ?>"
                                data-table="<?php echo htmlspecialchars($product['product_table'] ?? 'product'); ?>">
                                <input type="checkbox" 
                                       class="form-check-input" 
                                       name="products[]" 
                                       value="<?php echo $product['id']; ?>"
                                       <?php echo $product['is_in_collection'] ? 'checked' : ''; ?>
                                       id="product_<?php echo $product['id']; ?>">
                                
                                <img src="<?php echo htmlspecialchars(getProductImageSrc($product)); ?>" 
                                     class="product-image" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.src='../images/product-thumb-11.jpg';">
                                
                                <div class="product-info">
                                    <div class="product-name">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                        <span class="product-badge" style="background: var(--kjd-beige); color: var(--kjd-dark-green);">
                                            <?php echo htmlspecialchars($product['product_table'] ?? 'product'); ?>
                                        </span>
                                    </div>
                                    <div class="product-price">
                                        <?php echo number_format((float)($product['price'] ?? 0), 0, ',', ' '); ?> Kč
                                    </div>
                                </div>
                                
                                <i class="fas fa-grip-vertical drag-handle"></i>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" name="add_products" class="btn btn-kjd-primary">
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
        // Inicializace Sortable.js pro přetahování produktů
        const sortableList = document.getElementById('sortableProducts');
        if (sortableList) {
            new Sortable(sortableList, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                handle: '.drag-handle',
                filter: '.product-item:not(.selected)',
                onEnd: function(evt) {
                    // Po přetažení aktualizujeme pořadí pouze zaškrtnutých produktů
                    updateProductOrder();
                }
            });
        }

        // Aktualizace vzhledu při změně checkboxu
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const productItem = this.closest('.product-item');
                productItem.classList.toggle('selected', this.checked);
                
                // Pokud je produkt zaškrtnutý, přesuneme ho nahoru
                if (this.checked) {
                    const selectedItems = Array.from(document.querySelectorAll('.product-item.selected'));
                    const unselectedItems = Array.from(document.querySelectorAll('.product-item:not(.selected)'));
                    
                    // Seřadíme zaškrtnuté podle aktuálního pořadí
                    selectedItems.sort((a, b) => {
                        const aIndex = Array.from(sortableList.children).indexOf(a);
                        const bIndex = Array.from(sortableList.children).indexOf(b);
                        return aIndex - bIndex;
                    });
                    
                    // Přesuneme zaškrtnuté nahoru
                    selectedItems.forEach(item => {
                        sortableList.insertBefore(item, sortableList.firstChild);
                    });
                }
            });
        });

        // Funkce pro aktualizaci pořadí produktů
        function updateProductOrder() {
            const checkedItems = Array.from(document.querySelectorAll('.product-item.selected'));
            checkedItems.forEach((item, index) => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    // Pořadí je určeno pozicí v seznamu
                    // Produkty jsou uloženy v pořadí, v jakém jsou v DOM
                }
            });
        }

        // Při odeslání formuláře zajistíme správné pořadí
        document.getElementById('productsForm').addEventListener('submit', function(e) {
            // Produkty jsou již v správném pořadí v DOM, takže je to v pořádku
            // Pořadí se určí podle pozice v seznamu při zpracování na serveru
        });
    </script>
</body>
</html>

