<?php
session_start();
require_once 'config.php';

// Pokud je admin již přihlášen, přesměruj na hlavní stránku
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Ověření přihlašovacích údajů
    try {
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Úspěšné přihlášení
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Nesprávné uživatelské jméno nebo heslo';
        }
    } catch(PDOException $e) {
        $error = 'Chyba při přihlášení: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KJD Administrace - Přihlášení</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .login-logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            color: #4D2D18;
            margin-bottom: 20px;
        }
        .btn-login {
            background-color: #4D2D18;
            color: white;
            border: none;
            padding: 12px;
            font-weight: bold;
        }
        .btn-login:hover {
            background-color: #8A6240;
            color: white;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">KJD Administrace</div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Uživatelské jméno</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Heslo</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-login">Přihlásit se</button>
            </div>
        </form>
    </div>
</body>
</html> 