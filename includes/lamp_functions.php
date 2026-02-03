<?php
/**
 * Functions for Lamp Configurator
 */

function get_lamp_components($conn, $type = null) {
    if ($type) {
        $stmt = $conn->prepare("SELECT * FROM lamp_components WHERE type = ? AND active = 1 ORDER BY name ASC");
        $stmt->execute([$type]);
    } else {
        $stmt = $conn->query("SELECT * FROM lamp_components WHERE active = 1 ORDER BY type, name ASC");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_component_by_id($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM lamp_components WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
