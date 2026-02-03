<?php
// Jednoduchá stránka pro přidání recenze, vychází z nova_recenze.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Připojení k databázi (stejné jako na webu)
$servername = "wh51.farma.gigaserver.cz";
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->query("SET NAMES utf8");
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jmeno'], $_POST['prijmeni'], $_POST['text_recenze'], $_POST['hodnoceni'], $_POST['objednavka_cislo'])) {
    $jmeno = trim((string)$_POST['jmeno']);
    $prijmeni = trim((string)$_POST['prijmeni']);
    $textRecenze = trim((string)$_POST['text_recenze']);
    $hodnoceni = (int)$_POST['hodnoceni'];
    $objednavkaCislo = trim((string)$_POST['objednavka_cislo']);
    $obrazekCesta = null; // hodnota ukládaná do DB (relativně k web rootu)

    // Kontrola existence objednávky - podle order_id
    try {
        $stmtCheck = $pdo->prepare("SELECT id FROM orders WHERE order_id = ?");
        $stmtCheck->execute([$objednavkaCislo]);
        $objednavka = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $objednavka = false; }

    if (!$objednavka) {
        $zprava = "Objednávka s tímto číslem nebyla nalezena. Zkontrolujte prosím číslo objednávky.";
        $zprava_typ = "danger";
    } else {
        // Upload obrázku (volitelné)
        if (isset($_FILES['obrazek']) && is_array($_FILES['obrazek']) && ($_FILES['obrazek']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            // Cílový adresář na úrovni web rootu (index v /02/ načítá obrázky jako ../ + cesta_uložená_v_DB)
            $uploadFsDir = __DIR__ . '/../recenze/';
            $publicDirPrefix = 'recenze/'; // tato hodnota se uloží do DB (bez ../)

            if (!is_dir($uploadFsDir)) {
                @mkdir($uploadFsDir, 0777, true);
            }

            $orig = basename((string)$_FILES['obrazek']['name']);
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed, true)) {
                $filename = uniqid('rev_', true) . '_' . preg_replace('~[^a-z0-9_.-]+~i', '-', $orig);
                $targetFs = $uploadFsDir . $filename;
                if (move_uploaded_file($_FILES['obrazek']['tmp_name'], $targetFs)) {
                    $obrazekCesta = $publicDirPrefix . $filename; // do DB bez ../
                } else {
                    $zprava = "Chyba při nahrávání obrázku.";
                    $zprava_typ = "danger";
                }
            } else {
                $zprava = "Nepovolený formát obrázku. Povolené formáty: JPG, JPEG, PNG, GIF, WEBP.";
                $zprava_typ = "danger";
            }
        }

        // Kontrola, zda už pro objednávku recenze neexistuje
        if (!isset($zprava)) {
            try {
                $stmtCheckReview = $pdo->prepare("SELECT id FROM recenze WHERE objednavka_cislo = ?");
                $stmtCheckReview->execute([$objednavkaCislo]);
                $existingReview = $stmtCheckReview->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) { $existingReview = false; }

            if ($existingReview) {
                $zprava = "Pro tuto objednávku již byla napsána recenze. Každou objednávku lze hodnotit pouze jednou.";
                $zprava_typ = "warning";
            }
        }

        // Uložení recenze
        if (!isset($zprava)) {
            $timestamp = time();
            try {
                $stmt = $pdo->prepare("INSERT INTO recenze (objednavka_cislo, jmeno, prijmeni, text_recenze, hodnoceni, obrazek, datum) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$objednavkaCislo, $jmeno, $prijmeni, $textRecenze, $hodnoceni, $obrazekCesta, $timestamp]);
                $zprava = "Recenze byla úspěšně odeslána! Děkujeme za váš názor.";
                $zprava_typ = "success";
                $_POST = [];
            } catch (PDOException $e) {
                $zprava = "Chyba při ukládání recenze: " . $e->getMessage();
                $zprava_typ = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přidat recenzi - KJD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      body { background:#f5f2ea; }
      .kjd-card { max-width: 860px; margin: 32px auto; background:#fff; border-radius:16px; border:3px solid var(--kjd-earth-green); box-shadow:0 8px 30px rgba(16,40,32,0.08); }
      .kjd-card .header { display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:2px solid var(--kjd-earth-green); background:linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown)); color:#fff; border-radius:13px 13px 0 0; }
      .kjd-card .body { padding:24px; }
      .logo { font-weight:800; font-size:28px; color:#fff; text-decoration:none; }
      .btn-back { color:#fff; text-decoration:none; font-weight:700; }
      .btn-back:hover { text-decoration:underline; }
      label { font-weight:600; color: var(--kjd-dark-green); }
      .form-control, .form-select { border:2px solid var(--kjd-earth-green); border-radius:12px; }
      .btn-kjd { background: var(--kjd-dark-brown); color:#fff; border: none; border-radius:12px; padding:.8rem 1.5rem; font-weight:700; }
      .btn-kjd:hover { background: var(--kjd-gold-brown); }
      .order-info { background:#f2e8d5; border-left: 4px solid var(--kjd-dark-brown); padding: 12px 16px; border-radius: 8px; margin-bottom: 1rem; }
      .alert { border-radius:12px; border-width:2px; }
      @media (max-width: 576px) { .kjd-card .body { padding:16px; } }
    </style>
  </head>
  <body>
    <div class="kjd-card">
      <div class="header">
        <a href="index.php" class="logo">KJ<span style="color:#fff; opacity:.9;">D</span></a>
        <a href="index.php" class="btn-back">← Zpět na hlavní stránku</a>
      </div>
      <div class="body">
        <h1 class="h3 mb-3" style="font-weight:800; color: var(--kjd-dark-green);">Přidat recenzi</h1>
        <div class="order-info"><strong>Důležité:</strong> Pro přidání recenze potřebujete číslo objednávky, které jste obdrželi v potvrzovacím e‑mailu.</div>

        <?php if (isset($zprava)): ?>
          <div class="alert alert-<?= htmlspecialchars($zprava_typ ?? 'info') ?> border-2">
            <?= htmlspecialchars($zprava) ?>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <div class="col-12">
            <label for="objednavka_cislo" class="form-label">Číslo objednávky *</label>
            <input type="text" class="form-control" id="objednavka_cislo" name="objednavka_cislo" value="<?= htmlspecialchars($_POST['objednavka_cislo'] ?? '') ?>" required>
            <div class="form-text">Zadejte číslo objednávky, kterou chcete ohodnotit (např. KJD-2023-001).</div>
          </div>

          <div class="col-md-6">
            <label for="jmeno" class="form-label">Jméno *</label>
            <input type="text" class="form-control" id="jmeno" name="jmeno" value="<?= htmlspecialchars($_POST['jmeno'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label for="prijmeni" class="form-label">Příjmení *</label>
            <input type="text" class="form-control" id="prijmeni" name="prijmeni" value="<?= htmlspecialchars($_POST['prijmeni'] ?? '') ?>" required>
          </div>

          <div class="col-12">
            <label for="text_recenze" class="form-label">Vaše recenze *</label>
            <textarea class="form-control" id="text_recenze" name="text_recenze" rows="5" required><?= htmlspecialchars($_POST['text_recenze'] ?? '') ?></textarea>
          </div>

          <div class="col-12">
            <label class="form-label">Hodnocení *</label>
            <div>
              <?php $sel = (int)($_POST['hodnoceni'] ?? 0); for ($i=5; $i>=1; $i--): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" id="star<?= $i ?>" name="hodnoceni" value="<?= $i ?>" <?= $sel === $i ? 'checked' : '' ?> required>
                  <label class="form-check-label" for="star<?= $i ?>"><?php for ($s=0;$s<$i;$s++) echo '★'; ?></label>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div class="col-12">
            <label for="obrazek" class="form-label">Nahrát fotografii (nepovinné)</label>
            <input type="file" class="form-control" id="obrazek" name="obrazek" accept=".jpg,.jpeg,.png,.gif,.webp">
            <div class="form-text">Můžete přiložit fotografii produktu. Podporované formáty: JPG, JPEG, PNG, GIF, WEBP.</div>
          </div>

          <div class="col-12 text-center mt-2">
            <button type="submit" class="btn btn-kjd">Odeslat recenzi</button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  </body>
</html>


