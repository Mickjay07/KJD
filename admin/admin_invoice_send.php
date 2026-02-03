<?php
require_once 'config.php';
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['admin_error'] = 'Neplatné ID faktury.';
    header('Location: admin_invoices.php');
    exit;
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
            .'<td class="num">'.(int)$it['quantity'].'</td>'
            .'<td class="num">'.number_format((float)$it['unit_price_without_vat'], 2, ',', ' ').' Kč</td>'
            .'<td class="num">'.number_format((float)$it['total_with_vat'], 2, ',', ' ').' Kč</td>'
            .'</tr>';
    }
    // Generate PDF using TCPDF
    $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/','-', $number);
    $pdfDir = __DIR__ . '/invoices';
    if (!is_dir($pdfDir)) { @mkdir($pdfDir, 0755, true); }
    $pdfPath = $pdfDir . '/faktura_' . $safeNumber . '.pdf';
    
    $pdfGenerated = false;
    $pdfData = null;
    
    // Try to generate PDF with TCPDF
    if (file_exists(__DIR__ . '/../../vendor/tcpdf/tcpdf.php')) {
        try {
            require_once __DIR__ . '/../../vendor/tcpdf/tcpdf.php';
            
            // vytvoř PDF
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(TRUE, 10);
            $pdf->AddPage();
            $pdf->SetFont('dejavusans', '', 9);

            // Připrav bloky Dodavatel / Odběratel
            $seller = '<strong>'.h($settings['seller_name'] ?? 'Kubaja Designs').'</strong><br>'
                .h($settings['seller_address1'] ?? 'Mezilesí 2078').'<br>'
                .(($settings['seller_zip'] ?? '19300').' '.($settings['seller_city'] ?? 'Praha 20')).'<br>';
            if (!empty($settings['seller_ico'])) $seller .= 'IČO: '.h($settings['seller_ico']).'<br>';
            if (!empty($settings['seller_dic'])) $seller .= 'DIČ: '.h($settings['seller_dic']).'<br>';
            $seller .= 'Tel: 722 341 256<br>Email: info@kubajadesigns.eu';

            $buyer = '<strong>'.h($inv['buyer_name']).'</strong><br>';
            if (!empty($inv['buyer_address1'])) $buyer .= h($inv['buyer_address1']).'<br>';
            if (!empty($inv['buyer_address2'])) $buyer .= h($inv['buyer_address2']).'<br>';
            $buyerLine = trim(($inv['buyer_zip']??'').' '.($inv['buyer_city']??''));
            if ($buyerLine !== '') $buyer .= h($buyerLine).'<br>';
            if (!empty($inv['buyer_ico'])) $buyer .= 'IČO: '.h($inv['buyer_ico']).'<br>';
            if (!empty($inv['buyer_dic'])) $buyer .= 'DIČ: '.h($inv['buyer_dic']).'<br>';
            if (!empty($inv['buyer_phone'])) $buyer .= 'Tel: '.h($inv['buyer_phone']).'<br>';
            if (!empty($inv['buyer_email'])) $buyer .= 'Email: '.h($inv['buyer_email']);

            // Načti doručovací údaje z products_json
            $deliveryInfo = '';
            if (!empty($inv['products_json'])) {
                $productsData = json_decode($inv['products_json'], true);
                if (isset($productsData['_delivery_info'])) {
                    $delivery = $productsData['_delivery_info'];
                    if (!empty($delivery['method'])) {
                        $deliveryInfo .= '<strong>Doprava:</strong> '.h($delivery['method']).'<br>';
                        if (!empty($delivery['address'])) $deliveryInfo .= 'Adresa: '.h($delivery['address']).'<br>';
                        if (!empty($delivery['postal_code'])) $deliveryInfo .= 'PSČ: '.h($delivery['postal_code']).'<br>';
                    }
                }
            }

            // Načti poznámku zákazníka
            $customerNote = '';
            if (!empty($inv['note'])) {
                $customerNote = '<strong>Poznámka zákazníka:</strong><br>'.nl2br(h($inv['note']));
            }

            // Připrav informace o způsobu platby
            $paymentMethods = [
                'bank_transfer' => 'Bankovní převod',
                'revolut' => 'Revolut', 
                'cash' => 'Hotovost',
                'card' => 'Kartou'
            ];
            $paymentMethod = $inv['payment_method'] ?? 'bank_transfer';
            $paymentMethodInfo = '<p style="margin:2px 0; font-size:8pt;"><strong>'.h($paymentMethods[$paymentMethod] ?? 'Bankovní převod').'</strong></p>';
            
            if ($paymentMethod === 'bank_transfer') {
                $paymentMethodInfo .= '<p style="margin:2px 0; font-size:8pt;">Bankovní účet: 2502903320/3030 (Air Bank)</p>';
                $paymentMethodInfo .= '<p style="margin:2px 0; font-size:8pt;">Variabilní symbol: '.h($inv['order_id'] ?? $inv['invoice_number']).'</p>';
            } elseif ($paymentMethod === 'revolut') {
                // Only show 'Revolut platba' without phone number
                // No additional information needed
            }

            // Položky
            $rows = '';
            foreach ($items as $it) {
                $rows .= '<tr>
                    <td>'.h($it['name']).'</td>
                    <td align="center">'.(int)$it['quantity'].'</td>
                    <td align="right">'.number_format($it['unit_price_without_vat'], 2, ',', ' ').' Kč</td>
                    <td align="right">'.number_format($it['total_with_vat'], 2, ',', ' ').' Kč</td>
                </tr>';
            }

            // Doprava se už nepridává automaticky - pouze ručně přidané položky

            // Totals with discount support
            $discount = (float)($inv['sleva'] ?? 0);
            $subtotalBeforeDiscount = (float)$inv['total_without_vat'] + $discount;
            
            // Debug log values
            error_log('Invoice totals debug: total_without_vat=' . $inv['total_without_vat'] . ', vat_total=' . $inv['vat_total'] . ', total_with_vat=' . $inv['total_with_vat'] . ', discount=' . $discount);
            
            // Simple totals display - just the final amount
            $finalTotal = (float)$inv['total_with_vat'];
            $totalBlock = '<div style="font-size:10pt; margin:15px 0; text-align:right;">';
            
            if ($discount > 0) {
                // Try to get discount reason from order or use generic description
                $discountReason = 'Poskytnutá sleva';
                if (!empty($inv['note']) && stripos($inv['note'], 'slev') !== false) {
                    $discountReason = 'Sleva dle poznámky';
                }
                $totalBlock .= '<p style="margin:3px 0; color:#28a745;">'.$discountReason.': <strong>-'.number_format($discount, 2, ',', ' ').' Kč</strong></p>';
            }
            
            $totalBlock .= '<p style="margin:5px 0; font-size:12pt; background-color:#f0f0f0; padding:8px; border:1px solid #ccc;"><strong>Celkem k úhradě: '.number_format($finalTotal, 2, ',', ' ').' Kč</strong></p>';
            $totalBlock .= '</div>';

            // Ultra compact HTML layout for A4
            $pdfContent = <<<EOD
<style>
    h1 { font-size:14pt; margin:0 0 5px 0; }
    .section-title { font-weight:bold; font-size:9pt; margin:5px 0 2px 0; color:#444; }
    table.items th { background:#caba9C; color:#fff; padding:3px; font-size:8pt; }
    table.items td { padding:3px; border-bottom:1px solid #ddd; font-size:8pt; }
    .info { font-size:8pt; line-height:1.1; }
    .totals { font-size:8pt; }
    .payment { font-size:8pt; margin:5px 0; }
</style>

<h1>Faktura č. {$number}</h1>
<p style="margin:0 0 5px 0; font-size:8pt;">Vystaveno: {$inv['issue_date']} | Splatnost: {$inv['due_date']}</p>
<p style="margin:0 0 5px 0; font-size:8pt;">DUZP: {$inv['issue_date']}</p>
<p style="margin:0 0 10px 0; font-size:8pt; font-weight:bold; color:#666;">Dodavatel není plátcem DPH</p>

<table cellpadding="4" cellspacing="0" style="width:100%; margin-bottom:10px;">
    <tr>
        <td width="50%" class="info">
            <div class="section-title">Dodavatel</div>
            {$seller}
        </td>
        <td width="50%" class="info">
            <div class="section-title">Odběratel</div>
            {$buyer}
        </td>
    </tr>
</table>

<div class="section-title">Položky</div>
<table class="items" cellpadding="3" cellspacing="0" border="0" style="width:100%; margin-bottom:10px;">
    <thead>
        <tr>
            <th align="left" width="50%">Název</th>
            <th align="center" width="15%">Ks</th>
            <th align="right" width="15%">Cena</th>
            <th align="right" width="20%">Celkem</th>
        </tr>
    </thead>
    <tbody>
        {$rows}
    </tbody>
</table>

<div class="totals" style="margin:10px 0;">
{$totalBlock}
</div>

<div class="payment">
<div class="section-title">Způsob platby</div>
{$paymentMethodInfo}
</div>

<!-- Doručovací údaje a poznámky -->
{$deliveryInfo}
{$customerNote}

<p style="margin-top:15px; font-size:8pt; color:#666; text-align:center;">
    KubaJa Designs — automaticky generovaná faktura
</p>
EOD;

            // --- END OF NEW PDF CONTENT ---
            
            // Write content to PDF
            $pdf->writeHTML($pdfContent, true, false, true, false, '');
            
            // Output PDF
            $pdfOutput = $pdf->Output('', 'S');
            
            if ($pdfOutput && strlen($pdfOutput) > 1000) {
                file_put_contents($pdfPath, $pdfOutput);
                $pdfData = $pdfOutput;
                $pdfGenerated = true;
                error_log('SUCCESS: PDF generated with TCPDF for invoice ' . $number . ', size: ' . strlen($pdfOutput) . ' bytes');
                
                // Mark invoice as paid after sending
                try {
                    $updateStmt = $conn->prepare('UPDATE invoices SET status = "paid", paid_date = NOW() WHERE id = ?');
                    $updateStmt->execute([$id]);
                    error_log('Invoice ' . $number . ' marked as paid after email sent');
                } catch (Exception $e) {
                    error_log('Failed to mark invoice as paid: ' . $e->getMessage());
                }
            } else {
                error_log('ERROR: TCPDF output too small or empty for invoice ' . $number);
            }
        } catch (Exception $e) {
            error_log('ERROR: TCPDF exception: ' . $e->getMessage());
        }
    } else {
        error_log('ERROR: TCPDF not found at: ' . __DIR__ . '/../../vendor/tcpdf/tcpdf.php');
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
    
    $mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktura ' . h($number) . '</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <tr>
            <td style="background: #caba9C; padding: 30px; text-align: center;">
                <h1 style="color: white; margin: 0 0 10px 0; font-size: 24px;">KubaJa Designs</h1>
                <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 16px;">Faktura byla odeslána</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <h2 style="color: #caba9C; margin: 0 0 20px 0; font-size: 20px;">✅ Platba byla zpracována</h2>
                
                <p>Dobrý den,</p>
                
                <p><strong>Děkujeme za Vaši platbu!</strong> Vaše objednávka byla úspěšně uhrazena a faktura je připravena.</p>
                
                <table width="100%" cellpadding="15" cellspacing="0" style="background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                    <tr>
                        <td>
                            <h3 style="color: #caba9C; margin: 0 0 10px 0;">Informace o faktuře</h3>
                            <p style="margin: 5px 0;"><strong>Číslo faktury:</strong> ' . h($number) . '</p>
                            <p style="margin: 5px 0;"><strong>Datum vystavení:</strong> ' . h($inv['issue_date']) . '</p>
                            ' . $discountInfo . '
                            <p style="margin: 5px 0;"><strong>Celkem k úhradě:</strong> ' . number_format((float)$inv['total_with_vat'], 2, ',', ' ') . ' Kč</p>
                        </td>
                    </tr>
                </table>
                
                <p>Fakturu si můžete stáhnout kliknutím na tlačítko níže:</p>
                
                <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
                    <tr>
                        <td style="text-align: center;">
                            <a href="' . h($downloadUrl) . '" style="display: inline-block; background: #caba9C; color: white; text-decoration: none; padding: 12px 25px; border-radius: 5px; font-weight: bold;" target="_blank">📄 Stáhnout fakturu (PDF)</a>
                        </td>
                    </tr>
                </table>
                
                <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                
                <h3 style="color: #caba9C; margin: 0 0 15px 0;">🚚 Co bude dál?</h3>
                <p>Vaše objednávka bude zpracována a odeslána v nejbližší možný termín. O odeslání Vás budeme informovat e-mailem.</p>
                
                <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
                
                <h3 style="color: #caba9C; margin: 0 0 15px 0;">Kontakt</h3>
                <p style="margin: 5px 0;"><strong>Telefon:</strong> 722 341 256</p>
                <p style="margin: 5px 0;"><strong>Email:</strong> info@kubajadesigns.eu</p>
            </td>
        </tr>
        <tr>
            <td style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eee;">
                <p style="margin: 0; color: #666; font-size: 14px;">Děkujeme za Vaši důvěru!</p>
                <p style="margin: 5px 0 0 0; color: #999; font-size: 12px;">Tým KubaJa Designs</p>
            </td>
        </tr>
    </table>
</body>
</html>';
    $mail->AltBody = 'Děkujeme za objednávku! Platba byla zpracována. Faktura ' . $number . ' ke stažení: ' . $downloadUrl . ' Kontakt: 722341256, info@kubajadesigns.eu';

    // Attach PDF or HTML
    error_log('DEBUG: pdfGenerated=' . ($pdfGenerated ? 'true' : 'false') . ', pdfData size=' . ($pdfData ? strlen($pdfData) : 'null'));
    
    if ($pdfGenerated && $pdfData) {
        $mail->addStringAttachment($pdfData, 'faktura_' . $safeNumber . '.pdf', 'base64', 'application/pdf');
        error_log('SUCCESS: PDF attached to email for invoice ' . $number . ', size: ' . strlen($pdfData) . ' bytes');
    } else {
        // Generate HTML fallback if PDF failed
        $htmlContent = $pdfContent ?? 'Faktura ' . $number . ' - HTML verze';
        $filename = 'faktura_' . $safeNumber . '.html';
        $mail->addStringAttachment($htmlContent, $filename, 'base64', 'text/html; charset=UTF-8');
        error_log('FALLBACK: HTML attached to email for invoice ' . $number . ', PDF generation failed');
    }

    // Send email
    $mail->send();

    $_SESSION['admin_success'] = 'Faktura byla odeslána na ' . h($inv['buyer_email']) . ($pdfGenerated ? ' (PDF)' : ' (HTML)') . '.';
    header('Location: admin_invoice_detail.php?id=' . (int)$id);
    exit;

} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Chyba při odesílání faktury: ' . $e->getMessage();
    header('Location: admin_invoice_detail.php?id=' . (int)$id);
    exit;
}
