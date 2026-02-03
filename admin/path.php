<?php

echo "<pre>";
echo "Hledám PHPMailer...\n\n";

function findPHPMailer($dir) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($rii as $file) {
        if ($file->isDir()) continue;

        if (basename($file) === "PHPMailer.php") {
            echo "Nalezeno: " . $file->getPathname() . "\n";
        }
    }
}

// Začneme v DOCUMENT_ROOT
$start = $_SERVER['DOCUMENT_ROOT'];
echo "Searching in: $start\n\n";

findPHPMailer($start);