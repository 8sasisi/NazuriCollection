<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/encryption.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Delete
delete_item($conn, 'pre_orders', 'preorders.php');

// Fetch Pre-Orders
$stmt = $conn->query("SELECT * FROM pre_orders ORDER BY created_at DESC");
$preorders = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($preorders as &$po) {
    if (isset($po['customer_phone'])) {
        $po['customer_phone'] = decrypt_data($po['customer_phone']);
    }
}
unset($po);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-Orders | Nazuri Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #1a1d20; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); transform: translateX(5px); }
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
                <h2 class="fw-bold mb-0">Oda za Mapema (Pre-Orders)</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Tarehe</th>
                                <th>Mteja</th>
                                <th>Simu</th>
                                <th>Bidhaa</th>
                                <th>Size & Rangi</th>
                                <th>Idadi</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($preorders) > 0): ?>
                                <?php foreach($preorders as $order): ?>
                                <tr>
                                    <td class="ps-4"><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $order['customer_phone']); ?>" target="_blank" class="text-decoration-none text-success fw-bold">
                                            <i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($order['size']); ?></span>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($order['color']); ?></span>
                                    </td>
                                    <td><?php echo $order['quantity']; ?></td>
                                    <td class="text-end pe-4">
                                        <form method="POST" action="preorders.php" class="d-inline" onsubmit="return confirm('Una uhakika unataka kufuta pre-order hii?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Hakuna pre-orders zilizopatikana.</td>
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
