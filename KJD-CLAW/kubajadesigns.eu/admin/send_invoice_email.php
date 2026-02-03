<?php
require_once 'config.php';
// Načti functions.php pro getColorName funkci
if (file_exists(__DIR__ . '/../functions.php')) {
    require_once __DIR__ . '/../functions.php';
}
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// PHPMailer loader - try multiple possible paths
$phpmailer_paths = [
    '/www/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/',
    '/www/kubajadesigns.eu/kubajadesigns.eu/vendor/phpmailer/phpmailer/src/',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
    dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/',
    dirname(dirname(__DIR__)) . '/vendor/phpmailer/phpmailer/src/'
];

$phpmailer_loaded = false;
foreach ($phpmailer_paths as $path) {
    $exception_file = $path . 'exception.php';
    $phpmailer_file = $path . 'PHPMailer.php';
    $smtp_file = $path . 'SMTP.php';
    
    if (file_exists($exception_file) && file_exists($phpmailer_file) && file_exists($smtp_file)) {
        require_once $exception_file;
        require_once $phpmailer_file;
        require_once $smtp_file;
        $phpmailer_loaded = true;
        break;
    }
}

if (!$phpmailer_loaded) {
    die('PHPMailer not found. Please check vendor directory.');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Support both GET and POST requests
$id = (int)($_GET['id'] ?? $_POST['invoice_id'] ?? 0);
if ($id <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Neplatné ID faktury.']);
        exit;
        } else {
        $_SESSION['admin_error'] = 'Neplatné ID faktury.';
        header('Location: admin_invoices.php');
        exit;
    }
}

try {
    // Load invoice and items
    $stmt = $conn->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$id]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inv) { throw new Exception('Faktura nenalezena.'); }
    if (empty($inv['buyer_email'])) { throw new Exception('Faktuře chybí e‑mail odběratele.'); }

    $it = $conn->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
    $it->execute([$id]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);

    // Load settings
    $settings = $conn->query('SELECT * FROM invoice_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];

    $number = $inv['invoice_number'];
    
    // Build HTML invoice
    $css = "body{font-family:-apple-system,BlinkMacSystemFont,'Montserrat',Arial,sans-serif;background:#f5f5f7;color:#111;margin:0;padding:20px} .wrap{max-width:900px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.08);overflow:hidden} .head{display:flex;justify-content:space-between;align-items:center;padding:24px 28px;border-bottom:1px solid #eee} .logo{font-weight:800;font-size:22px} .meta{color:#666;font-size:14px} .content{padding:26px 28px} h1{font-size:20px;margin:0 0 14px} table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid #eee;text-align:left} th{color:#666;font-weight:600} td.num{text-align:right} .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px} .card{border:1px solid #eee;border-radius:12px;padding:14px} .sumline{display:flex;justify-content:space-between;margin:6px 0} .total{font-size:18px;font-weight:800} .foot{background:#fafafa;padding:14px 28px;color:#666;font-size:13px;border-top:1px solid #eee}";

    $buyerBlock = '';
    $buyerBlock .= '<div>'.h($inv['buyer_name']).'</div>';
    if (!empty($inv['buyer_address1'])) $buyerBlock .= '<div>'.h($inv['buyer_address1']).'</div>';
    if (!empty($inv['buyer_address2'])) $buyerBlock .= '<div>'.h($inv['buyer_address2']).'</div>';
    $line = trim(($inv['buyer_zip']??'').' '.($inv['buyer_city']??''));
    if ($line !== '') $buyerBlock .= '<div>'.h($line).'</div>';
    if (!empty($inv['buyer_country'])) $buyerBlock .= '<div>'.h($inv['buyer_country']).'</div>';
    if (!empty($inv['buyer_ico'])) $buyerBlock .= '<div>IČO: '.h($inv['buyer_ico']).'</div>';
    if (!empty($inv['buyer_dic'])) $buyerBlock .= '<div>DIČ: '.h($inv['buyer_dic']).'</div>';
    if (!empty($inv['buyer_email'])) $buyerBlock .= '<div>✉️ '.h($inv['buyer_email']).'</div>';
    if (!empty($inv['buyer_phone'])) $buyerBlock .= '<div>📞 '.h($inv['buyer_phone']).'</div>';

    $sellerBlock = '';
    $sellerBlock .= '<div><strong>Jakub Jarolím</strong></div>';
    $sellerBlock .= '<div>Mezileší 2078</div>';
    $sellerBlock .= '<div>19300 Praha 20</div>';
    $sellerBlock .= '<div>IČO:</div>';
    $sellerBlock .= '<div style="margin-top:8px; font-size:7pt; line-height:1.2;">Fyzická osoba zapsaná v živnostenském rejstříku vedeném u Úřadu městské části Praha X.</div>';
    $sellerBlock .= '<div style="margin-top:6px;">Tel: 722 341 256</div>';
    $sellerBlock .= '<div>Email: info@kubajadesigns.eu</div>';

    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr>'
            .'<td>'.h($it['name']).'</td>'
            .'<td style="border: 1px solid #ccc; padding: 8px; text-align: center;">'.(int)$it['quantity'].'</td>'
            .'<td style="border: 1px solid #ccc; padding: 8px; text-align: right;">'.number_format((float)$it['unit_price_without_vat'], 2, ',', ' ').' Kč</td>'
            .'<td style="border: 1px solid #ccc; padding: 8px; text-align: right;">'.number_format((float)$it['total_with_vat'], 2, ',', ' ').' Kč</td>'
            .'</tr>';
    }
    // Generate PDF using TCPDF
    $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/','-', $number);
    $pdfDir = __DIR__ . '/invoices';
    if (!is_dir($pdfDir)) { @mkdir($pdfDir, 0755, true); }
    $pdfPath = $pdfDir . '/faktura_' . $safeNumber . '.pdf';
    
    $pdfGenerated = false;
    $pdfData = null;
    
    // Generate PDF with new HTML layout
    if (file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
        try {
            require_once __DIR__ . '/tcpdf/tcpdf.php';
            
            // Prepare payment method info
            $paymentMethods = [
                'bank_transfer' => 'Bankovní převod',
                'revolut' => 'Revolut', 
                'cash' => 'Hotovost',
                'card' => 'Kartou'
            ];
            $paymentMethod = $inv['payment_method'] ?? 'bank_transfer';
            $paymentMethodText = h($paymentMethods[$paymentMethod] ?? 'Bankovní převod');

            // Prepare items - zkontroluj products_json pro barvy
            $productsJsonData = [];
            if (!empty($inv['products_json'])) {
                $productsJsonData = json_decode($inv['products_json'], true);
                if (!is_array($productsJsonData)) {
                    $productsJsonData = [];
                }
            }
            
            $rows = '';
            foreach ($items as $it) {
                $productName = h($it['name']);
                
                // Zkus najít produkt v products_json podle názvu
                $productColor = '';
                $componentColors = [];
                foreach ($productsJsonData as $key => $product) {
                    if (!is_array($product)) continue;
                    // Přeskoč _delivery_info a další metadata
                    if (is_string($key) && strlen($key) > 0 && $key[0] === '_') continue;
                    
                    $prodName = $product['name'] ?? '';
                    if ($prodName === $it['name']) {
                        // Najdi selected_color nebo color
                        $productColor = $product['selected_color'] ?? $product['color'] ?? '';
                        // Najdi component_colors
                        if (!empty($product['component_colors']) && is_array($product['component_colors'])) {
                            $componentColors = $product['component_colors'];
                        }
                        break;
                    }
                }
                
                // Přidej barvu k názvu produktu, pokud existuje
                if (!empty($productColor)) {
                    $colorName = function_exists('getColorName') ? getColorName($productColor) : $productColor;
                    $productName .= '<br><small style="color: #666;">Barva: ' . h($colorName) . '</small>';
                }
                // Přidej component_colors
                if (!empty($componentColors)) {
                    foreach ($componentColors as $compColor) {
                        if (!empty($compColor)) {
                            $compColorName = function_exists('getColorName') ? getColorName($compColor) : $compColor;
                            $productName .= '<br><small style="color: #666;">Komponenta: ' . h($compColorName) . '</small>';
                        }
                    }
                }
                
                $rows .= '<tr>
                    <td>'.$productName.'</td>
                    <td class="center">'.(int)$it['quantity'].'</td>
                    <td class="right">'.number_format($it['unit_price_without_vat'], 2, ',', ' ').' Kč</td>
                    <td class="right">'.number_format($it['total_with_vat'], 2, ',', ' ').' Kč</td>
                </tr>';
            }

            // Prepare totals - čistý formát
            $totalWithoutVat = (float)($inv['total_without_vat'] ?? 0);
            $vatTotal = (float)($inv['vat_total'] ?? 0);
            $discount = (float)($inv['sleva'] ?? 0);
            $finalTotal = (float)$inv['total_with_vat'];
            
            $totalBlock = '<tr><td>Celkem bez DPH:</td><td class="right">' . number_format($totalWithoutVat, 2, ',', ' ') . ' Kč</td></tr>
<tr><td>DPH:</td><td class="right">' . number_format($vatTotal, 2, ',', ' ') . ' Kč</td></tr>
' . ($discount > 0 ? '<tr><td>Sleva:</td><td class="right">-' . number_format($discount, 2, ',', ' ') . ' Kč</td></tr>' : '') . '
<tr><td class="final-total">Celkem k úhradě:</td><td class="right final-total">' . number_format($finalTotal, 2, ',', ' ') . ' Kč</td></tr>';

            // Prepare delivery info
            $deliveryInfo = '';
            if (!empty($inv['products_json'])) {
                $productsData = json_decode($inv['products_json'], true);
                if (isset($productsData['_delivery_info'])) {
                    $delivery = $productsData['_delivery_info'];
                    if (!empty($delivery['method'])) {
                        $deliveryInfo = '<strong>'.h($delivery['method']).'</strong>';
                        if (!empty($delivery['address'])) {
                            $deliveryInfo .= '<br>Adresa: '.h($delivery['address']);
                        }
                        if (!empty($delivery['postal_code'])) {
                            $deliveryInfo .= '<br>PSČ: '.h($delivery['postal_code']);
                        }
                    }
                }
            }
            
            // Prepare variables for template
            $customerNote = '';
            if (!empty($inv['note'])) {
                $customerNote = $inv['note'];
            }

            // Profesionální kompaktní faktura - vše na jednu A4
            $sellerName = h($settings['seller_name'] ?? 'KJD');
            $sellerAddr = h($settings['seller_address1'] ?? 'Mezilesí 2078');
            $sellerCity = h(($settings['seller_zip'] ?? '19300') . ' ' . ($settings['seller_city'] ?? 'Praha 20'));
            $sellerIco = !empty($settings['seller_ico']) ? h($settings['seller_ico']) : '';

            $buyerAddr = !empty($inv['buyer_address1']) ? h($inv['buyer_address1']) : '';
            $buyerCity = (($inv['buyer_zip'] || $inv['buyer_city']) ? h($inv['buyer_zip'] . ' ' . $inv['buyer_city']) : '');
            $buyerCountry = ($inv['buyer_country'] ? h($inv['buyer_country']) : '');
            $buyerPhone = ($inv['buyer_phone'] ? 'Tel: ' . h($inv['buyer_phone']) : '');
            $buyerEmail = ($inv['buyer_email'] ? 'Email: ' . h($inv['buyer_email']) : '');
        
            // Create new PDF document with proper settings
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('KJD Invoice System');
            $pdf->SetAuthor('KubaJa Designs');
            $pdf->SetTitle('Faktura ' . $number);
            $pdf->SetSubject('Faktura ' . $number);
            $pdf->SetKeywords('faktura, invoice, KJD');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font - DejaVu Sans podporuje české znaky
            $pdf->SetFont('dejavusans', '', 10);
            
            // Prepare variables for template
            $customerNote = '';
            if (!empty($inv['note'])) {
                $customerNote = $inv['note'];
            }

            // Profesionální kompaktní faktura - vše na jednu A4
            $sellerName = h($settings['seller_name'] ?? 'KJD');
            $sellerAddr = h($settings['seller_address1'] ?? 'Mezilesí 2078');
            $sellerCity = h(($settings['seller_zip'] ?? '19300') . ' ' . ($settings['seller_city'] ?? 'Praha 20'));
            $sellerIco = !empty($settings['seller_ico']) ? h($settings['seller_ico']) : '';

            $buyerAddr = !empty($inv['buyer_address1']) ? h($inv['buyer_address1']) : '';
            $buyerCity = (($inv['buyer_zip'] || $inv['buyer_city']) ? h($inv['buyer_zip'] . ' ' . $inv['buyer_city']) : '');
            $buyerCountry = ($inv['buyer_country'] ? h($inv['buyer_country']) : '');
            $buyerPhone = ($inv['buyer_phone'] ? 'Tel: ' . h($inv['buyer_phone']) : '');
            $buyerEmail = ($inv['buyer_email'] ? 'Email: ' . h($inv['buyer_email']) : '');
            
            // --- TCPDF-SAFE PREMIUM TEMPLATE (KubaJaDesigns) ---

            $html = '<!doctype html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<style type="text/css">
  body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 9pt;
    color: #102820;
    line-height: 1.4;
  }
  
  /* Helper classes */
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .text-bold { font-weight: bold; }
  .text-muted { color: #666666; }
  .text-primary { color: #102820; }
  .text-accent { color: #CABA9C; }
  
  /* Header */
  .header-table {
    width: 100%;
    border-bottom: 2px solid #102820;
  }
  .logo {
    font-size: 24pt;
    font-weight: bold;
    color: #102820;
  }
  .logo span { color: #CABA9C; }
  .invoice-title {
    font-size: 18pt;
    font-weight: bold;
    color: #102820;
    text-align: right;
  }
  .invoice-meta {
    font-size: 9pt;
    color: #444;
    text-align: right;
    line-height: 1.4;
  }
  
  /* Addresses */
  .address-table {
    width: 100%;
    margin-top: 30px;
  }
  .address-box {
    width: 48%;
  }
  .address-title {
    font-size: 10pt;
    font-weight: bold;
    color: #CABA9C;
    text-transform: uppercase;
    border-bottom: 1px solid #CABA9C;
    margin-bottom: 5px;
    padding-bottom: 2px;
  }
  .address-content {
    font-size: 9pt;
    line-height: 1.4;
  }
  
  /* Info Bar */
  .info-bar {
    background-color: #f4f4f4;
    color: #333;
    padding: 8px;
    margin-top: 20px;
    border-left: 4px solid #102820;
  }
  .info-table { width: 100%; }
  .info-label { font-weight: bold; font-size: 8pt; color: #666; }
  .info-value { font-weight: bold; font-size: 10pt; color: #102820; }
  
  /* Items Table */
  .items-table {
    width: 100%;
    margin-top: 30px;
    border-collapse: collapse;
  }
  .items-header {
    background-color: #102820;
    color: #ffffff;
    font-weight: bold;
    padding: 8px;
  }
  .items-row td {
    border-bottom: 1px solid #eee;
    padding: 10px 8px;
    vertical-align: middle;
  }
  .items-row-alt td {
    background-color: #fdfdfd;
  }
  
  /* Totals */
  .totals-table {
    width: 100%;
    margin-top: 20px;
  }
  .totals-label {
    text-align: right;
    padding: 5px;
    font-weight: bold;
    color: #666;
  }
  .totals-value {
    text-align: right;
    padding: 5px;
    font-weight: bold;
    color: #102820;
  }
  .total-final-label {
    text-align: right;
    padding: 10px 5px;
    font-size: 11pt;
    font-weight: bold;
    color: #102820;
    border-top: 2px solid #102820;
  }
  .total-final-value {
    text-align: right;
    padding: 10px 5px;
    font-size: 14pt;
    font-weight: bold;
    color: #102820;
    border-top: 2px solid #102820;
  }
  
  /* Footer */
  .footer {
    text-align: center;
    color: #888;
    font-size: 8pt;
    border-top: 1px solid #eee;
    padding-top: 10px;
    margin-top: 50px;
  }
</style>
</head>
<body>

  <!-- Header -->
  <table class="header-table" cellpadding="5">
    <tr>
      <td width="50%">
        <div class="logo">KubaJa<span>Designs</span></div>
      </td>
      <td width="50%">
        <div class="invoice-title">FAKTURA</div>
        <div class="invoice-meta">
          Číslo dokladu: <strong>' . h($number) . '</strong><br>
          Datum vystavení: ' . h($inv["issue_date"]) . '<br>
          Datum splatnosti: ' . h($inv["due_date"]) . '
        </div>
      </td>
    </tr>
  </table>

  <!-- Addresses -->
  <table class="address-table" cellpadding="0" cellspacing="0">
    <tr>
      <td width="48%">
        <div class="address-title">DODAVATEL</div>
        <div class="address-content">
          <strong>' . $sellerName . '</strong><br>
          ' . nl2br($sellerAddr) . '<br>
          ' . nl2br($sellerCity) . '<br>
          IČO: 23982381<br>
          <br>
          Tel: 722 341 256<br>
          Email: info@kubajadesigns.eu
        </div>
      </td>
      <td width="4%"></td>
      <td width="48%">
        <div class="address-title">ODBĚRATEL</div>
        <div class="address-content">
          <strong>' . h($inv['buyer_name']) . '</strong><br>
          ' . ($buyerAddr ? $buyerAddr . '<br>' : '') . '
          ' . ($buyerCity ? $buyerCity . '<br>' : '') . '
          ' . ($buyerCountry ? $buyerCountry . '<br>' : '') . '
          ' . (!empty($inv['buyer_ico']) ? 'IČO: ' . h($inv['buyer_ico']) . '<br>' : '') . '
          ' . (!empty($inv['buyer_dic']) ? 'DIČ: ' . h($inv['buyer_dic']) . '<br>' : '') . '
          <br>
          ' . ($buyerPhone ? $buyerPhone . '<br>' : '') . '
          ' . ($buyerEmail ? $buyerEmail : '') . '
        </div>
      </td>
    </tr>
  </table>

  <!-- Payment Info Bar -->
  <div class="info-bar">
    <table class="info-table" cellpadding="2">
      <tr>
        <td width="33%">
          <div class="info-label">ZPŮSOB PLATBY</div>
          <div class="info-value">' . $paymentMethodText . '</div>
        </td>
        <td width="33%">
          ' . ($paymentMethod === "bank_transfer" ? '
          <div class="info-label">ČÍSLO ÚČTU</div>
          <div class="info-value">2502903320/3030</div>
          ' : '') . '
        </td>
        <td width="33%" align="right">
          ' . ($paymentMethod === "bank_transfer" ? '
          <div class="info-label">VARIABILNÍ SYMBOL</div>
          <div class="info-value">' . h($inv["order_id"] ?? $inv["invoice_number"]) . '</div>
          ' : '') . '
        </td>
      </tr>
    </table>
  </div>

  <!-- Items -->
  <table class="items-table" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th class="items-header" width="50%">Položka</th>
        <th class="items-header text-center" width="15%">Množství</th>
        <th class="items-header text-right" width="15%">Cena/ks</th>
        <th class="items-header text-right" width="20%">Celkem</th>
      </tr>
    </thead>
    <tbody>
      ' . $rows . '
    </tbody>
  </table>

  <!-- Totals -->
  <table class="totals-table" cellpadding="2" cellspacing="0">
    <tr>
      <td width="60%">
        ' . (!empty($deliveryInfo) ? '<div style="margin-top:10px; font-size:9pt; color:#555;"><strong>Způsob dopravy:</strong><br>' . strip_tags($deliveryInfo, "<br>") . '</div>' : '') . '
        ' . (!empty($customerNote) ? '<div style="margin-top:10px; font-size:9pt; color:#555;"><strong>Poznámka:</strong><br>' . nl2br(strip_tags($customerNote)) . '</div>' : '') . '
      </td>
      <td width="40%">
        <table width="100%" cellpadding="2">
          <tr>
            <td class="totals-label">Celkem bez DPH:</td>
            <td class="totals-value">' . number_format($totalWithoutVat, 2, ',', ' ') . ' Kč</td>
          </tr>
          <tr>
            <td class="totals-label">DPH:</td>
            <td class="totals-value">' . number_format($vatTotal, 2, ',', ' ') . ' Kč</td>
          </tr>
          ' . ($discount > 0 ? '
          <tr>
            <td class="totals-label text-accent">Sleva:</td>
            <td class="totals-value text-accent">-' . number_format($discount, 2, ',', ' ') . ' Kč</td>
          </tr>
          ' : '') . '
          <tr>
            <td class="total-final-label">CELKEM K ÚHRADĚ:</td>
            <td class="total-final-value">' . number_format($finalTotal, 2, ',', ' ') . ' Kč</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Footer -->
  <div class="footer">
    KubaJa Designs | Mezileší 2078, 19300 Praha 20 | IČO: 23982381<br>
    www.kubajadesigns.eu | info@kubajadesigns.eu
  </div>

</body>
</html>';
            // --- END TEMPLATE ---

            // --- END OF PDF CONTENT ---
            
            // Generate PDF
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Output PDF
            $pdfOutput = $pdf->Output('', 'S');
                
            if (!$pdfOutput || strlen($pdfOutput) <= 1000) {
                throw new Exception('Generated PDF is too small or empty');
            }

            // Save PDF to file
            if (file_put_contents($pdfPath, $pdfOutput) === false) {
                throw new Exception('Failed to save PDF file');
            }
            
            $pdfData = $pdfOutput;
            $pdfGenerated = true;
            error_log('SUCCESS: PDF generated with TCPDF for invoice ' . $number . ', size: ' . strlen($pdfOutput) . ' bytes');
            
            // Mark invoice as paid
            try {
                // Try to add paid_date column if it doesn't exist
                try {
                    $updateStmt = $conn->prepare('UPDATE invoices SET status = "paid", paid_date = NOW() WHERE id = ?');
                    $updateStmt->execute([$id]);
                    error_log('Invoice ' . $number . ' marked as paid with paid_date after email sent');
                } catch (PDOException $e) {
                    // Fallback if paid_date column doesn't exist
                    $updateStmt = $conn->prepare('UPDATE invoices SET status = "paid" WHERE id = ?');
                    $updateStmt->execute([$id]);
                    error_log('Invoice ' . $number . ' marked as paid (without paid_date) after email sent');
                }
            } catch (Exception $e) {
                error_log('Failed to mark invoice as paid: ' . $e->getMessage());
                // Don't fail the whole process if marking as paid fails
            }
        } catch (Exception $e) {
            error_log('ERROR: TCPDF exception: ' . $e->getMessage());
            throw $e; // Re-throw the exception to be caught by the outer try-catch
        }
    } else {
        $errorMsg = 'ERROR: TCPDF not found at: ' . __DIR__ . '/tcpdf/tcpdf.php';
        error_log($errorMsg);
        throw new Exception('Chyba při generování PDF: TCPDF knihovna nebyla nalezena');
    }

    // Setup email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'mail.gigaserver.cz';
    $mail->SMTPAuth = true;
    $mail->Username = 'info@kubajadesigns.eu';
    $mail->Password = '2007Mickey++';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    $mail->setFrom('info@kubajadesigns.eu', 'KJD');
    $mail->addAddress($inv['buyer_email'], $inv['buyer_name'] ?: '');
    $mail->Subject = 'Faktura ' . $number;

    // Build secure download URL
    $token = hash('sha256', $inv['invoice_number'] . '|' . $inv['buyer_email']);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'kubajadesigns.eu';
    $downloadUrl = $scheme . '://' . $host . '/invoice_download.php?id=' . (int)$id . '&token=' . rawurlencode($token);

    // Build email body with discount support
    $emailDiscount = (float)($inv['sleva'] ?? 0);
    $discountInfo = '';
    if ($emailDiscount > 0) {
        $emailSubtotal = (float)$inv['total_without_vat'] + $emailDiscount;
        $discountInfo = '<p style="margin: 5px 0;"><strong>Částka před slevou:</strong> ' . number_format($emailSubtotal, 2, ',', ' ') . ' Kč</p>
                            <p style="margin: 5px 0; color: #28a745;"><strong>Sleva:</strong> -' . number_format($emailDiscount, 2, ',', ' ') . ' Kč</p>';
    }
    
    // Check for modification flag
    $invoiceModified = !empty($_POST['invoice_modified']);
    $notificationHtml = '';
    if ($invoiceModified) {
        $notificationHtml = '<p style="font-size: 16px; color: #c62828; font-weight: 700; background: #ffebee; padding: 15px; border-radius: 8px; border: 1px solid #ef9a9a; margin: 20px 0;">⚠️ Právě jsme vám udělali změny ve fakturře prosím překontrolujte si to</p>';
    }
    
    $mail->Body = '
<!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktura ' . h($number) . '</title>
        <style>
            body { 
            font-family: "SF Pro Display", "SF Compact Text", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                color: #102820; 
                margin: 0; 
                padding: 0; 
                background: #f8f9fa;
            }
            .email-container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #fff; 
                border-radius: 16px; 
                overflow: hidden;
                box-shadow: 0 8px 32px rgba(16,40,32,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #102820, #4c6444); 
                color: #fff; 
                padding: 30px 20px; 
                text-align: center; 
                border-bottom: 3px solid #CABA9C;
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 800; 
                text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            }
            .header .logo { 
                font-size: 24px; 
                font-weight: 800; 
                margin-bottom: 10px;
            }
            .content { 
                padding: 30px 25px; 
                line-height: 1.6;
            }
            .content h2 { 
                color: #102820; 
            font-size: 22px; 
                font-weight: 700; 
            margin: 0 0 20px 0;
            }
            .content h3 { 
            color: #102820; 
            font-size: 18px; 
                font-weight: 700; 
            margin: 25px 0 15px 0;
            }
            .invoice-card {
            background: linear-gradient(135deg, rgba(202,186,156,0.1), rgba(202,186,156,0.05));
            border: 2px solid #CABA9C;
            border-radius: 16px;
            padding: 25px;
            margin: 25px 0;
                text-align: center;
            }
            .invoice-number {
                font-size: 24px;
                font-weight: 800;
                color: #102820;
                margin-bottom: 10px;
            }
            .invoice-amount {
                font-size: 32px;
                font-weight: 800;
                color: #4c6444;
                margin-bottom: 15px;
            }
        .btn-download {
            display: inline-block;
            background: linear-gradient(135deg, #4c6444, #102820);
            color: #fff;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            margin: 20px 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76,100,68,0.3);
        }
        .btn-download:hover {
            background: linear-gradient(135deg, #102820, #4c6444);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76,100,68,0.4);
        }
        .info-box {
            background: rgba(202,186,156,0.1);
            border: 2px solid #CABA9C;
            border-radius: 12px;
                padding: 20px;
            margin: 20px 0;
        }
        .info-box h4 {
            color: #102820;
            font-weight: 700;
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid rgba(202,186,156,0.2);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #4c6444;
            font-weight: 600;
        }
        .info-value {
            color: #102820;
            font-weight: 700;
        }
        .discount-row {
            color: #28a745;
            font-weight: 700;
            }
            .footer {
            background: linear-gradient(135deg, #102820, #4c6444);
            color: #fff;
            padding: 25px 20px;
                text-align: center;
            }
            .footer .logo {
                font-size: 20px;
                font-weight: 800;
                margin-bottom: 10px;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
        }
        .footer p:first-child {
            font-weight: 700;
            font-size: 16px;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .content {
                padding: 20px 15px;
            }
            .invoice-card {
                padding: 20px 15px;
            }
            .invoice-amount {
                font-size: 28px;
            }
            .btn-download {
                padding: 12px 25px;
                font-size: 14px;
            }
            }
        </style>
    </head>
    <body>
    <div class="email-container">
        <div class="header">
            <div class="logo">KJ<span style="color: #CABA9C;">D</span></div>
            <h1>Faktura byla odeslána</h1>
            </div>
            
        <div class="content">
            <h2>Dobrý den' . ($inv['buyer_name'] ? ', ' . h($inv['buyer_name']) : '') . '!</h2>
            ' . $notificationHtml . '
                
            <p style="font-size: 16px; color: #4c6444; font-weight: 600;">✅ Platba byla zpracována</p>
            
            <p><strong>Děkujeme za Vaši platbu!</strong> Vaše objednávka byla úspěšně uhrazena a faktura je připravena.</p>
                
            <div class="invoice-card">
                <h3 style="margin-top: 0; color: #102820;">Vaše faktura</h3>
                <div class="invoice-number">' . h($number) . '</div>
                <div class="invoice-amount">' . number_format((float)$inv['total_with_vat'], 2, ',', ' ') . ' Kč</div>
                <p style="color: #4c6444; font-weight: 600; margin: 0;">Celkem k úhradě</p>
            </div>
            
            <div class="info-box">
                <h4>Informace o faktuře</h4>
                <div class="info-row">
                    <span class="info-label">Datum vystavení:</span>
                    <span class="info-value">' . h($inv['issue_date']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Datum splatnosti:</span>
                    <span class="info-value">' . h($inv['due_date']) . '</span>
                </div>
                ' . ($discountInfo ? '
                <div class="info-row discount-row">
                    <span class="info-label">Sleva:</span>
                    <span class="info-value">-' . number_format($emailDiscount, 2, ',', ' ') . ' Kč</span>
                </div>
                ' : '') . '
                <div class="info-row">
                    <span class="info-label">Celkem k úhradě:</span>
                    <span class="info-value">' . number_format((float)$inv['total_with_vat'], 2, ',', ' ') . ' Kč</span>
                </div>
            </div>
            
            <p style="font-size: 16px; color: #4c6444; font-weight: 600; margin: 25px 0 15px 0;">Fakturu si můžete stáhnout kliknutím na tlačítko níže:</p>
            
            <div style="text-align: center; margin: 25px 0;">
                <a href="' . h($downloadUrl) . '" class="btn-download" target="_blank">
                    📄 Stáhnout fakturu (PDF)
                </a>
                </div>
                
            <h3>🚚 Co bude dál?</h3>
            <p>Vaše objednávka bude zpracována a odeslána v nejbližší možný termín. O odeslání Vás budeme informovat e-mailem.</p>
            
            <h3>Kontakt</h3>
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Telefon:</span>
                    <span class="info-value">722 341 256</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">info@kubajadesigns.eu</span>
                </div>
                </div>
                
            <p style="font-size: 16px; color: #102820; font-weight: 600; margin-top: 25px;">
S pozdravem,<br><strong>Tým KJD</strong>
                </p>
            </div>
            
        <div class="footer">
            <div class="logo">KJ<span style="color: #CABA9C;">D</span></div>
                <p><strong>Kubajadesigns.eu</strong></p>
                <p>Email: info@kubajadesigns.eu</p>
            </div>
        </div>
    </body>
</html>';
    $mail->AltBody = 'Děkujeme za objednávku! Platba byla zpracována. Faktura ' . $number . ' ke stažení: ' . $downloadUrl . ' Kontakt: 722341256, info@kubajadesigns.eu';
        
    // Attach PDF or HTML
    if ($pdfGenerated && $pdfData) {
        $mail->addStringAttachment($pdfData, 'faktura_' . $safeNumber . '.pdf', 'base64', 'application/pdf');
        error_log('SUCCESS: PDF attached to email for invoice ' . $number . ', size: ' . strlen($pdfData) . ' bytes');
    } else {
        // Generate HTML fallback if PDF failed
        $htmlContent = 'Faktura ' . $number . ' - HTML verze\n\n';
        $htmlContent .= 'Číslo faktury: ' . $number . '\n';
        $htmlContent .= 'Datum vystavení: ' . $inv['issue_date'] . '\n';
        $htmlContent .= 'Celkem k úhradě: ' . number_format((float)$inv['total_with_vat'], 2, ',', ' ') . ' Kč\n\n';
        $htmlContent .= 'Stáhnout fakturu: ' . $downloadUrl . '\n\n';
        $htmlContent .= 'Kontakt: 722 341 256, info@kubajadesigns.eu';
        
        $filename = 'faktura_' . $safeNumber . '.txt';
        $mail->addStringAttachment($htmlContent, $filename, 'base64', 'text/plain; charset=UTF-8');
        error_log('FALLBACK: Text file attached to email for invoice ' . $number . ', PDF generation failed');
    }

    // Send email
    $mail->send();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // AJAX request - return JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Faktura byla odeslána na ' . h($inv['buyer_email']) . ($pdfGenerated ? ' (PDF)' : ' (HTML)') . '.'
        ]);
        exit;
    } else {
        // GET request - redirect
        $_SESSION['admin_success'] = 'Faktura byla odeslána na ' . h($inv['buyer_email']) . ($pdfGenerated ? ' (PDF)' : ' (HTML)') . '.';
        header('Location: admin_invoice_detail.php?id=' . (int)$id);
        exit;
    }
    
} catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // AJAX request - return JSON error
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Chyba při odesílání faktury: ' . $e->getMessage()]);
        exit;
    } else {
        // GET request - redirect with error
        $_SESSION['admin_error'] = 'Chyba při odesílání faktury: ' . $e->getMessage();
        header('Location: admin_invoice_detail.php?id=' . (int)$id);
        exit;
    }
}
