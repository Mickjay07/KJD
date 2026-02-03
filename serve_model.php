<?php
/**
 * Serve 3D Model with XOR Encryption
 * 
 * This script serves 3D model files (.glb) with a simple XOR encryption
 * to prevent direct usage if downloaded. The client-side JS must decrypt it.
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *'); // Adjust if needed

// Get file parameter
$file = $_GET['file'] ?? '';

// Basic validation
if (empty($file)) {
    http_response_code(400);
    die('No file specified');
}

// Prevent directory traversal
$file = str_replace(['../', '..\\'], '', $file);
$file = ltrim($file, '/');

// Define allowed base directory for models
$baseDir = __DIR__; // Root directory
$filePath = $baseDir . '/' . $file;

// Check if file exists and is a GLB file
if (!file_exists($filePath) || !is_file($filePath) || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'glb') {
    http_response_code(404);
    die('File not found or invalid type');
}

// Set content type
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));

// XOR Key (must match JS)
$key = 0x42;

// Read and output file with XOR encryption
// Using a buffer to handle large files efficiently
$handle = fopen($filePath, 'rb');
if ($handle) {
    while (!feof($handle)) {
        $buffer = fread($handle, 8192);
        $len = strlen($buffer);
        for ($i = 0; $i < $len; $i++) {
            $buffer[$i] = chr(ord($buffer[$i]) ^ $key);
        }
        echo $buffer;
        flush();
    }
    fclose($handle);
}
