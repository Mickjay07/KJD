<?php
session_start();

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    } else {
        header('Location: cart.php');
    }
    exit;
}

$useWallet = isset($_POST['use_wallet']) && $_POST['use_wallet'] == '1';
$walletAmount = isset($_POST['wallet_amount']) ? (float)$_POST['wallet_amount'] : 0;

// Validate wallet amount
if ($useWallet && $walletAmount < 0) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Wallet amount cannot be negative']);
    } else {
        header('Location: cart.php?error=negative_amount');
    }
    exit;
}

// Save to session
$_SESSION['use_wallet'] = $useWallet;
$_SESSION['wallet_amount'] = $walletAmount;

if ($isAjax) {
    echo json_encode(['success' => true, 'message' => 'Wallet settings saved']);
} else {
    // Redirect back to cart
    header('Location: cart.php?wallet_updated=1');
}
?>
