<?php
session_start();
require_once 'config.php';
require_once 'includes/lamp_functions.php';

// Fetch components
$bases = get_lamp_components($conn, 'base');
$shades = get_lamp_components($conn, 'shade');

// Base price for the lamp (can be adjusted or fetched from DB settings if needed)
$base_lamp_price = 1500; 
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Konfigurátor Lampy - KJD</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap & Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="fonts/sf-pro.css">

    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-beige:#CABA9C; 
        }
        
        body {
            font-family: 'SF Pro Display', sans-serif;
            background: #f8f9fa;
        }

        .configurator-header {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--kjd-earth-green);
        }

        .pdf-viewer {
            width: 100%;
            height: 600px;
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            background: #fff;
        }

        .config-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 1px solid rgba(202,186,156,0.2);
            position: sticky;
            top: 2rem;
        }

        .price-tag {
            font-size: 2rem;
            font-weight: 800;
            color: var(--kjd-gold-brown);
            text-align: right;
            margin-bottom: 1rem;
        }

        .btn-kjd-primary {
            background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green));
            color: #fff;
            border: none;
            padding: 1rem;
            width: 100%;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .btn-kjd-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: #fff;
        }
    </style>
</head>
<body>

    <?php include 'includes/icons.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="configurator-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-start">
                    <h1 class="fw-bold" style="color: var(--kjd-dark-green);">
                        <i class="fas fa-lightbulb me-3"></i>Konfigurátor Lampy
                    </h1>
                    <p class="lead mb-0" style="color: var(--kjd-gold-brown);">Sestavte si vlastní designovou lampu podle katalogu.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <div class="row">
            <!-- Left Column: PDF Catalogs -->
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="card shadow-sm">
                    <div class="card-header bg-white p-0">
                        <ul class="nav nav-tabs nav-fill" id="catalogTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold py-3" id="bases-tab" data-bs-toggle="tab" data-bs-target="#bases" type="button" role="tab" style="color: var(--kjd-dark-green);">
                                    <i class="fas fa-cubes me-2"></i>Katalog Podstavců (Spodky)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold py-3" id="shades-tab" data-bs-toggle="tab" data-bs-target="#shades" type="button" role="tab" style="color: var(--kjd-dark-green);">
                                    <i class="fas fa-umbrella me-2"></i>Katalog Vršky
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content" id="catalogTabsContent">
                            <!-- Bases Catalog (Spodky) -->
                            <div class="tab-pane fade show active" id="bases" role="tabpanel">
                                <iframe src="https://drive.google.com/file/d/1cydZvjA0tyXWHifhgSnBl-vo8Z6nYKwL/preview" class="pdf-viewer" style="border:0;"></iframe>
                            </div>
                            
                            <!-- Shades Catalog (Vršky) -->
                            <div class="tab-pane fade" id="shades" role="tabpanel">
                                <iframe src="https://drive.google.com/file/d/1VvZypWi89EmJ_vdfqT_0WJbf8dkXXZZN/preview" class="pdf-viewer" style="border:0;"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Configuration Form -->
            <div class="col-lg-4">
                <div class="config-card">
                    <h3 class="mb-4" style="color: var(--kjd-dark-green);">Moje sestava</h3>
                    
                    <form action="add_lamp_to_cart.php" method="POST" id="configForm">
                        <input type="hidden" name="base_price" value="<?= $base_lamp_price ?>">
                        
                        <!-- Base Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase text-muted small">1. Vyberte podstavec</label>
                            <select name="base_id" id="baseSelect" class="form-select form-select-lg" required onchange="updatePrice()">
                                <option value="" data-price="0" disabled selected>Zvolte podstavec...</option>
                                <?php foreach ($bases as $base): ?>
                                    <option value="<?= $base['id'] ?>" data-price="<?= $base['price_modifier'] ?>">
                                        <?= htmlspecialchars($base['name']) ?> 
                                        <?php if($base['price_modifier'] > 0) echo "(+" . $base['price_modifier'] . " Kč)"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Shade Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase text-muted small">2. Vyberte stínidlo</label>
                            <select name="shade_id" id="shadeSelect" class="form-select form-select-lg" required onchange="updatePrice()">
                                <option value="" data-price="0" disabled selected>Zvolte stínidlo...</option>
                                <?php foreach ($shades as $shade): ?>
                                    <option value="<?= $shade['id'] ?>" data-price="<?= $shade['price_modifier'] ?>">
                                        <?= htmlspecialchars($shade['name']) ?>
                                        <?php if($shade['price_modifier'] > 0) echo "(+" . $shade['price_modifier'] . " Kč)"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Cena celkem:</span>
                            <div class="price-tag" id="totalPrice"><?= number_format($base_lamp_price, 0, ',', ' ') ?> Kč</div>
                        </div>

                        <button type="submit" class="btn btn-kjd-primary">
                            <i class="fas fa-cart-plus me-2"></i>Vložit do košíku
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePrice = <?= $base_lamp_price ?>;

        function updatePrice() {
            const baseSelect = document.getElementById('baseSelect');
            const shadeSelect = document.getElementById('shadeSelect');
            
            const baseModifier = parseFloat(baseSelect.options[baseSelect.selectedIndex].dataset.price) || 0;
            const shadeModifier = parseFloat(shadeSelect.options[shadeSelect.selectedIndex].dataset.price) || 0;
            
            const total = basePrice + baseModifier + shadeModifier;
            
            document.getElementById('totalPrice').textContent = total.toLocaleString('cs-CZ') + ' Kč';
        }
    </script>
</body>
</html>
