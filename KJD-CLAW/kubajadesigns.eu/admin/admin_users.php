<?php
require_once 'config.php';
session_start();

// Kontrola, zda je uživatel přihlášený jako admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

// Načtení seznamu uživatelů
try {
    $stmt = $conn->query("SELECT *, voucher_balance FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání uživatelů: " . $e->getMessage();
}

// Zpracování formulářů
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'toggle_active' && isset($_POST['id'])) {
            $id = $_POST['id'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $id]);
            
            $_SESSION['message'] = "Stav uživatele byl úspěšně změněn.";
            header('Location: admin_users.php');
            exit;
        } elseif ($action === 'add_user') {
            // Přidání nového uživatele
            $email = $_POST['email'] ?? '';
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $initial_points = (int)($_POST['initial_points'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Kontrola, zda email již neexistuje
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Uživatel s tímto emailem již existuje.');
            }
            
            $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, phone, points, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $password, $first_name, $last_name, $phone, $initial_points, $is_active]);
            
            // Pokud byly přidány počáteční body, zaznamenat do historie
            if ($initial_points > 0) {
                $new_user_id = $conn->lastInsertId();
                $stmt = $conn->prepare("INSERT INTO points_history (user_id, points, description) VALUES (?, ?, ?)");
                $stmt->execute([$new_user_id, $initial_points, 'Počáteční body při vytvoření účtu']);
            }
            
            $_SESSION['message'] = "Uživatel byl úspěšně přidán.";
            header('Location: admin_users.php');
            exit;
        } elseif ($action === 'delete_user' && isset($_POST['id'])) {
            $id = $_POST['id'];
            
            // Kontrola, zda uživatel nemá objednávky
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Nelze smazat uživatele, který má objednávky.');
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['message'] = "Uživatel byl úspěšně smazán.";
            header('Location: admin_users.php');
            exit;
        } elseif ($action === 'update_voucher_balance' && isset($_POST['id'])) {
            $id = $_POST['id'];
            $new_balance = $_POST['voucher_balance'] ?? 0;

            // Aktualizace zůstatku voucheru
            $stmt = $conn->prepare("UPDATE users SET voucher_balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $id]);

            $_SESSION['message'] = "Zůstatek voucheru byl úspěšně aktualizován.";
            header('Location: admin_users.php');
            exit;
        }
    } catch(Exception $e) {
        $errorMessage = "Chyba: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa uživatelů - KJD Admin</title>
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
        
        .table-responsive {
          font-size: 0.9rem;
        }
        
        .table th, .table td {
          padding: 0.5rem;
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
                                <i class="fas fa-users me-2"></i>Správa uživatelů
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Správa uživatelských účtů</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-kjd-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus-circle me-2"></i>Přidat uživatele
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
                
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="cart-item">
            <h3 class="cart-product-name mb-4">
                <i class="fas fa-list me-2"></i>Seznam uživatelů
            </h3>
            <div class="table-responsive">
                <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Jméno</th>
                                        <th>Email</th>
                                        <th>Body</th>
                                        <th>Zůstatek voucheru</th>
                                        <th>Registrace</th>
                                        <th>Poslední přihlášení</th>
                                        <th>Aktivní</th>
                                        <th>Akce</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($users)): ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo number_format($user['points'] ?? 0, 0, ',', ' '); ?></td>
                                            <td><?php echo number_format($user['voucher_balance'], 2, ',', ' '); ?> Kč</td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                            <td><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Nikdy'; ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-success' : 'btn-danger'; ?>" style="border-radius: 8px; font-weight: 600;">
                                                        <?php echo $user['is_active'] ? 'Aktivní' : 'Neaktivní'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <a href="admin_user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-kjd-secondary btn-sm">
                                                        <i class="fas fa-eye"></i> Detail
                                                    </a>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Opravdu chcete smazat tohoto uživatele?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #dc3545, #c82333); color: #fff; border: none; border-radius: 8px; font-weight: 600;">
                                                            <i class="fas fa-trash"></i> Smazat
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--kjd-beige); margin-bottom: 1rem;"></i>
                                                <h5 style="color: var(--kjd-dark-green);">Žádní uživatelé k zobrazení</h5>
                                                <p class="text-muted">Zatím nebyli vytvořeni žádní uživatelé.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
        </div>
    </div>
    
    <!-- Modal pro přidání uživatele -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Přidat nového uživatele</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Heslo *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="first_name" class="form-label">Jméno *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Příjmení *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="initial_points" class="form-label">Počáteční body</label>
                            <input type="number" class="form-control" id="initial_points" name="initial_points" min="0" value="0">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Aktivní účet</label>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-kjd-secondary" data-bs-dismiss="modal">Zrušit</button>
                            <button type="submit" class="btn btn-kjd-primary">Přidat uživatele</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>