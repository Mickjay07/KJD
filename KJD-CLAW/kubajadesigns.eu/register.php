<?php
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'Česká republika');
    
    // Validation
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $error = 'Všechna povinná pole musí být vyplněna.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Neplatný email.';
    } elseif (strlen($password) < 6) {
        $error = 'Heslo musí mít alespoň 6 znaků.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Hesla se neshodují.';
    } else {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email je již registrován.';
            } else {
                // Insert new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO users (email, password, first_name, last_name, phone, address, city, postal_code, country, created_at, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
                ");
                $stmt->execute([$email, $hashedPassword, $firstName, $lastName, $phone, $address, $city, $postalCode, $country]);
                
                $success = 'Registrace byla úspěšná! Nyní se můžete přihlásit.';
            }
        } catch (PDOException $e) {
            $error = 'Chyba při registraci: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrace - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
        
        body {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
        }
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .register-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(16,40,32,0.15);
            border: 3px solid var(--kjd-earth-green);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown));
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .register-body {
            padding: 2.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            color: var(--kjd-dark-green);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid var(--kjd-beige);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--kjd-earth-green);
            box-shadow: 0 0 0 0.2rem rgba(76,100,68,0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(77,45,24,0.3);
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.4);
        }
        
        .btn-login {
            background: transparent;
            color: var(--kjd-earth-green);
            border: 2px solid var(--kjd-earth-green);
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            background: var(--kjd-earth-green);
            color: #fff;
            text-decoration: none;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 600;
        }
        
        .required {
            color: #dc3545;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo a {
            text-decoration: none;
        }
        
        .logo span {
            font-size: 2.5rem;
            font-weight: 800;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo">
                    <a href="index.php">
                        <span style="color: #fff;">KJ</span><span style="color: var(--kjd-beige);">D</span>
                    </a>
                </div>
                <h1><i class="fas fa-user-plus me-2"></i>Registrace</h1>
            </div>
            
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-login">Přihlásit se</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">Jméno <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name" class="form-label">Příjmení <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email <span class="required">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <div class="alert alert-warning mt-2 mb-0" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 0.75rem; font-size: 0.85rem;">
                                <i class="fas fa-exclamation-triangle me-1" style="color: #856404;"></i>
                                <strong style="color: #856404;">Pozor:</strong> <span style="color: #856404;">Naše emaily často končí ve spamu. Zkontrolujte prosím složku Spam/Promo!</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password" class="form-label">Heslo <span class="required">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Potvrdit heslo <span class="required">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">Adresa</label>
                            <input type="text" class="form-control" id="address" name="address" 
                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="city" class="form-label">Město</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="postal_code" class="form-label">PSČ</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                           value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="country" class="form-label">Země</label>
                            <select class="form-control" id="country" name="country">
                                <option value="Česká republika" <?= ($_POST['country'] ?? 'Česká republika') === 'Česká republika' ? 'selected' : '' ?>>Česká republika</option>
                                <option value="Slovensko" <?= ($_POST['country'] ?? '') === 'Slovensko' ? 'selected' : '' ?>>Slovensko</option>
                                <option value="Německo" <?= ($_POST['country'] ?? '') === 'Německo' ? 'selected' : '' ?>>Německo</option>
                                <option value="Rakousko" <?= ($_POST['country'] ?? '') === 'Rakousko' ? 'selected' : '' ?>>Rakousko</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-register">
                            <i class="fas fa-user-plus me-2"></i>Registrovat se
                        </button>
                        
                        <div class="text-center">
                            <a href="login.php" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>Již máte účet? Přihlaste se
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
