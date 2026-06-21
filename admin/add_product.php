<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$msg_type = "";

// Check if adding a pre-order specifically
$is_preorder_mode = isset($_GET['type']) && $_GET['type'] == 'preorder';
$page_title = $is_preorder_mode ? "Weka Bidhaa ya Pre-Order (Upcoming)" : "Ongeza Bidhaa Mpya";
$default_status = $is_preorder_mode ? 'preorder' : 'active';

// Hakikisha table ya product_gallery ipo
$conn->exec("CREATE TABLE IF NOT EXISTS product_gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $available_sizes = $_POST['available_sizes'];
    $available_colors = $_POST['available_colors'];
    $keywords = $_POST['keywords'];
    $status = $_POST['status'];
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : 0;
    $product_code = !empty($_POST['product_code']) ? trim($_POST['product_code']) : null;
    $coupon_code = !empty($_POST['coupon_code']) ? trim($_POST['coupon_code']) : null;
    $offer_badge = isset($_POST['offer_badge']) ? $_POST['offer_badge'] : 0;
    $discount_percentage = !empty($_POST['discount_percentage']) ? $_POST['discount_percentage'] : 0;
    $offer_expires_at = !empty($_POST['offer_expires_at']) ? $_POST['offer_expires_at'] : null;

    // Kushughulikia Picha
    require_once __DIR__ . '/../includes/functions.php';
    ensure_product_code_column($conn);
    $target_dir = "../uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Validate main image
    list($ok, $msg, $detected_mime) = validate_upload_file($_FILES['image'] ?? [], ['image/jpeg','image/png','image/webp','image/gif'], 5 * 1024 * 1024);
    if (!$ok) {
        $message = $msg;
        $msg_type = 'danger';
    } else {
        $file_name = basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        // Jina la kipekee kuzuia kufuta picha zingine
        $new_file_name = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            try {
                $sql = "INSERT INTO products (name, description, price, category, available_sizes, available_colors, keywords, image, status, discount_price, coupon_code, product_code, offer_badge, discount_percentage, offer_expires_at) 
                        VALUES (:name, :description, :price, :category, :sizes, :colors, :keywords, :image, :status, :discount_price, :coupon_code, :product_code, :offer_badge, :discount_percentage, :offer_expires_at)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':price' => $price,
                    ':category' => $category,
                    ':sizes' => $available_sizes,
                    ':colors' => $available_colors,
                    ':keywords' => $keywords,
                    ':image' => $new_file_name,
                    ':status' => $status,
                    ':discount_price' => $discount_price,
                    ':coupon_code' => $coupon_code,
                    ':product_code' => $product_code,
                    ':offer_badge' => $offer_badge,
                    ':discount_percentage' => $discount_percentage,
                    ':offer_expires_at' => $offer_expires_at
                ]);

                $product_id = $conn->lastInsertId();

                // Kushughulikia Picha za Gallery (Multiple)
                if (!empty($_FILES['gallery']['name'][0])) {
                    $total_files = count($_FILES['gallery']['name']);
                    $gallery_sql = "INSERT INTO product_gallery (product_id, image) VALUES (?, ?)";
                    $stmt_gallery = $conn->prepare($gallery_sql);

                    for ($i = 0; $i < $total_files; $i++) {
                        if (empty($_FILES['gallery']['name'][$i])) continue;
                        list($g_ok, $g_msg, $g_mime) = validate_upload_file($_FILES['gallery']['tmp_name'] ? $_FILES['gallery']['tmp_name'] : $_FILES['gallery'][$i], ['image/jpeg','image/png','image/webp','image/gif'], 5 * 1024 * 1024);
                        // Note: PHP's _FILES structure requires using $_FILES['gallery'] entries
                        $g_name = basename($_FILES['gallery']['name'][$i]);
                        $g_ext = strtolower(pathinfo($g_name, PATHINFO_EXTENSION));
                        $g_new_name = uniqid() . '_g' . $i . '.' . $g_ext;
                        $g_target = $target_dir . $g_new_name;
                        if (move_uploaded_file($_FILES['gallery']['tmp_name'][$i], $g_target)) {
                            $stmt_gallery->execute([$product_id, $g_new_name]);
                        }
                    }
                }

                $message = "Bidhaa imeongezwa kikamilifu!";
                $msg_type = "success";
            } catch(PDOException $e) {
                $message = "Kosa la Database: " . $e->getMessage();
                $msg_type = "danger";
            }
        } else {
            $message = "Kosa wakati wa kupakia picha.";
            $msg_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Grant Admin</title>
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
        <!-- Sidebar (Inajirudia kwa consistency) -->
        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-main-content col-md-9 col-lg-10 ms-auto p-4 bg-light">
            <div class="admin-page-header d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold"><?php echo $page_title; ?></h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <a href="<?php echo $is_preorder_mode ? 'preorders.php' : 'products.php'; ?>" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i> Rudi</a>
                </div>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Jina la Bidhaa</label>
                                <input type="text" name="name" class="form-control" required placeholder="Mfano: Royal Black Abaya">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Bei (Tsh)</label>
                                <input type="number" name="price" class="form-control" required placeholder="Mfano: 45000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kundi (Category)</label>
                                <select name="category" id="categorySelect" class="form-select" required>
                                    <option value="abaya">Abaya</option>
                                    <option value="gown">Gown (Gauni)</option>
                                    <option value="two_pieces">Two Pieces</option>
                                    <option value="guberi">Guberi</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Hali ya Bidhaa</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" <?php echo ($default_status == 'active') ? 'selected' : ''; ?>>Ipo (Active)</option>
                                    <option value="preorder" <?php echo ($default_status == 'preorder') ? 'selected' : ''; ?>>Pre-Order (Inakuja)</option>
                                    <option value="not_active">Imeisha (Not Active)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Onyesha Badge ya Punguzo?</label>
                                <select name="offer_badge" class="form-select">
                                    <option value="0">Hapana</option>
                                    <option value="1">Ndiyo (Onyesha 'OFA')</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Asilimia ya Punguzo (%)</label>
                                <input type="number" name="discount_percentage" class="form-control" placeholder="Mfano: 20 (Itaandika 20% OFF)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Mwisho wa Ofa (Tarehe na Muda)</label>
                                <input type="datetime-local" name="offer_expires_at" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Bei ya Punguzo (Discount Price)</label>
                                <input type="number" name="discount_price" class="form-control" placeholder="Acha wazi kama hakuna">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kodi ya Bidhaa (Product Code)</label>
                                <input type="text" name="product_code" class="form-control" placeholder="Mfano: GRANT001">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kodi ya Kuponi (Coupon Code)</label>
                                <input type="text" name="coupon_code" class="form-control" placeholder="Mfano: SALE2024">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Maelezo</label>
                                <textarea name="description" class="form-control" rows="3" required placeholder="Maelezo mafupi kuhusu bidhaa..."></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Saizi Zinazopatikana</label>
                                <input type="text" name="available_sizes" id="sizesInput" class="form-control" value="L,XL,XXL,XXXL" placeholder="Tenganisha kwa koma">
                                <small class="text-muted">Mfano: L,XL,XXL</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Rangi Zinazopatikana</label>
                                <input type="text" name="available_colors" class="form-control" value="Black,White,Navy Blue,Maroon,Gold" placeholder="Tenganisha kwa koma">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Keywords / Tags (Tenganisha kwa koma)</label>
                                <input type="text" name="keywords" class="form-control" placeholder="Mfano: dera, kaftan, gauni la harusi, trending">
                            </div>
                            <div class="col-12 mb-4">
                                <label class="form-label fw-bold">Picha ya Bidhaa</label>
                                <input type="file" name="image" class="form-control" required accept="image/*">
                            </div>
                            <div class="col-12 mb-4">
                                <label class="form-label fw-bold">Picha za Nyongeza (Gallery)</label>
                                <input type="file" name="gallery[]" class="form-control" multiple accept="image/*">
                                <small class="text-muted">Unaweza kuchagua picha zaidi ya moja.</small>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark btn-lg rounded-pill">Hifadhi Bidhaa</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('categorySelect');
        const sizesInput = document.getElementById('sizesInput');
        const defaultSizes = 'L,XL,XXL,XXXL';
        const guberiSize = 'Free Size';

        categorySelect.addEventListener('change', function() {
            if (this.value === 'guberi') {
                sizesInput.value = guberiSize;
            } else {
                sizesInput.value = defaultSizes;
            }
        });
    });
</script>
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
