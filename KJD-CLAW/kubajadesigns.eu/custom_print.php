<?php
session_start();
require_once 'config.php';

// Fetch filaments for the dropdown
$filaments = $conn->query("SELECT * FROM filaments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Poptávka 3D Tisku - KJD</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap & Styles -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/vendor.css">
    <link rel="stylesheet" type="text/css" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Open+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
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
        
        /* Apple SF Pro Font */
        body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6, select, input, textarea {
            font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        /* Page Layout */
        .cart-page { 
            background: #f8f9fa; 
            min-height: 100vh; 
        }
        
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

        /* Content Card */
        .content-card { 
            background: #fff; 
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 4px 20px rgba(16,40,32,0.08);
            border: 2px solid var(--kjd-earth-green);
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--kjd-dark-green);
        }

        /* File Upload */
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            height: 80px;
            border: 2px dashed #ccc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fdfdfd;
        }

        .file-upload-wrapper:hover {
            border-color: var(--kjd-gold-brown);
            background: #fff;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
        }

        .file-upload-text {
            color: #666;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-upload-text i {
            font-size: 1.5rem;
            color: var(--kjd-gold-brown);
        }

        /* Inputs */
        select, input[type="text"], input[type="email"], input[type="tel"], textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: var(--kjd-gold-brown);
            box-shadow: 0 0 0 4px rgba(138, 98, 64, 0.1);
        }

        /* Button */
        .btn-kjd-primary { 
            background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
            color: #fff; 
            border: none; 
            padding: 1rem 2rem; 
            border-radius: 12px; 
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-kjd-primary:hover { 
            background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77,45,24,0.3);
        }

        /* Badge */
        .badge-new {
            background: linear-gradient(135deg, #ffc107, #ff8c00);
            color: #000;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            vertical-align: middle;
            margin-left: 15px;
            box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body class="cart-page">

    <?php include 'includes/icons.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-start">
                    <h1>
                        <i class="fas fa-cube me-3"></i>Poptávka 3D Tisku
                        <span class="badge badge-new"><i class="fas fa-star me-1"></i>Novinka</span>
                    </h1>
                    <p class="mt-3" style="max-width: 800px;">
                        Prosíme, vyplňte následující formulář a obratem vám zašleme přesnou nezávaznou cenovou nabídku spolu s odhadovaným termínem výroby.<br>
                        Pokud si nejste jistí vyplněním některých políček, neváhejte přidat své dotazy do poznámky. Jsme zde, abychom vám s radostí pomohli.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="border-radius: 12px; border: 2px solid #198754;">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h4 class="alert-heading mb-1">Poptávka úspěšně odeslána!</h4>
                            <p class="mb-0">Děkujeme. Potvrzení jsme vám zaslali na email. Brzy se vám ozveme s kalkulací.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="content-card">
                    <form id="customPrintForm" action="submit_custom_request.php" method="POST" enctype="multipart/form-data">
                        
                        <!-- File Upload -->
                        <div class="form-group">
                            <label>1. Nahrajte model (.stl)</label>
                            <div class="file-upload-wrapper">
                                <span class="file-upload-text" id="fileName">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    Vybrat soubor...
                                </span>
                                <input type="file" name="stl_file" id="stlFile" accept=".stl" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Material Selection -->
                                <div class="form-group">
                                    <label>2. Preferovaný materiál</label>
                                    <select name="filament_id" id="filamentSelect" required>
                                        <option value="" disabled selected>Zvolte materiál</option>
                                        <?php foreach ($filaments as $f): ?>
                                            <option value="<?php echo $f['id']; ?>">
                                                <?php echo htmlspecialchars($f['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Settings -->
                                <div class="form-group">
                                    <label>3. Výplň a požadavky</label>
                                    <select name="infill" id="infillSelect">
                                        <option value="10%">Lehký (10% výplň)</option>
                                        <option value="20%" selected>Standardní (20% výplň)</option>
                                        <option value="40%">Pevný (40% výplň)</option>
                                        <option value="100%">Plný (100% výplň)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Info -->
                        <div class="form-group">
                            <label>4. Kontaktní údaje</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="email" name="email" class="form-control" placeholder="Váš e-mail" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="tel" name="phone" class="form-control" placeholder="Telefon (nepovinné)">
                                </div>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="form-group">
                            <label>Poznámka</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Specifické požadavky, barva, termín..."></textarea>
                        </div>

                        <button type="submit" class="btn-kjd-primary" id="submitBtn">
                            <i class="fas fa-paper-plane"></i> Odeslat nezávaznou poptávku
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File Upload Handler - Just updates the text
        document.getElementById('stlFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Update UI with filename
            const fileNameContainer = document.getElementById('fileName');
            fileNameContainer.innerHTML = '<i class="fas fa-file-code"></i> ' + file.name;
            fileNameContainer.style.color = 'var(--kjd-dark-green)';
            fileNameContainer.style.fontWeight = '700';
        });
    </script>
</body>
</html>
