<?php
// Modern Sidebar V2 for KJD Admin
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar d-flex flex-column p-3">
    <a href="admin.php" class="sidebar-brand text-decoration-none d-flex align-items-center mb-3 mb-md-0 me-md-auto">
        <span class="fs-4">KJD Admin</span>
    </a>
    <hr style="border-color: rgba(255,255,255,0.2);">
    
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="admin.php" class="nav-link <?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        
        <li class="nav-header text-uppercase text-muted mt-3 mb-2 ps-3" style="font-size: 0.75rem; letter-spacing: 1px;">E-shop</li>
        
        <li>
            <a href="admin_orders.php" class="nav-link <?php echo ($current_page == 'admin_orders.php') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> Objednávky
            </a>
        </li>
        <li>
            <a href="admin_products.php" class="nav-link <?php echo ($current_page == 'admin_products.php') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i> Produkty
            </a>
        </li>
        <li>
            <a href="admin_invoices.php" class="nav-link <?php echo ($current_page == 'admin_invoices.php') ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i> Faktury
            </a>
        </li>
        
        <li class="nav-header text-uppercase text-muted mt-3 mb-2 ps-3" style="font-size: 0.75rem; letter-spacing: 1px;">Marketing</li>
        
        <li>
            <a href="discount_codes.php" class="nav-link <?php echo ($current_page == 'discount_codes.php') ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Slevy
            </a>
        </li>
        <li>
            <a href="admin_newsletter.php" class="nav-link <?php echo ($current_page == 'admin_newsletter.php') ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open-text"></i> Newsletter
            </a>
        </li>

        <li class="nav-header text-uppercase text-muted mt-3 mb-2 ps-3" style="font-size: 0.75rem; letter-spacing: 1px;">Systém</li>
        
        <li>
            <a href="admin_settings.php" class="nav-link <?php echo ($current_page == 'admin_settings.php') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Nastavení
            </a>
        </li>
    </ul>
    
    <hr style="border-color: rgba(255,255,255,0.2);">
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; color: #000;">
                <i class="fas fa-user"></i>
            </div>
            <strong>Admin</strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <li><a class="dropdown-item" href="logout.php">Odhlásit se</a></li>
        </ul>
    </div>
</div>
