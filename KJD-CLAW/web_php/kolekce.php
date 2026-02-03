<?php
// Load data from JSON (Flat File Database)
$products = [];
$collectionName = 'Všechny produkty';
$jsonPath = 'data/products.json';

if (file_exists($jsonPath)) {
    $json = file_get_contents($jsonPath);
    $products = json_decode($json, true);
}

// Fallback logic
if (empty($products)) {
    // If JSON fails, try DB (just in case)
    // require_once 'includes/config.php';
    // ... DB logic ...
}

// Filter by cat if needed (not implemented in JSON structure yet, but we have names)
// ...
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kolekce | Kubaja Designs</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;800&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.29/dist/lenis.min.js"></script>
</head>
<body>

    <div class="cursor-dot"></div>
    <div class="cursor-outline"></div>

    <header>
        <div class="container nav-wrapper">
            <a href="index.php" class="logo">KUBAJA<span style="color: var(--color-gold-brown);">DESIGNS</span></a>
            <nav class="nav-links">
                <a href="index.php" class="nav-item">Domů</a>
                <a href="kolekce.php" class="nav-item" style="color: var(--color-gold-brown);">Kolekce</a>
                <a href="konfigurator.php" class="nav-item">Konfigurátor</a>
                <a href="o-nas.php" class="nav-item">O nás</a>
            </nav>
            <div class="nav-actions"><a href="#" class="btn btn-outline">Košík (0)</a></div>
        </div>
    </header>

    <div class="container" style="padding-top: 150px; padding-bottom: 100px;">
        <div class="section-header">
            <h1 style="font-size: 3.5rem; text-transform: capitalize; margin-bottom: 10px;">
                <?php echo htmlspecialchars($collectionName); ?>
            </h1>
            <p style="opacity: 0.6; font-size: 1.2rem;">
                <?php echo count($products); ?> produktů
            </p>
        </div>
        
        <?php if (!empty($products)): ?>
        <div class="grid-collections">
            <?php foreach($products as $p): ?>
            <div class="card-collection" style="height: 450px; margin-top: 0;">
                <div class="card-img-wrapper">
                    <div class="card-img" style="background-image: url('<?php echo $p['image']; ?>');"></div>
                </div>
                <div class="card-overlay">
                    <h3 style="font-size: 1.8rem;"><?php echo htmlspecialchars($p['name']); ?></h3>
                    <p style="font-weight: 600; color: var(--color-gold-brown);"><?php echo number_format($p['price'], 0, ',', ' '); ?> Kč</p>
                    <a href="#" class="btn btn-glass" style="margin-top: 15px; padding: 10px 20px; font-size: 0.8rem;">Detail</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div style="text-align: center; padding: 50px;">
                <h3>Žádné produkty nalezeny</h3>
                <p>Nepodařilo se načíst data z databáze.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const cursorDot = document.querySelector(".cursor-dot");
        const cursorOutline = document.querySelector(".cursor-outline");

        window.addEventListener("mousemove", function (e) {
            const posX = e.clientX;
            const posY = e.clientY;
            cursorDot.style.left = `${posX}px`;
            cursorDot.style.top = `${posY}px`;
            cursorOutline.animate({ left: `${posX}px`, top: `${posY}px` }, { duration: 500, fill: "forwards" });
        });

        const lenis = new Lenis({ smooth: true });
        function raf(time) { lenis.raf(time); requestAnimationFrame(raf); }
        requestAnimationFrame(raf);
    </script>
</body>
</html>
