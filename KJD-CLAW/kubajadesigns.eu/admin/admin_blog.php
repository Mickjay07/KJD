<?php
require_once 'config.php'; // Načtení konfigurace
session_start(); // Spuštění session

// Kontrola, zda je uživatel přihlášený jako admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php'); // Přesměrování na přihlašovací stránku
    exit; // Ukončení skriptu
}

$successMessage = ''; // Proměnná pro úspěšné zprávy
$errorMessage = ''; // Proměnná pro chybové zprávy

// Načtení blogových příspěvků
try {
    $stmt = $conn->query("SELECT b.*, 
                          (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                           FROM blog_post_categories pc 
                           JOIN blog_categories c ON pc.category_id = c.id 
                           WHERE pc.post_id = b.id) as categories 
                          FROM blog_posts b 
                          ORDER BY b.created_at DESC");
    $blogPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT * FROM blog_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $errorMessage = "Chyba při načítání blogu: " . $e->getMessage();
}

// Zpracování formulářů
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_post') {
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
            
            // Vytvoření slug z titulku
            $slug = create_slug($title);
            
            // Zpracování obrázku
            $image = '';
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
                $upload_dir = 'uploads/blog/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image = $target_file;
                } else {
                    throw new Exception('Chyba při nahrávání obrázku.');
                }
            }
            
            // Vytvoření nového příspěvku
            $stmt = $conn->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, author, status, image, published_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$title, $slug, $content, $excerpt, $author, $status, $image, $published_at]);
            
            $post_id = $conn->lastInsertId();
            
            // Přidání kategorií
            if (!empty($selected_categories)) {
                $stmt = $conn->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
                foreach ($selected_categories as $category_id) {
                    $stmt->execute([$post_id, $category_id]);
                }
            }
            
            $_SESSION['message'] = "Nový blogový příspěvek byl úspěšně vytvořen.";
            header('Location: admin_blog.php');
            exit;
        } elseif ($action === 'delete_post' && isset($_POST['post_id'])) {
            $post_id = $_POST['post_id'];
            $conn->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$post_id]);
            $_SESSION['message'] = "Blogový příspěvek byl úspěšně smazán.";
            header('Location: admin_blog.php');
            exit;
        } elseif ($action === 'create_category') {
            $name = $_POST['category_name'] ?? '';
            
            if (empty($name)) {
                throw new Exception('Název kategorie je povinný.');
            }
            
            $slug = create_slug($name);
            
            $stmt = $conn->prepare("INSERT INTO blog_categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            
            $_SESSION['message'] = "Nová kategorie byla úspěšně vytvořena.";
            header('Location: admin_blog.php?tab=categories');
            exit;
        } elseif ($action === 'delete_category' && isset($_POST['category_id'])) {
            $category_id = $_POST['category_id'];
            $conn->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$category_id]);
            $_SESSION['message'] = "Kategorie byla úspěšně smazána.";
            header('Location: admin_blog.php?tab=categories');
            exit;
        }
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

// Určení aktivní záložky
$activeTab = $_GET['tab'] ?? 'posts';
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Správa blogu - KJD Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
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
    <style>
        .blog-stats {
            margin-bottom: 2rem;
        }
        .blog-stat-card {
            text-align: center;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background-color: white;
        }
        .blog-stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4D2D18;
        }
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        .table img {
            max-width: 100px;
            max-height: 60px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'admin_header.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4" id="main-content">
                <h1 class="h2 mb-4">Správa blogu</h1>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $errorMessage; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistiky -->
                <div class="row blog-stats">
                    <div class="col-md-4 mb-4">
                        <div class="blog-stat-card">
                            <div class="blog-stat-number"><?php echo count($blogPosts); ?></div>
                            <div class="stat-label">Celkem příspěvků</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="blog-stat-card">
                            <div class="blog-stat-number"><?php echo count(array_filter($blogPosts, function($post) { return $post['status'] === 'published'; })); ?></div>
                            <div class="stat-label">Publikovaných</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="blog-stat-card">
                            <div class="blog-stat-number"><?php echo count(array_filter($blogPosts, function($post) { return $post['status'] === 'draft'; })); ?></div>
                            <div class="stat-label">Konceptů</div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="blogTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'posts' ? 'active' : ''; ?>" id="posts-tab" data-bs-toggle="tab" href="#posts" role="tab">Příspěvky</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'new-post' ? 'active' : ''; ?>" id="new-post-tab" data-bs-toggle="tab" href="#new-post" role="tab">Nový příspěvek</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" id="categories-tab" data-bs-toggle="tab" href="#categories" role="tab">Kategorie</a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content mt-3" id="blogTabsContent">
                    <!-- Příspěvky -->
                    <div class="tab-pane fade <?php echo $activeTab === 'posts' ? 'show active' : ''; ?>" id="posts" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Titulek</th>
                                                <th>Autor</th>
                                                <th>Kategorie</th>
                                                <th>Stav</th>
                                                <th>Datum</th>
                                                <th>Akce</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($blogPosts) > 0): ?>
                                                <?php foreach ($blogPosts as $post): ?>
                                                <tr>
                                                    <td><?php echo $post['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($post['author']); ?></td>
                                                    <td><?php echo htmlspecialchars($post['categories'] ?? 'Bez kategorie'); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $post['status'] === 'published' ? 'bg-success' : 'bg-warning'; ?>">
                                                            <?php echo $post['status'] === 'published' ? 'Publikováno' : 'Koncept'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></td>
                                                    <td class="actions">
                                                        <a href="admin_blog_edit.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i> Upravit
                                                        </a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Opravdu chcete smazat tento příspěvek?');">
                                                            <input type="hidden" name="action" value="delete_post">
                                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i> Smazat
                                                            </button>
                                                        </form>
                                                        <?php if ($post['status'] === 'published'): ?>
                                                        <a href="blog-detail.php?slug=<?php echo $post['slug']; ?>" target="_blank" class="btn btn-sm btn-secondary">
                                                            <i class="fas fa-eye"></i> Zobrazit
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Žádné příspěvky nebyly nalezeny.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Nový příspěvek -->
                    <div class="tab-pane fade <?php echo $activeTab === 'new-post' ? 'show active' : ''; ?>" id="new-post" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Vytvořit nový příspěvek</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="create_post">
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Titulek *</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="excerpt" class="form-label">Perex (krátký popis)</label>
                                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"></textarea>
                                        <small class="text-muted">Krátký popis, který se zobrazí v přehledu článků</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Obsah</label>
                                        <textarea class="form-control" id="content" name="content"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Obrázek</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Doporučený formát: JPG, PNG, maximální velikost: 2MB</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="author" class="form-label">Autor</label>
                                        <input type="text" class="form-control" id="author" name="author" value="KJD">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Kategorie</label>
                                        <div class="row">
                                            <?php if (count($categories) > 0): ?>
                                                <?php foreach ($categories as $category): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="category-<?php echo $category['id']; ?>">
                                                        <label class="form-check-label" for="category-<?php echo $category['id']; ?>">
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="col-12">
                                                    <p class="text-muted">Nemáte vytvořené žádné kategorie. <a href="#categories-tab" data-bs-toggle="tab">Vytvořit kategorii</a></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Stav</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft">Koncept</option>
                                            <option value="published">Publikovat</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus-circle"></i> Vytvořit příspěvek
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Kategorie -->
                    <div class="tab-pane fade <?php echo $activeTab === 'categories' ? 'show active' : ''; ?>" id="categories" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-5 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header">
                                                <h5>Nová kategorie</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <input type="hidden" name="action" value="create_category">
                                                    <div class="mb-3">
                                                        <label for="category_name" class="form-label">Název kategorie *</label>
                                                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-plus-circle"></i> Přidat kategorii
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-7">
                                        <div class="card h-100">
                                            <div class="card-header">
                                                <h5>Existující kategorie</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Název</th>
                                                                <th>Slug</th>
                                                                <th>Akce</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (count($categories) > 0): ?>
                                                                <?php foreach ($categories as $category): ?>
                                                                <tr>
                                                                    <td><?php echo $category['id']; ?></td>
                                                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                                    <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                                                    <td>
                                                                        <form method="post" class="d-inline" onsubmit="return confirm('Opravdu chcete smazat tuto kategorii?');">
                                                                            <input type="hidden" name="action" value="delete_category">
                                                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                                <i class="fas fa-trash"></i> Smazat
                                                                            </button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td colspan="4" class="text-center">Žádné kategorie nebyly nalezeny.</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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

    <!-- Fixed bottom menu pro mobilní zařízení -->
    <div class="fixed-bottom bg-dark text-white py-2 d-none" id="bottom-nav">
        <div class="container-fluid">
            <div class="row text-center">
                <div class="col-3">
                    <a href="admin.php" class="text-white text-decoration-none">
                        <i class="fas fa-tachometer-alt d-block mb-1"></i>
                        <small>Dashboard</small>
                    </a>
                </div>
                <div class="col-3">
                    <a href="admin_products.php" class="text-white text-decoration-none">
                        <i class="fas fa-box d-block mb-1"></i>
                        <small>Produkty</small>
                    </a>
                </div>
                <div class="col-3">
                    <a href="admin_blog.php" class="text-white text-decoration-none">
                        <i class="fas fa-newspaper d-block mb-1"></i>
                        <small>Blog</small>
                    </a>
                </div>
                <div class="col-3">
                    <a href="admin_settings.php" class="text-white text-decoration-none">
                        <i class="fas fa-cog d-block mb-1"></i>
                        <small>Nastavení</small>
                    </a>
                </div>
            </div>
        </div>
    </div>

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
            const bottomNav = document.getElementById('bottom-nav');
            const mainContent = document.getElementById('main-content');
            
            // Počáteční nastavení pro mobilní zobrazení
            if (sidebar) sidebar.style.display = "none";
            if (menuBtn) menuBtn.classList.remove('d-none');
            if (bottomNav) bottomNav.classList.remove('d-none');
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