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
$user = null;
$orders = [];

// Kontrola, zda byl předán ID uživatele
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_users.php');
    exit;
}

$user_id = $_GET['id'];

// Načtení dat uživatele
try {
    $stmt = $conn->prepare("SELECT *, voucher_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['message'] = "Uživatel nebyl nalezen.";
        header('Location: admin_users.php');
        exit;
    }
    
    // Načtení objednávek uživatele
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání uživatele: " . $e->getMessage();
}

// Zpracování formuláře pro aktualizaci uživatele
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_user') {
            $email = $_POST['email'] ?? '';
            $first_name = $_POST['first_name'] ?? '';
            $last_name = $_POST['last_name'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password'] ?? '';
            
            // Kontrola, zda email již neexistuje u jiného uživatele
            if ($email !== $user['email']) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Uživatel s tímto emailem již existuje.');
                }
            }
            
            // Aktualizace uživatele
            if (!empty($password)) {
                // Aktualizace včetně hesla
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, is_active = ?, password = ? WHERE id = ?");
                $stmt->execute([$email, $first_name, $last_name, $is_active, $password_hash, $user_id]);
            } else {
                // Aktualizace bez hesla
                $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$email, $first_name, $last_name, $is_active, $user_id]);
            }
            
            $_SESSION['message'] = "Uživatel byl úspěšně aktualizován.";
            header('Location: admin_user_detail.php?id=' . $user_id);
            exit;
        } elseif ($action === 'update_voucher_balance') {
            $new_balance = $_POST['voucher_balance'] ?? 0;

            // Aktualizace zůstatku voucheru
            $stmt = $conn->prepare("UPDATE users SET voucher_balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $user_id]);

            $_SESSION['message'] = "Zůstatek voucheru byl úspěšně aktualizován.";
            header('Location: admin_user_detail.php?id=' . $user_id);
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
    <title>Detail uživatele - KJD Admin</title>
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
      
      .form-control, .form-select {
        border: 2px solid var(--kjd-earth-green);
        border-radius: 8px;
        padding: 0.75rem;
        font-weight: 600;
      }
      
      .form-control:focus, .form-select:focus {
        border-color: var(--kjd-dark-green);
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .form-label {
        font-weight: 700;
        color: var(--kjd-dark-green);
        margin-bottom: 0.5rem;
      }
      
      .form-check-input:checked {
        background-color: var(--kjd-earth-green);
        border-color: var(--kjd-earth-green);
      }
      
      .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
      }
      
      .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
      }
      
      .badge.bg-success {
        background: linear-gradient(135deg, #28a745, #20c997) !important;
      }
      
      .badge.bg-danger {
        background: linear-gradient(135deg, #dc3545, #e83e8c) !important;
      }
      
      .badge.bg-warning {
        background: linear-gradient(135deg, #ffc107, #ff8c00) !important;
      }
      
      .badge.bg-info {
        background: linear-gradient(135deg, #17a2b8, #6f42c1) !important;
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
      
      .form-control-static {
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px solid var(--kjd-beige);
        color: var(--kjd-dark-green);
        font-weight: 600;
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
                                <i class="fas fa-user me-2"></i>Detail uživatele
                            </h1>
                            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin_users.php" class="btn btn-kjd-secondary d-flex align-items-center">
                                <i class="fas fa-arrow-left me-2"></i>Zpět na seznam
                            </a>
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
        
        <div class="row">
            <div class="col-md-6">
                <div class="cart-item mb-4">
                    <h3 class="cart-product-name mb-4">
                        <i class="fas fa-user-circle me-2"></i>Informace o uživateli
                    </h3>
                                <form method="post">
                                    <input type="hidden" name="action" value="update_user">
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">E-mail *</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Heslo</label>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Ponechte prázdné pro zachování stávajícího hesla">
                                        <small class="text-muted">Vyplňte pouze pokud chcete změnit heslo.</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="first_name" class="form-label">Jméno *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="last_name" class="form-label">Příjmení *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Telefon</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Aktivní účet</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Registrace</label>
                                        <p class="form-control-static"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></p>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Poslední přihlášení</label>
                                        <p class="form-control-static"><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Nikdy'; ?></p>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-kjd-primary">
                                        <i class="fas fa-save me-2"></i>Uložit změny
                                    </button>
                                </form>
                </div>

                <!-- Nová sekce pro správu voucheru -->
                <div class="cart-item mb-4">
                    <h3 class="cart-product-name mb-4">
                        <i class="fas fa-wallet me-2"></i>Správa voucheru
                    </h3>
                    <form method="post">
                        <input type="hidden" name="action" value="update_voucher_balance">
                        <div class="mb-3">
                            <label for="voucher_balance" class="form-label">Zůstatek voucheru</label>
                            <input type="number" class="form-control" id="voucher_balance" name="voucher_balance" value="<?php echo number_format($user['voucher_balance'], 2, '.', ''); ?>" step="0.01" min="0" required>
                            <small class="text-muted">Aktuální zůstatek: <strong><?php echo number_format($user['voucher_balance'], 2, ',', ' '); ?> Kč</strong></small>
                        </div>
                        <button type="submit" class="btn btn-kjd-primary">
                            <i class="fas fa-save me-2"></i>Uložit zůstatek
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="cart-item">
                    <h3 class="cart-product-name mb-4">
                        <i class="fas fa-shopping-cart me-2"></i>Objednávky uživatele
                    </h3>
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Datum</th>
                                                    <th>Stav</th>
                                                    <th>Celkem</th>
                                                    <th>Akce</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orders as $order): ?>
                                                <tr>
                                                    <td><?php echo $order['id']; ?></td>
                                                    <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge <?php 
                                                            switch($order['payment_status'] ?? $order['status']) {
                                                                case 'pending': echo 'bg-warning'; break;
                                                                case 'processing': echo 'bg-info'; break;
                                                                case 'paid': 
                                                                case 'completed': echo 'bg-success'; break;
                                                                case 'cancelled': echo 'bg-danger'; break;
                                                                default: echo 'bg-secondary';
                                                            }
                                                        ?>">
                                                            <?php 
                                                                $status = $order['payment_status'] ?? $order['status'];
                                                                switch($status) {
                                                                    case 'pending': echo 'Čeká na platbu'; break;
                                                                    case 'processing': echo 'Zpracovává se'; break;
                                                                    case 'paid':
                                                                    case 'completed': echo 'Zaplaceno'; break;
                                                                    case 'cancelled': echo 'Zrušeno'; break;
                                                                    default: echo htmlspecialchars($status);
                                                                }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td><strong><?php echo number_format($order['total_price'], 0, ',', ' '); ?> Kč</strong></td>
                                                    <td>
                                                        <a href="admin_order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-kjd-secondary btn-sm">
                                                            <i class="fas fa-eye"></i> Detail
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--kjd-beige); margin-bottom: 1rem;"></i>
                            <h5 style="color: var(--kjd-dark-green);">Žádné objednávky</h5>
                            <p class="text-muted">Uživatel nemá žádné objednávky.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 