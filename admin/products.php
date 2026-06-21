<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Kushughulikia Kubadili Status (Toggle)
if (isset($_GET['id']) && isset($_GET['status_toggle'])) {
    // CSRF Check
    if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) {
        die('Invalid CSRF token');
    }
    $id = $_GET['id'];
    $current_status = $_GET['current'];
    // Badilisha status: Ikiwa active iwe not_active, na kinyume chake
    $new_status = ($current_status == 'active') ? 'not_active' : 'active';

    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);

    // Redirect ili kurefresh ukurasa
    header("Location: products.php");
    exit();
}

// 2. Kushughulikia Kufuta Bidhaa (Refactored)
$delete_product_files_callback = function($id) use ($conn) {
    // Futa picha kuu
    $stmt_main = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $stmt_main->execute([$id]);
    if ($main_image = $stmt_main->fetchColumn()) {
        if ($main_image && file_exists("../uploads/" . $main_image)) {
            unlink("../uploads/" . $main_image);
        }
    }

    // Futa picha za gallery na rekodi zake
    $stmt_gallery = $conn->prepare("SELECT image FROM product_gallery WHERE product_id = ?");
    $stmt_gallery->execute([$id]);
    $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_COLUMN);
    foreach ($gallery_images as $gallery_image) {
        if ($gallery_image && file_exists("../uploads/" . $gallery_image)) {
            unlink("../uploads/" . $gallery_image);
        }
    }
    $conn->prepare("DELETE FROM product_gallery WHERE product_id = ?")->execute([$id]);
};

delete_item($conn, 'products', 'products.php', 'delete_id', $delete_product_files_callback);

// 3. Vuta Bidhaa Zote
$stmt = $conn->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidhaa Zote | Nazuri Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #1a1d20; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .table-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        /* Dark Mode for Admin */
        [data-bs-theme="dark"] body { background-color: #212529; color: #f8f9fa; }
        [data-bs-theme="dark"] .bg-light { background-color: #2b3035 !important; }
        [data-bs-theme="dark"] .card { background-color: #343a40; color: #f8f9fa; }
        [data-bs-theme="dark"] .text-muted { color: #adb5bd !important; }
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
        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-main-content col-md-9 col-lg-10 ms-auto p-4 bg-light">
            <div class="admin-page-header d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Orodha ya Bidhaa</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <a href="add_product.php" class="btn btn-dark rounded-pill px-4"><i class="bi bi-plus-lg me-2"></i> Ongeza Mpya</a>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Picha</th>
                                <th>Jina</th>
                                <th>Bei</th>
                                <th>Kundi</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td class="ps-4">
                                    <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" class="table-img shadow-sm" onerror="this.src='https://via.placeholder.com/50'" loading="lazy">
                                </td>
                                <td class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>Tsh <?php echo number_format($product['price']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo ucfirst($product['category']); ?></span></td>
                                <td>
                                    <?php if($product['status'] == 'active'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Active</span>
                                    <?php elseif($product['status'] == 'preorder'): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">Pre-Order</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">Not Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <!-- Edit Button -->
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary me-2"
                                       title="Hariri">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <!-- Toggle Status Button -->
                                    <a href="products.php?id=<?php echo $product['id']; ?>&status_toggle=1&current=<?php echo $product['status']; ?>&token=<?php echo $_SESSION['csrf_token']; ?>" 
                                       class="btn btn-sm <?php echo ($product['status'] == 'active') ? 'btn-outline-warning' : 'btn-outline-success'; ?> me-2"
                                       title="<?php echo ($product['status'] == 'active') ? 'Zima Bidhaa' : 'Washa Bidhaa'; ?>">
                                        <i class="bi <?php echo ($product['status'] == 'active') ? 'bi-eye-slash' : 'bi-eye'; ?>"></i>
                                    </a>
                                    
                                    <!-- Delete Button -->
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-product-id="<?php echo $product['id']; ?>"
                                            title="Futa">
                                        <i class="bi bi-trash"></i>
                                    </button>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Thibitisha Kufuta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Je, una uhakika unataka kufuta bidhaa hii? Hatua hii haiwezi kurudishwa.
      </div>
      <div class="modal-footer">
        <form method="POST" action="products.php" id="deleteForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="delete_id" id="deleteProductId">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ghairi</button>
            <button type="submit" class="btn btn-danger">Futa Bidhaa</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var productId = button.getAttribute('data-product-id');
        var deleteInput = document.getElementById('deleteProductId');
        deleteInput.value = productId;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('admin-theme-toggle');
        const icon = themeToggle.querySelector('i');
        const html = document.documentElement;

        if (html.getAttribute('data-bs-theme') === 'dark') {
            icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
            themeToggle.classList.replace('btn-outline-dark', 'btn-outline-light');
        }

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            if (newTheme === 'dark') {
                icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
                themeToggle.classList.replace('btn-outline-dark', 'btn-outline-light');
            } else {
                icon.classList.replace('bi-sun-fill', 'bi-moon-stars-fill');
                themeToggle.classList.replace('btn-outline-light', 'btn-outline-dark');
            }
        });
    });
</script>
</body>
</html>
