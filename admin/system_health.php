<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// Expected Database Schema is now loaded from a central file
$schema = require_once __DIR__ . '/../config/schema.php';

$report = [];
$system_status = 'healthy';

foreach ($schema as $table => $data) {
    $table_report = ['name' => $table, 'status' => 'ok', 'missing' => []];
    
    try {
        // Check if table exists
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() == 0) {
            $table_report['status'] = 'missing_table';
            $system_status = 'critical';
        } else {
            // Check columns
            $stmt = $conn->query("SHOW COLUMNS FROM $table");
            $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach (array_keys($data['columns']) as $col) {
                if (!in_array($col, $existing_columns)) {
                    $table_report['missing'][] = $col;
                }
            }
            
            if (!empty($table_report['missing'])) {
                $table_report['status'] = 'missing_columns';
                if ($system_status != 'critical') $system_status = 'warning';
            }
        }
    } catch (PDOException $e) {
        $table_report['status'] = 'error';
        $table_report['error'] = $e->getMessage();
    }
    
    $report[] = $table_report;
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afya ya Mfumo | Nazuri Admin</title>
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
                <h2 class="fw-bold">Afya ya Mfumo (System Health)</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <?php if($system_status == 'healthy'): ?>
                            <div class="display-1 text-success me-3"><i class="bi bi-check-circle-fill"></i></div>
                            <div>
                                <h3 class="fw-bold text-success mb-1">Mfumo Uko Salama</h3>
                                <p class="text-muted mb-0">Database tables na columns zote muhimu zipo.</p>
                            </div>
                        <?php elseif($system_status == 'warning'): ?>
                            <div class="display-1 text-warning me-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div>
                                <h3 class="fw-bold text-warning mb-1">Kuna Mapungufu</h3>
                                <p class="text-muted mb-0">Baadhi ya columns zinakosekana kwenye tables.</p>
                            </div>
                        <?php else: ?>
                            <div class="display-1 text-danger me-3"><i class="bi bi-x-circle-fill"></i></div>
                            <div>
                                <h3 class="fw-bold text-danger mb-1">Tatizo la Kiufundi</h3>
                                <p class="text-muted mb-0">Baadhi ya tables muhimu hazipo.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="fw-bold mb-0">Ukaguzi wa Database</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Jina la Table</th>
                                <th>Status</th>
                                <th>Maelezo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report as $row): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td>
                                    <?php if($row['status'] == 'ok'): ?>
                                        <span class="badge bg-success rounded-pill px-3">OK</span>
                                    <?php elseif($row['status'] == 'missing_table'): ?>
                                        <span class="badge bg-danger rounded-pill px-3">Missing Table</span>
                                    <?php elseif($row['status'] == 'missing_columns'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3">Missing Columns</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?php 
                                    if($row['status'] == 'ok') {
                                        echo '<i class="bi bi-check-lg text-success me-1"></i> Ipo sawa';
                                    } elseif($row['status'] == 'missing_columns') {
                                        echo 'Columns zinazokosekana: <span class="text-danger fw-bold">' . implode(', ', $row['missing']) . '</span>';
                                    } elseif($row['status'] == 'missing_table') {
                                        echo 'Table haipo kwenye database.';
                                    } else {
                                        echo $row['error'];
                                    }
                                    ?>
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
