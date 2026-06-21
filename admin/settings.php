<?php
require_once __DIR__ . '/../includes/admin_auth.php'; // Handles session and authentication
require_once __DIR__ . '/../config/db_connect.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$msg_type = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection: Validate Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $settings = [
        'shop_name' => $_POST['shop_name'],
        'phone' => $_POST['phone'],
        'email' => $_POST['email'],
        'address' => $_POST['address'],
        'instagram' => $_POST['instagram'],
        'facebook' => $_POST['facebook'],
        'default_language' => $_POST['default_language']
    ];

    // Handle Logo Upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        require_once __DIR__ . '/../includes/functions.php';
        $target_dir = "../uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        list($ok, $msg, $mime) = validate_upload_file($_FILES['logo'], ['image/jpeg','image/png','image/webp','image/gif'], 2 * 1024 * 1024);
        if (!$ok) {
            $message = $msg;
            $msg_type = 'danger';
        } else {
            $file_name = basename($_FILES["logo"]["name"]);
            $imageFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = "shop_logo_" . uniqid() . "." . $imageFileType;
            $target_file = $target_dir . $new_file_name;

            if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
                // Futa logo ya zamani kama ipo
                $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'");
                $stmt->execute();
                $old_logo = $stmt->fetchColumn();
                if ($old_logo && file_exists("../uploads/" . $old_logo)) {
                    unlink("../uploads/" . $old_logo);
                }

                $settings['logo'] = $new_file_name;
            } else {
                $message = "Kosa wakati wa kupakia logo.";
                $msg_type = "danger";
            }
        }
    }

    if (empty($message)) {
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([':key' => $key, ':value' => $value]);
        }
        $conn->commit();
        $message = "Mipangilio imesasishwa kikamilifu!";
        $msg_type = "success";
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "Kosa: " . $e->getMessage();
        $msg_type = "danger";
    }
    }
}

// Fetch Current Settings
$stmt = $conn->query("SELECT * FROM site_settings");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$current_settings = [];
foreach ($rows as $row) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

// Helper function to get setting safely
function get_setting($key, $data) {
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : '';
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mipangilio | Grant Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #1a1d20; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); transform: translateX(5px); }
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
        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-main-content col-md-9 col-lg-10 ms-auto p-4 bg-light">
            <div class="admin-page-header d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Mipangilio ya Tovuti</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
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
                        <h5 class="fw-bold mb-3 border-bottom pb-2">Taarifa za Duka</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Jina la Duka</label>
                                <input type="text" name="shop_name" class="form-control" value="<?php echo get_setting('shop_name', $current_settings); ?>" placeholder="Grant Fashions">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Anwani / Mahali</label>
                                <input type="text" name="address" class="form-control" value="<?php echo get_setting('address', $current_settings); ?>" placeholder="Dar es Salaam, Kariakoo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Lugha ya Admin (Default Language)</label>
                                <select name="default_language" class="form-select">
                                    <option value="sw" <?php echo (get_setting('default_language', $current_settings) == 'sw' || get_setting('default_language', $current_settings) == '') ? 'selected' : ''; ?>>Kiswahili</option>
                                    <option value="en" <?php echo (get_setting('default_language', $current_settings) == 'en') ? 'selected' : ''; ?>>English</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Logo ya Duka</label>
                                <input type="file" name="logo" class="form-control" accept="image/*">
                                <?php if(get_setting('logo', $current_settings)): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Logo ya sasa:</small><br>
                                        <img src="../uploads/<?php echo get_setting('logo', $current_settings); ?>" alt="Shop Logo" style="max-height: 80px; max-width: 200px;" class="mt-1 rounded border p-1">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3 border-bottom pb-2">Mawasiliano</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Namba ya Simu</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo get_setting('phone', $current_settings); ?>" placeholder="Mfano: 0712345678">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Barua Pepe (Email)</label>
                                <input type="email" name="email" class="form-control" value="<?php echo get_setting('email', $current_settings); ?>" placeholder="Mfano: info@grantfashions.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Instagram Link</label>
                                <input type="text" name="instagram" class="form-control" value="<?php echo get_setting('instagram', $current_settings); ?>" placeholder="https://instagram.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Facebook Link</label>
                                <input type="text" name="facebook" class="form-control" value="<?php echo get_setting('facebook', $current_settings); ?>" placeholder="https://facebook.com/...">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark btn-lg rounded-pill">Hifadhi Mabadiliko</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
