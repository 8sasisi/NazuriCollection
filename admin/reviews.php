<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get filter status
$filter_status = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved']) ? $_GET['status'] : '';

// Build redirect URL with filter
$redirect_url = 'reviews.php';
if ($filter_status) {
    $redirect_url .= '?status=' . urlencode($filter_status);
}

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['selected_ids'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $action = $_POST['bulk_action'];
    $ids = $_POST['selected_ids'];

    if (is_array($ids) && !empty($ids)) {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids); // Remove zeros
        
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE reviews SET status = 'approved' WHERE id IN ($placeholders)");
                $stmt->execute($ids);
            } elseif ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM reviews WHERE id IN ($placeholders)");
                $stmt->execute($ids);
            }
        }
    }
    header("Location: " . $redirect_url);
    exit();
}

// Handle Approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }
    $id = $_POST['approve_id'];
    $stmt = $conn->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . $redirect_url);
    exit();
}

// Handle Delete
delete_item($conn, 'reviews', $redirect_url);

// Pagination Settings
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$where_clauses = [];
$params = [];

if ($filter_status) {
    $where_clauses[] = "r.status = :status";
    $params[':status'] = $filter_status;
}

// 1. Count Total Reviews
$count_sql = "SELECT COUNT(*) FROM reviews r JOIN products p ON r.product_id = p.id";
if (!empty($where_clauses)) {
    $count_sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$stmt_count_total = $conn->prepare($count_sql);
$stmt_count_total->execute($params);
$total_rows = $stmt_count_total->fetchColumn();
$total_pages = ceil($total_rows / $limit);

if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
$offset = ($page - 1) * $limit;

// 2. Fetch Reviews with Limit/Offset
$sql = "
    SELECT r.*, p.name as product_name, p.image as product_image 
    FROM reviews r 
    JOIN products p ON r.product_id = p.id
";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count Pending Reviews for Sidebar Badge
$stmt_count = $conn->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
$pending_reviews = $stmt_count->fetchColumn();
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maoni ya Wateja | Nazuri Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/icons/bootstrap-icons.css">
    <link href="../assets/fonts/fonts.css" rel="stylesheet">
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
                <h2 class="fw-bold">Maoni ya Wateja</h2>
                <div class="admin-page-actions d-flex align-items-center gap-2">
                    <?php include __DIR__ . '/../includes/admin_top_actions.php'; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <form method="GET" action="reviews.php" class="d-flex gap-2 align-items-center">
                    <label for="statusFilter" class="form-label mb-0 small text-muted">Filter by:</label>
                    <select name="status" id="statusFilter" class="form-select form-select-sm w-auto shadow-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php if ($filter_status === 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="approved" <?php if ($filter_status === 'approved') echo 'selected'; ?>>Approved</option>
                    </select>
                </form>
                <div class="d-flex gap-2 align-items-center">
                    <select id="bulkActionSelect" class="form-select form-select-sm w-auto shadow-sm">
                        <option value="">Bulk Actions</option>
                        <option value="approve">Approve Selected</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-dark shadow-sm" onclick="submitBulkAction()">Apply</button>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>Bidhaa</th>
                                <th>Mteja</th>
                                <th>Rating</th>
                                <th>Maoni</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Matendo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($reviews) > 0): ?>
                                <?php foreach($reviews as $review): ?>
                                <tr>
                                    <td class="ps-4"><input type="checkbox" class="form-check-input row-checkbox" value="<?php echo $review['id']; ?>"></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../uploads/<?php echo htmlspecialchars($review['product_image']); ?>" class="rounded" width="40" height="50" style="object-fit: cover;">
                                            <span class="ms-2 small fw-bold"><?php echo htmlspecialchars($review['product_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($review['customer_name']); ?></td>
                                    <td class="text-warning">
                                        <?php for($i=1; $i<=5; $i++) echo ($i <= $review['rating']) ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>'; ?>
                                    </td>
                                    <td class="small text-muted" style="max-width: 300px;"><?php echo htmlspecialchars($review['comment']); ?></td>
                                    <td>
                                        <?php if($review['status'] == 'approved'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if($review['status'] == 'pending'): ?>
                                            <form action="reviews.php" method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="approve_id" value="<?php echo $review['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success me-1" title="Kubali"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form action="reviews.php" method="POST" class="d-inline" onsubmit="return confirm('Futa maoni haya?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="delete_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Futa">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Hakuna maoni yanayolingana na kichujio hiki.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white py-3 border-top-0">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Previous -->
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                            </li>
                            
                            <!-- Numbers -->
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next -->
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<form id="bulkActionForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="bulk_action" id="bulkActionInput">
</form>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('selectAll').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.row-checkbox');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });

    function submitBulkAction() {
        var action = document.getElementById('bulkActionSelect').value;
        if (!action) {
            alert('Tafadhali chagua kitendo (action).');
            return;
        }
        
        var selected = [];
        document.querySelectorAll('.row-checkbox:checked').forEach(function(cb) {
            selected.push(cb.value);
        });
        
        if (selected.length === 0) {
            alert('Tafadhali chagua angalau maoni moja.');
            return;
        }
        
        if (!confirm('Una uhakika unataka kutekeleza kitendo hiki kwa maoni ' + selected.length + '?')) {
            return;
        }
        
        var form = document.getElementById('bulkActionForm');
        document.getElementById('bulkActionInput').value = action;
        
        // Clear previous hidden inputs if any
        var oldInputs = form.querySelectorAll('input[name="selected_ids[]"]');
        oldInputs.forEach(el => el.remove());
        
        // Add new inputs
        selected.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        form.submit();
    }
</script>
</body>
</html>
