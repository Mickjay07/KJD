<?php
// Simple test file for send payment link
header('Content-Type: application/json');

// Disable all error reporting
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Get JSON input
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Debug information
$debug_info = [
    'raw_input' => $raw_input,
    'json_decode_result' => $input,
    'json_last_error' => json_last_error_msg(),
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
];

if (!$input || !isset($input['order_id']) || !isset($input['revolut_link']) || $input['action'] !== 'send_payment_link') {
    echo json_encode([
        'success' => false, 
        'message' => 'Neplatné parametry',
        'debug' => $debug_info
    ]);
    exit;
}

$order_id = intval($input['order_id']);
$revolut_link = trim($input['revolut_link']);

if (empty($revolut_link)) {
    echo json_encode(['success' => false, 'message' => 'Revolut odkaz je povinný']);
    exit;
}

// Simulate success response
echo json_encode([
    'success' => true, 
    'message' => 'Test: Platební odkaz pro objednávku ' . $order_id . ' byl úspěšně odeslán (test režim)'
]);
?>
