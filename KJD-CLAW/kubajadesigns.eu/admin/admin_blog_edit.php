<?php
require_once 'config.php';
session_start();

// Kontrola, zda je uživatel přihlášený jako admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';
$post = null;
$post_categories = [];

// Kontrola, zda byl předán ID příspěvku
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_blog.php');
    exit;
}

$post_id = $_GET['id'];

// Načtení kategorií
try {
    $stmt = $conn->query("SELECT * FROM blog_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání kategorií: " . $e->getMessage();
}

// Načtení dat příspěvku
try {
    $stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        $_SESSION['message'] = "Příspěvek nebyl nalezen.";
        header('Location: admin_blog.php');
        exit;
    }
    
    // Načtení kategorií příspěvku
    $stmt = $conn->prepare("SELECT category_id FROM blog_post_categories WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $post_categories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání příspěvku: " . $e->getMessage();
}

// Zpracování formuláře pro aktualizaci příspěvku
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $excerpt = $_POST['excerpt'] ?? '';
        $author = $_POST['author'] ?? 'KJD';
        $status = $_POST['status'] ?? 'draft';
        $selected_categories = $_POST['categories'] ?? [];
        
        // Kontrola povinných polí
        if (empty($title)) {
            throw new Exception('Titulek je povinný.');
        }
        
        // Vytvoření slug z titulku, pokud se změnil titulek
        $slug = $post['slug'];
        if ($title !== $post['title']) {
            $slug = create_slug($title);
            
            // Kontrola, zda slug již neexistuje
            $stmt = $conn->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $post_id]);
            if ($stmt->fetchColumn() > 0) {
                $slug = $slug . '-' . $post_id;
            }
        }
        
        // Zpracování obrázku
        $image = $post['image'];
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
            $upload_dir = 'uploads/blog/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Smazání starého obrázku, pokud existuje
                if (!empty($post['image']) && file_exists($post['image'])) {
                    unlink($post['image']);
                }
                $image = $target_file;
            } else {
                throw new Exception('Chyba při nahrávání obrázku.');
            }
        }
        
        // Aktualizace příspěvku
        $published_at = $post['published_at'];
        if ($status === 'published' && $post['status'] !== 'published') {
            $published_at = date('Y-m-d H:i:s');
        }
        
        $stmt = $conn->prepare("UPDATE blog_posts SET 
                                title = ?, 
                                slug = ?, 
                                content = ?, 
                                excerpt = ?, 
                                author = ?, 
                                status = ?, 
                                image = ?, 
                                published_at = ? 
                                WHERE id = ?");
        
        $stmt->execute([$title, $slug, $content, $excerpt, $author, $status, $image, $published_at, $post_id]);
        
        // Aktualizace kategorií
        $stmt = $conn->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
        $stmt->execute([$post_id]);
        
        if (!empty($selected_categories)) {
            $stmt = $conn->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
            foreach ($selected_categories as $category_id) {
                $stmt->execute([$post_id, $category_id]);
            }
        }
        
        $_SESSION['message'] = "Příspěvek byl úspěšně aktualizován.";
        header('Location: admin_blog.php');
        exit;
    } catch(Exception $e) {
        $errorMessage = "Chyba: " . $e->getMessage();
    }
}

// Funkce pro vytvoření slug z textu
function create_slug($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(
        ['á', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ř', 'š', 'ť', 'ú', 'ů', 'ý', 'ž'],
        ['a', 'c', 'd', 'e', 'e', 'i', 'n', 'o', 'r', 's', 't', 'u', 'u', 'y', 'z'],
        $text
    );
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upravit příspěvek - KJD Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#content').summernote({
                height: 400,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
        });
    </script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'admin_header.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Upravit příspěvek</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="admin_blog.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Zpět na seznam
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Titulek *</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="excerpt" class="form-label">Perex (krátký popis)</label>
                                <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                                <small class="text-muted">Krátký popis, který se zobrazí v přehledu článků</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">Obsah</label>
                                <textarea class="form-control" id="content" name="content"><?php echo htmlspecialchars($post['content']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image" class="form-label">Obrázek</label>
                                <?php if (!empty($post['image'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo $post['image']; ?>" alt="Náhled" class="img-thumbnail" style="max-width: 200px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <small class="text-muted">Nahrajte nový obrázek pouze pokud chcete změnit stávající. Doporučený formát: JPG, PNG, maximální velikost: 2MB</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="author" class="form-label">Autor</label>
                                <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($post['author']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Kategorie</label>
                                <div class="row">
                                    <?php if (count($categories) > 0): ?>
                                        <?php foreach ($categories as $category): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category-<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $post_categories) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <p class="text-muted">Nemáte vytvořené žádné kategorie. <a href="admin_blog.php?tab=categories">Vytvořit kategorii</a></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Stav</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Koncept</option>
                                    <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Publikovat</option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Uložit změny
                                </button>
                                
                                <a href="admin_blog.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Zrušit
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mobilní tlačítko menu -->
    <button id="mobile-menu-btn" class="btn btn-primary position-fixed top-0 end-0 m-2 d-none d-md-none">
        <i class="fas fa-bars"></i> Menu
    </button>

    <!-- Overlay pro mobilní menu -->
    <div id="mobile-overlay" class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-none"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Detekce mobilního zařízení
        const isMobile = window.innerWidth < 992;
        
        if (isMobile) {
            // Reference na elementy
            const sidebar = document.querySelector('.sidebar');
            const menuBtn = document.getElementById('mobile-menu-btn');
            const overlay = document.getElementById('mobile-overlay');
            const mainContent = document.getElementById('main-content');
            
            // Počáteční nastavení pro mobilní zobrazení
            if (sidebar) sidebar.style.display = "none";
            if (menuBtn) menuBtn.classList.remove('d-none');
            if (mainContent) mainContent.classList.add('ms-0');
            
            // Tlačítko pro otevření menu
            if (menuBtn && sidebar && overlay) {
                menuBtn.addEventListener('click', function() {
                    sidebar.style.display = sidebar.style.display === "none" ? "block" : "none";
                    overlay.classList.toggle('d-none');
                });
                
                // Overlay pro zavření menu
                overlay.addEventListener('click', function() {
                    sidebar.style.display = "none";
                    overlay.classList.add('d-none');
                });
                
                // Zavření menu po kliknutí na položku
                const menuItems = sidebar.querySelectorAll('a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (isMobile) {
                            sidebar.style.display = "none";
                            overlay.classList.add('d-none');
                        }
                    });
                });
            }
        }
    });
    </script>
</body>
</html> 