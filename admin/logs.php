<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// Fetch All Logs (with pagination)
$logs_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $logs_per_page;

$total_logs_stmt = $conn->query("SELECT COUNT(*) FROM activity_logs");
$total_logs = $total_logs_stmt->fetchColumn();
$total_pages = ceil($total_logs / $logs_per_page);

$all_logs_stmt = $conn->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$all_logs_stmt->bindParam(':limit', $logs_per_page, PDO::PARAM_INT);
$all_logs_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$all_logs_stmt->execute();
$all_logs = $all_logs_stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch Top IPs
$top_ips_stmt = $conn->query("
    SELECT ip_address, COUNT(*) as visit_count, MAX(created_at) as last_visit
    FROM activity_logs
    GROUP BY ip_address
    ORDER BY visit_count DESC
    LIMIT 15
");
$top_ips = $top_ips_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch 404 Errors
$errors_404_stmt = $conn->query("SELECT * FROM activity_logs WHERE log_type = '404_error' ORDER BY created_at DESC LIMIT 50");
$errors_404 = $errors_404_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Suspicious Requests
$suspicious_stmt = $conn->query("SELECT * FROM activity_logs WHERE log_type = 'suspicious' ORDER BY created_at DESC LIMIT 50");
$suspicious_logs = $suspicious_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs | Grant Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #1a1d20; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.7); padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .nav-tabs .nav-link { color: #6c757d; }
        .nav-tabs .nav-link.active { color: #000; font-weight: bold; border-color: #dee2e6 #dee2e6 #fff; }
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
                <h2 class="fw-bold mb-0">Kumbukumbu za Matukio (Activity Logs)</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-logs-tab" data-bs-toggle="tab" data-bs-target="#all-logs" type="button" role="tab">Matukio Yote</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="top-ips-tab" data-bs-toggle="tab" data-bs-target="#top-ips" type="button" role="tab">Top IPs</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="errors-tab" data-bs-toggle="tab" data-bs-target="#errors" type="button" role="tab">404 Errors</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="suspicious-tab" data-bs-toggle="tab" data-bs-target="#suspicious" type="button" role="tab">Suspicious</button>
                </li>
            </ul>

            <div class="tab-content" id="myTabContent">
                <!-- All Logs Tab -->
                <div class="tab-pane fade show active" id="all-logs" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Aina</th>
                                        <th>IP Address</th>
                                        <th>Ukurasa</th>
                                        <th>Maelezo</th>
                                        <th>Tarehe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($all_logs as $log): ?>
                                    <tr>
                                        <td class="ps-4"><span class="badge bg-secondary"><?php echo $log['log_type']; ?></span></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($log['request_uri']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Pagination for All Logs -->
                </div>

                <!-- Top IPs Tab -->
                <div class="tab-pane fade" id="top-ips" role="tabpanel">
                     <div class="card border-0 shadow-sm rounded-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">IP Address</th>
                                        <th>Idadi ya Matukio</th>
                                        <th>Ziara ya Mwisho</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_ips as $ip): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                        <td><span class="badge bg-primary rounded-pill"><?php echo $ip['visit_count']; ?></span></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($ip['last_visit'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 404 Errors Tab -->
                <div class="tab-pane fade" id="errors" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">IP Address</th>
                                        <th>Ukurasa Uliokosewa</th>
                                        <th>Referrer</th>
                                        <th>Tarehe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($errors_404 as $error): ?>
                                    <tr>
                                        <td class="ps-4"><?php echo htmlspecialchars($error['ip_address']); ?></td>
                                        <td class="fw-bold text-danger"><?php echo htmlspecialchars($error['request_uri']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($error['referrer_url']); ?></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($error['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Suspicious Tab -->
                <div class="tab-pane fade" id="suspicious" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">IP Address</th>
                                        <th>Ukurasa</th>
                                        <th>Maelezo ya Shambulio</th>
                                        <th>Tarehe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($suspicious_logs as $log): ?>
                                    <tr class="table-danger">
                                        <td class="ps-4 fw-bold"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td><?php echo htmlspecialchars($log['request_uri']); ?></td>
                                        <td class="small"><?php echo htmlspecialchars($log['details']); ?></td>
                                        <td><?php echo date('d M Y, H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
