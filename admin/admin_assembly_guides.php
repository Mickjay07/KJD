<?php
session_start();
require_once 'config.php';

// Kontrola přihlášení
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.php');
    exit;
}

$message = '';
$error = '';

// Připravit proměnnou pro režim editace a případně ji načíst hned na začátku
$edit_guide = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $conn->prepare("SELECT * FROM assembly_guides WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_guide = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Pokud selže, necháme $edit_guide = null a ukážeme chybu až níže, aby stránka zůstala funkční
        $error = 'Chyba při načítání návodu k editaci: ' . $e->getMessage();
    }
}

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $stmt = $conn->prepare("
                        INSERT INTO assembly_guides 
                        (product_id, product_category, title, description, steps_json, tips, tools_needed, estimated_time, difficulty_level) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['product_id'] ?: null,
                        $_POST['product_category'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['steps_json'],
                        $_POST['tips'],
                        $_POST['tools_needed'],
                        (int)$_POST['estimated_time'],
                        $_POST['difficulty_level']
                    ]);
                    $message = 'Návod byl úspěšně přidán.';
                    break;

                case 'edit':
                    $stmt = $conn->prepare("
                        UPDATE assembly_guides 
                        SET product_id = ?, product_category = ?, title = ?, description = ?, 
                            steps_json = ?, tips = ?, tools_needed = ?, estimated_time = ?, difficulty_level = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['product_id'] ?: null,
                        $_POST['product_category'],
                        $_POST['title'],
                        $_POST['description'],
                        $_POST['steps_json'],
                        $_POST['tips'],
                        $_POST['tools_needed'],
                        (int)$_POST['estimated_time'],
                        $_POST['difficulty_level'],
                        (int)$_POST['id']
                    ]);
                    $message = 'Návod byl úspěšně upraven.';
                    break;

                case 'delete':
                    $stmt = $conn->prepare("DELETE FROM assembly_guides WHERE id = ?");
                    $stmt->execute([(int)$_POST['id']]);
                    $message = 'Návod byl úspěšně smazán.';
                    break;
            }
        }
    } catch (Exception $e) {
        $error = 'Chyba: ' . $e->getMessage();
    }
}

// Načtení návodů
$stmt = $conn->prepare("
    SELECT ag.*, p.name as product_name 
    FROM assembly_guides ag 
    LEFT JOIN product p ON ag.product_id = p.id 
    ORDER BY ag.created_at DESC
");
$stmt->execute();
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Načtení produktů pro dropdown (podle kolekcí obsahujících "lamp"/"lampa")
$stmt = $conn->prepare("
    SELECT DISTINCT p.id, p.name, pcm.name AS category_name
    FROM product p
    INNER JOIN product_collection_items pci ON pci.product_id = p.id
    INNER JOIN product_collections_main pcm ON pcm.id = pci.collection_id
    WHERE LOWER(pcm.name) LIKE '%lamp%'
       OR LOWER(pcm.name) LIKE '%lampa%'
    ORDER BY pcm.name, p.name
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pokud v režimu editace vybraný produkt není v nabídce (např. změna filtrů), přidáme ho
if ($edit_guide && !empty($edit_guide['product_id'])) {
    $present = false;
    foreach ($products as $pr) {
        if ((int)$pr['id'] === (int)$edit_guide['product_id']) { $present = true; break; }
    }
    if (!$present) {
        $stmt = $conn->prepare("SELECT id, name FROM product WHERE id = ?");
        $stmt->execute([(int)$edit_guide['product_id']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['category_name'] = '—';
            $products[] = $row;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa návodů na sestavení - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #CABA9C;
            font-family: 'Inter', sans-serif;
        }
        .main-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 20px;
            padding: 30px;
        }
        .page-title {
            color: #4D2D18;
            font-weight: 700;
            margin-bottom: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4D2D18, #8A6240);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #3A1F0F, #6B4A2F);
        }
        .table th {
            background-color: #f8f9fa;
            color: #4D2D18;
            font-weight: 600;
        }
        .badge-easy { background-color: #28a745; }
        .badge-medium { background-color: #ffc107; color: #000; }
        .badge-hard { background-color: #dc3545; }
        .steps-preview {
            max-height: 100px;
            overflow-y: auto;
            font-size: 0.9em;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">
                <i class="fas fa-tools"></i> Správa návodů na sestavení
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#guideModal">
                <i class="fas fa-plus"></i> Přidat návod
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabulka návodů -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Název</th>
                        <th>Produkt/Kategorie</th>
                        <th>Obtížnost</th>
                        <th>Čas</th>
                        <th>Kroky</th>
                        <th>Vytvořeno</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guides as $guide): ?>
                        <tr>
                            <td><?php echo $guide['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($guide['title']); ?></strong>
                                <?php if ($guide['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($guide['description'], 0, 100)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($guide['product_name']): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($guide['product_name']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($guide['product_category']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $guide['difficulty_level']; ?>">
                                    <?php 
                                    switch($guide['difficulty_level']) {
                                        case 'easy': echo 'Snadné'; break;
                                        case 'medium': echo 'Střední'; break;
                                        case 'hard': echo 'Těžké'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $guide['estimated_time']; ?> min</td>
                            <td>
                                <?php 
                                $steps = json_decode($guide['steps_json'], true);
                                echo is_array($steps) ? count($steps) : 0;
                                ?> kroků
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($guide['created_at'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?php echo $guide['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteGuide(<?php echo $guide['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal pro přidání/úpravu návodu -->
    <div class="modal fade" id="guideModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?php echo $edit_guide ? 'Upravit návod' : 'Přidat návod'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $edit_guide ? 'edit' : 'add'; ?>">
                        <?php if ($edit_guide): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_guide['id']; ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Konkrétní produkt</label>
                                    <select name="product_id" class="form-select">
                                        <option value="">-- Vyberte produkt --</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>" 
                                                <?php echo ($edit_guide && $edit_guide['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($product['name']); ?><?php if (!empty($product['category_name'])): ?> — <?php echo htmlspecialchars($product['category_name']); ?><?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Nebo zadejte kategorii níže</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kategorie produktu</label>
                                    <input type="text" name="product_category" class="form-control" 
                                           value="<?php echo $edit_guide ? htmlspecialchars($edit_guide['product_category']) : 'lampa'; ?>"
                                           placeholder="např. lampa, stolní lampa">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Název návodu *</label>
                            <input type="text" name="title" class="form-control" required
                                   value="<?php echo $edit_guide ? htmlspecialchars($edit_guide['title']) : ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Popis</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo $edit_guide ? htmlspecialchars($edit_guide['description']) : ''; ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Obtížnost</label>
                                    <select name="difficulty_level" class="form-select">
                                        <option value="easy" <?php echo ($edit_guide && $edit_guide['difficulty_level'] == 'easy') ? 'selected' : ''; ?>>Snadné</option>
                                        <option value="medium" <?php echo (!$edit_guide || $edit_guide['difficulty_level'] == 'medium') ? 'selected' : ''; ?>>Střední</option>
                                        <option value="hard" <?php echo ($edit_guide && $edit_guide['difficulty_level'] == 'hard') ? 'selected' : ''; ?>>Těžké</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Odhadovaný čas (min)</label>
                                    <input type="number" name="estimated_time" class="form-control" 
                                           value="<?php echo $edit_guide ? $edit_guide['estimated_time'] : '30'; ?>" min="5" max="300">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Potřebné nástroje</label>
                                    <input type="text" name="tools_needed" class="form-control" 
                                           value="<?php echo $edit_guide ? htmlspecialchars($edit_guide['tools_needed']) : ''; ?>"
                                           placeholder="šroubovák, kleště...">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kroky sestavení *</label>
                            <div id="stepsBuilder"></div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addStepBtn">
                                    <i class="fas fa-plus"></i> Přidat krok
                                </button>
                                <small class="text-muted">Každý krok může mít název, popis, URL obrázku (volitelné) a upozornění (volitelné).</small>
                            </div>
                            <input type="hidden" name="steps_json" id="steps_json">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Užitečné tipy</label>
                            <textarea name="tips" class="form-control" rows="4" 
                                      placeholder="Každý tip na nový řádek"><?php echo $edit_guide ? htmlspecialchars($edit_guide['tips']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_guide ? 'Uložit změny' : 'Přidat návod'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Steps builder
        const stepsContainer = document.getElementById('stepsBuilder');
        const addStepBtn = document.getElementById('addStepBtn');
        let steps = [];

        try {
            steps = <?php echo json_encode($edit_guide && !empty($edit_guide['steps_json']) ? json_decode($edit_guide['steps_json'], true) : []); ?> || [];
        } catch(e) { steps = []; }

        function blankStep() {
            return { title: '', description: '', image: '', warning: '' };
        }

        function renderSteps() {
            stepsContainer.innerHTML = '';
            if (!steps.length) steps.push(blankStep());
            steps.forEach((step, idx) => {
                const card = document.createElement('div');
                card.className = 'card mb-3';
                card.innerHTML = `
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Krok ${idx + 1}</strong>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" data-action="up" data-index="${idx}" ${idx===0?'disabled':''}><i class="fas fa-arrow-up"></i></button>
                            <button type="button" class="btn btn-outline-secondary" data-action="down" data-index="${idx}" ${idx===steps.length-1?'disabled':''}><i class="fas fa-arrow-down"></i></button>
                            <button type="button" class="btn btn-outline-danger" data-action="remove" data-index="${idx}"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Název kroku *</label>
                                <input type="text" class="form-control" data-field="title" data-index="${idx}" value="${escapeHtml(step.title)}" placeholder="Např. Připravte šroubky">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">URL obrázku (volitelné)</label>
                                <input type="text" class="form-control" data-field="image" data-index="${idx}" value="${escapeHtml(step.image||'')}" placeholder="https://...">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Popis *</label>
                                <textarea class="form-control" rows="3" data-field="description" data-index="${idx}" placeholder="Podrobný popis kroků..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Upozornění (volitelné)</label>
                                <input type="text" class="form-control" data-field="warning" data-index="${idx}" value="${escapeHtml(step.warning||'')}" placeholder="Na co si dát pozor">
                            </div>
                            ${step.image ? `<div class="col-12"><img src="${escapeAttr(step.image)}" alt="Náhled" class="img-fluid rounded border"></div>` : ''}
                        </div>
                    </div>`;
                stepsContainer.appendChild(card);
            });

            // Set textarea values after adding to DOM
            steps.forEach((step, idx) => {
                const ta = stepsContainer.querySelector(`textarea[data-index="${idx}"][data-field="description"]`);
                if (ta) ta.value = step.description || '';
            });

            // Wire events
            stepsContainer.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const i = parseInt(btn.getAttribute('data-index'), 10);
                    const action = btn.getAttribute('data-action');
                    if (action === 'remove') { steps.splice(i,1); renderSteps(); return; }
                    if (action === 'up' && i>0) { [steps[i-1], steps[i]] = [steps[i], steps[i-1]]; renderSteps(); return; }
                    if (action === 'down' && i<steps.length-1) { [steps[i+1], steps[i]] = [steps[i], steps[i+1]]; renderSteps(); return; }
                });
            });
            stepsContainer.querySelectorAll('input[data-field], textarea[data-field]').forEach(el => {
                el.addEventListener('input', () => {
                    const i = parseInt(el.getAttribute('data-index'), 10);
                    const field = el.getAttribute('data-field');
                    steps[i][field] = el.value;
                    if (field === 'image') renderSteps(); // refresh preview if image URL changes
                });
            });
        }

        function escapeHtml(str) {
            if (str == null) return '';
            return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));
        }
        function escapeAttr(str) {
            return escapeHtml(str).replace(/"/g, '&quot;');
        }

        addStepBtn.addEventListener('click', () => { steps.push(blankStep()); renderSteps(); });

        // Before submit -> serialize
        document.addEventListener('submit', function(e){
            const hidden = document.getElementById('steps_json');
            // basic validation: at least one step with title+description
            const valid = steps.some(s => (s.title||'').trim() && (s.description||'').trim());
            if (!valid) {
                e.preventDefault();
                alert('Přidejte alespoň jeden krok a vyplňte Název a Popis.');
                return false;
            }
            hidden.value = JSON.stringify(steps);
        }, true);

        // Initialize
        renderSteps();
        <?php if ($edit_guide): ?>
            // Automaticky otevřít modal pro editaci
            document.addEventListener('DOMContentLoaded', function() {
                new bootstrap.Modal(document.getElementById('guideModal')).show();
            });
        <?php endif; ?>

        function deleteGuide(id) {
            if (confirm('Opravdu chcete smazat tento návod?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
