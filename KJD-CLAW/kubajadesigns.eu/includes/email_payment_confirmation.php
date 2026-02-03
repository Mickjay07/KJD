<?php
/**
 * Send payment confirmation email with invoice PDF attachment
 * Called after successful GoPay payment
 */

function sendPaymentConfirmationWithInvoice($orderId, $invoiceId) {
    global $conn;
    
    // Load order
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        error_log("Cannot send payment email - order not found: $orderId");
        return false;
    }
    
    // Load invoice
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        error_log("Cannot send payment email - invoice not found: $invoiceId");
        return false;
    }
    
    // Generate PDF content
    $pdfContent = generateInvoicePDF($invoiceId);
    if (!$pdfContent) {
        error_log("Failed to generate PDF for invoice $invoiceId");
        return false;
    }
    
    $to = $order['email'];
    $subject = "✓ Platba potvrzena - Faktura #{$invoice['invoice_number']} - KJD";
    
    // HTML email body
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; color: #102820; margin: 0; padding: 0; background: #f8f9fa; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: linear-gradient(135deg, #4c6444, #102820); color: #fff; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px; line-height: 1.6; }
            .success-badge { 
                background: linear-gradient(135deg, #51cf66, #37b24d); 
                color: #fff; 
                padding: 20px; 
                border-radius: 12px; 
                text-align: center; 
                font-size: 18px; 
                font-weight: bold; 
                margin: 20px 0;
                box-shadow: 0 4px 15px rgba(81,207,102,0.3);
            }
            .info-box { 
                background: #f5f0e8; 
                padding: 20px; 
                border-left: 4px solid #8A6240; 
                margin: 20px 0; 
                border-radius: 8px;
            }
            .info-box p { margin: 8px 0; }
            .footer { 
                background: #4D2D18; 
                color: #fff; 
                padding: 20px; 
                text-align: center; 
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✓ Platba úspěšně přijata!</h1>
            </div>
            
            <div class='content'>
                <div class='success-badge'>
                    ✓ Vaše platba byla potvrzena
                </div>
                
                <h2>Dobrý den, " . htmlspecialchars($order['name']) . "!</h2>
                
                <p>Děkujeme za platbu! Vaše objednávka byla úspěšně zaplacena a nyní ji připravujeme k odeslání.</p>
                
                <div class='info-box'>
                    <p><strong>Číslo objednávky:</strong> {$orderId}</p>
                    <p><strong>Číslo faktury:</strong> {$invoice['invoice_number']}</p>
                    <p><strong>Celková částka:</strong> " . number_format($order['total_price'], 0, ',', ' ') . " Kč</p>
                    <p><strong>Status platby:</strong> <span style='color: #51cf66;'>✓ ZAPLACENO</span></p>
                </div>
                
                <h3 style='color: #4D2D18;'>📄 Faktura</h3>
                <p><strong>Faktura je v příloze tohoto emailu jako PDF soubor.</strong></p>
                <p>Klikněte na přílohu a uložte si ji nebo ji otevřete přímo.</p>
                
                <h3 style='color: #4D2D18;'>📦 Co dál?</h3>
                <p>Vaši objednávku nyní zpracováváme a brzy ji připravíme k odeslání. O odeslání vás budeme informovat dalším emailem se sledovacím číslem zásilky.</p>
                
                <p style='margin-top: 30px; font-weight: 600;'>Děkujeme za nákup!</p>
                <p><strong>Tým KJD Designs</strong></p>
            </div>
            
            <div class='footer'>
                <p style='margin: 5px 0;'><strong>Kubajadesigns.eu</strong></p>
                <p style='margin: 5px 0;'>Email: info@kubajadesigns.eu</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Multipart MIME for attachment
    $boundary = md5(time());
    $filename = "Faktura_{$invoice['invoice_number']}.pdf";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'From: KJD Designs <info@kubajadesigns.eu>',
        'Reply-To: info@kubajadesigns.eu'
    ];
    
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    
    // Attach PDF
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
    $message .= chunk_split(base64_encode($pdfContent)) . "\r\n";
    $message .= "--{$boundary}--";
    
    $result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if ($result) {
        error_log("Payment confirmation email with PDF sent for order $orderId to $to");
    } else {
        error_log("Failed to send payment confirmation email for order $orderId");
    }
    
    return $result;
}

/**
 * Generate Invoice PDF using TCPDF
 */
function generateInvoicePDF($invoiceId) {
    global $conn;
    
    // Load invoice
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        return false;
    }
    
    // Load invoice items
    $stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load settings
    $settings = $conn->query('SELECT * FROM invoice_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Use TCPDF
    require_once(__DIR__ . '/../admin/tcpdf/tcpdf.php');
    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    $pdf->SetCreator('KJD Designs');
    $pdf->SetAuthor('KJD Designs');
    $pdf->SetTitle('Faktura ' . $invoice['invoice_number']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    
    $pdf->AddPage();
    
    // Set font - use DejaVu for Czech characters support
    $pdf->SetFont('dejavusans', '', 10);
    
    // Header
    $pdf->SetFillColor(16, 40, 32);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 15, 'KJD Designs - Faktura ' . $invoice['invoice_number'], 0, 1, 'C', true);
    $pdf->Ln(5);
    
    // Reset text color
    $pdf->SetTextColor(0, 0, 0);
    
    // Invoice details
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 7, 'Faktura č. ' . $invoice['invoice_number'], 0, 1);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 5, 'Datum vystavení: ' . $invoice['issue_date'], 0, 1);
    $pdf->Cell(0, 5, 'Datum splatnosti: ' . $invoice['due_date'], 0, 1);
    $pdf->Ln(5);
    
    // Seller and Buyer side by side
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(90, 7, 'Dodavatel', 0, 0);
    $pdf->Cell(90, 7, 'Odběratel', 0, 1);
    
    $pdf->SetFont('dejavusans', '', 9);
    
    // Build seller and buyer info
    $sellerInfo = ($settings['seller_name'] ?? 'KJD Designs') . "\n";
    $sellerInfo .= ($settings['seller_address1'] ?? '') . "\n";
    $sellerInfo .= ($settings['seller_zip'] ?? '') . ' ' . ($settings['seller_city'] ?? '') . "\n";
    $sellerInfo .= "IČO: 23982381\n";
    if (!empty($settings['seller_dic'])) $sellerInfo .= "DIČ: " . $settings['seller_dic'] . "\n";
    
    $buyerInfo = $invoice['buyer_name'] . "\n";
    if (!empty($invoice['buyer_address1'])) $buyerInfo .= $invoice['buyer_address1'] . "\n";
    $buyerInfo .= ($invoice['buyer_zip'] ?? '') . ' ' . ($invoice['buyer_city'] ?? '') . "\n";
    if (!empty($invoice['buyer_email'])) $buyerInfo .= $invoice['buyer_email'] . "\n";
    
    $pdf->MultiCell(90, 5, $sellerInfo, 0, 'L', false, 0);
    $pdf->MultiCell(90, 5, $buyerInfo, 0, 'L', false, 1);
    
    $pdf->Ln(10);
    
    // Items table
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(76, 100, 68);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(80, 7, 'Položka', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'Množství', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Jedn. cena', 1, 0, 'R', true);
    $pdf->Cell(40, 7, 'Celkem', 1, 1, 'R', true);
    
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    foreach ($items as $item) {
        $pdf->Cell(80, 6, $item['name'], 1, 0, 'L');
        $pdf->Cell(20, 6, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(40, 6, number_format($item['unit_price_without_vat'], 2) . ' Kč', 1, 0, 'R');
        $pdf->Cell(40, 6, number_format($item['total_with_vat'], 2) . ' Kč', 1, 1, 'R');
    }
    
    $pdf->Ln(5);
    
    // Summary
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->Cell(140, 7, 'Celkem:', 0, 0, 'R');
    $pdf->Cell(40, 7, number_format($invoice['total_without_vat'], 2) . ' Kč', 0, 1, 'R');
    
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(140, 7, 'DPH (0% - neplátce):', 0, 0, 'R');
    $pdf->Cell(40, 7, '0,00 Kč', 0, 1, 'R');
    
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(140, 10, 'Celkem k úhradě:', 0, 0, 'R');
    $pdf->Cell(40, 10, number_format($invoice['total_with_vat'], 2) . ' Kč', 0, 1, 'R');
    
    $pdf->Ln(10);
    $pdf->SetFont('dejavusans', 'I', 8);
    $pdf->Cell(0, 5, 'Dodavatel není plátcem DPH.', 0, 1);
    
    return $pdf->Output('', 'S'); // Return as string
}
