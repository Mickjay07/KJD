<?php
if (!isset($_COOKIE['cookie_consent'])) {
?>
<style>
  .cookie-consent {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
    color: #fff;
    padding: 1.5rem;
    z-index: 9999;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
    border-top: 3px solid var(--kjd-beige);
  }
  
  .cookie-consent-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
  }
  
  .cookie-consent-text {
    flex: 1;
    min-width: 300px;
  }
  
  .cookie-consent-text p {
    margin: 0;
    color: #fff;
    font-size: 0.95rem;
    line-height: 1.5;
    font-weight: 500;
  }
  
  .cookie-consent-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
  }
  
  .cookie-btn {
    padding: 0.65rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
  }
  
  .cookie-btn-accept {
    background: var(--kjd-beige);
    color: var(--kjd-dark-green);
  }
  
  .cookie-btn-accept:hover {
    background: #d4c4a8;
    transform: translateY(-2px);
  }
  
  .cookie-btn-decline {
    background: rgba(255,255,255,0.2);
    color: #fff;
    border: 2px solid rgba(255,255,255,0.3);
  }
  
  .cookie-btn-decline:hover {
    background: rgba(255,255,255,0.3);
  }
  
  .cookie-btn-info {
    color: var(--kjd-beige);
    text-decoration: underline;
    padding: 0.65rem 0.5rem;
    background: transparent;
  }
  
  .cookie-btn-info:hover {
    color: #fff;
  }
  
  @media (max-width: 768px) {
    .cookie-consent {
      padding: 1rem;
    }
    
    .cookie-consent-content {
      flex-direction: column;
      align-items: stretch;
    }
    
    .cookie-consent-buttons {
      width: 100%;
      justify-content: center;
    }
    
    .cookie-btn {
      flex: 1;
      text-align: center;
    }
  }
</style>

<div id="cookie-consent" class="cookie-consent">
    <div class="cookie-consent-content">
        <div class="cookie-consent-text">
            <p>
                <i class="fas fa-cookie-bite me-2"></i>
                Tento web používá soubory cookie k zajištění správného fungování a analýze návštěvnosti. 
                Kliknutím na "Přijmout" souhlasíte s použitím všech souborů cookie.
            </p>
        </div>
        <div class="cookie-consent-buttons">
            <button id="cookie-accept" class="cookie-btn cookie-btn-accept">
                <i class="fas fa-check me-1"></i> Přijmout
            </button>
            <a href="#" id="cookie-decline" class="cookie-btn cookie-btn-decline">
                <i class="fas fa-times me-1"></i> Odmítnout
            </a>
            <a href="/CE/GDPR.pdf" target="_blank" class="cookie-btn cookie-btn-info">
                Více informací
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cookieConsent = document.getElementById('cookie-consent');
    const acceptBtn = document.getElementById('cookie-accept');
    const declineBtn = document.getElementById('cookie-decline');

    // Check if cookie is already set
    if (document.cookie.split(';').some((item) => item.trim().startsWith('cookie_consent='))) {
        cookieConsent.style.display = 'none';
    }

    // Set cookie for 1 year
    function setCookieConsent(accepted) {
        const date = new Date();
        date.setFullYear(date.getFullYear() + 1);
        document.cookie = `cookie_consent=${accepted}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
        cookieConsent.style.display = 'none';
        
        // If you're using Google Analytics or other tracking, initialize it here
        if (accepted === 'accepted') {
            // Initialize analytics here
            // Example: gtag('consent', 'update', { 'analytics_storage': 'granted' });
        }
    }

    // Event listeners
    acceptBtn.addEventListener('click', function() {
        setCookieConsent('accepted');
    });

    declineBtn.addEventListener('click', function(e) {
        e.preventDefault();
        setCookieConsent('declined');
    });
});
</script>
<?php } ?>
