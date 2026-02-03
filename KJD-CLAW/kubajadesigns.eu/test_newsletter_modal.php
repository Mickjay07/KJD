<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Newsletter Modal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
        .btn-kjd-primary { background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green)); color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 12px; font-weight: 700; }
        .btn-kjd-primary:hover { background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green)); color: #fff; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Test Newsletter Modal</h1>
        <p>Klikněte na tlačítko pro otestování newsletter modalu:</p>
        
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newsletterModal">
            Otevřít Newsletter Modal
        </button>
        
        <div class="mt-3">
            <button type="button" class="btn btn-secondary" onclick="clearNewsletterFlag()">
                Vymazat newsletter flag (pro opětovné testování)
            </button>
        </div>
        
        <div class="mt-3">
            <h3>Console Log:</h3>
            <div id="console-log" style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; height: 200px; overflow-y: auto;"></div>
        </div>
    </div>

    <!-- Newsletter Popup Modal -->
    <div class="modal fade" id="newsletterModal" tabindex="-1" aria-labelledby="newsletterModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: 3px solid var(--kjd-earth-green);">
          <div class="modal-header" style="background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-gold-brown)); color: #fff; border-radius: 17px 17px 0 0;">
            <h5 class="modal-title" id="newsletterModalLabel" style="font-weight: 800;">
              <i class="fas fa-gift me-2"></i>Vítejte v KJD!
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center" style="padding: 2rem;">
            <h4 style="color: var(--kjd-dark-green); font-weight: 700; margin-bottom: 1rem;">
              Získejte 10% slevu!
            </h4>
            <p style="color: #666; margin-bottom: 2rem;">
              Zaregistrujte se do našeho newsletteru a získejte okamžitě 10% slevu na první nákup. 
              Slevový kód vám pošleme na email.
            </p>
            <form id="newsletterForm">
              <div class="mb-3">
                <input type="email" class="form-control" id="newsletterEmail" placeholder="Váš email" 
                       style="border: 2px solid var(--kjd-earth-green); border-radius: 12px; padding: 0.75rem; font-weight: 600;" required>
              </div>
              <button type="submit" class="btn btn-kjd-primary w-100" style="padding: 0.75rem 2rem; font-size: 1.1rem;">
                <i class="fas fa-gift me-2"></i>Získat 10% slevu
              </button>
            </form>
            <div id="newsletterMessage" class="mt-3" style="display: none;"></div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Override console.log to show in our div
        const originalLog = console.log;
        const originalError = console.error;
        const logDiv = document.getElementById('console-log');
        
        function addToLog(message, type = 'log') {
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? 'red' : 'black';
            logDiv.innerHTML += `<div style="color: ${color};">[${timestamp}] ${message}</div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            addToLog(args.join(' '), 'log');
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            addToLog(args.join(' '), 'error');
        };
        
        function clearNewsletterFlag() {
            localStorage.removeItem('newsletterShown');
            console.log('Newsletter flag cleared');
            alert('Newsletter flag vymazán! Nyní se modal zobrazí při načtení stránky.');
        }
        
        // Newsletter form submission
        document.getElementById('newsletterForm').addEventListener('submit', function(e) {
          e.preventDefault();
          console.log('Newsletter form submitted');
          
          const email = document.getElementById('newsletterEmail').value;
          const messageDiv = document.getElementById('newsletterMessage');
          
          console.log('Email:', email);
          console.log('Sending request to ajax/newsletter_signup.php');
          
          fetch('ajax/newsletter_signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
          })
          .then(response => {
            console.log('Response status:', response.status);
            return response.json();
          })
          .then(data => {
            console.log('Response data:', JSON.stringify(data));
            if (data.success) {
              messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + data.message + '</div>';
              localStorage.setItem('newsletterShown', 'true');
              setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('newsletterModal')).hide();
              }, 2000);
            } else {
              messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + data.message + '</div>';
            }
            messageDiv.style.display = 'block';
          })
          .catch(error => {
            console.error('Error:', error);
            messageDiv.innerHTML = '<div class="alert alert-danger">Chyba při odesílání: ' + error.message + '</div>';
            messageDiv.style.display = 'block';
          });
        });

        // Mark as shown when modal is closed
        document.getElementById('newsletterModal').addEventListener('hidden.bs.modal', function() {
          localStorage.setItem('newsletterShown', 'true');
          console.log('Newsletter modal closed, flag set');
        });
        
        console.log('Test page loaded');
        console.log('Current newsletterShown flag:', localStorage.getItem('newsletterShown'));
    </script>
</body>
</html>
