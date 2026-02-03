<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add Component
    if (isset($_POST['add_component'])) {
        $type = $_POST['type'];
        $name = trim($_POST['name']);
        $price = floatval($_POST['price_modifier']);
        
        if (!empty($name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO lamp_components (type, name, price_modifier) VALUES (?, ?, ?)");
                $stmt->execute([$type, $name, $price]);
                header("Location: admin_lamp_config.php?success=added");
            } catch (PDOException $e) {
                header("Location: admin_lamp_config.php?error=db_error");
            }
        } else {
            header("Location: admin_lamp_config.php?error=empty_name");
        }
        exit;
    }
    
    // Delete Component
    if (isset($_POST['delete_component'])) {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $conn->prepare("DELETE FROM lamp_components WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: admin_lamp_config.php?success=deleted");
        } catch (PDOException $e) {
            header("Location: admin_lamp_config.php?error=db_error");
        }
        exit;
    }
}

// Redirect back if accessed directly without POST
header("Location: admin_lamp_config.php");
exit;
?>
