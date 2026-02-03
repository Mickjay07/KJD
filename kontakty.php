<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$pageTitle = 'Kontaktujte nás | KJD';
$pageDescription = 'Máte dotaz nebo zájem o naše produkty? Kontaktujte nás a my vám rádi odpovíme.';

// Calculate cart count and total
$cart_count = 0;
$cart_total = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int)($item['quantity'] ?? 0);
        $cart_total += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <title><?php echo $pageTitle; ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
      :root { --kjd-dark-green:#102820; --kjd-earth-green:#4c6444; --kjd-gold-brown:#8A6240; --kjd-dark-brown:#4D2D18; --kjd-beige:#CABA9C; }
      
      /* Apple SF Pro Font */
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      }
      
      body {
        background-color: #f8f9fa;
        color: #333;
        line-height: 1.6;
      }
      
      /* Header Styles */
      .page-header {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: white;
        padding: 4rem 0;
        margin-bottom: 3rem;
        text-align: center;
      }
      
      .page-header h1 {
        font-weight: 700;
        margin-bottom: 1rem;
      }
      
      .page-header p {
        font-size: 1.2rem;
        opacity: 0.9;
      }
      
      /* Contact Cards */
      .contact-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        padding: 2rem;
        margin-bottom: 2rem;
        height: 100%;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
      }
      
      .contact-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      }
      
      .contact-icon {
        font-size: 2.5rem;
        color: var(--kjd-gold-brown);
        margin-bottom: 1.5rem;
      }
      
      /* Contact Form */
      .contact-form {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        padding: 2.5rem;
        margin-bottom: 2rem;
      }
      
      .form-control, .form-select {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        margin-bottom: 1.25rem;
        transition: all 0.3s;
      }
      
      .form-control:focus, .form-select:focus {
        border-color: var(--kjd-beige);
        box-shadow: 0 0 0 0.25rem rgba(202, 186, 156, 0.25);
      }
      
      .btn-primary {
        background-color: var(--kjd-earth-green);
        border: none;
        border-radius: 8px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s;
      }
      
      .btn-primary:hover {
        background-color: var(--kjd-dark-green);
        transform: translateY(-2px);
      }
      
      /* Map Container */
      .map-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
      }
      
      /* FAQ Section */
      .faq-item {
        margin-bottom: 1.5rem;
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
      }
      
      .faq-question {
        background: white;
        padding: 1.25rem;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s;
      }
      
      .faq-question:hover {
        background-color: #f9f9f9;
      }
      
      .faq-answer {
        padding: 1.25rem;
        background: #f9f9f9;
        border-top: 1px solid #eee;
        display: none;
      }
      
      .faq-item.active .faq-answer {
        display: block;
      }
      
      /* KJD Custom Preloader */
      .preloader-wrapper {
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8) !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
      }
      
      .preloader-wrapper .preloader {
        margin: 0;
        transform: none;
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5rem;
        background: transparent;
        border: none;
        border-radius: 0;
      }
      
      .preloader:before,
      .preloader:after {
        display: none !important;
      }
      
      .preloader-text {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--kjd-dark-green);
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        margin-bottom: 0.5rem;
        animation: textFadeIn 1s ease-out;
      }
      
      .preloader-progress {
        width: 300px;
        height: 6px;
        background: rgba(202,186,156,0.3);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
        margin-bottom: 1rem;
      }
      
      .preloader-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--kjd-earth-green), var(--kjd-dark-green));
        border-radius: 10px;
        width: 0;
        transition: width 0.3s ease;
        box-shadow: 0 2px 8px rgba(76,100,68,0.3);
      }
      
      .preloader-percentage {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--kjd-gold-brown);
      }
      
      @keyframes textFadeIn {
        0% { opacity: 0; transform: translateY(-20px); }
        100% { opacity: 1; transform: translateY(0); }
      }
      
      @keyframes zoomOut {
        0% { transform: scale(1); opacity: 1; }
        100% { transform: scale(1.2); opacity: 0; }
      }
      
      .preloader-wrapper.fade-out {
        animation: zoomOut 0.8s ease-in-out forwards;
      }
      
      /* Responsive Adjustments */
      @media (max-width: 767.98px) {
        .page-header {
          padding: 1.5rem 0;
        }
        
        .contact-form {
          padding: 1.5rem;
        }
      }
        
        .btn-primary {
            background-color: var(--kjd-dark-brown);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--kjd-gold-brown);
        }
        
        .map-container {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .map-container iframe {
            width: 100%;
            height: 100%;
            min-height: 300px;
    </style>
</head>
<body class="contact-page">

    <?php include 'includes/icons.php'; ?>

    <div class="preloader-wrapper">
      <div class="preloader">
        <div class="preloader-text">kubajadesigns.eu</div>
        <div class="preloader-progress">
          <div class="preloader-progress-bar"></div>
        </div>
        <div class="preloader-percentage">0%</div>
      </div>
    </div>

    <?php include 'includes/navbar.php'; ?>

    <!-- Page Title -->
    <section class="py-4" style="background: var(--kjd-beige); border-bottom: 2px solid var(--kjd-earth-green);">
        <div class="container">
            <h1 class="mb-0" style="color: var(--kjd-dark-green); font-weight: 700; font-size: 2rem;">
                <i class="fas fa-envelope me-2"></i>Kontaktujte nás
            </h1>
            <p class="mb-0 mt-2" style="color: var(--kjd-gold-brown);">Jsme tu pro vás a rádi vám pomůžeme</p>
        </div>
    </section>

    <!-- Contact Info Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="contact-card text-center h-100">
                        <div class="contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <h3 class="h4 mb-3" style="color: var(--kjd-dark-green);">Telefon</h3>
                        <p class="mb-2"><a href="tel:+420 722 341 256" class="text-decoration-none" style="color: var(--kjd-gold-brown);">+420 722 341 256</a></p>
                        <p class="text-muted mb-0">Po-Pá: 9:00 - 18:00</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-card text-center h-100">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3 class="h4 mb-3" style="color: var(--kjd-dark-green);">Email</h3>
                        <p class="mb-2"><a href="mailto:info@kubajadesigns.eu" class="text-decoration-none" style="color: var(--kjd-gold-brown);">info@kubajadesigns.eu</a></p>
                        <div class="alert alert-warning mt-3 mb-0" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 0.75rem; font-size: 0.85rem;">
                            <i class="fas fa-exclamation-triangle me-1" style="color: #856404;"></i>
                            <strong style="color: #856404;">Pozor:</strong> <span style="color: #856404;">Naše emaily často končí ve spamu. Prosím, zkontrolujte složku Spam/Promo!</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-card text-center h-100">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3 class="h4 mb-3" style="color: var(--kjd-dark-green);">Adresa</h3>
                        <p class="mb-1 fw-bold">Jakub Jarolím</p>
                        <p class="mb-1">Mezilesí 2078</p>
                        <p class="mb-1">193 00 Praha 20</p>
                        <p class="mb-0">Česká republika</p>
                        <p class="mb-0 mt-2"><strong>IČO:</strong> 23982381</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form & Map Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="contact-form">
                        <h2 class="h3 mb-4" style="color: var(--kjd-dark-green);">Napište nám zprávu</h2>
                        <form id="contactForm" action="process_contact.php" method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Jméno a příjmení <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Telefon</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+420 123 456 789">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Předmět <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Zpráva <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <div class="alert alert-warning mb-3" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 1rem;">
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                                    <i class="fas fa-exclamation-triangle" style="color: #856404; font-size: 1.1rem; margin-top: 0.1rem;"></i>
                                    <div>
                                        <strong style="color: #856404; font-size: 0.95rem;">Důležité upozornění:</strong>
                                        <p style="color: #856404; font-size: 0.85rem; margin: 0.5rem 0 0 0; line-height: 1.5;">
                                            Všechny naše emaily často končí ve složce Spam. Prosím, zkontrolujte si po odeslání zprávy složku Spam/Promo, aby vám naše odpověď neunikla!
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="gdpr" name="gdpr" required>
                                    <label class="form-check-label" for="gdpr">
                                        Souhlasím se zpracováním osobních údajů v souladu s 
                                        <a href="/CE/GDPR.pdf" target="_blank" class="text-decoration-underline" style="color: var(--kjd-gold-brown);">zásadami ochrany osobních údajů</a> 
                                        <span class="text-danger">*</span>
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Odeslat zprávu</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="map-container h-100">
                        <iframe src="https://www.google.com/maps?q=Mezilesí+2078,+193+00+Praha+20,+Česká+republika&output=embed" width="100%" height="100%" style="border:0; min-height: 450px;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="h1 mb-3" style="color: var(--kjd-dark-green);">Často kladené dotazy</h2>
                <p class="lead text-muted">Odpovědi na nejčastější otázky našich zákazníků</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Jak dlouho trvá dodání zboží?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p class="mb-0">Dodací lhůta se pohybuje obvykle 3-5 pracovních dní od odeslání objednávky. U ručně vyráběných kusů může být dodací lhůta delší, o čemž vás budeme informovat.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Jak mohu platit za objednávku?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p class="mb-0">Přijímáme platby bankovním převodem, platební kartou online. Všechny platby jsou zabezpečeny šifrováním SSL.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Jak mohu vrátit zboží?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p class="mb-0">Zboží můžete vrátit do 14 dnů od převzetí. Podrobné informace o vrácení zboží naleznete v sekci <a href="odstoupeni-od-smlouvy.php" class="text-decoration-underline" style="color: var(--kjd-gold-brown);">Vrácení zboží</a>.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Jak mám postupovat při reklamaci?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p class="mb-0">V případě reklamace nás prosím kontaktujte na emailu <a href="mailto:info@kubajadesigns.eu" class="text-decoration-underline" style="color: var(--kjd-gold-brown);">info@kubajadesigns.eu</a> nebo vyplňte náš <a href="reklamace.php" class="text-decoration-underline" style="color: var(--kjd-gold-brown);">reklamační formulář</a>. Každou reklamaci řešíme individuálně a co nejrychleji.</p>
                        </div>
                    </div>
                    
                    
                </div>
            </div>
        </div>
    </section>

    <script>
        // FAQ Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const faqQuestions = document.querySelectorAll('.faq-question');
            
            faqQuestions.forEach(question => {
                question.addEventListener('click', function() {
                    const faqItem = this.parentElement;
                    const isActive = faqItem.classList.contains('active');
                    
                    // Close all FAQ items
                    document.querySelectorAll('.faq-item').forEach(item => {
                        item.classList.remove('active');
                        const icon = item.querySelector('.fa-chevron-down');
                        if (icon) {
                            icon.style.transform = 'rotate(0deg)';
                        }
                    });
                    
                    // Toggle current item if it wasn't active
                    if (!isActive) {
                        faqItem.classList.add('active');
                        const icon = this.querySelector('.fa-chevron-down');
                        if (icon) {
                            icon.style.transform = 'rotate(180deg)';
                        }
                    }
                });
            });
            
            // Form submission handling
            const contactForm = document.getElementById('contactForm');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Odesílám...';
                    
                    // Remove any existing alerts
                    const existingAlerts = document.querySelectorAll('.contact-form .alert');
                    existingAlerts.forEach(alert => alert.remove());
                    
                    // Send form data via AJAX
                    const formData = new FormData(this);
                    
                    fetch('process_contact.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            const successAlert = `
                                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Děkujeme za vaši zprávu!</strong> ${data.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            
                            // Insert success message after the form
                            const formContainer = document.querySelector('.contact-form');
                            if (formContainer) {
                                formContainer.insertAdjacentHTML('beforeend', successAlert);
                            }
                            
                            // Reset form
                            contactForm.reset();
                            
                            // Scroll to success message
                            window.scrollTo({
                                top: document.querySelector('.contact-form').offsetTop - 100,
                                behavior: 'smooth'
                            });
                        } else {
                            // Show error message
                            const errorAlert = `
                                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <strong>Chyba!</strong> ${data.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            
                            const formContainer = document.querySelector('.contact-form');
                            if (formContainer) {
                                formContainer.insertAdjacentHTML('beforeend', errorAlert);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        const errorAlert = `
                            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Chyba!</strong> Nepodařilo se odeslat zprávu. Zkuste to prosím později.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        
                        const formContainer = document.querySelector('.contact-form');
                        if (formContainer) {
                            formContainer.insertAdjacentHTML('beforeend', errorAlert);
                        }
                    })
                    .finally(() => {
                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="js/jquery-1.11.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="js/script.js"></script>
    
    <script>
        // Custom Preloader Script
        document.addEventListener('DOMContentLoaded', function() {
          const preloaderWrapper = document.querySelector('.preloader-wrapper');
          const progressBar = document.querySelector('.preloader-progress-bar');
          const percentageElement = document.querySelector('.preloader-percentage');
          let progress = 0;
          
          const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 100) progress = 100;
            
            progressBar.style.width = progress + '%';
            percentageElement.textContent = Math.round(progress) + '%';
            
            if (progress >= 100) {
              clearInterval(progressInterval);
              
              setTimeout(() => {
                preloaderWrapper.classList.add('fade-out');
                setTimeout(() => {
                  preloaderWrapper.style.display = 'none';
                }, 800);
              }, 500);
            }
          }, 150);
          
          // Fallback
          setTimeout(() => {
            if (preloaderWrapper.style.display !== 'none') {
              clearInterval(progressInterval);
              percentageElement.textContent = '100%';
              preloaderWrapper.classList.add('fade-out');
              setTimeout(() => {
                preloaderWrapper.style.display = 'none';
              }, 800);
            }
          }, 4000);
        });
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
