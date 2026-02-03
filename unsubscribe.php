<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

$success = '';
$error = '';

function normalizeEmail($val){
  $val = trim((string)$val);
  return filter_var($val, FILTER_VALIDATE_EMAIL) ? $val : '';
}

// Support GET ?email= for one‑click unsubscribe links
$prefillEmail = '';
if (!empty($_GET['email'])) {
  $prefillEmail = normalizeEmail($_GET['email']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = normalizeEmail($_POST['email'] ?? '');
  if ($email === '') {
    $error = 'Neplatný e‑mail.';
  } else {
    try {
      // Remove from newsletter list (simple and effective)
      $stmt = $conn->prepare('DELETE FROM newsletter WHERE email = ?');
      $stmt->execute([$email]);
      $success = 'E‑mail byl úspěšně odhlášen z newsletteru.';
      $prefillEmail = $email; // keep in UI
    } catch (PDOException $e) {
      $error = 'Chyba při odhlašování. Zkuste to prosím znovu.';
      error_log('Unsubscribe error: ' . $e->getMessage());
    }
  }
}
?>
<!doctype html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odhlášení newsletteru – KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="fonts/sf-pro.css">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 { font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important; }
      body { background:#f8f9fa; color: var(--kjd-dark-green); }
      .wrap { max-width: 720px; margin: 40px auto; }
      .card-kjd { background:#fff; border-radius:16px; border:2px solid var(--kjd-earth-green); box-shadow:0 8px 24px rgba(16,40,32,0.08); overflow:hidden; }
      .card-kjd .header { background: linear-gradient(135deg, #102820, #4c6444); color:#fff; padding:28px 22px; border-bottom:3px solid var(--kjd-beige); text-align:center; }
      .card-kjd .content { padding:24px; }
      .badge-kjd { background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); color: var(--kjd-dark-green); border: 2px solid var(--kjd-earth-green); border-radius: 999px; padding: .35rem .75rem; font-weight:700; }
      .btn-kjd { background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); color:#fff; border:none; padding:.9rem 1.4rem; border-radius:12px; font-weight:700; box-shadow:0 4px 15px rgba(77,45,24,0.3); }
      .btn-kjd:hover { background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); color:#fff; }
      .form-control { border:2px solid var(--kjd-earth-green); border-radius:12px; }
      .footer { background: linear-gradient(135deg, #4D2D18, #8A6240); color:#fff; padding:18px; text-align:center; font-weight:600; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="card-kjd">
        <div class="header">
          <div style="font-size:24px; font-weight:800;">KJ<span style="color:#CABA9C;">D</span></div>
          <h1 class="h4 m-0">Odhlášení newsletteru</h1>
        </div>
        <div class="content">
          <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
          <?php elseif ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
          <?php else: ?>
            <p>Vyplňte prosím e‑mail, který chcete odhlásit z odběru.</p>
          <?php endif; ?>

          <form method="POST" class="mt-3">
            <div class="mb-3">
              <label for="email" class="form-label" style="font-weight:700; color:var(--kjd-dark-brown);">E‑mail</label>
              <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($prefillEmail) ?>" placeholder="vas@email.cz">
            </div>
            <button type="submit" class="btn-kjd"><i class="fas fa-envelope-open-text me-2"></i>Odhlásit odběr</button>
          </form>

          <div class="mt-4">
            <span class="badge-kjd"><i class="fas fa-info-circle me-1"></i> Pokud vám e‑maily nepatří, můžete tuto stránku ignorovat.</span>
          </div>
        </div>
        <div class="footer">
          <div style="font-size: 20px; font-weight: 800;">KJ<span style="color: #CABA9C;">D</span></div>
          <div>Kubajadesigns.eu • info@kubajadesigns.eu</div>
        </div>
      </div>
    </div>
  </body>
</html>


