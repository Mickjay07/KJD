<?php
session_start();
require_once '../config.php';

// Check login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Handle Add/Edit/Delete
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            
            if (!empty($name) && $price > 0) {
                $stmt = $conn->prepare("INSERT INTO filaments (name, price_per_kg) VALUES (?, ?)");
                if ($stmt->execute([$name, $price])) {
                    $success_msg = "Filament byl úspěšně přidán.";
                } else {
                    $error_msg = "Chyba při přidávání filamentu.";
                }
            } else {
                $error_msg = "Vyplňte název a platnou cenu.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM filaments WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success_msg = "Filament byl smazán.";
            } else {
                $error_msg = "Chyba při mazání filamentu.";
            }
        } elseif ($_POST['action'] === 'edit') {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            
            if (!empty($name) && $price > 0) {
                $stmt = $conn->prepare("UPDATE filaments SET name = ?, price_per_kg = ? WHERE id = ?");
                if ($stmt->execute([$name, $price, $id])) {
                    $success_msg = "Filament byl upraven.";
                } else {
                    $error_msg = "Chyba při úpravě filamentu.";
                }
            } else {
                $error_msg = "Vyplňte název a platnou cenu.";
            }
        }
    }
}

// Fetch filaments
$filaments = $conn->query("SELECT * FROM filaments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa filamentů - KJD Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        body {
            font-family: 'SF Pro Display', sans-serif;
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(16,40,32,0.05);
        }
        .btn-primary {
            background-color: var(--kjd-dark-green);
            border-color: var(--kjd-dark-green);
        }
        .btn-primary:hover {
            background-color: var(--kjd-earth-green);
            border-color: var(--kjd-earth-green);
        }
        .text-gold { color: var(--kjd-gold-brown); }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark"><i class="fas fa-cubes me-2 text-gold"></i>Správa filamentů</h2>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Add New Filament -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                <h5 class="card-title fw-bold">Přidat nový filament</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="add">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Název filamentu</label>
                                        <input type="text" class="form-control" id="name" name="name" required placeholder="např. PLA Black">
                                    </div>
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Cena za 1 kg (Kč)</label>
                                        <input type="number" step="0.01" class="form-control" id="price" name="price" required placeholder="např. 500">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-plus me-2"></i>Přidat filament
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- List Filaments -->
                    <div class="col-md-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                <h5 class="card-title fw-bold">Seznam filamentů</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Název</th>
                                                <th>Cena za 1 kg</th>
                                                <th>Cena za 1 g</th>
                                                <th class="text-end">Akce</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($filaments)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4 text-muted">Zatím žádné filamenty.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($filaments as $f): ?>
                                                    <tr>
                                                        <td class="fw-bold"><?php echo htmlspecialchars($f['name']); ?></td>
                                                        <td><?php echo number_format($f['price_per_kg'], 2, ',', ' '); ?> Kč</td>
                                                        <td class="text-muted"><?php echo number_format($f['price_per_kg'] / 1000, 4, ',', ' '); ?> Kč</td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $f['id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Opravdu smazat?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>

                                                    <!-- Edit Modal -->
                                                    <div class="modal fade" id="editModal<?php echo $f['id']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Upravit filament</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <form method="post">
                                                                        <input type="hidden" name="action" value="edit">
                                                                        <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Název</label>
                                                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($f['name']); ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Cena za 1 kg (Kč)</label>
                                                                            <input type="number" step="0.01" class="form-control" name="price" value="<?php echo $f['price_per_kg']; ?>" required>
                                                                        </div>
                                                                        <div class="d-grid">
                                                                            <button type="submit" class="btn btn-primary">Uložit změny</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
