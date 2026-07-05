<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

$message = "";
$msg_type = "";

// Angalia kama ID ipo
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$id = $_GET['id'];

// Handle Gallery Image Deletion
if (isset($_POST['delete_gallery_image'])) {
    // CSRF Check (Simplified for inline action)
    $img_id = $_POST['gallery_id'];
    $stmt_del = $conn->prepare("SELECT image FROM product_gallery WHERE id = ?");
    $stmt_del->execute([$img_id]);
    $img_name = $stmt_del->fetchColumn();
    
    if ($img_name && file_exists("../uploads/" . $img_name)) {
        unlink("../uploads/" . $img_name);
    }
    $conn->prepare("DELETE FROM product_gallery WHERE id = ?")->execute([$img_id]);
    header("Location: edit_product.php?id=" . $id . "&msg=deleted");
    exit();
}

// Vuta taarifa za bidhaa iliyopo
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $message = "Picha ya gallery imefutwa.";
    $msg_type = "success";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once __DIR__ . '/../includes/functions.php';
    ensure_product_code_column($conn);

    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    $available_sizes = $_POST['available_sizes'];
    $available_colors = $_POST['available_colors'];
    $keywords = $_POST['keywords'];
    $status = $_POST['status'];
    $product_code = !empty($_POST['product_code']) ? trim($_POST['product_code']) : null;
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : 0;
    $coupon_code = !empty($_POST['coupon_code']) ? trim($_POST['coupon_code']) : null;
    $offer_badge = isset($_POST['offer_badge']) ? $_POST['offer_badge'] : 0;
    $discount_percentage = !empty($_POST['discount_percentage']) ? $_POST['discount_percentage'] : 0;
    $offer_expires_at = !empty($_POST['offer_expires_at']) ? $_POST['offer_expires_at'] : null;

    // Kushughulikia Picha (Ikiwa imepakiwa mpya)
    $image_query_part = "";
    $params = [
        ':name' => $name,
        ':description' => $description,
        ':price' => $price,
        ':category' => $category,
        ':sizes' => $available_sizes,
        ':colors' => $available_colors,
        ':keywords' => $keywords,
        ':status' => $status,
        ':discount_price' => $discount_price,
        ':coupon_code' => $coupon_code,
        ':offer_badge' => $offer_badge,
        ':discount_percentage' => $discount_percentage,
        ':offer_expires_at' => $offer_expires_at,
        ':id' => $id
    ];

    if (!empty($_FILES["image"]["name"])) {
        require_once __DIR__ . '/../includes/functions.php';
        ensure_product_code_column($conn);
        $target_dir = "../uploads/";
        $file_name = basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_file_name;

        list($ok, $msg, $mime) = validate_upload_file($_FILES['image'], ['image/jpeg','image/png','image/webp','image/gif'], 5 * 1024 * 1024);
        if (!$ok) {
            $message = $msg;
            $msg_type = 'danger';
        } else {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // Futa picha ya zamani
                if (!empty($product['image']) && file_exists("../uploads/" . $product['image'])) {
                    unlink("../uploads/" . $product['image']);
                }
                $image_query_part = ", image = :image";
                $params[':image'] = $new_file_name;
            } else {
                $message = "Kosa wakati wa kupakia picha mpya.";
                $msg_type = "danger";
            }
        }
    }

    if (empty($message)) {
        try {
            $sql = "UPDATE products SET name = :name, description = :description, price = :price, category = :category, available_sizes = :sizes, available_colors = :colors, keywords = :keywords, status = :status, discount_price = :discount_price, coupon_code = :coupon_code, product_code = :product_code, offer_badge = :offer_badge, discount_percentage = :discount_percentage, offer_expires_at = :offer_expires_at $image_query_part WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $params[':product_code'] = $product_code;
            $stmt->execute($params);

            // Handle New Gallery Images
            if (!empty($_FILES['gallery']['name'][0])) {
                $target_dir = "../uploads/";
                $total_files = count($_FILES['gallery']['name']);
                $gallery_sql = "INSERT INTO product_gallery (product_id, image) VALUES (?, ?)";
                $stmt_gallery = $conn->prepare($gallery_sql);

                for ($i = 0; $i < $total_files; $i++) {
                    $g_name = basename($_FILES['gallery']['name'][$i]);
                    if (!empty($g_name)) {
                        $g_new_name = uniqid() . '_g' . $i . '.' . strtolower(pathinfo($g_name, PATHINFO_EXTENSION));
                        if (move_uploaded_file($_FILES['gallery']['tmp_name'][$i], $target_dir . $g_new_name)) {
                            $stmt_gallery->execute([$id, $g_new_name]);
                        }
                    }
                }
            }
            
            $message = "Bidhaa imesasishwa kikamilifu!";
            $msg_type = "success";
            
            // Refresh data
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $message = "Kosa la Database: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}

// Fetch Gallery Images
$stmt_g = $conn->prepare("SELECT * FROM product_gallery WHERE product_id = ?");
$stmt_g->execute([$id]);
$gallery_images = $stmt_g->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hariri Bidhaa | Nazuri Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/icons/bootstrap-icons.css">
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
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
                <h2 class="fw-bold">Hariri Bidhaa</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <a href="products.php" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i> Rudi</a>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Jina la Bidhaa</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Bei (Tsh)</label>
                                <input type="number" name="price" class="form-control" required value="<?php echo $product['price']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kundi (Category)</label>
                                <select name="category" id="categorySelect" class="form-select" required>
                                    <option value="abaya" <?php echo ($product['category'] == 'abaya') ? 'selected' : ''; ?>>Abaya</option>
                                    <option value="gown" <?php echo ($product['category'] == 'gown') ? 'selected' : ''; ?>>Gown (Gauni)</option>
                                    <option value="two_pieces" <?php echo ($product['category'] == 'two_pieces') ? 'selected' : ''; ?>>Two Pieces</option>
                                    <option value="guberi" <?php echo ($product['category'] == 'guberi') ? 'selected' : ''; ?>>Guberi</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Hali ya Bidhaa</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" <?php echo ($product['status'] == 'active') ? 'selected' : ''; ?>>Ipo (Active)</option>
                                    <option value="preorder" <?php echo ($product['status'] == 'preorder') ? 'selected' : ''; ?>>Pre-Order (Inakuja)</option>
                                    <option value="not_active" <?php echo ($product['status'] == 'not_active') ? 'selected' : ''; ?>>Imeisha (Not Active)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Onyesha Badge ya Punguzo?</label>
                                <select name="offer_badge" class="form-select">
                                    <option value="0" <?php echo (isset($product['offer_badge']) && $product['offer_badge'] == 0) ? 'selected' : ''; ?>>Hapana</option>
                                    <option value="1" <?php echo (isset($product['offer_badge']) && $product['offer_badge'] == 1) ? 'selected' : ''; ?>>Ndiyo (Onyesha 'OFA')</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Asilimia ya Punguzo (%)</label>
                                <input type="number" name="discount_percentage" class="form-control" value="<?php echo isset($product['discount_percentage']) ? $product['discount_percentage'] : ''; ?>" placeholder="Mfano: 20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Mwisho wa Ofa (Tarehe na Muda)</label>
                                <input type="datetime-local" name="offer_expires_at" class="form-control" value="<?php echo !empty($product['offer_expires_at']) ? date('Y-m-d\TH:i', strtotime($product['offer_expires_at'])) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Bei ya Punguzo (Discount Price)</label>
                                <input type="number" name="discount_price" class="form-control" value="<?php echo $product['discount_price']; ?>" placeholder="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kodi ya Bidhaa (Product Code)</label>
                                <input type="text" name="product_code" class="form-control" value="<?php echo htmlspecialchars($product['product_code'] ?? ''); ?>" placeholder="Mfano: GRANT001">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Kodi ya Kuponi (Coupon Code)</label>
                                <input type="text" name="coupon_code" class="form-control" value="<?php echo htmlspecialchars($product['coupon_code'] ?? ''); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Maelezo</label>
                                <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Saizi Zinazopatikana</label>
                                <input type="text" name="available_sizes" id="sizesInput" class="form-control" value="<?php echo htmlspecialchars($product['available_sizes']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Rangi Zinazopatikana</label>
                                <input type="text" name="available_colors" class="form-control" value="<?php echo htmlspecialchars($product['available_colors']); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Keywords / Tags (Tenganisha kwa koma)</label>
                                <input type="text" name="keywords" class="form-control" value="<?php echo htmlspecialchars($product['keywords'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">Picha ya Bidhaa (Acha wazi kama hubadilishi)</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <div class="mt-2">
                                    <small class="text-muted">Picha ya sasa:</small><br>
                                    <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" width="100" class="rounded mt-1" loading="lazy">
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">Ongeza Picha za Gallery</label>
                                <input type="file" name="gallery[]" class="form-control" multiple accept="image/*">
                            </div>
                            
                            <!-- Gallery Management -->
                            <div class="col-12 mb-4">
                                <label class="form-label fw-bold border-bottom w-100 pb-2">Picha za Gallery Zilizopo</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php if(count($gallery_images) > 0): ?>
                                        <?php foreach($gallery_images as $g_img): ?>
                                            <div class="position-relative border rounded p-1" style="width: 100px;">
                                                <img src="../uploads/<?php echo htmlspecialchars($g_img['image']); ?>" class="w-100 rounded" style="height: 100px; object-fit: cover;">
                                                <button type="submit" name="delete_gallery_image" formaction="edit_product.php?id=<?php echo $id; ?>" onclick="
                                                    var input = document.createElement('input');
                                                    input.type = 'hidden';
                                                    input.name = 'gallery_id';
                                                    input.value = '<?php echo $g_img['id']; ?>';
                                                    this.form.appendChild(input);
                                                    return confirm('Futa picha hii?');
                                                " class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 p-0 px-1 rounded-circle" style="font-size: 10px;">X</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted small">Hakuna picha za nyongeza.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark btn-lg rounded-pill">Sasisha Bidhaa</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
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
                // Only change if it's currently 'Free Size', to avoid overwriting custom sizes
                if (sizesInput.value.trim().toLowerCase() === 'free size') {
                    sizesInput.value = defaultSizes;
                }
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
