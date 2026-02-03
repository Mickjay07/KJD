<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['custom_lightbox_confirmation'])) {
    header('Location: custom_lightbox.php');
    exit;
}

$confirmation = $_SESSION['custom_lightbox_confirmation'];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrzení objednávky - Custom Lightbox - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --kjd-dark-green:#102820; 
            --kjd-earth-green:#4c6444; 
            --kjd-gold-brown:#8A6240; 
            --kjd-dark-brown:#4D2D18; 
            --kjd-beige:#CABA9C; 
        }
        
        body {
            background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 2rem 0;
        }
        
        .confirmation-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .confirmation-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(16,40,32,0.15);
            border: 3px solid var(--kjd-earth-green);
            overflow: hidden;
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .success-icon {
            font-size: 5rem;
            color: var(--kjd-earth-green);
            margin-bottom: 1.5rem;
        }
        
        .confirmation-card h1 {
            color: var(--kjd-dark-green);
            font-weight: 800;
            margin-bottom: 1rem;
        }
        
        .confirmation-card p {
            color: var(--kjd-dark-brown);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .order-info {
            background: var(--kjd-beige);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            border: 2px solid var(--kjd-earth-green);
        }
        
        .order-info strong {
            color: var(--kjd-dark-green);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown));
            color: #fff;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 1rem;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown));
            color: #fff;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container confirmation-container">
        <div class="confirmation-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Objednávka byla přijata!</h1>
            <p>Děkujeme za vaši objednávku Custom Lightbox.</p>
            
            <div class="order-info">
                <p><strong>Číslo objednávky:</strong><br><?= htmlspecialchars($confirmation['order_id']) ?></p>
                <p><strong>Celková cena:</strong><br><?= number_format($confirmation['total'], 0, ',', ' ') ?> Kč</p>
            </div>
            
            <p><strong>Co bude dál?</strong></p>
            <p>Nyní připravíme návrh vašeho Custom Lightbox. Jakmile bude návrh hotový, obdržíte email s finálním návrhem k potvrzení. Po vašem potvrzení zahájíme výrobu.</p>
            
            <div class="alert alert-warning mt-4" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 1rem;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Důležité:</strong> Naše emaily často končí ve spamu. Prosím, zkontrolujte si složku Spam/Promo!
            </div>
            
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Zpět na hlavní stránku
            </a>
        </div>
    </div>
    
    <?php
    // Vyčištění session po zobrazení
    unset($_SESSION['custom_lightbox_confirmation']);
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

