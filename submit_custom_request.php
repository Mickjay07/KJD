<?php
session_start();
require_once 'config.php';

// Ensure table exists (Lazy creation workaround for CLI connection issues)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS custom_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        file_path VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        material_id INT,
        infill VARCHAR(50),
        note TEXT,
        status VARCHAR(50) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {
    // Ignore if table exists or error, insert will fail later if critical
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $material_id = $_POST['filament_id'] ?? null;
    $infill = $_POST['infill'] ?? '';
    $note = $_POST['note'] ?? '';
    
    // File Upload
    if (isset($_FILES['stl_file']) && $_FILES['stl_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/custom_prints/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $originalName = basename($_FILES['stl_file']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if ($ext !== 'stl') {
            die("Povoleny jsou pouze soubory .stl");
        }
        
        $filename = uniqid() . '_' . $originalName;
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['stl_file']['tmp_name'], $targetPath)) {
            // Insert into DB
            try {
                $stmt = $conn->prepare("INSERT INTO custom_requests (email, phone, file_path, original_filename, material_id, infill, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$email, $phone, $targetPath, $originalName, $material_id, $infill, $note]);
                
                // --- Email Sending using native mail() as in process_order.php ---
                
                $downloadLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $targetPath;
                
                // 1. Admin Notification
                $adminEmail = 'mickeyjarolim3@gmail.com';
                $subjectAdmin = "Nová poptávka 3D tisku: " . $email;
                
                $messageAdmin = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                        h2 { color: #102820; }
                        p { margin: 10px 0; }
                        .label { font-weight: bold; color: #555; }
                        .value { color: #000; }
                        .btn { display: inline-block; background: #4c6444; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 10px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Nová poptávka 3D tisku</h2>
                        <p><span class='label'>Email:</span> <span class='value'>$email</span></p>
                        <p><span class='label'>Telefon:</span> <span class='value'>$phone</span></p>
                        <p><span class='label'>Materiál ID:</span> <span class='value'>$material_id</span></p>
                        <p><span class='label'>Výplň:</span> <span class='value'>$infill</span></p>
                        <p><span class='label'>Poznámka:</span><br><span class='value'>" . nl2br(htmlspecialchars($note)) . "</span></p>
                        <p><a href='$downloadLink' class='btn'>Stáhnout soubor: $originalName</a></p>
                    </div>
                </body>
                </html>";

                $headersAdmin = [
                    'MIME-Version: 1.0',
                    'Content-type: text/html; charset=UTF-8',
                    'From: KJD Poptávky <info@kubajadesigns.eu>',
                    'Reply-To: ' . $email,
                    'X-Mailer: PHP/' . phpversion()
                ];

                mail($adminEmail, $subjectAdmin, $messageAdmin, implode("\r\n", $headersAdmin));

                // 2. User Confirmation
                $subjectUser = 'Potvrzení přijetí poptávky - KubaJa Designs';
                $messageUser = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                        h2 { color: #102820; }
                        p { margin: 10px 0; }
                        .footer { margin-top: 20px; font-size: 12px; color: #888; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Dobrý den,</h2>
                        <p>děkujeme za Vaši poptávku 3D tisku.</p>
                        <p>Model <strong>$originalName</strong> jsme v pořádku přijali.</p>
                        <p>Brzy se Vám ozveme s cenovou nabídkou.</p>
                        <br>
                        <p>S pozdravem,<br><strong>Tým KubaJa Designs</strong></p>
                        <div class='footer'>
                            KubaJa Designs | info@kubajadesigns.eu
                        </div>
                    </div>
                </body>
                </html>";

                $headersUser = [
                    'MIME-Version: 1.0',
                    'Content-type: text/html; charset=UTF-8',
                    'From: KubaJa Designs <info@kubajadesigns.eu>',
                    'Reply-To: info@kubajadesigns.eu',
                    'X-Mailer: PHP/' . phpversion()
                ];

                mail($email, $subjectUser, $messageUser, implode("\r\n", $headersUser));
                
                // Success - Redirect with message
                $_SESSION['success_msg'] = "Vaše poptávka byla odeslána! Brzy se vám ozveme s cenovou nabídkou.";
                header("Location: custom_print.php?success=1");
                exit;
                
            } catch(PDOException $e) {
                die("Chyba databáze: " . $e->getMessage());
            }
        } else {
            die("Chyba při nahrávání souboru.");
        }
    } else {
        die("Nebyl vybrán žádný soubor.");
    }
}
?>
