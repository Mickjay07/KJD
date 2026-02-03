<?php
function getColorName($hexColor) {
    $colorMap = [
        '#ff0000' => 'Červená',
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
    
    $hexColor = strtolower($hexColor);
    return $colorMap[$hexColor] ?? $hexColor;
}

/**
 * Resolve Czech color name to hex from the same map.
 * If a hex is provided already, it is returned as-is.
 * Falls back to the original input when no mapping exists (to allow CSS named colors).
 */
function getColorHexByName($name) {
    $map = [
        '#ff0000' => 'Červená', '#00ff00' => 'Zelená', '#0000ff' => 'Modrá', '#ffff00' => 'Žlutá', '#ffd1dc' => 'Růžová', '#00ffff' => 'Tyrkysová', '#000000' => 'Černá', '#ffffff' => 'Bílá', '#808080' => 'Šedá', '#800000' => 'Vínová', '#808000' => 'Olivová', '#008000' => 'Tmavě zelená', '#800080' => 'Fialová', '#008080' => 'Modrozelená', '#000080' => 'Námořnická modrá', '#ffa500' => 'Oranžová', '#a52a2a' => 'Hnědá', '#deb887' => 'Béžová', '#d3d3d3' => 'Transparentně skleněná', '#c0c0c0' => 'Náhodná barva', '#ffd700' => 'Univerzální', '#00eaff' => 'Světle modrá', '#a7c17a' => 'Pistáciově zelená', '#d2b48c' => 'Hnědo-béžová', '#4b0082' => 'TikTok', '#ff6347' => 'Instagram', '#ff1493' => 'YouTube', '#32cd32' => 'Snapchat', '#20b2aa' => 'Facebook', '#f0e68c' => 'Prázdné', '#8a2be2' => 'Orchidejová fialová', '#ff8c00' => 'Tmavě oranžová', '#d2691e' => 'Čokoládová', '#4169e1' => 'Královská modrá', '#c71585' => 'Střední orchidová růžová', '#adff2f' => 'Jarní zeleň', '#b22222' => 'Ohnivě červená', '#800000' => 'Kaštanová červená', '#fffff0' => '1', '#5c403e' => '3', '#704f4c' => '2', '#4a3432' => '4',
    ];
    $n = mb_strtolower(trim($name), 'UTF-8');
    if (strpos($n, '#') === 0) return $n; // already hex
    foreach ($map as $hex => $label) {
        if (mb_strtolower($label, 'UTF-8') === $n) return $hex;
    }
    return $name; // allow CSS named colors or custom values
}
?>
