<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Filter Logic
$whereClauses = [];
$params = [];
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

if ($start_date) {
    $whereClauses[] = "DATE(created_at) >= :start_date";
    $params[':start_date'] = $start_date;
}
if ($end_date) {
    $whereClauses[] = "DATE(created_at) <= :end_date";
    $params[':end_date'] = $end_date;
}

$whereSQL = "";
if (!empty($whereClauses)) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=grant_fashions_orders.csv');

    $output = fopen('php://output', 'w');
    // Add CSV Header
    fputcsv($output, ['Order ID', 'Customer Name', 'Customer Phone', 'Payer Phone', 'Total Amount', 'Payment Method', 'Order Status', 'Order Date']);

    $sql = "SELECT id, customer_name, customer_phone, total_amount, payment_method, order_status, created_at FROM orders $whereSQL ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            '#' . $row['id'],
            $row['customer_name'],
            $row['customer_phone'],
        $row['payer_phone'] ?? '',
        $row['total_amount'],
        $row['payment_method'],
        ucfirst($row['order_status']),
        date('Y-m-d H:i:s', strtotime($row['created_at']))
    ]);
    }
    fclose($output);
    exit();
}

// Handle Status Update
if (isset($_POST['update_status'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $order_id = $_POST['order_id'];
    $new_status = $_POST['order_status'];
    
    $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    header("Location: orders.php");
    exit();
}

// Handle Order Delete
if (isset($_POST['delete_order'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $order_id = (int)$_POST['order_id'];

    try {
        $conn->beginTransaction();

        // Futa items za oda kwanza ili kuepuka foreign key errors.
        $stmtDeleteItems = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmtDeleteItems->execute([$order_id]);

        // Kisha futa oda yenyewe.
        $stmtDeleteOrder = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmtDeleteOrder->execute([$order_id]);

        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
    }

    header("Location: orders.php");
    exit();
}

// Fetch Orders
$sql = "SELECT * FROM orders $whereSQL ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Order Items for each order to display in modal
$order_items = [];
if (count($orders) > 0) {
    $order_ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt_items = $conn->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders)");
    $stmt_items->execute($order_ids);
    $all_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_items as $item) {
        $order_items[$item['order_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oda za Wateja | Grant Admin</title>
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
                <h2 class="fw-bold mb-0">Oda za Wateja</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                    <a href="orders.php?export=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success rounded-pill px-4">
                        <i class="bi bi-file-earmark-excel me-2"></i> Export to CSV
                    </a>
                </div>
            </div>

            <!-- Date Filter Form -->
            <form method="GET" class="row g-3 mb-4 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-bold text-muted">Tarehe ya Kuanza</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small fw-bold text-muted">Tarehe ya Mwisho</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-dark"><i class="bi bi-filter"></i> Chuja</button>
                    <a href="orders.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Mteja</th>
                                <th>Simu</th>
                                <th>Simu (Mlipaji)</th>
                                <th>Jumla</th>
                                <th>Malipo</th>
                                <th>Tarehe</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($orders) > 0): ?>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <td class="ps-4">#<?php echo $order['id']; ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                    <td><?php echo htmlspecialchars($order['payer_phone'] ?? ''); ?></td>
                                    <td>Tsh <?php echo number_format($order['total_amount']); ?></td>
                                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = 'bg-secondary';
                                        if($order['order_status'] == 'confirmed') $status_class = 'bg-success';
                                        if($order['order_status'] == 'pending') $status_class = 'bg-warning text-dark';
                                        if($order['order_status'] == 'cancelled') $status_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?> <?php if($order['order_status'] == 'pending') echo 'bg-opacity-10'; ?> px-3 py-2 rounded-pill border">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $order['id']; ?>" title="Angalia Maelezo">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $order['id']; ?>">
                                            <i class="bi bi-pencil-square"></i> Badili
                                        </button>
                                    </td>
                                </tr>
                                
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Hakuna oda
                                        zilizopatikana.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals Loop (Placed outside table for valid HTML) -->
<?php if(count($orders) > 0): ?>
    <?php foreach($orders as $order): ?>
        <!-- Status Modal -->
        <div class="modal fade" id="statusModal<?php echo $order['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Badili Status ya Oda #<?php echo $order['id']; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <label class="form-label">Chagua Status Mpya:</label>
                            <select name="order_status" class="form-select">
                                <option value="pending" <?php echo ($order['order_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo ($order['order_status'] == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo ($order['order_status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="submit"
                                name="delete_order"
                                class="btn btn-danger me-auto"
                                onclick="return confirm('Una uhakika unataka kufuta oda hii? Hatua hii haiwezi kurejeshwa.');"
                            >
                                Futa Oda
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Funga</button>
                            <button type="submit" name="update_status" class="btn btn-primary">Hifadhi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Details Modal -->
        <div class="modal fade" id="viewModal<?php echo $order['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold">Maelezo ya Oda #<?php echo $order['id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Mteja:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p class="mb-1"><strong>Simu:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                                <p class="mb-1"><strong><?php echo t('payer_phone_label'); ?>:</strong> <?php echo htmlspecialchars($order['payer_phone'] ?? ''); ?></p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Tarehe:</strong> <?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></p>
                                <p class="mb-1"><strong>Malipo:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                            </div>
                        </div>
                        <h6 class="fw-bold border-bottom pb-2 mb-3">Bidhaa Zilizonunuliwa</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Bidhaa</th>
                                        <th>Size</th>
                                        <th>Rangi</th>
                                        <th class="text-center">Idadi</th>
                                        <th class="text-end">Bei</th>
                                        <th class="text-end">Jumla</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (isset($order_items[$order['id']])):
                                        foreach ($order_items[$order['id']] as $item): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['size']); ?></td>
                                        <td><?php echo htmlspecialchars($item['color']); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['price']); ?></td>
                                        <td class="text-end"><?php echo number_format($item['price'] * $item['quantity']); ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-end fw-bold">Jumla Kuu</td>
                                        <td class="text-end fw-bold text-primary">Tsh <?php echo number_format($order['total_amount']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Funga</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

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
