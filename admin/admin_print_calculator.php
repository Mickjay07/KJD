<?php
session_start();
require_once '../config.php';

// Check login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Handle Save/Delete
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save') {
            $filament_name = $_POST['filament_name'];
            $filament_2_name = !empty($_POST['filament_2_name']) ? $_POST['filament_2_name'] : null;
            $product_name = $_POST['product_name'];
            $weight = floatval($_POST['weight']);
            $weight_2 = !empty($_POST['weight_2']) ? floatval($_POST['weight_2']) : 0;
            $other_costs = floatval($_POST['other_costs']);
            $material_cost = floatval($_POST['material_cost']);
            $total_cost = floatval($_POST['total_cost']);
            $selling_price = floatval($_POST['selling_price']);
            $profit = floatval($_POST['profit']);
            $margin = floatval($_POST['margin']);
            $note = !empty($_POST['note']) ? $_POST['note'] : null;

            $stmt = $conn->prepare("INSERT INTO print_calculations (product_name, filament_name, filament_2_name, weight, weight_2, other_costs, material_cost, total_cost, selling_price, profit, margin, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$product_name, $filament_name, $filament_2_name, $weight, $weight_2, $other_costs, $material_cost, $total_cost, $selling_price, $profit, $margin, $note])) {
                $success_msg = "Výpočet byl uložen do historie.";
            } else {
                $error_msg = "Chyba při ukládání výpočtu.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $stmt = $conn->prepare("DELETE FROM print_calculations WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success_msg = "Záznam byl smazán.";
            } else {
                $error_msg = "Chyba při mazání záznamu.";
            }
        }
    }
}

// Fetch filaments
$filaments = $conn->query("SELECT * FROM filaments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch products (only ID, name, price)
$products = $conn->query("SELECT id, name, price FROM product ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch history
$history = $conn->query("SELECT * FROM print_calculations ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalkulačka tisku - KJD Administrace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        
        /* Result cards */
        .result-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid #eee;
            transition: transform 0.2s;
        }
        .result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .result-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--kjd-dark-green);
        }
        .result-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6c757d;
        }
        
        /* Select2 Customization */
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark"><i class="fas fa-calculator me-2 text-gold"></i>Kalkulačka tisku</h2>
                    <a href="admin_filaments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-cubes me-2"></i>Spravovat filamenty
                    </a>
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
                    <!-- Input Form -->
                    <div class="col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                <h5 class="card-title fw-bold">Vstupní data</h5>
                            </div>
                            <div class="card-body">
                                <form id="calculatorForm">
                                    <!-- Filament 1 -->
                                    <div class="mb-3">
                                        <label for="filament" class="form-label">Filament 1</label>
                                        <select class="form-select" id="filament" required>
                                            <option value="" selected disabled>Vyberte filament</option>
                                            <?php foreach ($filaments as $f): ?>
                                                <option value="<?php echo $f['price_per_kg']; ?>" data-name="<?php echo htmlspecialchars($f['name']); ?>"><?php echo htmlspecialchars($f['name']); ?> (<?php echo $f['price_per_kg']; ?> Kč/kg)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text" id="pricePerGram">Cena za 1g: - Kč</div>
                                    </div>

                                    <!-- Weight 1 -->
                                    <div class="mb-3">
                                        <label for="weight" class="form-label">Váha 1 (g)</label>
                                        <input type="number" class="form-control" id="weight" placeholder="např. 150" min="0" step="0.1">
                                    </div>

                                    <!-- 2nd Filament Toggle -->
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="useSecondFilament">
                                        <label class="form-check-label" for="useSecondFilament">Použít druhý filament</label>
                                    </div>

                                    <!-- Filament 2 (Hidden by default) -->
                                    <div id="secondFilamentGroup" class="d-none p-3 bg-light rounded mb-3 border">
                                        <div class="mb-3">
                                            <label for="filament2" class="form-label">Filament 2</label>
                                            <select class="form-select" id="filament2">
                                                <option value="" selected disabled>Vyberte filament</option>
                                                <?php foreach ($filaments as $f): ?>
                                                    <option value="<?php echo $f['price_per_kg']; ?>" data-name="<?php echo htmlspecialchars($f['name']); ?>"><?php echo htmlspecialchars($f['name']); ?> (<?php echo $f['price_per_kg']; ?> Kč/kg)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text" id="pricePerGram2">Cena za 1g: - Kč</div>
                                        </div>
                                        <div class="mb-0">
                                            <label for="weight2" class="form-label">Váha 2 (g)</label>
                                            <input type="number" class="form-control" id="weight2" placeholder="např. 50" min="0" step="0.1">
                                        </div>
                                    </div>

                                    <!-- Product Selection -->
                                    <div class="mb-3">
                                        <label for="product" class="form-label">Produkt (pro načtení ceny)</label>
                                        <select class="form-select select2" id="product">
                                            <option value="" selected disabled>Vyberte produkt nebo zadejte cenu ručně</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?php echo $p['price']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['price']; ?> Kč)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Selling Price (Editable) -->
                                    <div class="mb-3">
                                        <label for="sellingPriceInput" class="form-label">Prodejní cena (Kč)</label>
                                        <input type="number" class="form-control" id="sellingPriceInput" placeholder="Zadejte cenu" min="0" step="1">
                                    </div>

                                    <!-- Other Costs -->
                                    <div class="mb-3">
                                        <label for="otherCosts" class="form-label">Další náklady (elektřina, práce, atd.)</label>
                                        <input type="number" class="form-control" id="otherCosts" placeholder="např. 50" min="0" value="0">
                                    </div>
                                    
                                    <!-- Note -->
                                    <div class="mb-3">
                                        <label for="note" class="form-label">Poznámka</label>
                                        <textarea class="form-control" id="note" rows="2" placeholder="Např. marže se liší podle barvy..."></textarea>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary" onclick="calculate()">
                                            <i class="fas fa-calculator me-2"></i>Vypočítat
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Save Form (Hidden) -->
                                <form method="post" id="saveForm" class="mt-3 d-none">
                                    <input type="hidden" name="action" value="save">
                                    <input type="hidden" name="filament_name" id="saveFilamentName">
                                    <input type="hidden" name="filament_2_name" id="saveFilament2Name">
                                    <input type="hidden" name="product_name" id="saveProductName">
                                    <input type="hidden" name="weight" id="saveWeight">
                                    <input type="hidden" name="weight_2" id="saveWeight2">
                                    <input type="hidden" name="other_costs" id="saveOtherCosts">
                                    <input type="hidden" name="material_cost" id="saveMaterialCost">
                                    <input type="hidden" name="total_cost" id="saveTotalCost">
                                    <input type="hidden" name="selling_price" id="saveSellingPrice">
                                    <input type="hidden" name="profit" id="saveProfit">
                                    <input type="hidden" name="margin" id="saveMargin">
                                    <input type="hidden" name="note" id="saveNote">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-outline-success">
                                            <i class="fas fa-save me-2"></i>Uložit do historie
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Results -->
                    <div class="col-lg-8 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                <h5 class="card-title fw-bold">Výsledky kalkulace</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Material Cost -->
                                    <div class="col-md-6 col-xl-4">
                                        <div class="result-card">
                                            <div class="result-label">Cena materiálu</div>
                                            <div class="result-value text-primary" id="resMaterial">0 Kč</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Total Cost -->
                                    <div class="col-md-6 col-xl-4">
                                        <div class="result-card">
                                            <div class="result-label">Celkové náklady</div>
                                            <div class="result-value text-danger" id="resTotalCost">0 Kč</div>
                                            <small class="text-muted">Materiál + Další</small>
                                        </div>
                                    </div>

                                    <!-- Selling Price -->
                                    <div class="col-md-6 col-xl-4">
                                        <div class="result-card">
                                            <div class="result-label">Prodejní cena</div>
                                            <div class="result-value text-dark" id="resSellingPrice">0 Kč</div>
                                        </div>
                                    </div>

                                    <!-- Profit -->
                                    <div class="col-md-6 col-xl-6">
                                        <div class="result-card border-success" style="border-width: 2px;">
                                            <div class="result-label text-success">Zisk (Kč)</div>
                                            <div class="result-value text-success" id="resProfit">0 Kč</div>
                                        </div>
                                    </div>

                                    <!-- Margin -->
                                    <div class="col-md-6 col-xl-6">
                                        <div class="result-card border-warning" style="border-width: 2px;">
                                            <div class="result-label text-warning">Marže (%)</div>
                                            <div class="result-value text-warning" id="resMargin">0 %</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 p-3 bg-light rounded">
                                    <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>Detail výpočtu:</h6>
                                    <ul class="mb-0 text-muted small">
                                        <li>Filament 1: <span id="detailFilament1">0</span> Kč</li>
                                        <li id="detailFilament2Row" class="d-none">Filament 2: <span id="detailFilament2">0</span> Kč</li>
                                        <li>Náklady na materiál celkem: <span id="detailMaterialCost">0</span> Kč</li>
                                        <li>Další náklady: <span id="detailOtherCosts">0</span> Kč</li>
                                        <li><strong>Celkové náklady: <span id="detailTotalCost">0</span> Kč</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- History Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                <h5 class="card-title fw-bold">Historie výpočtů</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Datum</th>
                                                <th>Produkt</th>
                                                <th>Filamenty</th>
                                                <th>Váha</th>
                                                <th>Náklady</th>
                                                <th>Prodej</th>
                                                <th>Zisk</th>
                                                <th>Marže</th>
                                                <th class="text-end">Akce</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($history)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4 text-muted">Zatím žádná historie.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($history as $h): ?>
                                                    <tr>
                                                        <td class="text-muted small"><?php echo date('d.m.Y H:i', strtotime($h['created_at'])); ?></td>
                                                        <td>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($h['product_name']); ?></div>
                                                            <?php if (!empty($h['note'])): ?>
                                                                <div class="text-muted small mt-1"><i class="fas fa-sticky-note me-1 text-warning"></i><?php echo htmlspecialchars($h['note']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($h['filament_name']); ?>
                                                            <?php if (!empty($h['filament_2_name'])): ?>
                                                                <br><small class="text-muted">+ <?php echo htmlspecialchars($h['filament_2_name']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $h['weight']; ?> g
                                                            <?php if (!empty($h['weight_2']) && $h['weight_2'] > 0): ?>
                                                                <br><small class="text-muted">+ <?php echo $h['weight_2']; ?> g</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo number_format($h['total_cost'], 0, ',', ' '); ?> Kč</td>
                                                        <td><?php echo number_format($h['selling_price'], 0, ',', ' '); ?> Kč</td>
                                                        <td class="fw-bold <?php echo $h['profit'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo number_format($h['profit'], 0, ',', ' '); ?> Kč
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $h['margin'] > 30 ? 'bg-success' : ($h['margin'] > 0 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                                <?php echo number_format($h['margin'], 1, ',', ' '); ?> %
                                                            </span>
                                                        </td>
                                                        <td class="text-end">
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Opravdu smazat?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'default',
                width: '100%'
            });

            // Toggle 2nd Filament
            $('#useSecondFilament').change(function() {
                if ($(this).is(':checked')) {
                    $('#secondFilamentGroup').removeClass('d-none');
                } else {
                    $('#secondFilamentGroup').addClass('d-none');
                    $('#filament2').val('');
                    $('#weight2').val('');
                    $('#pricePerGram2').text('Cena za 1g: - Kč');
                }
                calculate();
            });

            // Update price per gram display when filament changes
            $('#filament').change(function() {
                const pricePerKg = parseFloat($(this).val());
                if (pricePerKg) {
                    const pricePerGram = pricePerKg / 1000;
                    $('#pricePerGram').text('Cena za 1g: ' + pricePerGram.toFixed(4) + ' Kč');
                } else {
                    $('#pricePerGram').text('Cena za 1g: - Kč');
                }
                calculate();
            });

            $('#filament2').change(function() {
                const pricePerKg = parseFloat($(this).val());
                if (pricePerKg) {
                    const pricePerGram = pricePerKg / 1000;
                    $('#pricePerGram2').text('Cena za 1g: ' + pricePerGram.toFixed(4) + ' Kč');
                } else {
                    $('#pricePerGram2').text('Cena za 1g: - Kč');
                }
                calculate();
            });

            // Auto-fill price when product selected
            $('#product').change(function() {
                const price = $(this).val();
                if (price) {
                    $('#sellingPriceInput').val(price);
                }
                calculate();
            });

            // Auto calculate on changes
            $('#weight, #weight2, #otherCosts, #sellingPriceInput, #note').on('change keyup', function() {
                calculate();
            });
        });

        function calculate() {
            // Get inputs
            const pricePerKg1 = parseFloat($('#filament').val()) || 0;
            const weight1 = parseFloat($('#weight').val()) || 0;
            
            const useSecond = $('#useSecondFilament').is(':checked');
            const pricePerKg2 = parseFloat($('#filament2').val()) || 0;
            const weight2 = parseFloat($('#weight2').val()) || 0;

            const sellingPrice = parseFloat($('#sellingPriceInput').val()) || 0;
            const otherCosts = parseFloat($('#otherCosts').val()) || 0;
            const note = $('#note').val();

            // Get names
            const filamentName = $('#filament option:selected').data('name') || '';
            const filament2Name = $('#filament2 option:selected').data('name') || '';
            let productName = $('#product option:selected').data('name') || 'Vlastní produkt';
            if ($('#product').val() === null || $('#product').val() === "") {
                 productName = "Vlastní kalkulace";
            }

            // Calculate Material 1
            const pricePerGram1 = pricePerKg1 / 1000;
            const matCost1 = weight1 * pricePerGram1;

            // Calculate Material 2
            let matCost2 = 0;
            if (useSecond) {
                const pricePerGram2 = pricePerKg2 / 1000;
                matCost2 = weight2 * pricePerGram2;
            }

            const materialCost = matCost1 + matCost2;
            const totalCost = materialCost + otherCosts;
            const profit = sellingPrice - totalCost;
            
            let margin = 0;
            if (sellingPrice > 0) {
                margin = ((sellingPrice - totalCost) / sellingPrice) * 100;
            }

            // Update UI
            $('#resMaterial').text(formatMoney(materialCost));
            $('#resTotalCost').text(formatMoney(totalCost));
            $('#resSellingPrice').text(formatMoney(sellingPrice));
            $('#resProfit').text(formatMoney(profit));
            $('#resMargin').text(margin.toFixed(1) + ' %');

            // Update Detail
            $('#detailFilament1').text(matCost1.toFixed(2));
            if (useSecond) {
                $('#detailFilament2Row').removeClass('d-none');
                $('#detailFilament2').text(matCost2.toFixed(2));
            } else {
                $('#detailFilament2Row').addClass('d-none');
            }
            $('#detailMaterialCost').text(materialCost.toFixed(2));
            $('#detailOtherCosts').text(otherCosts.toFixed(2));
            $('#detailTotalCost').text(totalCost.toFixed(2));

            // Color coding for profit/margin
            if (profit < 0) {
                $('#resProfit').removeClass('text-success').addClass('text-danger');
            } else {
                $('#resProfit').removeClass('text-danger').addClass('text-success');
            }

            // Update Save Form
            if (pricePerKg1 > 0 && sellingPrice > 0 && weight1 > 0) {
                $('#saveFilamentName').val(filamentName);
                $('#saveFilament2Name').val(useSecond ? filament2Name : '');
                $('#saveProductName').val(productName);
                $('#saveWeight').val(weight1);
                $('#saveWeight2').val(useSecond ? weight2 : 0);
                $('#saveOtherCosts').val(otherCosts);
                $('#saveMaterialCost').val(materialCost);
                $('#saveTotalCost').val(totalCost);
                $('#saveSellingPrice').val(sellingPrice);
                $('#saveProfit').val(profit);
                $('#saveMargin').val(margin);
                $('#saveNote').val(note);
                
                $('#saveForm').removeClass('d-none');
            } else {
                $('#saveForm').addClass('d-none');
            }
        }

        function formatMoney(amount) {
            return amount.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " Kč";
        }
    </script>
</body>
</html>
