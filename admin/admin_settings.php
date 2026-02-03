<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config.php';

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Ensure settings table exists and has validation columns
try {
    // Check if table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'settings'")->rowCount() > 0;
    
    // Check if new columns exist
    $columnsOK = false;
    if ($tableExists) {
        $colCheck = $conn->query("SHOW COLUMNS FROM settings LIKE 'contact_email'");
        if ($colCheck->rowCount() > 0) {
            $columnsOK = true;
        }
    }

    if (!$tableExists || !$columnsOK) {
        // Table missing OR columns missing -> Rebuild
        $conn->exec("DROP TABLE IF EXISTS settings");
        $conn->exec("CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contact_email VARCHAR(255),
            free_shipping_limit DECIMAL(10,2) DEFAULT 0,
            maintenance_mode TINYINT(1) DEFAULT 0,
            newsletter_enabled TINYINT(1) DEFAULT 0,
            newsletter_popup_delay INT DEFAULT 5,
            newsletter_popup_frequency INT DEFAULT 7,
            newsletter_always_show TINYINT(1) DEFAULT 0,
            banner_active TINYINT(1) DEFAULT 0,
            banner_text TEXT,
            banner_bg_color VARCHAR(10) DEFAULT '#8A6240',
            banner_text_color VARCHAR(10) DEFAULT '#ffffff',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $conn->exec("INSERT INTO settings (id, contact_email, free_shipping_limit, maintenance_mode, newsletter_enabled, banner_active, banner_text) 
                     VALUES (1, 'info@kubajadesigns.eu', 2000.00, 0, 1, 0, 'Vítejte na našem webu!')");
    } else {
        // Table exists and has columns, ensure row 1 exists
        $test = $conn->query("SELECT id FROM settings WHERE id = 1");
        if ($test->rowCount() == 0) {
             $conn->exec("INSERT INTO settings (id, contact_email, free_shipping_limit, maintenance_mode, newsletter_enabled, banner_active, banner_text) 
                     VALUES (1, 'info@kubajadesigns.eu', 2000.00, 0, 1, 0, 'Vítejte na našem webu!')");
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$success = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                try {
                    $stmt = $conn->prepare("UPDATE settings SET 
                        contact_email = ?, 
                        free_shipping_limit = ?, 
                        maintenance_mode = ? 
                        WHERE id = 1");
                    $stmt->execute([
                        $_POST['contact_email'] ?? '',
                        floatval($_POST['free_shipping_limit'] ?? 0),
                        isset($_POST['maintenance_mode']) ? 1 : 0
                    ]);
                    $success = 'Obecná nastavení uložena.';
                } catch (Exception $e) { $error = $e->getMessage(); }
                break;

            case 'update_banner':
                try {
                    $stmt = $conn->prepare("UPDATE settings SET 
                        banner_active = ?, 
                        banner_text = ?, 
                        banner_bg_color = ?, 
                        banner_text_color = ? 
                        WHERE id = 1");
                    $stmt->execute([
                        isset($_POST['banner_active']) ? 1 : 0,
                        $_POST['banner_text'] ?? '',
                        $_POST['banner_bg_color'] ?? '#8A6240',
                        $_POST['banner_text_color'] ?? '#ffffff'
                    ]);
                    $success = 'Banner uložen.';
                } catch (Exception $e) { $error = $e->getMessage(); }
                break;

            case 'change_password':
                $new_pass = $_POST['new_password'] ?? '';
                $confirm_pass = $_POST['confirm_password'] ?? '';
                if ($new_pass !== $confirm_pass) {
                    $error = 'Hesla se neshodují.';
                } elseif (strlen($new_pass) < 6) {
                    $error = 'Heslo musí mít alespoň 6 znaků.';
                } else {
                    try {
                        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                        $stmt->execute([$hash, $_SESSION['admin_id']]);
                        $success = 'Heslo změněno.';
                    } catch (Exception $e) { $error = $e->getMessage(); }
                }
                break;
                
            case 'add_admin':
                 $username = $_POST['admin_username'] ?? '';
                 $password = $_POST['admin_password'] ?? '';
                 if ($username && $password) {
                     try {
                         $hash = password_hash($password, PASSWORD_DEFAULT);
                         $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                         $stmt->execute([$username, $hash]);
                         $success = 'Nový administrátor přidán.';
                     } catch (Exception $e) { $error = "Chyba (možná jméno již existuje): " . $e->getMessage(); }
                 } else { $error = "Vyplňte jméno a heslo."; }
                 break;
        }
    }
}

// Fetch Data
$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$admins = $conn->query("SELECT id, username FROM admins")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nastavení | KubaJaDesigns Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 { font-family: 'SF Pro Display', sans-serif !important; }
      .nav-tabs .nav-link { color: var(--kjd-dark-green); font-weight: 600; }
      .nav-tabs .nav-link.active { border-color: var(--kjd-earth-green); border-bottom-color: transparent; color: var(--kjd-gold-brown); }
      .card { border: 2px solid var(--kjd-beige); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
      .card-header { background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); font-weight: 700; color: var(--kjd-dark-green); border-bottom: 2px solid var(--kjd-beige); }
      .btn-primary { background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); border: none; }
      .btn-primary:hover { background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); }
    </style>
</head>
<body class="bg-light">
<?php include 'admin_sidebar.php'; ?>

<div class="layout-wrapper layout-content-navbar">
  <div class="layout-container">
    <div class="layout-page">
      <div class="content-wrapper">
        <div class="container-xxl flex-grow-1 container-p-y">
            
            <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">Admin /</span> Nastavení</h4>

            <?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <ul class="nav nav-tabs mb-4" id="settingTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">Obecné</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="banner-tab" data-bs-toggle="tab" data-bs-target="#banner" type="button">Oznámení (Banner)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins" type="button">Administrátoři</button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- GENERAL -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card mb-4">
                        <div class="card-header">Základní nastavení webu</div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_general">
                                <div class="mb-3">
                                    <label class="form-label">Kontaktní Email</label>
                                    <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Doprava zdarma od (Kč)</label>
                                    <input type="number" step="0.01" name="free_shipping_limit" class="form-control" value="<?= htmlspecialchars($settings['free_shipping_limit'] ?? 0) ?>">
                                </div>
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenance" <?= ($settings['maintenance_mode']??0)==1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="maintenance">Režim údržby (Web nepřístupný pro zákazníky)</label>
                                </div>
                                <button type="submit" class="btn btn-primary">Uložit obecné</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- BANNER -->
                <div class="tab-pane fade" id="banner">
                    <div class="card mb-4">
                        <div class="card-header">Celostránkové Oznámení</div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_banner">
                                <div class="mb-3 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="banner_active" id="bannerActive" <?= ($settings['banner_active']??0)==1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="bannerActive">Aktivovat banner</label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Text Oznámení (HTML povoleno)</label>
                                    <textarea name="banner_text" class="form-control" rows="3"><?= htmlspecialchars($settings['banner_text'] ?? '') ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Barva Pozadí</label>
                                        <input type="color" name="banner_bg_color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['banner_bg_color'] ?? '#8A6240') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Barva Textu</label>
                                        <input type="color" name="banner_text_color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['banner_text_color'] ?? '#ffffff') ?>">
                                    </div>
                                </div>
                                
                                <!-- Preview -->
                                <div class="mb-3 p-3 border rounded">
                                    <label class="mb-2">Náhled:</label>
                                    <div id="bannerPreview" style="padding:10px; text-align:center;">Text</div>
                                </div>

                                <button type="submit" class="btn btn-primary">Uložit Banner</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ADMINS -->
                <div class="tab-pane fade" id="admins">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">Změna mého hesla</div>
                                <div class="card-body p-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="change_password">
                                        <div class="mb-3">
                                            <label class="form-label">Nové heslo</label>
                                            <input type="password" name="new_password" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Potvrzení hesla</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Změnit heslo</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">Přidat administrátora</div>
                                <div class="card-body p-4">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_admin">
                                        <div class="mb-3">
                                            <label class="form-label">Uživatelské jméno</label>
                                            <input type="text" name="admin_username" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Heslo</label>
                                            <input type="password" name="admin_password" class="form-control" required>
                                        </div>
                                        <button type="submit" class="btn btn-success">Přidat</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">Seznam administrátorů</div>
                        <div class="card-body">
                            <table class="table">
                                <thead><tr><th>ID</th><th>Jméno</th></tr></thead>
                                <tbody>
                                    <?php foreach($admins as $a): ?>
                                    <tr>
                                        <td><?= $a['id'] ?></td>
                                        <td><?= htmlspecialchars($a['username']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live Preview for Banner
    const txt = document.querySelector('[name="banner_text"]');
    const bg = document.querySelector('[name="banner_bg_color"]');
    const fg = document.querySelector('[name="banner_text_color"]');
    const prev = document.getElementById('bannerPreview');

    function updatePreview() {
        prev.style.backgroundColor = bg.value;
        prev.style.color = fg.value;
        prev.innerHTML = txt.value;
    }
    
    txt.addEventListener('input', updatePreview);
    bg.addEventListener('input', updatePreview);
    fg.addEventListener('input', updatePreview);
    updatePreview();
</script>
</body>
</html>