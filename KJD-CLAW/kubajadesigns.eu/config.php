<?php
// Kontrola, zda již session není spuštěna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Načtení pomocných funkcí
require_once __DIR__ . '/functions.php';

// Databázové připojení
$servername = "wh51.farma.gigaserver.cz"; // správně, ne DB_HOST
$username = "81986_KJD";
$password = "2007mickey";
$dbname = "kubajadesigns_eu_";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Odstraníme odtud kontrolu režimu údržby, ta je nyní v maintenance_check.php
    
} catch(PDOException $e) {
    // Chybová zpráva při neúspěšném připojení k DB
    // V produkčním prostředí by neměla být zobrazena
    if (strpos($current_file, 'admin') !== false) {
        echo "Chyba připojení k databázi: " . $e->getMessage();
    }
}

// Packeta (Zásilkovna) API credentials
define('PACKETA_API_KEY', 'fc9e38cb95f48f3f');
define('PACKETA_API_PASSWORD', 'fc9e38cb95f48f3ff7d7f525307e9cfd');

// GoPay Payment Gateway Configuration - PRODUCTION
define('GOPAY_GOID', '8245698549'); // Production GoID
define('GOPAY_CLIENT_ID', '1216251718'); // Production Client ID
define('GOPAY_CLIENT_SECRET', 'c4re3hPB'); // Production Client Secret
define('GOPAY_IS_PRODUCTION', true); // PRODUCTION MODE - LIVE PAYMENTS
define('GOPAY_LANGUAGE', 'CS');
define('GOPAY_CURRENCY', 'CZK');
define('GOPAY_GATEWAY_URL', 'https://gate.gopay.cz/api'); // Production URL
define('GOPAY_CALLBACK_URL', 'https://kubajadesigns.eu/gopay_callback.php');
define('GOPAY_RETURN_URL', 'https://kubajadesigns.eu/order_confirmation.php');
define('GOPAY_NOTIFICATION_URL', 'https://kubajadesigns.eu/gopay_notify.php');

// Ruční načtení PHPMaileru s robustní detekcí cesty (funguje jak v /02/, tak z rootu)
function kjd_require_first_existing(array $paths) {
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; return true; }
    }
    return false;
}

$baseCandidates = [
    // Nejprve root vendor/
    dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src',
    // Poté lokální vendor v /02/ (pokud by existoval)
    __DIR__ . '/vendor/phpmailer/phpmailer/src',
    // Ruční balík PHPMailer v rootu
    dirname(__DIR__) . '/PHPMailer-6.9.3 2/src',
];

$loaded = kjd_require_first_existing(array_map(function($b){ return $b . '/Exception.php'; }, $baseCandidates))
       && kjd_require_first_existing(array_map(function($b){ return $b . '/PHPMailer.php'; }, $baseCandidates))
       && kjd_require_first_existing(array_map(function($b){ return $b . '/SMTP.php'; }, $baseCandidates));

if (!$loaded) {
    // Pokud PHPMailer není k dispozici, neházej fatální chybu – jen ho nepoužijeme
    // (odesílání e-mailů pak musí být ošetřeno v kódu, který PHPMailer potřebuje)
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
?>
