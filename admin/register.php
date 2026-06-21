<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$msg_type = "";

// Hakikisha tunajua role ya admin aliyelogin (kwa usalama kama session ni ya zamani)
if (!isset($_SESSION['admin_role'])) {
    // Ensure role column exists
    try {
        $conn->query("SELECT role FROM admins LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'Editor'");
        $conn->exec("UPDATE admins SET role = 'Super Admin' ORDER BY id ASC LIMIT 1");
    }

    $stmt = $conn->prepare("SELECT role FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $_SESSION['admin_role'] = $stmt->fetchColumn();
}

// Handle Delete Admin
if (isset($_GET['delete_id'])) {
    // CSRF Validation
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $delete_id = $_GET['delete_id'];
    
    // Zuia kujifuta mwenyewe
    if ($delete_id == $_SESSION['admin_id']) {
        $message = "Huwezi kujifuta mwenyewe!";
        $msg_type = "danger";
    } elseif ($_SESSION['admin_role'] !== 'Super Admin') {
        $message = "Huna ruhusa ya kufuta admin. Hii ni kazi ya Super Admin pekee.";
        $msg_type = "danger";
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: register.php?msg=deleted");
        exit();
    }
}

// Handle Change Role
if (isset($_GET['change_role_id']) && isset($_GET['new_role'])) {
    // CSRF Validation
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    if ($_SESSION['admin_role'] !== 'Super Admin') {
        $message = "Huna ruhusa ya kubadilisha role.";
        $msg_type = "danger";
    } else {
        $id = $_GET['change_role_id'];
        $new_role = $_GET['new_role'];
        $stmt = $conn->prepare("UPDATE admins SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $id]);
        $message = "Role ya admin imebadilishwa kuwa " . htmlspecialchars($new_role);
        $msg_type = "success";
    }
}

// 1. Hesabu idadi ya admin waliopo
$stmt = $conn->query("SELECT COUNT(*) FROM admins");
$admin_count = $stmt->fetchColumn();
$limit_reached = ($admin_count >= 3);

// 2. Shughulikia usajili (POST request)
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $message = "Admin amefutwa kikamilifu.";
    $msg_type = "success";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$limit_reached) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Uhakiki wa data
    if (empty($username) || empty($email) || empty($phone) || empty($password)) {
        $message = "Tafadhali jaza sehemu zote.";
        $msg_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Tafadhali ingiza anwani sahihi ya barua pepe.";
        $msg_type = "danger";
    } elseif (strlen($password) < 6) {
        $message = "Nenosiri linapaswa kuwa na angalau herufi 6.";
        $msg_type = "danger";
    } else {
        // Angalia kama username au email tayari inatumika
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $message = "Username au Email tayari inatumika.";
            $msg_type = "danger";
        } else {
            // Hash nenosiri
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Ingiza data kwenye database
            try {
                $sql = "INSERT INTO admins (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$username, $email, $phone, $hashed_password, $role]);
                
                $message = "Admin mpya amesajiliwa kikamilifu!";
                $msg_type = "success";
                // Refresh admin count
                $admin_count++;
                $limit_reached = ($admin_count >= 3);

            } catch(PDOException $e) {
                $message = "Kosa la Database: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

// Vuta orodha ya admins
$stmt_list = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
$admins_list = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sajili Admin | Nazuri Admin</title>
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
                <h2 class="fw-bold">Sajili Admin Mpya</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <a href="index.php" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-arrow-left me-2"></i> Rudi</a>
                </div>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($limit_reached && $msg_type != "success"): ?>
                <div class="alert alert-warning" role="alert">
                    <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Ukomo Umefikiwa!</h4>
                    <p>Tayari umefikisha idadi ya juu ya wasimamizi (admins) ambayo ni 3. Huwezi kuongeza msimamizi mwingine.</p>
                    <hr>
                    <p class="mb-0">Ili kuongeza msimamizi mpya, tafadhali futa mmoja wa wasimamizi waliopo kwenye orodha hapa chini.</p>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="register.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <fieldset <?php if ($limit_reached) echo 'disabled'; ?>>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Jina la Msimamizi (Username)</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Namba ya Simu</label>
                                    <input type="tel" name="phone" class="form-control" required placeholder="Mfano: 0712345678">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">Barua Pepe (Email)</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">Nenosiri (Password)</label>
                                    <input type="password" name="password" class="form-control" required>
                                    <small class="text-muted">Nenosiri liwe na angalau herufi 6.</small>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label fw-bold">Role (Cheo)</label>
                                    <select name="role" class="form-select" required>
                                        <option value="Editor">Editor (Anaweza kuhariri bidhaa)</option>
                                        <option value="Super Admin">Super Admin (Anaweza kufuta admins)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark btn-lg rounded-pill">Sajili Msimamizi</button>
                            </div>
                            <div class="mt-2 text-muted small"><i class="bi bi-info-circle"></i> <strong>Super Admin:</strong> Ana uwezo kamili. <strong>Editor:</strong> Hawezi kufuta au kubadili admins wengine.</div>
                        </fieldset>
                    </form>
                </div>
            </div>

            <!-- Orodha ya Admins -->
            <div class="card border-0 shadow-sm rounded-4 mt-5">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="fw-bold mb-0">Orodha ya Wasimamizi (<?php echo $admin_count; ?>/3)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Username</th>
                                <th>Email</th>
                                <th>Simu</th>
                                <th>Role</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($admins_list as $admin): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo htmlspecialchars($admin['username']); ?> <?php if($admin['id'] == $_SESSION['admin_id']) echo '<span class="badge bg-success ms-2">Wewe</span>'; ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td><?php echo htmlspecialchars($admin['phone']); ?></td>
                                <td><span class="badge <?php echo ($admin['role'] == 'Super Admin') ? 'bg-dark' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($admin['role']); ?></span></td>
                                <td class="text-end pe-4">
                                    <?php if($admin['id'] != $_SESSION['admin_id']): ?>
                                        <?php if($_SESSION['admin_role'] == 'Super Admin'): ?>
                                            <!-- Change Role Button -->
                                            <?php if($admin['role'] == 'Editor'): ?>
                                                <a href="register.php?change_role_id=<?php echo $admin['id']; ?>&new_role=Super Admin&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Pandisha Cheo"><i class="bi bi-arrow-up-circle"></i></a>
                                            <?php else: ?>
                                                <a href="register.php?change_role_id=<?php echo $admin['id']; ?>&new_role=Editor&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="Shusha Cheo"><i class="bi bi-arrow-down-circle"></i></a>
                                            <?php endif; ?>

                                            <!-- Delete Button -->
                                            <a href="register.php?delete_id=<?php echo $admin['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Una uhakika unataka kumfuta admin huyu?');">
                                                <i class="bi bi-trash"></i> Futa
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-lock"></i> Locked</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
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
