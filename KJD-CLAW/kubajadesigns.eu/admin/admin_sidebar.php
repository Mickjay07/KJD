<?php
// Admin Navigation Menu - KJD Style
if (defined('KJD_NAV_INCLUDED')) { return; }
define('KJD_NAV_INCLUDED', true);
?>

<!-- Admin Navigation Menu -->
<header style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border-bottom: 3px solid var(--kjd-earth-green); box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
    <div class="container-fluid">
        <div class="row py-4">
            <div class="col-sm-4 col-lg-3 text-center text-sm-start">
                <div class="main-logo">
                    <a href="admin.php" class="text-decoration-none">
                        <span style="font-size: 2.5rem; font-weight: 800; color: #000; text-shadow: 2px 2px 4px rgba(16,40,32,0.1);">KJ</span><span style="font-size: 2.5rem; font-weight: 800; color: var(--kjd-dark-green); text-shadow: 2px 2px 4px rgba(16,40,32,0.1);">D</span>
                    </a>
                </div>
            </div>
            
            <div class="col-sm-8 col-lg-9 d-flex justify-content-end gap-2 align-items-center mt-4 mt-sm-0">
                <ul class="d-flex justify-content-end list-unstyled m-0 flex-wrap">
                    <!-- Produkty Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle rounded-circle p-2 mx-1" href="#" role="button" data-bs-toggle="dropdown" title="Produkty" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-box" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                        <ul class="dropdown-menu" style="border: 2px solid var(--kjd-earth-green); border-radius: 12px; box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
                            <li><a class="dropdown-item" href="admin_products.php"><i class="fas fa-list me-2"></i>Seznam produktů</a></li>
                            <li><a class="dropdown-item" href="admin_novy_produkt.php"><i class="fas fa-plus me-2"></i>Nový produkt</a></li>
                            <li><a class="dropdown-item" href="admin_edit_product.php"><i class="fas fa-edit me-2"></i>Upravit produkt</a></li>
                            <li><a class="dropdown-item" href="admin_manage_colors.php"><i class="fas fa-palette me-2"></i>Správa barev</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin_collections.php"><i class="fas fa-layer-group me-2"></i>Kolekce</a></li>
                            <li><a class="dropdown-item" href="admin_lampy.php"><i class="fas fa-lightbulb me-2"></i>Lampy</a></li>
                        </ul>
                    </li>
                    
                    <!-- Objednávky & Faktury -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle rounded-circle p-2 mx-1" href="#" role="button" data-bs-toggle="dropdown" title="Objednávky a Faktury" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-shopping-cart" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                        <ul class="dropdown-menu" style="border: 2px solid var(--kjd-earth-green); border-radius: 12px; box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
                            <li class="<?php echo ($current_page == 'admin_inquiries.php') ? 'active' : ''; ?>">
            <a class="dropdown-item" href="admin_inquiries.php"><i class="fas fa-inbox me-2"></i> Poptávky tisku</a>
        </li>
        <li class="<?php echo ($current_page == 'admin_lamp_config.php') ? 'active' : ''; ?>">
            <a class="dropdown-item" href="admin_lamp_config.php"><i class="fas fa-lightbulb me-2"></i> Konfigurátor Lamp</a>
        </li>
        <li class="<?php echo ($current_page == 'admin_orders.php') ? 'active' : ''; ?>"><a class="dropdown-item" href="admin_orders.php"><i class="fas fa-shopping-cart me-2"></i>Objednávky</a></li>
                            <li class="<?php echo ($current_page == 'admin_shipping.php') ? 'active' : ''; ?>"><a class="dropdown-item" href="admin_shipping.php"><i class="fas fa-truck me-2"></i>Doprava (Zásilkovna)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin_invoices.php"><i class="fas fa-file-invoice me-2"></i>Faktury</a></li>
                            <li><a class="dropdown-item" href="admin_invoice_add.php"><i class="fas fa-plus me-2"></i>Nová faktura</a></li>
                        </ul>
                    </li>
                    
                    <!-- Marketing & Komunikace -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle rounded-circle p-2 mx-1" href="#" role="button" data-bs-toggle="dropdown" title="Marketing a Komunikace" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-bullhorn" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                        <ul class="dropdown-menu" style="border: 2px solid var(--kjd-earth-green); border-radius: 12px; box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
                            <li><h6 class="dropdown-header">E-maily</h6></li>
                            <li><a class="dropdown-item" href="admin_emails.php"><i class="fas fa-envelope me-2"></i>Přehled e-mailů</a></li>
                            <li><a class="dropdown-item" href="admin_email_compose.php"><i class="fas fa-pen me-2"></i>Napsat e-mail</a></li>
                            <li><a class="dropdown-item" href="admin_newsletter.php"><i class="fas fa-envelope-open-text me-2"></i>Newsletter</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Marketing</h6></li>
                            <li><a class="dropdown-item" href="discount_codes.php"><i class="fas fa-tag me-2"></i>Slevové kódy</a></li>
                            <li><a class="dropdown-item" href="voucher_generator.php"><i class="fas fa-gift me-2"></i>Vouchery</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Komunikace</h6></li>
                            <li><a class="dropdown-item" href="admin_inquiries.php"><i class="fas fa-comments me-2"></i>Dotazy</a></li>
                        </ul>
                    </li>
                    
                    <!-- Obsah a Nástroje -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle rounded-circle p-2 mx-1" href="#" role="button" data-bs-toggle="dropdown" title="Obsah a Nástroje" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-tools" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                        <ul class="dropdown-menu" style="border: 2px solid var(--kjd-earth-green); border-radius: 12px; box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
                            <li><a class="dropdown-item" href="admin_blog.php"><i class="fas fa-newspaper me-2"></i>Blog</a></li>
                            <li><a class="dropdown-item" href="admin_assembly_guides.php"><i class="fas fa-tools me-2"></i>Návody</a></li>
                            <li><a class="dropdown-item" href="admin_print_calculator.php"><i class="fas fa-calculator me-2"></i>Kalkulačka tisku</a></li>
                            <li><a class="dropdown-item" href="admin_custom_lightbox.php"><i class="fas fa-lightbulb me-2"></i>Custom Lightbox</a></li>
                            <li><a class="dropdown-item" href="admin_qr_generator.php"><i class="fas fa-qrcode me-2"></i>QR kódy</a></li>
                        </ul>
                    </li>
            
                    <!-- Uživatelé -->
                    <li>
                        <a href="admin_users.php" class="rounded-circle p-2 mx-1" title="Uživatelé" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-users" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                    </li>
            
                    <!-- Nastavení -->
                    <li>
                        <a href="admin_settings.php" class="rounded-circle p-2 mx-1" title="Nastavení" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-cog" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                    </li>
            
                    <!-- Odhlásit se -->
                    <li>
                        <a href="logout.php" class="rounded-circle p-2 mx-1" title="Odhlásit se" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <i class="fas fa-sign-out-alt" style="color: var(--kjd-dark-green); font-size: 20px;"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>

<style>
/* Admin Navigation Styles */
:root { 
    --kjd-dark-green:#102820; 
    --kjd-earth-green:#4c6444; 
    --kjd-gold-brown:#8A6240; 
    --kjd-dark-brown:#4D2D18; 
    --kjd-beige:#CABA9C; 
}

/* Navigation styles */
.rounded-circle {
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 48px !important;
    height: 48px !important;
}

.rounded-circle i {
    width: 20px !important;
    height: 20px !important;
}

/* Dropdown menu styles */
.dropdown-menu {
    background: #fff;
    border: 2px solid var(--kjd-earth-green) !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 20px rgba(16,40,32,0.1) !important;
    padding: 0.5rem 0;
    margin-top: 0.5rem;
}

.dropdown-item {
    color: var(--kjd-dark-green);
    font-weight: 600;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.dropdown-item:hover {
    background: var(--kjd-beige);
    color: var(--kjd-dark-green);
    transform: translateX(5px);
}

.dropdown-item i {
    width: 16px;
    height: 16px;
    margin-right: 0.5rem;
    color: var(--kjd-earth-green);
}

.dropdown-toggle::after {
    display: none;
}

/* Navigation menu responsive */
@media (max-width: 1200px) {
    .d-flex.justify-content-end.gap-2 {
        gap: 0.5rem !important;
    }
    
    .rounded-circle {
        width: 40px !important;
        height: 40px !important;
    }
    
    .rounded-circle i {
        font-size: 18px !important;
    }
}

@media (max-width: 992px) {
    .d-flex.justify-content-end.gap-2 {
        gap: 0.25rem !important;
    }
    
    .rounded-circle {
        width: 36px !important;
        height: 36px !important;
    }
    
    .rounded-circle i {
        font-size: 16px !important;
    }
}
</style>