<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$msg_type = "";

// Initialize Form Data
$form_data = [
    'title' => '',
    'subtitle' => '',
    'button_text' => 'Nunua Sasa',
    'button_link' => 'shop.php',
    'sort_order' => 1
];
$is_editing = false;
$edit_id = null;

// Handle Edit Request (GET)
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM sliders WHERE id = ?");
    $stmt->execute([$edit_id]);
    $fetched_slider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fetched_slider) {
        $is_editing = true;
        $form_data = $fetched_slider;
    }
}

// Handle Add New Slider
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_slider'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $button_text = $_POST['button_text'];
    $button_link = $_POST['button_link'];
    $sort_order = $_POST['sort_order'];

    // Video Upload
    $target_dir = "../uploads/videos/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["video"]["name"]);
    $videoFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    // Jina la kipekee
    $new_file_name = uniqid() . '.' . $videoFileType;
    $target_file = $target_dir . $new_file_name;
    $uploadOk = 1;

    // Validate video upload (size limit 50MB)
    require_once __DIR__ . '/../includes/functions.php';
    list($ok, $msg, $mime) = validate_upload_file($_FILES['video'] ?? [], ['video/mp4','video/webm','application/mp4'], 50 * 1024 * 1024);
    if (!$ok) {
        $message = $msg;
        $msg_type = 'danger';
    } else {
        if (move_uploaded_file($_FILES["video"]["tmp_name"], $target_file)) {
            $video_path = "videos/" . $new_file_name;
            try {
                $sql = "INSERT INTO sliders (title, subtitle, button_text, button_link, video_path, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$title, $subtitle, $button_text, $button_link, $video_path, $sort_order]);
                $message = "Slider imeongezwa kikamilifu!";
                $msg_type = "success";
            } catch(PDOException $e) {
                $message = "Kosa la Database: " . $e->getMessage();
                $msg_type = "danger";
            }
        } else {
            $message = "Kosa wakati wa kupakia video.";
            $msg_type = "danger";
        }
    }
}

// Handle Update Slider
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_slider'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $id = $_POST['slider_id'];
    $title = $_POST['title'];
    $subtitle = $_POST['subtitle'];
    $button_text = $_POST['button_text'];
    $button_link = $_POST['button_link'];
    $sort_order = $_POST['sort_order'];

    $video_update_sql = "";
    $params = [$title, $subtitle, $button_text, $button_link, $sort_order];

    // Check if new video is uploaded
    if (!empty($_FILES["video"]["name"])) {
        $target_dir = "../uploads/videos/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_name = basename($_FILES["video"]["name"]);
        $videoFileType = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_file_name = uniqid() . '.' . $videoFileType;
        $target_file = $target_dir . $new_file_name;
        
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES["video"]["tmp_name"]);
        } else {
            $mime = $_FILES["video"]["type"];
        }
        $allowed_mimes = ['video/mp4', 'video/webm', 'application/mp4'];

        if(!in_array($mime, $allowed_mimes)) {
            $message = "Faili la video sio sahihi. Tafadhali tumia MP4 au WEBM.";
            $msg_type = "danger";
        } else {
            if (move_uploaded_file($_FILES["video"]["tmp_name"], $target_file)) {
                // Delete old video
                $stmt = $conn->prepare("SELECT video_path FROM sliders WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists("../uploads/" . $old)) {
                    unlink("../uploads/" . $old);
                }

                $video_update_sql = ", video_path = ?";
                $params[] = "videos/" . $new_file_name;
            } else {
                $message = "Kosa wakati wa kupakia video.";
                $msg_type = "danger";
            }
        }
    }

    if (empty($message)) {
        $params[] = $id;
        $sql = "UPDATE sliders SET title = ?, subtitle = ?, button_text = ?, button_link = ?, sort_order = ? $video_update_sql WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        header("Location: sliders.php?msg=updated");
        exit();
    }
}

// Handle Delete Slider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $id = $_POST['delete_id'];
        $stmt = $conn->prepare("SELECT video_path FROM sliders WHERE id = ?");
        $stmt->execute([$id]);
        $slider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($slider) {
            $file_path = "../uploads/" . $slider['video_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $stmt = $conn->prepare("DELETE FROM sliders WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: sliders.php");
            exit();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'updated') {
    $message = "Slider imesasishwa kikamilifu!";
    $msg_type = "success";
}

// Fetch Sliders
$stmt = $conn->query("SELECT * FROM sliders ORDER BY sort_order ASC");
$sliders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simamia Sliders | Nazuri Admin</title>
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
                <h2 class="fw-bold mb-0">Simamia Video za Mwanzo (Sliders)</h2>
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

            <!-- Add Slider Form -->
            <div class="card border-0 shadow-sm rounded-4 mb-5">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><?php echo $is_editing ? 'Hariri Slider' : 'Ongeza Slider Mpya'; ?></h5>
                </div>
                <div class="card-body">
                    <form action="sliders.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if($is_editing): ?>
                            <input type="hidden" name="slider_id" value="<?php echo $edit_id; ?>">
                            <input type="hidden" name="update_slider" value="1">
                        <?php else: ?>
                            <input type="hidden" name="add_slider" value="1">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kichwa Kikuu (Title)</label>
                                <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($form_data['title']); ?>" placeholder="Mfano: Elegance Redefined">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maelezo (Subtitle)</label>
                                <input type="text" name="subtitle" class="form-control" required value="<?php echo htmlspecialchars($form_data['subtitle']); ?>" placeholder="Maelezo mafupi...">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Maandishi ya Kitufe</label>
                                <input type="text" name="button_text" class="form-control" value="<?php echo htmlspecialchars($form_data['button_text']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Link ya Kitufe</label>
                                <input type="text" name="button_link" class="form-control" value="<?php echo htmlspecialchars($form_data['button_link']); ?>">
                            </div>
                             <div class="col-md-4 mb-3">
                                <label class="form-label">Mpangilio (Sort Order)</label>
                                <input type="number" name="sort_order" class="form-control" value="<?php echo htmlspecialchars($form_data['sort_order']); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Faili la Video (MP4 au WEBM) <?php if($is_editing) echo '<span class="text-muted small">(Acha wazi kama hubadilishi)</span>'; ?></label>
                                <input type="file" name="video" class="form-control" <?php echo $is_editing ? '' : 'required'; ?> accept="video/mp4,video/webm" />
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark rounded-pill px-4"><?php echo $is_editing ? 'Hifadhi Mabadiliko' : 'Ongeza Slider'; ?></button>
                        <?php if($is_editing): ?>
                            <a href="sliders.php" class="btn btn-outline-secondary rounded-pill px-4 ms-2">Ghairi</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Existing Sliders Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sliders Zilizopo</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Mpangilio</th>
                                <th>Video</th>
                                <th>Kichwa</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($sliders) > 0): ?>
                                <?php foreach($sliders as $slider): ?>
                                <tr>
                                    <td class="ps-4"><span class="badge bg-secondary"><?php echo $slider['sort_order']; ?></span></td>
                                    <td>
                                        <video width="120" muted class="rounded">
                                            <source src="../uploads/<?php echo htmlspecialchars($slider['video_path']); ?>" type="video/mp4">
                                        </video>
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($slider['title']); ?></td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Active</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="sliders.php?edit_id=<?php echo $slider['id']; ?>" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="bi bi-pencil"></i> Hariri
                                        </a>
                                        <form action="sliders.php" method="POST" onsubmit="return confirm('Una uhakika unataka kufuta slider hii?');" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo $slider['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Futa</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Hakuna sliders zilizoongezwa bado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
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
