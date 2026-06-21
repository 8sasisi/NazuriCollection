<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$admin_id = $_SESSION['admin_id'];
$message = "";
$msg_type = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($username) || empty($email) || empty($phone)) {
        $message = "Tafadhali jaza jina, barua pepe na simu.";
        $msg_type = "danger";
    } else {
        // Check if username or email exists for other users
        $stmt = $conn->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $admin_id]);
        if ($stmt->fetch()) {
            $message = "Jina la mtumiaji au Barua pepe tayari inatumika na admin mwingine.";
            $msg_type = "danger";
        } else {
            try {
                $conn->beginTransaction();
                
                // Update basic info
                $sql = "UPDATE admins SET username = ?, email = ?, phone = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$username, $email, $phone, $admin_id]);

                // Update Password if provided
                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) {
                        throw new Exception("Nenosiri lazima liwe na angalau herufi 6.");
                    }
                    if ($new_password !== $confirm_password) {
                        throw new Exception("Nenosiri mpya hazifanani.");
                    }
                    
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $admin_id]);
                }

                $conn->commit();
                $message = "Wasifu umesasishwa kikamilifu.";
                $msg_type = "success";
                
                // Update session username
                $_SESSION['admin_username'] = $username;

            } catch (Exception $e) {
                $conn->rollBack();
                $message = "Kosa: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

// Fetch Admin Details
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wasifu Wangu | Nazuri Admin</title>
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
                <h2 class="fw-bold">Wasifu Wangu (Profile)</h2>
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
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <h5 class="fw-bold mb-3 border-bottom pb-2">Taarifa Binafsi</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Jina la Mtumiaji (Username)</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Namba ya Simu</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Barua Pepe (Email)</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3 border-bottom pb-2">Badilisha Nenosiri</h5>
                        <div class="alert alert-info small"><i class="bi bi-info-circle"></i> Acha wazi kama hutaki kubadilisha nenosiri.</div>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nenosiri Jipya</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Kima cha chini herufi 6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Thibitisha Nenosiri</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Rudia nenosiri jipya">
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
