<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// Fallback for translation function '__' if not defined
if (!function_exists('__')) {
    function __($key) {
        return function_exists('t') ? t($key) : $key;
    }
}

// Helper to safely fetch count
function safe_count($conn, $sql) {
    try {
        $stmt = $conn->query($sql);
        return $stmt ? $stmt->fetchColumn() : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

// 1. Jumla ya Bidhaa
$total_products = (int) safe_count($conn, "SELECT COUNT(*) FROM products");

// 2. Jumla ya Makundi (Categories)
$total_categories = (int) safe_count($conn, "SELECT COUNT(DISTINCT category) FROM products");

// 3. Bidhaa za Hivi Karibuni (5 za mwisho)
$recent_products = [];
try {
    $stmt = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 5");
    if ($stmt) $recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 4. Vuta Takwimu za Wageni (Real-time kutoka activity_logs)
$visit_log_base = "FROM activity_logs WHERE log_type = 'visit'";
$total_visits = (int) (safe_count($conn, "SELECT COUNT(*) {$visit_log_base}") ?: 0);
$visits_today = (int) (safe_count($conn, "SELECT COUNT(*) {$visit_log_base} AND DATE(created_at) = CURDATE()") ?: 0);
$visits_week = (int) (safe_count($conn, "SELECT COUNT(*) {$visit_log_base} AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)") ?: 0);
$visits_month = (int) (safe_count($conn, "SELECT COUNT(*) {$visit_log_base} AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())") ?: 0);
$visits_year = (int) (safe_count($conn, "SELECT COUNT(*) {$visit_log_base} AND YEAR(created_at) = YEAR(CURDATE())") ?: 0);

// 5. Vuta Takwimu za Oda (Orders)
$confirmed_orders = (int) safe_count($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'confirmed'");
$pending_orders = (int) safe_count($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");

// Vuta Takwimu za Pre-Orders
$total_preorders = (int) safe_count($conn, "SELECT COUNT(*) FROM pre_orders");

// Kwa sasa 'unconfirmed' tutaichukulia kama 'pending'
$unconfirmed_orders = (int) $pending_orders; 

// 6. Bidhaa Zinazouzika Zaidi (Top Selling)
$top_products = [];
try {
    $stmt = $conn->query("
        SELECT p.name, p.image, SUM(oi.quantity) as total_sold 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        JOIN orders o ON oi.order_id = o.id 
        WHERE o.order_status != 'cancelled' 
        GROUP BY oi.product_id, p.name, p.image 
        ORDER BY total_sold DESC LIMIT 5
    ");
    if ($stmt) $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 7. Mwenendo wa Mauzo (Sales Trend) - Mwaka Huu
$sales_data_raw = [];
try {
    $stmt = $conn->query("
        SELECT MONTH(created_at) as month, SUM(total_amount) as total_sales 
        FROM orders 
        WHERE order_status = 'confirmed' AND YEAR(created_at) = YEAR(CURDATE()) 
        GROUP BY MONTH(created_at)
    ");
    if ($stmt) $sales_data_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {}

// Jaza miezi yote 12 na 0 kama hakuna mauzo
$sales_values = [];
$months_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

for ($i = 1; $i <= 12; $i++) {
    $sales_values[] = isset($sales_data_raw[$i]) ? (float)$sales_data_raw[$i] : 0.0;
}
$annual_sales_total = (float) array_sum($sales_values);

// 8. Idadi ya Maoni yanayosubiri (Pending Reviews)
$pending_reviews = (int) safe_count($conn, "SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Grant Fashions</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --admin-bg-1: #f8fbff;
            --admin-bg-2: #edf3fb;
            --admin-surface: #ffffff;
            --admin-surface-soft: #f5f8ff;
            --admin-text: #1a2437;
            --admin-muted: #667085;
            --admin-teal: #0f766e;
            --admin-teal-soft: #ccfbf1;
            --admin-blue: #2563eb;
            --admin-blue-soft: #dbeafe;
            --admin-amber: #d97706;
            --admin-amber-soft: #fef3c7;
            --admin-rose: #e76f51;
            --admin-rose-soft: #fde2dc;
            --admin-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
            --admin-radius: 1.15rem;
        }
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--admin-text);
            min-height: 100vh;
            background:
                radial-gradient(circle at 12% 10%, rgba(14, 165, 233, 0.16), transparent 34%),
                radial-gradient(circle at 88% 14%, rgba(15, 118, 110, 0.14), transparent 30%),
                linear-gradient(180deg, var(--admin-bg-1) 0%, var(--admin-bg-2) 100%);
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
            color: #fff;
            box-shadow: 10px 0 28px rgba(2, 6, 23, 0.25);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.72);
            padding: 0.75rem 1rem;
            margin-bottom: 0.35rem;
            border-radius: 0.75rem;
            transition: all 0.25s ease;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: linear-gradient(90deg, rgba(20, 184, 166, 0.24), rgba(59, 130, 246, 0.28));
            transform: translateX(4px);
        }
        .admin-main-content.dashboard-shell {
            background: transparent !important;
        }
        .dashboard-hero {
            border-radius: 1.4rem;
            padding: 1.5rem 1.6rem;
            background: linear-gradient(140deg, #0f172a 0%, #1e293b 48%, #0f766e 100%);
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.24);
        }
        .dashboard-hero::after {
            content: '';
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            right: -60px;
            top: -70px;
            pointer-events: none;
        }
        .dashboard-title {
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.01em;
        }
        .dashboard-subtitle {
            color: rgba(226, 232, 240, 0.92) !important;
            margin-bottom: 0;
        }
        .stat-card {
            border: 1px solid rgba(15, 23, 42, 0.05);
            border-radius: var(--admin-radius);
            background: var(--admin-surface);
            box-shadow: var(--admin-shadow);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            overflow: hidden;
        }
        /* Restored stat layout with focused icon & responsive tweaks */
        .stat-card .card-body { display: flex; align-items: center; gap: 1rem; padding: 1rem; }
        /* Make icons circular */
        .stat-card .icon-square { flex: 0 0 54px; width:54px; height:54px; display:flex; align-items:center; justify-content:center; border-radius: 50%; }
        .stat-card .icon-square i { font-size: 1.35rem; line-height: 1; }
        .stat-card .metric-text { flex: 1 1 auto; min-width: 0; }
        .stat-card .metric-number { flex: 0 0 64px; text-align: right; }

        .metric-title {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            color: var(--admin-muted) !important;
            margin: 0;
            overflow-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
            display: block;
            line-height: 1.1;
        }
        .metric-value {
            font-size: clamp(1.15rem, 1.6vw, 1.6rem);
            font-weight: 700;
            color: var(--admin-text);
            margin: 0;
            white-space: nowrap;
        }

        /* Small-screen stacking and adjustments */
        @media (max-width: 991.98px) {
            .stat-card .card-body { flex-direction: column; text-align: center; }
            .stat-card .icon-square { margin-bottom: 0.5rem; }
            .stat-card .metric-number { text-align: center; margin-top: 0.35rem; white-space: nowrap; }
            .stat-card .metric-text { max-width: 100%; }
            .metric-title { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        }

        /* Large-screen: center icon, title and number vertically */
        @media (min-width: 1200px) {
            .stat-card .card-body { display:flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 1.5rem; min-height: 220px; }
            .stat-card .icon-square { margin-bottom: 0.75rem; width:72px; height:72px; flex: 0 0 72px; border-radius: 50%; display:flex; align-items:center; justify-content:center; }
            .stat-card .icon-square i { font-size: 1.6rem; }
            .stat-card .metric-text { order: 2; margin: 0.25rem 0; }
            .stat-card .metric-number { order: 3; margin-top: 0.5rem; font-size: clamp(1.25rem, 1.8vw, 1.9rem); }
        }

        /* Behave nicely on narrow screens: stack vertical */
        @media (max-width: 991.98px) {
            .stat-card .card-body { flex-direction: column; text-align: center; }
            .stat-card .metric-number { text-align: center; }
            .stat-card .metric-text { max-width: 100%; }
            .metric-title { -webkit-line-clamp: 3; }
        }

        /* On very large screens, allow label to wrap beneath the icon/number instead of overflowing */
        @media (min-width: 1200px) {
            .stat-card .metric-text { max-width: calc(100% - 160px); }
            .stat-card.long-label .metric-text { max-width: calc(100% - 160px); }
        }
        .stat-card .metric-text { margin-left: 0.2rem; }
        .stat-card .metric-number { margin-left: auto; }
        .stat-confirmed .metric-title,
        .stat-pending .metric-title { -webkit-line-clamp: 2; font-size: 0.72rem; }
            transform: translateY(-6px);
            box-shadow: 0 24px 42px rgba(15, 23, 42, 0.14);
        }
        .icon-square {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            font-size: 1.45rem;
        }
        .metric-icon.visitors { background: #dbeafe; color: #1d4ed8; }
        .metric-icon.confirmed { background: #dcfce7; color: #15803d; }
        .metric-icon.pending { background: #fef3c7; color: #b45309; }
        .metric-icon.preorder { background: #e0f2fe; color: #0369a1; }
        .metric-icon.products { background: #ccfbf1; color: #0f766e; }
        .metric-icon.categories { background: #fde2dc; color: #c2410c; }
        .visitor-mini {
            background: var(--admin-surface-soft);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 0.8rem;
            padding: 0.65rem 0.55rem;
        }
        .visitor-total {
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
            margin-top: 1rem;
            padding-top: 0.75rem;
        }
        .whatsapp-card {
            border: none;
            color: #fff;
            background: linear-gradient(135deg, #0ea5a4 0%, #0f766e 100%);
        }
        .whatsapp-card .metric-title {
            color: rgba(240, 253, 250, 0.86) !important;
        }
        .panel-card {
            border: 1px solid rgba(15, 23, 42, 0.05);
            border-radius: var(--admin-radius);
            background: var(--admin-surface);
            box-shadow: var(--admin-shadow);
            overflow: hidden;
        }
        .panel-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(148, 163, 184, 0.24);
            padding: 1rem 1.25rem;
        }
        .panel-card .card-footer {
            background: transparent;
            border-top: 1px solid rgba(148, 163, 184, 0.24);
        }
        .sales-trend-card {
            position: relative;
        }
        .sales-trend-card .card-header {
            padding: 1.1rem 1.25rem;
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(37, 99, 235, 0.08));
        }
        .sales-trend-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9rem;
            flex-wrap: wrap;
        }
        .sales-trend-title {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .sales-trend-icon {
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0f766e;
            background: rgba(15, 118, 110, 0.14);
        }
        .sales-trend-note {
            margin: 0.15rem 0 0;
            font-size: 0.82rem;
            color: #64748b;
        }
        .sales-total-pill {
            border: 1px solid rgba(15, 118, 110, 0.2);
            background: rgba(255, 255, 255, 0.75);
            color: #0f766e;
            border-radius: 999px;
            padding: 0.4rem 0.8rem;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .sales-chart-shell {
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: linear-gradient(180deg, rgba(14, 165, 233, 0.08), rgba(15, 118, 110, 0.04));
            border-radius: 0.9rem;
            padding: 0.85rem 0.75rem 0.4rem;
        }
        .table-head th {
            background: var(--admin-surface-soft);
            color: #334155;
            border-bottom: 1px solid rgba(148, 163, 184, 0.28);
            font-weight: 600;
            letter-spacing: 0.01em;
        }
        .table td,
        .table th {
            border-color: rgba(148, 163, 184, 0.18);
        }
        .table-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
        }
        .top-product-item {
            border-bottom: 1px solid rgba(148, 163, 184, 0.2) !important;
        }
        .top-product-item:last-child {
            border-bottom: none !important;
        }
        .sales-badge {
            background: var(--admin-amber-soft);
            color: var(--admin-amber);
        }
        .quick-link {
            border: 1px solid rgba(148, 163, 184, 0.34);
            background: var(--admin-surface-soft);
            color: #0f172a;
            transition: all 0.2s ease;
        }
        .quick-link:hover {
            border-color: rgba(37, 99, 235, 0.35);
            background: #eef4ff;
            transform: translateY(-2px);
            color: #1e293b;
        }
        .quick-link i {
            color: var(--admin-blue);
        }
        [data-bs-theme="dark"] body {
            color: #e2e8f0;
            background:
                radial-gradient(circle at 14% 10%, rgba(20, 184, 166, 0.22), transparent 35%),
                radial-gradient(circle at 86% 15%, rgba(30, 64, 175, 0.22), transparent 34%),
                linear-gradient(180deg, #050b18 0%, #0b1220 100%);
        }
        [data-bs-theme="dark"] .sidebar {
            background: linear-gradient(180deg, #030712 0%, #0f172a 100%);
        }
        [data-bs-theme="dark"] .dashboard-hero {
            background: linear-gradient(140deg, #020617 0%, #0f172a 55%, #115e59 100%);
        }
        [data-bs-theme="dark"] .stat-card,
        [data-bs-theme="dark"] .panel-card {
            background: #0f172a;
            border-color: rgba(148, 163, 184, 0.2);
            box-shadow: 0 14px 34px rgba(2, 6, 23, 0.45);
        }
        [data-bs-theme="dark"] .sales-trend-card .card-header {
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.18), rgba(30, 64, 175, 0.16));
        }
        [data-bs-theme="dark"] .sales-trend-icon {
            color: #5eead4;
            background: rgba(45, 212, 191, 0.2);
        }
        [data-bs-theme="dark"] .sales-trend-note {
            color: #9aa9c0;
        }
        [data-bs-theme="dark"] .sales-total-pill {
            color: #5eead4;
            background: rgba(15, 23, 42, 0.78);
            border-color: rgba(45, 212, 191, 0.34);
        }
        [data-bs-theme="dark"] .sales-chart-shell {
            border-color: rgba(148, 163, 184, 0.25);
            background: linear-gradient(180deg, rgba(30, 64, 175, 0.2), rgba(15, 23, 42, 0.18));
        }
        [data-bs-theme="dark"] .metric-title,
        [data-bs-theme="dark"] .text-muted {
            color: #9aa9c0 !important;
        }
        [data-bs-theme="dark"] .metric-value {
            color: #e5e7eb;
        }
        [data-bs-theme="dark"] .visitor-mini,
        [data-bs-theme="dark"] .quick-link,
        [data-bs-theme="dark"] .table-head th {
            background: rgba(15, 23, 42, 0.7) !important;
            border-color: rgba(148, 163, 184, 0.2);
            color: #e5e7eb;
        }
        [data-bs-theme="dark"] .table {
            color: #dbe5f5;
        }
        [data-bs-theme="dark"] .table td,
        [data-bs-theme="dark"] .table th {
            border-color: rgba(148, 163, 184, 0.2);
        }
        [data-bs-theme="dark"] .sales-badge {
            background: rgba(251, 191, 36, 0.2);
            color: #facc15;
        }
        [data-bs-theme="dark"] .badge.bg-light.text-dark.border {
            background: rgba(148, 163, 184, 0.24) !important;
            color: #e5e7eb !important;
            border-color: rgba(148, 163, 184, 0.36) !important;
        }
        [data-bs-theme="dark"] .quick-link {
            color: #e5e7eb;
        }
        [data-bs-theme="dark"] .quick-link:hover {
            background: rgba(30, 64, 175, 0.24);
            border-color: rgba(96, 165, 250, 0.42);
            color: #f8fafc;
        }
        @media (max-width: 991.98px) {
            .dashboard-hero {
                padding: 1.2rem;
            }
            .metric-value {
                font-size: 1.7rem;
            }
        }
    </style>
    <script>
        const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
    </script>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="admin-main-content col-md-9 col-lg-10 ms-auto p-4 dashboard-shell">
            <!-- Welcome Section -->
            <div class="admin-page-header dashboard-hero d-flex justify-content-between align-items-center mb-4">
                <div class="position-relative">
                    <h2 class="dashboard-title fw-bold mb-1"><?php echo __('dashboard'); ?></h2>
                    <p class="dashboard-subtitle"><?php echo __('welcome_message'); ?></p>
                </div>
                <div class="admin-page-actions d-flex gap-2 position-relative">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <a href="add_product.php" class="btn btn-dark rounded-pill px-4 shadow-sm fw-semibold">
                        <i class="bi bi-plus-lg me-2"></i> <?php echo __('add_product'); ?>
                    </a>
                </div>
            </div>

            <!-- Stats Cards Row 1 -->
            <div class="row g-4 mb-4 align-items-stretch">
                <!-- Total Visits -->
                <div class="col-12 col-xl-6">
                    <div class="card stat-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-square metric-icon visitors me-3">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <h6 class="metric-title text-uppercase fw-bold mb-0"><?php echo __('visitors_stats'); ?></h6>
                            </div>
                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <div class="visitor-mini">
                                        <small class="d-block text-muted small"><?php echo __('today'); ?></small>
                                        <span class="fw-bold d-block fs-5"><?php echo $visits_today; ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="visitor-mini">
                                        <small class="d-block text-muted small"><?php echo __('this_week'); ?></small>
                                        <span class="fw-bold d-block fs-5"><?php echo $visits_week; ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="visitor-mini">
                                        <small class="d-block text-muted small"><?php echo __('this_month'); ?></small>
                                        <span class="fw-bold d-block fs-5"><?php echo $visits_month; ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="visitor-mini">
                                        <small class="d-block text-muted small"><?php echo __('this_year'); ?></small>
                                        <span class="fw-bold d-block fs-5"><?php echo $visits_year; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="visitor-total text-center">
                                <small class="text-muted">Jumla Kuu: <strong><?php echo $total_visits ?? 0; ?></strong></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmed Orders -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                                    <div class="card stat-card h-100 stat-confirmed">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="icon-square metric-icon confirmed me-3">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div>
                                <h6 class="metric-title text-uppercase fw-bold mb-1"><?php echo __('confirmed_orders'); ?></h6>
                                <h3 class="metric-value"><?php echo $confirmed_orders ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Orders -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                                    <div class="card stat-card h-100 stat-pending">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="icon-square metric-icon pending me-3">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div>
                                <h6 class="metric-title text-uppercase fw-bold mb-1"><?php echo __('pending_orders'); ?></h6>
                                <h3 class="metric-value"><?php echo $pending_orders ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pre-Orders -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-2">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="icon-square metric-icon preorder me-3">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <h6 class="metric-title text-uppercase fw-bold mb-1"><?php echo __('pre_orders'); ?></h6>
                                <h3 class="metric-value"><?php echo $total_preorders ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-5">
                <!-- Total Products -->
                <div class="col-md-6 col-xl-4">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="icon-square metric-icon products me-3">
                                <i class="bi bi-bag-check-fill"></i>
                            </div>
                            <div>
                                <h6 class="metric-title text-uppercase fw-bold mb-1"><?php echo __('total_products'); ?></h6>
                                <h3 class="metric-value"><?php echo $total_products; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="col-md-6 col-xl-4">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center p-4">
                            <div class="icon-square metric-icon categories me-3">
                                <i class="bi bi-tags-fill"></i>
                            </div>
                            <div>
                                <h6 class="metric-title text-uppercase fw-bold mb-1"><?php echo __('total_categories'); ?></h6>
                                <h3 class="metric-value"><?php echo $total_categories; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders (WhatsApp) -->
                <div class="col-xl-4">
                    <div class="card stat-card whatsapp-card h-100">
                        <div class="card-body d-flex align-items-center p-4 position-relative">
                            <div class="icon-square bg-white bg-opacity-25 text-white me-3">
                                <i class="bi bi-whatsapp"></i>
                            </div>
                            <div>
                                <h6 class="metric-title text-uppercase fw-bold mb-1">Oda (WhatsApp)</h6>
                                <a href="https://web.whatsapp.com" target="_blank" class="text-white text-decoration-none fw-semibold stretched-link d-inline-flex align-items-center gap-1">
                                    Fungua WhatsApp Web <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart + Top Selling -->
            <div class="row mb-4">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <div class="card panel-card sales-trend-card h-100">
                        <div class="card-header">
                            <div class="sales-trend-head">
                                <div>
                                    <div class="sales-trend-title">
                                        <span class="sales-trend-icon"><i class="bi bi-graph-up-arrow"></i></span>
                                        <h5 class="fw-bold mb-0"><?php echo __('sales_trend') . ' (' . date('Y') . ')'; ?></h5>
                                    </div>
                                    <p class="sales-trend-note">Mwenendo wa mapato yaliyothibitishwa kwa kila mwezi.</p>
                                </div>
                                <span class="sales-total-pill">Jumla: Tsh <?php echo number_format($annual_sales_total); ?></span>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="sales-chart-shell">
                                <canvas id="salesChart" style="max-height: 330px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card panel-card h-100">
                        <div class="card-header">
                            <h5 class="fw-bold mb-0"><?php echo __('top_selling'); ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if($top_products): foreach($top_products as $top): ?>
                                <li class="list-group-item top-product-item d-flex align-items-center p-3 border-0">
                                    <img src="../uploads/<?php echo htmlspecialchars($top['image']); ?>" 
                                         class="rounded-3 shadow-sm" 
                                         width="50" height="50" style="object-fit: cover;"
                                         onerror="this.src='https://via.placeholder.com/50'"
                                         loading="lazy">
                                    <div class="ms-3 flex-grow-1">
                                        <h6 class="mb-0 fw-bold small"><?php echo htmlspecialchars($top['name']); ?></h6>
                                        <small class="text-muted"><?php echo (int) $top['total_sold']; ?> zimeuzwa</small>
                                    </div>
                                    <span class="badge sales-badge rounded-pill fw-bold"><?php echo (int) $top['total_sold']; ?></span>
                                </li>
                                <?php endforeach; else: ?>
                                <li class="list-group-item p-4 text-center text-muted small">Hakuna data ya mauzo bado.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Products + Manage Site -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card panel-card overflow-hidden">
                        <div class="card-header">
                            <h5 class="fw-bold mb-0"><?php echo __('recent_products'); ?></h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-head">
                                    <tr>
                                        <th class="ps-4"><?php echo __('image'); ?></th>
                                        <th><?php echo __('product_name'); ?></th>
                                        <th><?php echo __('price'); ?></th>
                                        <th><?php echo __('category'); ?></th>
                                        <th><?php echo __('status'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($recent_products) > 0): ?>
                                        <?php foreach($recent_products as $product): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                                                     class="table-img shadow-sm" 
                                                     alt="Product"
                                                     onerror="this.src='https://via.placeholder.com/40'"
                                                     loading="lazy">
                                            </td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td class="text-muted">Tsh <?php echo number_format($product['price']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo ucfirst($product['category']); ?></span></td>
                                            <td>
                                                <?php if(isset($product['status']) && ($product['status'] == 'not_active' || $product['status'] == 'preorder')): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><?php echo __('not_active'); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><?php echo __('active'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">Hakuna bidhaa zilizoongezwa bado.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer text-center py-3">
                            <a href="products.php" class="text-decoration-none fw-semibold"><?php echo __('view_all_products'); ?> <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card panel-card mb-4">
                        <div class="card-header">
                            <h5 class="fw-bold mb-0"><?php echo __('manage_site'); ?></h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-grid gap-3">
                                <a href="sliders.php" class="btn quick-link py-3 text-start px-4 rounded-3">
                                    <i class="bi bi-film me-2 fs-5 align-middle"></i> 
                                    <span class="align-middle"><?php echo __('change_hero_video'); ?></span>
                                </a>
                                <a href="add_product.php" class="btn quick-link py-3 text-start px-4 rounded-3">
                                    <i class="bi bi-plus-square me-2 fs-5 align-middle"></i> 
                                    <span class="align-middle"><?php echo __('add_product'); ?></span>
                                </a>
                                <a href="settings.php" class="btn quick-link py-3 text-start px-4 rounded-3">
                                    <i class="bi bi-gear me-2 fs-5 align-middle"></i> 
                                    <span class="align-middle"><?php echo __('general_settings'); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    let salesChart = null;

    function getChartPalette(theme) {
        if (theme === 'dark') {
            return {
                line: '#2dd4bf',
                fill: 'rgba(45, 212, 191, 0.2)',
                point: '#5eead4',
                grid: 'rgba(148, 163, 184, 0.3)',
                label: '#cbd5e1'
            };
        }
        return {
            line: '#0f766e',
            fill: 'rgba(15, 118, 110, 0.18)',
            point: '#0f766e',
            grid: 'rgba(148, 163, 184, 0.28)',
            label: '#475569'
        };
    }

    function applyChartPalette(theme) {
        if (!salesChart) {
            return;
        }
        const palette = getChartPalette(theme);
        salesChart.data.datasets[0].borderColor = palette.line;
        salesChart.data.datasets[0].backgroundColor = palette.fill;
        salesChart.data.datasets[0].pointBackgroundColor = palette.point;
        salesChart.options.scales.y.grid.color = palette.grid;
        salesChart.options.scales.y.ticks.color = palette.label;
        salesChart.options.scales.x.ticks.color = palette.label;
        salesChart.update();
    }

    const initialThemeForChart = document.documentElement.getAttribute('data-bs-theme') || 'light';
    const initialPalette = getChartPalette(initialThemeForChart);

    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months_labels); ?>,
            datasets: [{
                label: 'Mauzo (Tsh)',
                data: <?php echo json_encode($sales_values); ?>,
                borderColor: initialPalette.line,
                backgroundColor: initialPalette.fill,
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: initialPalette.point,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleColor: '#f8fafc',
                    bodyColor: '#e2e8f0',
                    borderColor: 'rgba(148, 163, 184, 0.35)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('sw-TZ', { style: 'currency', currency: 'TZS' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2, 4], color: initialPalette.grid },
                    ticks: { color: initialPalette.label }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: initialPalette.label }
                }
            },
            animation: {
                duration: 900,
                easing: 'easeOutQuart'
            }
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('admin-theme-toggle');
        if (!themeToggle) {
            return;
        }
        const icon = themeToggle.querySelector('i');
        const html = document.documentElement;

        // Add attribute to enable transition disabling during theme changes
        html.setAttribute('data-bs-theme-transition', 'true');

        if (html.getAttribute('data-bs-theme') === 'dark') {
            icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill');
            themeToggle.classList.replace('btn-outline-dark', 'btn-outline-light');
        }

        themeToggle.addEventListener('click', () => {
            // Disable transitions during theme change
            html.setAttribute('data-bs-theme-transition', 'true');
            
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
            
            applyChartPalette(newTheme);
            
            // Re-enable transitions after a short delay
            setTimeout(() => {
                html.removeAttribute('data-bs-theme-transition');
            }, 300);
        });
    });
</script>
</body>
</html>
