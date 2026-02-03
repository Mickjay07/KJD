<?php
header('Cache-Control: no-store');
require_once __DIR__ . '/../config.php';

// RFC 8058 One-Click Unsubscribe: some providers send POST without body, we rely on query email
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo 'Bad Request';
  exit;
}

try {
  $stmt = $conn->prepare('DELETE FROM newsletter WHERE email = ?');
  $stmt->execute([$email]);
  http_response_code(204); // No Content
} catch (Exception $e) {
  error_log('Unsubscribe one-click error: ' . $e->getMessage());
  http_response_code(500);
  echo 'Server Error';
}

