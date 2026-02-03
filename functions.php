<?php
/**
 * Utility functions for the KJD admin system
 */

/**
 * Returns the text representation of the given order status
 *
 * @param string $status The status code
 * @return string The status text in Czech
 */
function getStatusText($status) {
    $statuses = [
        'pending' => 'Čeká na zpracování',
        'processing' => 'Zpracovává se',
        'shipped' => 'Odesláno',
        'delivered' => 'Doručeno',
        'cancelled' => 'Zrušeno'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * Returns the Bootstrap CSS class for the given status
 *
 * @param string $status The status code
 * @return string The CSS class
 */
function getStatusClass($status) {
    $classes = [
        'pending' => 'bg-warning',
        'processing' => 'bg-info',
        'shipped' => 'bg-primary',
        'delivered' => 'bg-success',
        'cancelled' => 'bg-danger'
    ];
    
    return isset($classes[$status]) ? $classes[$status] : '';
}

/**
 * Checks if a product is a pre-order with a release date
 *
 * @param array $item The product item array
 * @return bool True if the product is a pre-order with a release date
 */
function isPreorderWithReleaseDate($item) {
    return ($item['is_preorder'] == 1 && (!empty($item['release_date']) || !empty($item['available_from'])));
}

/**
 * Gets the availability date for a product
 *
 * @param array $item The product item array
 * @return string|null The availability date or null if not set
 */
function getAvailabilityDate($item) {
    if (!empty($item['release_date'])) {
        return $item['release_date'];
    } elseif (!empty($item['available_from'])) {
        return $item['available_from'];
    }
    return null;
}

/**
 * Formats a date to localized Czech format
 *
 * @param string $date The date in MySQL format
 * @return string The formatted date
 */
function formatDate($date) {
    if (empty($date)) return '';
    $dateObj = new DateTime($date);
    return $dateObj->format('d.m.Y');
}

/**
 * Sets a flash message to be displayed on the next page load
 *
 * @param string $type The type of message (success, error, warning)
 * @param string $message The message text
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Gets and clears the flash message
 *
 * @return array|null The flash message or null if none exists
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $message;
}

/**
 * Check if user is logged in as admin
 * 
 * @return bool True if user is logged in as admin
 */
function isAdmin() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect to login page if not logged in as admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: admin_login.php');
        exit;
    }
}

/**
 * Generate a random string for order IDs and similar
 * 
 * @param int $length The length of the string
 * @return string The random string
 */
function generateRandomString($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Format price with currency
 * 
 * @param float $price The price
 * @param string $currency The currency code (default: Kč)
 * @return string The formatted price
 */
function formatPrice($price, $currency = 'Kč') {
    return number_format($price, 0, ',', ' ') . ' ' . $currency;
}

/**
 * Get the total price of an order
 * 
 * @param array $order_items The order items
 * @param float $shipping_cost The shipping cost
 * @return float The total price
 */
function calculateOrderTotal($order_items, $shipping_cost = 0) {
    $total = 0;
    foreach ($order_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total + $shipping_cost;
}

/**
 * Check if an order contains any pre-orders
 * 
 * @param array $order_items The order items
 * @return bool True if the order contains pre-orders
 */
function hasPreorders($order_items) {
    foreach ($order_items as $item) {
        if ($item['is_preorder'] == 1) {
            return true;
        }
    }
    return false;
}

/**
 * Check if an order contains any pre-orders with release dates
 * 
 * @param array $order_items The order items
 * @return bool True if the order contains pre-orders with release dates
 */
function hasPreordersWithReleaseDate($order_items) {
    foreach ($order_items as $item) {
        if (isPreorderWithReleaseDate($item)) {
            return true;
        }
    }
    return false;
}

/**
 * Returns the map of color hex codes to color names
 * 
 * @return array The color map
 */
function getColorMap() {
    return [
        '#00ff00' => 'Zelená',
        '#0000ff' => 'Modrá',
        '#ffff00' => 'Žlutá',
        '#ffd1dc' => 'Růžová',
        '#00ffff' => 'Tyrkysová',
        '#000000' => 'Černá',
        '#ffffff' => 'Bílá',
        '#808080' => 'Šedá',
        '#800000' => 'Vínová',
        '#808000' => 'Olivová',
        '#008000' => 'Tmavě zelená',
        '#800080' => 'Fialová',
        '#008080' => 'Modrozelená',
        '#000080' => 'Námořnická modrá',
        '#ffa500' => 'Oranžová',
        '#a52a2a' => 'Hnědá',
        '#deb887' => 'Béžová',
        '#d3d3d3' => 'Transparentně skleněná',
        '#c0c0c0' => 'Náhodná barva',
        '#ffd700' => 'Univerzální',
        '#00eaff' => 'Světle modrá',
        '#a7c17a' => 'Pistáciově zelená',
        '#d2b48c' => 'Hnědo-béžová',

        // Barvy pro sociální sítě
        '#4b0082' => 'TikTok',
        '#ff6347' => 'Instagram',
        '#ff1493' => 'YouTube',
        '#32cd32' => 'Snapchat',
        '#20b2aa' => 'Facebook',
        '#f0e68c' => 'Prázdné',
        '#8a2be2' => 'Orchidejová fialová',
        '#ff8c00' => 'Tmavě oranžová',
        '#d2691e' => 'Čokoládová',
        '#4169e1' => 'Královská modrá',
        '#c71585' => 'Střední orchidová růžová',
        '#adff2f' => 'Jarní zeleň',
        '#b22222' => 'Ohnivě červená',
        '#800000' => 'Kaštanová červená',
        '#fffff0' => '1', 
        '#5c403e' => '3',
        '#704f4c' => '2',
        '#4a3432' => '4',
    ];
}

function getColorName($hexColor) {
    $colorMap = getColorMap();
    
    $hexColor = strtolower($hexColor);
    return $colorMap[$hexColor] ?? $hexColor;
}

/**
 * Debug function to check file paths
 * @param string $path The file path to check
 * @param bool $returnOutput Whether to return the output or echo it
 * @return string|void The debug information if $returnOutput is true
 */
function debugFilePath($path, $returnOutput = false) {
    $output = "";
    $output .= "Checking path: " . $path . "\n";
    $output .= "File exists: " . (file_exists($path) ? "Yes" : "No") . "\n";
    $output .= "Is readable: " . (is_readable($path) ? "Yes" : "No") . "\n";
    
    if (file_exists($path)) {
        $output .= "File size: " . filesize($path) . " bytes\n";
        $output .= "File type: " . (is_file($path) ? "File" : "Not a file") . "\n";
        
        if (is_file($path)) {
            $output .= "MIME type: " . mime_content_type($path) . "\n";
        }
    }
    
    if ($returnOutput) {
        return $output;
    } else {
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
} 