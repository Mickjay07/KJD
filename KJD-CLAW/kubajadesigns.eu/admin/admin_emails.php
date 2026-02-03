<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config.php';

// Page title
$pageTitle = 'Správa e-mailů';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Admin KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
        body { font-family: 'SF Pro Display', sans-serif; background-color: #f8f9fa; }
        .email-card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .email-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .email-icon {
            font-size: 3rem;
            color: var(--kjd-gold-brown, #8A6240);
            margin-bottom: 1.5rem;
        }
        .email-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--kjd-dark-green, #102820);
            margin-bottom: 1rem;
        }
        .email-desc {
            color: #666;
            margin-bottom: 1.5rem;
        }
        .btn-action {
            background: linear-gradient(135deg, #102820, #2c4c3b);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-action:hover {
            background: linear-gradient(135deg, #2c4c3b, #102820);
            color: #fff;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'admin_sidebar.php'; ?>

        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
                <div class="d-flex align-items-center">
                    <i class="fas fa-envelope me-3 text-primary fs-4"></i>
                    <h2 class="mb-0 fw-bold text-dark">E-maily</h2>
                </div>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div class="row g-4">
                    <!-- Compose New Email -->
                    <div class="col-md-6 col-lg-4">
                        <div class="email-card">
                            <div class="email-icon">
                                <i class="fas fa-pen-fancy"></i>
                            </div>
                            <h3 class="email-title">Napsat nový e-mail</h3>
                            <p class="email-desc">Vytvořit a odeslat nový e-mail s prémiovým designem KJD.</p>
                            <a href="admin_email_compose.php" class="btn-action">
                                <i class="fas fa-plus me-2"></i>Vytvořit e-mail
                            </a>
                        </div>
                    </div>

                    <!-- Newsletter (Existing) -->
                    <div class="col-md-6 col-lg-4">
                        <div class="email-card">
                            <div class="email-icon">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <h3 class="email-title">Newsletter</h3>
                            <p class="email-desc">Správa odběratelů a hromadné rozesílání novinek.</p>
                            <a href="admin_newsletter.php" class="btn-action">
                                <i class="fas fa-users me-2"></i>Spravovat newsletter
                            </a>
                        </div>
                    </div>
                    
                    <!-- Templates -->
                    <div class="col-md-6 col-lg-4">
                        <div class="email-card">
                            <div class="email-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3 class="email-title">Šablony</h3>
                            <p class="email-desc">Správa předpřipravených šablon pro různé situace.</p>
                            <a href="admin_email_templates.php" class="btn-action">
                                <i class="fas fa-list me-2"></i>Spravovat šablony
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
