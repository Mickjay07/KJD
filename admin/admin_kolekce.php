<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Zpracování formuláře pro novou kolekci
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_collection':
                $name = $_POST['name'];
                $slug = createSlug($name); // Funkci createSlug si musíte vytvořit
                $description = $_POST['description'];
                $launch_date = $_POST['launch_date'];
                $end_date = $_POST['end_date'];
                
                $stmt = $conn->prepare("INSERT INTO collections (name, slug, description, launch_date, end_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $launch_date, $end_date]);
                $successMessage = "Kolekce byla úspěšně vytvořena.";
                break;
                
            case 'add_to_collection':
                $collection_id = $_POST['collection_id'];
                $product_id = $_POST['product_id'];
                $position = $_POST['position'] ?? 0;
                
                $stmt = $conn->prepare("INSERT INTO product_collections (product_id, collection_id, position) VALUES (?, ?, ?)");
                $stmt->execute([$product_id, $collection_id, $position]);
                $successMessage = "Produkt byl přidán do kolekce.";
                break;
        }
    } catch(PDOException $e) {
        $errorMessage = "Chyba: " . $e->getMessage();
    }
}

// Získání všech kolekcí
$collections = $conn->query("SELECT * FROM collections ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa kolekcí - KJD Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'admin_header.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>Správa kolekcí</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCollectionModal">
                        <i class="fas fa-plus"></i> Nová kolekce
                    </button>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success"><?php echo $successMessage; ?></div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                <?php endif; ?>

                <!-- Seznam kolekcí -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Název</th>
                                <th>Počet produktů</th>
                                <th>Datum spuštění</th>
                                <th>Datum ukončení</th>
                                <th>Status</th>
                                <th>Akce</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $collection): 
                                // Získání počtu produktů v kolekci
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM product_collections WHERE collection_id = ?");
                                $stmt->execute([$collection['id']]);
                                $productCount = $stmt->fetchColumn();
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($collection['name']); ?></td>
                                    <td><?php echo $productCount; ?></td>
                                    <td><?php echo $collection['launch_date']; ?></td>
                                    <td><?php echo $collection['end_date']; ?></td>
                                    <td>
                                        <?php if ($collection['is_active']): ?>
                                            <span class="badge bg-success">Aktivní</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Neaktivní</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_collection.php?id=<?php echo $collection['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-success" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#addProductModal" 
                                                    data-collection-id="<?php echo $collection['id']; ?>">
                                                <i class="fas fa-plus"></i> Přidat produkty
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal pro novou kolekci -->
    <div class="modal fade" id="newCollectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nová kolekce</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_collection">
                        <div class="mb-3">
                            <label for="name" class="form-label">Název kolekce</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Popis</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="launch_date" class="form-label">Datum spuštění</label>
                            <input type="datetime-local" class="form-control" id="launch_date" name="launch_date">
                        </div>
                        <div class="mb-3">
                            <label for="end_date" class="form-label">Datum ukončení</label>
                            <input type="datetime-local" class="form-control" id="end_date" name="end_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                        <button type="submit" class="btn btn-primary">Vytvořit kolekci</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>