<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Force Newsletter</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .btn { padding: 10px 20px; margin: 10px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
    </style>
</head>
<body>
    <h1>Force Newsletter Modal</h1>
    
    <p>Tato stránka vymaže newsletter flag a přesměruje na hlavní stránku, kde se newsletter modal zobrazí okamžitě.</p>
    
    <button class="btn btn-primary" onclick="forceNewsletter()">Zobrazit Newsletter na Hlavní Stránce</button>
    
    <div id="status" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;"></div>
    
    <script>
        function updateStatus() {
            const status = document.getElementById('status');
            const newsletterShown = localStorage.getItem('newsletterShown');
            status.innerHTML = `
                <strong>Current status:</strong><br>
                newsletterShown: ${newsletterShown || 'null'}
            `;
        }
        
        function forceNewsletter() {
            // Clear newsletter flag
            localStorage.removeItem('newsletterShown');
            
            // Redirect to main page
            window.location.href = 'index.php';
        }
        
        // Update status on load
        updateStatus();
    </script>
</body>
</html>
