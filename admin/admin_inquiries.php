<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Handle Status Update
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE custom_requests SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    header("Location: admin_inquiries.php");
    exit;
}

// Fetch Requests
$stmt = $conn->query("SELECT * FROM custom_requests ORDER BY created_at DESC");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Materials for display
$mats = $conn->query("SELECT id, name FROM filaments")->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poptávky tisku - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <!-- Apple SF Pro Font -->
    <link rel="stylesheet" href="../fonts/sf-pro.css">
    <style>
      :root { 
        --kjd-dark-green:#102820; 
        --kjd-earth-green:#4c6444; 
        --kjd-gold-brown:#8A6240; 
        --kjd-dark-brown:#4D2D18; 
        --kjd-beige:#CABA9C; 
      }
      
      /* Apple SF Pro Font */
      body, .btn, .form-control, .nav-link, h1, h2, h3, h4, h5, h6 {
        font-family: 'SF Pro Display', 'SF Compact Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      }
      
      /* Cart page background */
      .cart-page { 
        background: #f8f9fa; 
        min-height: 100vh; 
      }
      
      /* Cart header */
      .cart-header { 
        background: linear-gradient(135deg, var(--kjd-beige), #f5f0e8); 
        padding: 3rem 0; 
        margin-bottom: 2rem; 
        border-bottom: 3px solid var(--kjd-earth-green);
        box-shadow: 0 4px 20px rgba(16,40,32,0.1);
      }
      
      .cart-header h1 { 
        font-size: 2.5rem; 
        font-weight: 800; 
        text-shadow: 2px 2px 4px rgba(16,40,32,0.1);
        margin-bottom: 0.5rem;
        color: var(--kjd-dark-green);
      }
      
      .cart-header p { 
        font-size: 1.1rem; 
        font-weight: 500;
        opacity: 0.8;
        color: var(--kjd-gold-brown);
      }
      
      /* Cart items */
      .cart-item { 
        background: #fff; 
        border-radius: 16px; 
        padding: 2rem; 
        margin-bottom: 1.5rem; 
        box-shadow: 0 4px 20px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }
      
      .cart-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(16,40,32,0.12);
      }
      
      .cart-product-name { 
        color: var(--kjd-dark-green); 
        font-weight: 700; 
        font-size: 1.3rem;
        margin-bottom: 0.5rem; 
      }
      
      /* KJD Buttons */
      .btn-kjd-primary { 
        background: linear-gradient(135deg, var(--kjd-dark-brown), var(--kjd-gold-brown)); 
        color: #fff; 
        border: none; 
        padding: 0.5rem 1rem; 
        border-radius: 8px; 
        font-weight: 700;
        transition: all 0.3s ease;
      }
      
      .btn-kjd-primary:hover { 
        background: linear-gradient(135deg, var(--kjd-gold-brown), var(--kjd-dark-brown)); 
        color: #fff;
        transform: translateY(-2px);
      }

      /* Table styles */
      .table {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 16px rgba(16,40,32,0.08);
        border: 2px solid var(--kjd-earth-green);
        margin-bottom: 0;
      }
      
      .table th {
        background: linear-gradient(135deg, var(--kjd-earth-green), var(--kjd-dark-green));
        color: #fff;
        font-weight: 700;
        padding: 1rem;
        border: none;
      }
      
      .table td {
        padding: 1rem;
        border-bottom: 1px solid rgba(202,186,156,0.1);
        vertical-align: middle;
      }
      
      .table tbody tr:hover {
        background: rgba(202,186,156,0.05);
      }

      /* Mobile Styles */
      @media (max-width: 768px) {
        .cart-header { padding: 2rem 0; }
        .cart-header h1 { font-size: 2rem; }
        .cart-item { padding: 1.5rem; margin-bottom: 1rem; }
        .table-responsive { font-size: 0.9rem; }
        .table th, .table td { padding: 0.5rem; }
      }
    </style>
</head>

<body class="cart-page">
    <?php include '../includes/icons.php'; ?>
    
    <!-- Navigation Menu -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Admin Header -->
    <div class="cart-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1><i class="fas fa-inbox me-3"></i>Poptávky tisku</h1>
                    <p>Přehled poptávek na zakázkový 3D tisk</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                
                <div class="cart-item">
                    <h2 class="cart-product-name mb-4">
                        <i class="fas fa-list me-2"></i>Seznam poptávek
                    </h2>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Email / Telefon</th>
                                    <th>Soubor</th>
                                    <th>Materiál / Výplň</th>
                                    <th>Poznámka</th>
                                    <th>Stav</th>
                                    <th>Akce</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></td>
                                        <td>
                                            <div style="font-weight:600; color:var(--kjd-dark-green);"><?php echo htmlspecialchars($r['email']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($r['phone']); ?></small>
                                        </td>
                                        <td>
                                            <a href="../<?php echo htmlspecialchars($r['file_path']); ?>" class="btn btn-sm btn-outline-primary" download>
                                                <i class="fas fa-download"></i> <?php echo htmlspecialchars($r['original_filename']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?php echo htmlspecialchars($mats[$r['material_id']] ?? 'Neznámý'); ?></div>
                                            <small><?php echo htmlspecialchars($r['infill']); ?></small>
                                        </td>
                                        <td>
                                            <div style="max-width: 200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($r['note']); ?>">
                                                <?php echo htmlspecialchars($r['note']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($r['status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Nová</span>
                                            <?php elseif ($r['status'] == 'quoted'): ?>
                                                <span class="badge bg-info">Naceněno</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Hotovo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                    <?php if ($r['status'] != 'done'): ?>
                                                        <button type="submit" name="status" value="done" class="btn btn-success btn-sm" title="Označit jako vyřešené">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                                <a href="mailto:<?php echo htmlspecialchars($r['email']); ?>?subject=Re: Poptávka 3D tisku" class="btn btn-kjd-primary btn-sm" title="Odpovědět">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>