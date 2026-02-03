<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// DB connection
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

$dsn = "mysql:host=$servername;dbname=$dbname";
try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SET NAMES utf8");
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

$message = '';
$messageType = '';

// Process voucher activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_code'])) {
    $voucherCode = trim($_POST['voucher_code']);
    
    if (empty($voucherCode)) {
        $message = 'Zadejte prosím kód voucheru.';
        $messageType = 'error';
    } elseif (!isset($_SESSION['user_id'])) {
        $message = 'Pro aktivaci voucheru se musíte přihlásit.';
        $messageType = 'error';
    } else {
        try {
            // Check if voucher exists and is active
            $stmt = $conn->prepare("
                SELECT * FROM vouchers 
                WHERE code = ? AND status = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$voucherCode]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$voucher) {
                $message = 'Neplatný nebo již použitý kód voucheru.';
                $messageType = 'error';
            } else {
                // Voucher exists and is active - allow activation by any logged-in user
                // The recipient_email is just for tracking where the voucher was sent
                // Start transaction
                $conn->beginTransaction();
                
                try {
                    // Update voucher status
                    $stmt = $conn->prepare("
                        UPDATE vouchers 
                        SET status = 'used', activated_at = NOW(), activated_by = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $voucher['id']]);
                    
                    // Get or create user wallet
                    $stmt = $conn->prepare("
                        INSERT INTO user_wallet (user_id, balance) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $voucher['amount']]);
                    
                    // Record transaction
                    $stmt = $conn->prepare("
                        INSERT INTO wallet_transactions 
                        (user_id, voucher_id, type, amount, description) 
                        VALUES (?, ?, 'credit', ?, ?)
                    ");
                    $description = "Aktivace voucheru {$voucherCode}";
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $voucher['id'], 
                        $voucher['amount'], 
                        $description
                    ]);
                    
                    $conn->commit();
                    
                    $message = "Voucher byl úspěšně aktivován! Na váš účet bylo přičteno " . number_format($voucher['amount'], 0, ',', ' ') . " Kč.";
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
            }
        } catch (PDOException $e) {
            $message = 'Chyba při aktivaci voucheru: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get user wallet info and orders
$walletBalance = 0;
$walletTransactions = [];
$userOrders = [];

if (isset($_SESSION['user_id'])) {
    try {
        // Get user email from session or database
        $userEmail = $_SESSION['user_email'] ?? '';
        if (empty($userEmail)) {
            // Try to get email from users table
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userEmail = $user['email'] ?? '';
        }
        
        // Get wallet balance
        $stmt = $conn->prepare("SELECT balance FROM user_wallet WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $walletBalance = $wallet ? (float)$wallet['balance'] : 0;
        
        // Get recent transactions
        $stmt = $conn->prepare("
            SELECT wt.*, v.code as voucher_code 
            FROM wallet_transactions wt 
            LEFT JOIN vouchers v ON wt.voucher_id = v.id 
            WHERE wt.user_id = ? 
            ORDER BY wt.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $walletTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get user orders by email
        if (!empty($userEmail)) {
            $stmt = $conn->prepare("
                SELECT * FROM orders 
                WHERE email = ? 
                ORDER BY created_at DESC 
                LIMIT 20
            ");
            $stmt->execute([$userEmail]);
            $userOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Můj účet - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="fonts/sf-pro.css">
    
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        body {
            background: #f8f9fa;
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        
        .account-header {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid var(--kjd-earth-green);
            box-shadow: 0 4px 20px rgba(16,40,32,0.1);
        }
        
        .account-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 2px solid rgba(202,186,156,0.2);
            margin-bottom: 2rem;
        }
        
        .account-card h3 {
            color: var(--kjd-dark-green);
            font-weight: 700;
            margin-bottom: 1.5rem;
            border-bottom: 3px solid var(--kjd-earth-green);
            padding-bottom: 0.75rem;
        }
        
        .wallet-balance {
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
            color: #fff;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(76,100,68,0.3);
        }
        
        .wallet-balance .amount {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }
        
        .wallet-balance .label {
            font-size: 1.2rem;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .form-label {
            color: var(--kjd-dark-brown);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid var(--kjd-beige);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-weight: 500;
        }
        
        .form-control:focus {
            border-color: var(--kjd-earth-green);
            box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
        }
        
        .btn-kjd-primary {
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
            color: #fff;
            border: none;
            padding: 1rem 2rem;
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
            padding: 1rem 2rem;
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
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .transaction-item {
            background: rgba(202,186,156,0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border: 1px solid var(--kjd-beige);
        }
        
        .transaction-item:hover {
            background: rgba(202,186,156,0.2);
            border-color: var(--kjd-earth-green);
        }
        
        .transaction-credit {
            color: var(--kjd-earth-green);
            font-weight: 700;
        }
        
        .transaction-debit {
            color: #dc3545;
            font-weight: 700;
        }
        
        .voucher-input-group {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .order-item {
            background: rgba(202,186,156,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid var(--kjd-beige);
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            background: rgba(202,186,156,0.2);
            border-color: var(--kjd-earth-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16,40,32,0.1);
        }
        
        .order-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .order-status.created {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 2px solid #ffc107;
        }
        
        .order-status.processing {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border: 2px solid #17a2b8;
        }
        
        .order-status.completed {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #28a745;
        }
        
        .order-status.cancelled {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/icons.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="account-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="h2 mb-0">
                        <i class="fas fa-user-circle me-3"></i>Můj účet
                    </h1>
                    <p class="mb-0 mt-2">Spravujte svůj účet a aktivujte vouchery</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-custom">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Wallet Balance -->
            <div class="wallet-balance">
                <div class="amount"><?= number_format($walletBalance, 0, ',', ' ') ?> Kč</div>
                <div class="label">Zůstatek na účtu</div>
            </div>
            <!-- My Orders -->
            <?php if (!empty($userOrders)): ?>
            <div class="account-card">
                <h3><i class="fas fa-shopping-bag me-2"></i>Moje objednávky</h3>
                
                <?php foreach ($userOrders as $order): ?>
                    <div class="order-item">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div style="font-weight: 700; color: var(--kjd-dark-green); font-size: 1.1rem;">
                                    #<?= htmlspecialchars($order['order_id']) ?>
                                </div>
                                <small style="color: #666;">
                                    <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <div style="font-weight: 600; color: var(--kjd-dark-brown);">
                                    <?= htmlspecialchars($order['name'] ?? 'N/A') ?>
                                </div>
                                <small style="color: #666;">
                                    <?= htmlspecialchars($order['email']) ?>
                                </small>
                            </div>
                            <div class="col-md-2 text-center">
                                <?php
                                $status = $order['status'] ?? 'created';
                                $statusText = [
                                    'created' => 'Vytvořeno',
                                    'processing' => 'Zpracovává se',
                                    'completed' => 'Dokončeno',
                                    'cancelled' => 'Zrušeno'
                                ];
                                $statusDisplay = $statusText[$status] ?? 'Neznámý';
                                ?>
                                <span class="order-status <?= $status ?>">
                                    <?= $statusDisplay ?>
                                </span>
                            </div>
                            <div class="col-md-2 text-center">
                                <?php if (!empty($order['total'])): ?>
                                    <div style="font-weight: 700; color: var(--kjd-earth-green); font-size: 1.1rem;">
                                        <?= number_format($order['total'], 0, ',', ' ') ?> Kč
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2 text-end">
                                <a href="assembly-guide.php?order=<?= urlencode($order['order_id']) ?>&admin_preview=1" 
                                   class="btn btn-kjd-secondary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Zobrazit
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-3">
                    <small style="color: #666;">
                        Zobrazuje se posledních 20 objednávek
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Voucher Activation -->
            <div class="account-card">
                <h3><i class="fas fa-gift me-2"></i>Aktivace voucheru</h3>
                
                <div class="voucher-input-group">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Kód voucheru</label>
                                <input type="text" class="form-control" name="voucher_code" 
                                       placeholder="Zadejte kód voucheru (např. KJD-ABC12345)" 
                                       value="<?= htmlspecialchars($_POST['voucher_code'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-kjd-primary w-100">
                                    <i class="fas fa-check me-2"></i>Aktivovat
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5 style="color: var(--kjd-dark-brown);">Jak aktivovat voucher?</h5>
                        <ol style="color: var(--kjd-dark-green); font-weight: 500;">
                            <li>Zadejte kód voucheru do pole výše</li>
                            <li>Klikněte na tlačítko "Aktivovat"</li>
                            <li>Částka se automaticky přičte na váš účet</li>
                            <li>Můžete ji použít při platbě</li>
                            <li><strong>Voucher funguje i když byl poslán na jiný email</strong></li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h5 style="color: var(--kjd-dark-brown);">Kde najdu kód?</h5>
                        <ul style="color: var(--kjd-dark-green); font-weight: 500;">
                            <li>V emailu s dárkovým voucherem</li>
                            <li>Kód začíná "KJD-"</li>
                            <li>Obsahuje 8 znaků</li>
                            <li>Například: KJD-ABC12345</li>
                            <li><strong>Voucher můžete aktivovat i když byl poslán na jiný email</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <?php if (!empty($walletTransactions)): ?>
            <div class="account-card">
                <h3><i class="fas fa-history me-2"></i>Historie transakcí</h3>
                
                <?php foreach ($walletTransactions as $transaction): ?>
                    <div class="transaction-item">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div style="font-weight: 600; color: var(--kjd-dark-green);">
                                    <?= htmlspecialchars($transaction['description']) ?>
                                </div>
                                <small style="color: #666;">
                                    <?= date('d.m.Y H:i', strtotime($transaction['created_at'])) ?>
                                </small>
                            </div>
                            <div class="col-md-3 text-center">
                                <span class="badge <?= $transaction['type'] === 'credit' ? 'bg-success' : 'bg-danger' ?> fs-6">
                                    <?= $transaction['type'] === 'credit' ? '+' : '-' ?>
                                    <?= number_format($transaction['amount'], 0, ',', ' ') ?> Kč
                                </span>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php if ($transaction['voucher_code']): ?>
                                    <small style="color: var(--kjd-gold-brown); font-weight: 600;">
                                        <?= htmlspecialchars($transaction['voucher_code']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="account-card text-center">
                <h3><i class="fas fa-lock me-2"></i>Přihlášení vyžadováno</h3>
                <p style="font-size: 1.1rem; color: #666; margin-bottom: 2rem;">
                    Pro aktivaci voucherů se musíte přihlásit do svého účtu.
                </p>
                <a href="login.php" class="btn btn-kjd-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Přihlásit se
                </a>
                <a href="register.php" class="btn btn-kjd-secondary ms-3">
                    <i class="fas fa-user-plus me-2"></i>Registrovat se
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
