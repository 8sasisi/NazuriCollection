<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_runtime.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$selected_month = isset($_GET['month']) ? $_GET['month'] : date('n');
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Month Names
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Machi', 4 => 'Aprili',
    5 => 'Mei', 6 => 'Juni', 7 => 'Julai', 8 => 'Agosti',
    9 => 'Septemba', 10 => 'Oktoba', 11 => 'Novemba', 12 => 'Desemba'
];

// Fetch Report Data (Confirmed Orders Only)
$sql = "SELECT * FROM orders WHERE order_status = 'confirmed' AND MONTH(created_at) = ? AND YEAR(created_at) = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$selected_month, $selected_year]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_sales = 0;
$total_orders = count($orders);
foreach ($orders as $order) {
    $total_sales += $order['total_amount'];
}
$average_order = $total_orders > 0 ? $total_sales / $total_orders : 0;

// Fetch Top Products for the month
$sql_products = "
    SELECT p.name, SUM(oi.quantity) as total_qty, SUM(oi.price * oi.quantity) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.order_status = 'confirmed' AND MONTH(o.created_at) = ? AND YEAR(o.created_at) = ?
    GROUP BY oi.product_id
    ORDER BY total_qty DESC
    LIMIT 5
";
$stmt_products = $conn->prepare($sql_products);
$stmt_products->execute([$selected_month, $selected_year]);
$top_products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

$report_subject = "Ripoti ya Mauzo - " . $months[$selected_month] . " " . $selected_year;
$report_summary_lines = [
    "Nazuri Collections - Ripoti ya Mauzo",
    "Kipindi: " . $months[$selected_month] . " " . $selected_year,
    "Jumla ya Mauzo: Tsh " . number_format($total_sales),
    "Idadi ya Oda: " . $total_orders,
    "Wastani wa Oda: Tsh " . number_format($average_order),
    "",
    "Bidhaa Zinazouzika Zaidi:",
];

if (!empty($top_products)) {
    foreach ($top_products as $prod) {
        $report_summary_lines[] = "- " . $prod['name'] . " | Qty: " . $prod['total_qty'] . " | Mapato: Tsh " . number_format($prod['total_revenue']);
    }
} else {
    $report_summary_lines[] = "- Hakuna data ya mauzo kwa kipindi hiki.";
}

$report_summary_text = implode("\n", $report_summary_lines);
$report_recipient_email = app_get_admin_email($conn);
$report_mailto_link = app_mailto_link($report_recipient_email, $report_subject, $report_summary_text);
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ripoti ya Mauzo | Nazuri Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
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
                <h2 class="fw-bold">Ripoti ya Mauzo</h2>
                <div class="admin-page-actions d-flex gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <form class="d-flex flex-wrap gap-2" method="GET">
                        <select name="month" class="form-select">
                            <?php foreach($months as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($num == $selected_month) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="year" class="form-select">
                            <?php for($y = date('Y'); $y >= 2023; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-dark">Angalia</button>
                    </form>
                    <button onclick="generatePDF()" class="btn btn-success"><i class="bi bi-download"></i> PDF</button>
                    <?php if ($report_mailto_link !== ''): ?>
                        <a href="<?php echo htmlspecialchars($report_mailto_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
                            <i class="bi bi-envelope-fill"></i> Fungua Email App
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-primary" id="copyReportSummaryBtn" data-report-summary="<?php echo htmlspecialchars($report_summary_text, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-clipboard"></i> Nakili Summary
                    </button>
                </div>
            </div>

            <!-- Report Content to Print -->
            <div id="report-content" class="p-3 bg-white rounded shadow-sm">
                <div class="text-center mb-4 border-bottom pb-3">
                    <h3 class="fw-bold text-uppercase">Nazuri Collections</h3>
                    <h5 class="text-muted">Ripoti ya Mauzo - <?php echo $months[$selected_month] . ' ' . $selected_year; ?></h5>
                    <small>Imetolewa: <?php echo date('d M Y, H:i'); ?></small>
                </div>

                <div class="row mb-4 text-center">
                    <div class="col-4">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-muted text-uppercase small fw-bold">Jumla ya Mauzo</h6>
                            <h4 class="fw-bold text-success">Tsh <?php echo number_format($total_sales); ?></h4>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-muted text-uppercase small fw-bold">Idadi ya Oda</h6>
                            <h4 class="fw-bold"><?php echo $total_orders; ?></h4>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light rounded">
                            <h6 class="text-muted text-uppercase small fw-bold">Wastani wa Oda</h6>
                            <h4 class="fw-bold">Tsh <?php echo number_format($average_order); ?></h4>
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Bidhaa Zinazouzika Zaidi</h5>
                <table class="table table-bordered table-sm mb-4">
                    <thead class="table-light">
                        <tr>
                            <th>Bidhaa</th>
                            <th class="text-center">Idadi Iliyouzwa</th>
                            <th class="text-end">Mapato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_products as $prod): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prod['name']); ?></td>
                            <td class="text-center"><?php echo $prod['total_qty']; ?></td>
                            <td class="text-end">Tsh <?php echo number_format($prod['total_revenue']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($top_products)) echo "<tr><td colspan='3' class='text-center text-muted'>Hakuna data.</td></tr>"; ?>
                    </tbody>
                </table>

                <h5 class="fw-bold mt-4 mb-3 border-bottom pb-2">Oda Zilizokamilika</h5>
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Tarehe</th>
                            <th>Mteja</th>
                            <th>Njia ya Malipo</th>
                            <th class="text-end">Kiasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orders as $order): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                            <td class="text-end">Tsh <?php echo number_format($order['total_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($orders)) echo "<tr><td colspan='4' class='text-center text-muted'>Hakuna oda zilizokamilika mwezi huu.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function generatePDF() {
        const element = document.getElementById('report-content');
        const opt = {
            margin:       0.5,
            filename:     'Ripoti_Mauzo_<?php echo $months[$selected_month] . '_' . $selected_year; ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const copyButton = document.getElementById('copyReportSummaryBtn');
        if (!copyButton) {
            return;
        }

        copyButton.addEventListener('click', async () => {
            const summary = copyButton.getAttribute('data-report-summary') || '';
            try {
                await navigator.clipboard.writeText(summary);
                copyButton.innerHTML = '<i class="bi bi-check2"></i> Imenakiliwa';
            } catch (error) {
                alert('Imeshindikana kunakili summary. Nakili kwa mkono kutoka kwenye report.');
            }
        });
    });
</script>
</body>
</html>
