<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check Role (Only Super Admin can backup)
if (!isset($_SESSION['admin_role'])) {
    // Ensure role column exists (Fix for existing sessions during upgrade)
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

$message = "";
$msg_type = "";

if (isset($_POST['download_backup'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    if ($_SESSION['admin_role'] !== 'Super Admin') {
        $message = "Huna ruhusa ya kupakua backup. Hii ni kazi ya Super Admin pekee.";
        $msg_type = "danger";
    } else {
        // Generate Backup
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "-- Database Backup: " . $dbname . "\n";
        $sql .= "-- Date: " . date("Y-m-d H:i:s") . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $result = $conn->query("SELECT * FROM $table");
            $num_fields = $result->columnCount();
            
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
            $sql .= $row2[1] . ";\n\n";
            
            for ($i = 0; $i < $num_fields; $i++) {
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $sql .= "INSERT INTO `$table` VALUES(";
                    for ($j = 0; $j < $num_fields; $j++) {
                        $row[$j] = addslashes($row[$j]);
                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                        if (isset($row[$j])) {
                            $sql .= '"' . $row[$j] . '"';
                        } else {
                            $sql .= '""';
                        }
                        if ($j < ($num_fields - 1)) {
                            $sql .= ',';
                        }
                    }
                    $sql .= ");\n";
                }
            }
            $sql .= "\n\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=db_backup_' . date('Y-m-d_H-i-s') . '.sql');
        echo $sql;
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup | Nazuri Admin</title>
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
                <h2 class="fw-bold">Hifadhi Nakala (Backup)</h2>
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
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <i class="bi bi-database-check display-1 text-success"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Pakua Nakala ya Database</h4>
                    <p class="text-muted mb-4">
                        Hii itakuruhusu kupakua faili la <strong>.sql</strong> lenye taarifa zote za bidhaa, oda, wateja na mipangilio.<br>
                        Ni muhimu kufanya hivi mara kwa mara kwa ajili ya usalama wa data zako.
                    </p>
                    
                    <?php if($_SESSION['admin_role'] === 'Super Admin'): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" name="download_backup" class="btn btn-dark btn-lg rounded-pill px-5">
                                <i class="bi bi-download me-2"></i> Pakua Backup Sasa
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning d-inline-block">
                            <i class="bi bi-lock-fill me-2"></i> Kipengele hiki kinapatikana kwa <strong>Super Admin</strong> pekee.
                        </div>
                    <?php endif; ?>
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
