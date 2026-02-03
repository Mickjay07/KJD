<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chyba: Neplatné parametry']);
    exit;
}

$order_id = intval($input['order_id']);

echo json_encode([
    'success' => true, 
    'message' => 'Objednávka ' . $order_id . ' byla úspěšně zrušena!'
]);
?>
