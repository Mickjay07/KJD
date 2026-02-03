<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['admin_error'] = 'Chybí ID faktury.';
    header('Location: admin_invoices.php');
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE invoices SET status='paid', paid_date = CURDATE() WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['admin_success'] = 'Faktura byla označena jako zaplacená.';
    header('Location: admin_invoice_detail.php?id=' . $id);
    exit;
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Chyba: ' . $e->getMessage();
    header('Location: admin_invoice_detail.php?id=' . $id);
    exit;
}
