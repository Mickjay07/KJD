<?php
require_once 'config.php';
require_once __DIR__ . '/../includes/InvoiceGenerator.php';

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Support both GET and POST methods
$order_id = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = trim($_POST['order_id'] ?? '');
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $order_id = trim($_GET['order_id'] ?? '');
} else {
    header('Location: admin_invoices.php');
    exit;
}

if ($order_id === '') {
    $_SESSION['admin_error'] = 'Chybí ID objednávky.';
    header('Location: admin_invoices.php');
    exit;
}

try {
    // Use InvoiceGenerator to create invoice from order
    $generator = new InvoiceGenerator($conn);
    $invoiceId = $generator->createFromOrder($order_id);
    
    // Get invoice number for success message
    $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $_SESSION['admin_success'] = 'Faktura ' . h($invoice['invoice_number'] ?? $invoiceId) . ' byla úspěšně vytvořena.';
    header('Location: admin_invoice_detail.php?id=' . $invoiceId);
    exit;

} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Chyba při vytváření faktury: ' . $e->getMessage();
    header('Location: admin_invoices.php');
    exit;
}