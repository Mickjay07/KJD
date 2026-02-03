<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

require_once '../config.php';

// Page title
$pageTitle = 'Napsat e-mail';
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
        selector: '#email_content',
        height: 500,
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px }'
      });
    </script>

    <style>
        body { font-family: 'SF Pro Display', sans-serif; background-color: #f8f9fa; }
        .form-card {
            background: #fff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .form-label {
            font-weight: 600;
            color: var(--kjd-dark-green, #102820);
        }
        .btn-send {
            background: linear-gradient(135deg, #102820, #2c4c3b);
            color: #fff;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-send:hover {
            background: linear-gradient(135deg, #2c4c3b, #102820);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16,40,32,0.2);
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
                    <h2 class="mb-0 fw-bold text-dark">Napsat nový e-mail</h2>
                </div>
            </nav>

            <div class="container-fluid px-4 py-4">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="form-card">
                            <form action="send_custom_email.php" method="POST" id="emailForm">
                                <div class="mb-4 p-3 bg-light rounded-3 border">
                                    <label for="template_select" class="form-label small text-muted text-uppercase fw-bold mb-2">Načíst šablonu</label>
                                    <div class="d-flex gap-2">
                                        <select class="form-select" id="template_select">
                                            <option value="">-- Vyberte šablonu --</option>
                                            <?php
                                            $stmt = $conn->query("SELECT id, name FROM email_templates ORDER BY name ASC");
                                            while($t = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<option value='{$t['id']}'>" . htmlspecialchars($t['name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary" onclick="loadTemplate()">
                                            <i class="fas fa-download me-2"></i>Načíst
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="recipient_email" class="form-label">Příjemce (E-mail)</label>
                                    <input type="email" class="form-control form-control-lg" id="recipient_email" name="recipient_email" required placeholder="např. zakaznik@email.cz">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Předmět</label>
                                    <input type="text" class="form-control form-control-lg" id="subject" name="subject" required placeholder="Předmět e-mailu">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="email_content" class="form-label">Obsah e-mailu</label>
                                    <textarea id="email_content" name="email_content"></textarea>
                                    <div class="form-text mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Obsah bude automaticky vložen do prémiové šablony KJD (s logem, hlavičkou a patičkou).
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-secondary" onclick="previewEmail()">
                                        <i class="fas fa-eye me-2"></i>Náhled
                                    </button>
                                    <button type="submit" class="btn btn-send">
                                        <i class="fas fa-paper-plane me-2"></i>Odeslat e-mail
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Náhled e-mailu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="previewFrame" style="width: 100%; height: 600px; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadTemplate() {
            const templateId = document.getElementById('template_select').value;
            if (!templateId) {
                alert('Vyberte prosím šablonu.');
                return;
            }
            
            if (!confirm('Opravdu chcete načíst šablonu? Přepíše to aktuální předmět a obsah.')) return;
            
            fetch('get_template.php?id=' + templateId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('subject').value = data.subject;
                        tinymce.get('email_content').setContent(data.content);
                    } else {
                        alert('Chyba při načítání šablony.');
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function previewEmail() {
            const content = tinymce.get('email_content').getContent();
            const subject = document.getElementById('subject').value;
            
            // Create a temporary form to post data to preview
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'send_custom_email.php?preview=1';
            form.target = 'previewFrame';
            
            const inputContent = document.createElement('input');
            inputContent.type = 'hidden';
            inputContent.name = 'email_content';
            inputContent.value = content;
            
            const inputSubject = document.createElement('input');
            inputSubject.type = 'hidden';
            inputSubject.name = 'subject';
            inputSubject.value = subject;
            
            form.appendChild(inputContent);
            form.appendChild(inputSubject);
            document.body.appendChild(form);
            
            // Open modal
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
            
            form.submit();
            document.body.removeChild(form);
        }
        
        // Handle form submission with AJAX to show success/error nicely
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('Opravdu chcete odeslat tento e-mail?')) return;
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Odesílám...';
            btn.disabled = true;
            
            const formData = new FormData(this);
            formData.append('email_content', tinymce.get('email_content').getContent());
            
            fetch('send_custom_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('E-mail byl úspěšně odeslán!');
                    window.location.href = 'admin_emails.php';
                } else {
                    alert('Chyba: ' + (data.error || 'Neznámá chyba'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Chyba při komunikaci se serverem.');
                console.error(error);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    </script>
</body>
</html>
