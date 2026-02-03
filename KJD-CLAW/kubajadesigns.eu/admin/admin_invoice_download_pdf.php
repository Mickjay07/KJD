<?php
/**
 * PDF Invoice Download/Preview Handler
 * Generates and outputs PDF invoice using TCPDF
 */
require_once 'config.php';
require_once __DIR__ . '/../includes/email_payment_confirmation.php'; // Contains generateInvoicePDF function

session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$invoice_id = (int)($_GET['id'] ?? 0);
if ($invoice_id <= 0) {
    http_response_code(400);
    echo 'Invalid invoice ID';
    exit;
}

// Get invoice data to validate it exists
$stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    http_response_code(404);
    echo 'Invoice not found';
    exit;
}

try {
    // Generate PDF content using existing function
    $pdfContent = generateInvoicePDF($invoice_id);
    
    if (!$pdfContent) {
        throw new Exception('Failed to generate PDF');
    }
    
    // Check if preview mode (inline display) or download
    $preview = isset($_GET['preview']) && $_GET['preview'] == '1';
    
    // Clean filename
    $filename = 'Faktura_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $invoice['invoice_number']) . '.pdf';
    
    // Set headers for PDF output
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdfContent));
    
    if ($preview) {
        // Display inline in browser
        header('Content-Disposition: inline; filename="' . $filename . '"');
    } else {
        // Force download
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
    
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output PDF
    echo $pdfContent;
    exit;
    
} catch (Exception $e) {
    error_log('PDF generation error for invoice ' . $invoice_id . ': ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating PDF: ' . htmlspecialchars($e->getMessage());
    exit;
}
?>
