<?php
// Centralized navigation menu with favorites.php styling
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

/* Mobilní styly pro navbar */
@media (max-width: 576px) {
  header .row {
    flex-direction: column !important;
  }
  
  header .col-sm-4,
  header .col-sm-8 {
    width: 100% !important;
    text-align: center !important;
  }
  
  header .col-sm-8 {
    justify-content: center !important;
    margin-top: 1rem !important;
  }
  
  header .col-sm-8 ul {
    justify-content: center !important;
  }
  
  header .main-logo {
    text-align: center !important;
  }
}
</style>
<header style="background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); border-bottom: 3px solid var(--kjd-earth-green); box-shadow: 0 4px 20px rgba(16,40,32,0.1);">
    <div class="container-fluid">
        <div class="row py-4">
            <div class="col-sm-4 col-lg-3 text-center text-sm-start">
                <div class="main-logo">
                    <a href="index.php" class="text-decoration-none">
                        <span style="font-size: 2.5rem; font-weight: 800; color: #000; text-shadow: 2px 2px 4px rgba(16,40,32,0.1);">KJ</span><span style="font-size: 2.5rem; font-weight: 800; color: var(--kjd-dark-green); text-shadow: 2px 2px 4px rgba(16,40,32,0.1);">D</span>
                    </a>
                </div>
            </div>
            
            <div class="col-sm-8 col-lg-9 d-flex justify-content-center justify-content-sm-end gap-3 align-items-center mt-4 mt-sm-0">
                <ul class="d-flex justify-content-center justify-content-sm-end list-unstyled m-0">


                    <li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="dropdown">
                                <a href="#" class="rounded-circle p-2 mx-1 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
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
                        <a href="cart.php" class="rounded-circle p-2 mx-1 position-relative" title="Košík" style="background: rgba(255,255,255,0.9); border: 2px solid var(--kjd-earth-green);">
                            <svg width="24" height="24" viewBox="0 0 24 24" style="color: var(--kjd-dark-green);"><use xlink:href="#cart"></use></svg>
                            <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                                <?php 
                                    $cart_count = 0;
                                    foreach ($_SESSION['cart'] as $item) {
                                        $cart_count += (int)($item['quantity'] ?? 0);
                                    }
                                ?>
                                <?php if ($cart_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background: #c62828; color: #fff; font-size: 0.7rem; font-weight: 700; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
                                        <?= $cart_count ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <div class="cart text-end d-none d-lg-block">
                    <a href="cart.php" class="text-decoration-none d-flex flex-column gap-2 lh-1" style="background: var(--kjd-earth-green); border: 2px solid var(--kjd-dark-green); border-radius: 12px; padding: 0.75rem 1rem;">
                        <span class="fs-6" style="color: #fff; font-weight: 600;">Košík</span>
                        <span class="cart-total fs-5 fw-bold" style="color: #fff;">
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
