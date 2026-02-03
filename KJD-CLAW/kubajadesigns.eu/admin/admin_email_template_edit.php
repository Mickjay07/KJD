<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$template = ['name' => '', 'subject' => '', 'content' => ''];
$isEdit = false;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($template) {
        $isEdit = true;
    } else {
        // Reset if not found
        $template = ['name' => '', 'subject' => '', 'content' => ''];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $subject = $_POST['subject'];
    $content = $_POST['content'];

    try {
        if ($isEdit) {
            $stmt = $conn->prepare("UPDATE email_templates SET name = ?, subject = ?, content = ? WHERE id = ?");
            $stmt->execute([$name, $subject, $content, $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO email_templates (name, subject, content) VALUES (?, ?, ?)");
            $stmt->execute([$name, $subject, $content]);
        }
        header('Location: admin_email_templates.php?msg=saved');
        exit;
    } catch (PDOException $e) {
        $error = "Chyba při ukládání: " . $e->getMessage();
    }
}

$pageTitle = $isEdit ? 'Upravit šablonu' : 'Nová šablona';
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
    
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/01xxy8vo5nbpsts18sbtttzawt4lcx1xl2js0l72x2siwprx/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#content',
        height: 500,
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px }'
      });
    </script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'admin_sidebar.php'; ?>

        <!-- Page Content -->
        <div id="page-content-wrapper" class="w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
                <div class="d-flex align-items-center">
                    <a href="admin_email_templates.php" class="text-decoration-none text-muted me-3">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h2 class="mb-0 fw-bold text-dark"><?= $pageTitle ?></h2>
                </div>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card border-0 shadow-sm rounded-3 p-4">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="name" class="form-label fw-bold">Název šablony (interní)</label>
                                    <input type="text" class="form-control form-control-lg" id="name" name="name" value="<?= htmlspecialchars($template['name']) ?>" required placeholder="např. Opožděná expedice">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label fw-bold">Předmět e-mailu</label>
                                    <input type="text" class="form-control form-control-lg" id="subject" name="subject" value="<?= htmlspecialchars($template['subject']) ?>" required placeholder="Předmět, který uvidí zákazník">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="content" class="form-label fw-bold">Obsah e-mailu</label>
                                    <textarea id="content" name="content"><?= htmlspecialchars($template['content']) ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="admin_email_templates.php" class="btn btn-outline-secondary">Zrušit</a>
                                    <button type="submit" class="btn btn-success px-4">
                                        <i class="fas fa-save me-2"></i>Uložit šablonu
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
