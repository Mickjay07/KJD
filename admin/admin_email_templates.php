<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config.php';

// Handle deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM email_templates WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: admin_email_templates.php?msg=deleted');
    exit;
}

// Fetch templates
$stmt = $conn->query("SELECT * FROM email_templates ORDER BY created_at DESC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$pageTitle = 'Šablony e-mailů';
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
        .template-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            border-left: 4px solid var(--kjd-gold-brown, #8A6240);
        }
        .template-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn-add {
            background: linear-gradient(135deg, #102820, #2c4c3b);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-add:hover {
            color: #fff;
            background: linear-gradient(135deg, #2c4c3b, #102820);
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
                    <a href="admin_emails.php" class="text-decoration-none text-muted me-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="mb-0 fw-bold text-dark">Šablony e-mailů</h2>
                </div>
                <div class="ms-auto">
                    <a href="admin_email_template_edit.php" class="btn-add">
                        <i class="fas fa-plus me-2"></i>Nová šablona
                    </a>
                </div>
            </nav>

            <div class="container-fluid px-4 py-4">
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Šablona byla úspěšně smazána.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <?php if (count($templates) > 0): ?>
                        <?php foreach($templates as $row): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="template-card h-100 d-flex flex-column">
                                    <h4 class="fw-bold text-dark mb-2"><?= htmlspecialchars($row['name']) ?></h4>
                                    <p class="text-muted mb-3 small">
                                        <i class="fas fa-heading me-1"></i> Předmět: <?= htmlspecialchars($row['subject']) ?>
                                    </p>
                                    <div class="mt-auto d-flex gap-2">
                                        <a href="admin_email_template_edit.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            <i class="fas fa-edit me-1"></i> Upravit
                                        </a>
                                        <a href="admin_email_templates.php?delete=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Opravdu smazat tuto šablonu?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <div class="text-muted mb-3">
                                <i class="fas fa-folder-open fa-3x"></i>
                            </div>
                            <h4>Žádné šablony</h4>
                            <p class="text-muted">Zatím jste nevytvořili žádné e-mailové šablony.</p>
                            <a href="admin_email_template_edit.php" class="btn btn-primary">Vytvořit první šablonu</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
