<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';

// Fetch Components
$bases = $conn->query("SELECT * FROM lamp_components WHERE type = 'base' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$shades = $conn->query("SELECT * FROM lamp_components WHERE type = 'shade' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Konfigurátor Lamp - Admin</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin_style.css">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'admin_sidebar.php'; ?>
        
        <div id="page-content-wrapper" class="w-100">
            <div class="container-fluid p-4">
                <h2 class="mb-4">Konfigurátor Lamp - Komponenty</h2>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">Akce provedena úspěšně.</div>
                <?php endif; ?>

                <div class="row">
                    <!-- Add New Component -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Přidat komponentu</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="process_lamp_config.php">
                                    <div class="mb-3">
                                        <label class="form-label">Typ</label>
                                        <select name="type" class="form-select" required>
                                            <option value="base">Podstavec (Spodek)</option>
                                            <option value="shade">Stínidlo (Vršek)</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Název</label>
                                        <input type="text" name="name" class="form-control" required placeholder="např. Model A1">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Příplatek (Kč)</label>
                                        <input type="number" name="price_modifier" class="form-control" value="0" step="1">
                                    </div>
                                    <button type="submit" name="add_component" class="btn btn-success w-100">
                                        <i class="fas fa-plus"></i> Přidat
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card shadow-sm mt-3">
                            <div class="card-body">
                                <a href="setup_lamp_db.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-database"></i> Inicializovat Databázi
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- List Components -->
                    <div class="col-md-8">
                        <div class="row">
                            <!-- Bases -->
                            <div class="col-md-6">
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Podstavce</h5>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($bases as $base): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($base['name']) ?></strong>
                                                    <?php if ($base['price_modifier'] > 0): ?>
                                                        <span class="badge bg-warning text-dark">+<?= $base['price_modifier'] ?> Kč</span>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST" action="process_lamp_config.php" onsubmit="return confirm('Opravdu smazat?');">
                                                    <input type="hidden" name="id" value="<?= $base['id'] ?>">
                                                    <button type="submit" name="delete_component" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (empty($bases)): ?>
                                            <li class="list-group-item text-muted">Žádné podstavce</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <!-- Shades -->
                            <div class="col-md-6">
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Stínidla</h5>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($shades as $shade): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= htmlspecialchars($shade['name']) ?></strong>
                                                    <?php if ($shade['price_modifier'] > 0): ?>
                                                        <span class="badge bg-warning text-dark">+<?= $shade['price_modifier'] ?> Kč</span>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST" action="process_lamp_config.php" onsubmit="return confirm('Opravdu smazat?');">
                                                    <input type="hidden" name="id" value="<?= $shade['id'] ?>">
                                                    <button type="submit" name="delete_component" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (empty($shades)): ?>
                                            <li class="list-group-item text-muted">Žádná stínidla</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
