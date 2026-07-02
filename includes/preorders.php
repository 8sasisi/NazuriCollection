<?php
require_once 'admin_auth.php';
require_once '../config/db_connect.php';
require_once 'functions.php';

// Handle Status Change (Mzigo Umefika -> Make Active)
if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] == 'activate') {
    $id = $_GET['id'];
    $stmt = $conn->prepare("UPDATE products SET status = 'active' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: preorders.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: preorders.php");
    exit();
}

// Fetch Pre-Order Products Only
$stmt = $conn->query("SELECT * FROM products WHERE status = 'preorder' ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pre-Orders | Nazuri Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #1a1d20; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .table-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        [data-bs-theme="dark"] body { background-color: #212529; color: #f8f9fa; }
        [data-bs-theme="dark"] .bg-light { background-color: #2b3035 !important; }
        [data-bs-theme="dark"] .card { background-color: #343a40; color: #f8f9fa; }
    </style>
    <script>
        const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
    </script>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 ms-auto p-4 bg-light">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Manage Pre-Orders</h2>
                <div class="d-flex align-items-center gap-2">
                    <?php include 'admin_top_actions.php'; ?>
                    <a href="../admin/add_product.php?type=preorder" class="btn btn-info text-white rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg me-2"></i> Ongeza Upcoming
                    </a>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Picha</th>
                                <th>Jina la Bidhaa</th>
                                <th>Bei</th>
                                <th>Kundi</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($products) > 0): ?>
                                <?php foreach($products as $product): ?>
                                <tr>
                                    <td class="ps-4">
                                        <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" class="table-img shadow-sm" onerror="this.src='../uploads/no-image.png'" loading="lazy">
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>Tsh <?php echo number_format($product['price']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo ucfirst($product['category']); ?></span></td>
                                    <td><span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">Inakuja</span></td>
                                    <td class="text-end pe-4">
                                        <!-- Activate Button (Mzigo Umefika) -->
                                        <a href="preorders.php?id=<?php echo $product['id']; ?>&action=activate" 
                                           class="btn btn-sm btn-success me-2"
                                           title="Mzigo Umefika (Weka Active)"
                                           onclick="return confirm('Je, mzigo huu umefika? Bidhaa itahamishiwa kwenye Active.');">
                                            <i class="bi bi-check-lg"></i> Umefika
                                        </a>
                                        
                                        <!-- Edit Button -->
                                        <a href="../admin/edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary me-2"><i class="bi bi-pencil"></i></a>
                                        
                                        <!-- Delete Button -->
                                        <a href="preorders.php?delete_id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Futa bidhaa hii ya Pre-Order?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">Hakuna bidhaa za Pre-Order kwa sasa.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>