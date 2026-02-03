<?php
// Specialized navigation menu for index.php with search functionality
?>
<style>
/* Fix for header icons - make them properly round */
.rounded-circle {
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 48px !important;
  height: 48px !important;
}

.rounded-circle svg {
  width: 24px !important;
  height: 24px !important;
  fill: currentColor !important;
}

/* Mobile navigation optimizations */
@media (max-width: 576px) {
  header {
    padding: 0.5rem 0 !important;
  }
  
  header .container-fluid .row {
    padding: 0.5rem 0 !important;
    margin: 0 !important;
  }
  
  /* Logo na mobilu - menší a vlevo */
  .col-sm-4 {
    flex: 0 0 auto !important;
    width: auto !important;
    max-width: none !important;
  }
  
  .main-logo a span {
    font-size: 1.8rem !important;
  }
  
  /* Ikony na mobilu - kompaktnější */
  .col-sm-8 {
    flex: 1 1 auto !important;
    width: auto !important;
    max-width: none !important;
    justify-content: flex-end !important;
  }
  
  .rounded-circle {
    width: 36px !important;
    height: 36px !important;
    padding: 0.4rem !important;
  }
  
  .rounded-circle svg {
    width: 18px !important;
    height: 18px !important;
  }
  
  /* Menší mezery mezi ikonami */
  .d-flex.gap-3 {
    gap: 0.25rem !important;
  }
  
  .d-flex.gap-3 li {
    margin: 0 !important;
  }
  
  /* Skryt košík na mobilu - máme ikonu */
  .cart.d-none.d-lg-block {
    display: none !important;
  }
  
  /* Upravit pozicování */
  .mt-4.mt-sm-0 {
    margin-top: 0 !important;
  }
}

@media (max-width: 768px) {
  .search-bar {
    margin: 0.5rem 0 !important;
    padding: 0.75rem !important;
  }
  
  .search-bar input {
    font-size: 0.9rem !important;
  }
  
  /* Na tabletu zmenšit logo */
  .main-logo a span {
    font-size: 2.2rem !important;
  }
}
</style>
<header style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border-bottom: 3px solid var(--kjd-earth-green); box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
    <div class="container-fluid">
        <div class="row py-4 align-items-center">
            <div class="col-sm-4 col-lg-3 text-center text-sm-start">
                <div class="main-logo">
                    <a href="index.php" class="text-decoration-none">
                        <span style="font-size: 2.5rem; font-weight: 800; color: #000; text-shadow: 2px 2px 4px rgba(16,40,32,0.1);">KJ</span><span style="font-size: 2.5rem; font-weight: 800; color: var(--kjd-dark-green); text-shadow: 2px 2px 4px rgba(16,40,32,0.1);">D</span>
                    </a>
                </div>
            </div>
            
            <div class="col-sm-6 offset-sm-2 offset-md-0 col-lg-5 d-none d-lg-block">
                <div class="search-bar row bg-light p-2 my-2 rounded-4" style="border: 2px solid var(--kjd-earth-green);">
                    <div class="col-md-4 d-none d-md-block">
                        <select class="form-select border-0 bg-transparent" style="color: var(--kjd-dark-green); font-weight: 600;">
                            <option><?= t('all_categories_select') ?></option>
                            <?php
                            // Dynamic categories from database
                            try {
                                $stmt = $conn->prepare("SELECT name, name_sk, name_en, slug FROM product_collections_main ORDER BY name");
                                $stmt->execute();
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    // mimic getCollectionDisplayName logic locally
                                    $optName = $row['name'];
                                    if ($lang === 'sk' && !empty($row['name_sk'])) { $optName = $row['name_sk']; }
                                    if ($lang === 'en' && !empty($row['name_en'])) { $optName = $row['name_en']; }
                                    echo '<option>' . htmlspecialchars($optName) . '</option>';
                                }
                            } catch (PDOException $e) {
                                // Fallback to static options (translated)
                                echo '<option>' . htmlspecialchars(t('all_categories')) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-11 col-md-7">
                        <form id="search-form" class="text-center" action="index.php" method="get">
                            <input type="text" name="q" value="<?= htmlspecialchars($searchQ ?? '') ?>" class="form-control border-0 bg-transparent" placeholder="<?= t('search_placeholder') ?>" style="color: var(--kjd-dark-green);" />
                        </form>
                    </div>
                    <div class="col-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-earth-green);"><path fill="currentColor" d="M21.71 20.29L18 16.61A9 9 0 1 0 16.61 18l3.68 3.68a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.39ZM11 18a7 7 0 1 1 7-7a7 7 0 0 1-7 7Z"/></svg>
                    </div>
                </div>
            </div>
            
            <div class="col-sm-8 col-lg-4 d-flex justify-content-end gap-3 align-items-center mt-4 mt-sm-0">
                <ul class="d-flex justify-content-end list-unstyled m-0 align-items-center">
                    <li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="dropdown">
                                <a href="#" class="rounded-circle p-2 mx-1 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);" onclick="event.preventDefault();">
                                    <svg width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-dark-green);"><use xlink:href="#user"></use></svg>
                                </a>
                                <ul class="dropdown-menu" style="border: 2px solid var(--kjd-earth-green); border-radius: 12px;">
                                    <li><a class="dropdown-item" href="my_account.php" style="color: var(--kjd-dark-green); font-weight: 600;"><?= htmlspecialchars($_SESSION['user_name']) ?></a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php" style="color: var(--kjd-dark-brown);">Odhlásit se</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="login.php" class="rounded-circle p-2 mx-1" title="Přihlásit se" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                                <svg width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-dark-green);"><use xlink:href="#user"></use></svg>
                            </a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <a href="favorites.php" class="rounded-circle p-2 mx-1" title="Oblíbené" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <svg width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-dark-green);"><use xlink:href="#heart"></use></svg>
                        </a>
                    </li>
                    <li class="d-lg-none">
                        <a href="#" class="rounded-circle p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart" aria-controls="offcanvasCart" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <svg width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-dark-green);"><use xlink:href="#cart"></use></svg>
                        </a>
                    </li>
                    <li class="d-lg-none">
                        <a href="#" class="rounded-circle p-2 mx-1" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSearch" aria-controls="offcanvasSearch" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <svg width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-dark-green);"><use xlink:href="#search"></use></svg>
                        </a>
                    </li>
                </ul>

                <div class="cart text-end d-none d-lg-block dropdown">
                    <a href="cart.php" class="text-decoration-none d-flex flex-column gap-2 lh-1" style="background: var(--kjd-earth-green); border: 2px solid var(--kjd-dark-green); border-radius: 12px; padding: 0.75rem 1rem;">
                        <span style="color: #fff; font-weight: 600;"><?= t('cart_button') ?></span>
                        <span style="color: #fff; font-weight: 700;">
                            <?php
                                $cart_count = 0; $cart_total = 0;
                                if (isset($_SESSION['cart'])) {
                                    foreach ($_SESSION['cart'] as $item) {
                                        $cart_count += (int)($item['quantity'] ?? 0);
                                        $cart_total += (float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 0);
                                    }
                                }
                                echo number_format($cart_total, 0, ',', ' ') . ' Kč';
                            ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Fix for mobile dropdown menu
document.addEventListener('DOMContentLoaded', function() {
    // Handle dropdown toggle clicks
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close other dropdowns
            const otherDropdowns = document.querySelectorAll('.dropdown-menu.show');
            otherDropdowns.forEach(function(menu) {
                menu.classList.remove('show');
            });
            
            // Toggle current dropdown
            const dropdown = this.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            menu.classList.toggle('show');
        });
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(function(menu) {
                menu.classList.remove('show');
            });
        }
    });
    
    // Handle dropdown item clicks
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    dropdownItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            // Close dropdown after click
            const dropdown = this.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            menu.classList.remove('show');
        });
    });
});
</script>
