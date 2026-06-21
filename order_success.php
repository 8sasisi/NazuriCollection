<?php
require_once 'config/db_connect.php';
require_once 'config/encryption.php';
include 'includes/header.php';

if (!isset($_GET['id']) || !preg_match('/^[a-f0-9]{32}$/', $_GET['id'])) {
    echo "<script>window.location.href='shop.php';</script>";
    exit;
}

$public_order_id = $_GET['id'];

// --------------------------------------------------------------------------
// SECURITY FIX: Prevent IDOR (Insecure Direct Object Reference)
// Only allow access if this is the order the user just placed, or if user is admin.
// --------------------------------------------------------------------------
$session_order_id = $_SESSION['last_order_id'] ?? null;
$is_admin = !empty($_SESSION['admin_logged_in']);

if (!$is_admin && $session_order_id != $public_order_id) {
    // Redirect unauthorized attempts to the shop main page
    echo "<script>window.location.href='shop.php';</script>";
    exit;
}

// Vuta taarifa za oda
$stmt = $conn->prepare("SELECT * FROM orders WHERE public_id = ?");
$stmt->execute([$public_order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if ($order) {
    decrypt_order_pii($order);
}

if (!$order) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Samahani, oda hii haikupatikana.</div></div>";
    include 'includes/footer.php';
    exit;
}

// Vuta bidhaa za oda
$stmt_items = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt_items->execute([$order['id']]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xxl-7">
            <?php if (!empty($_SESSION['order_notice'])): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars((string)$_SESSION['order_notice']); unset($_SESSION['order_notice']); ?></div>
            <?php endif; ?>
            <div class="text-center mb-5">
                <div class="mb-3 text-success">
                    <i class="bi bi-check-circle-fill display-1 animated-checkmark"></i>
                </div>
                <h1 class="fw-bold" style="font-family: 'Playfair Display', serif;"><?php echo t('thank_you_for_your_order'); ?></h1>
                <p class="lead text-muted"><?php echo t('order_received_message'); ?></p>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" id="receipt">
                <div class="card-header bg-dark text-white p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                             
                            <h5 class="mb-0 fw-bold">NAZURI COLLECTIONS</h5>
                            <small><?php echo t('order_receipt'); ?></small>
                        </div>                        <div class="text-end">
                            <h5 class="mb-0">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h5>
                            <small><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Customer Info -->
                    <div class="row mb-4">
                        <div class="col-sm-6">
                            <h6 class="fw-bold text-uppercase small text-muted"><?php echo t('customer_details'); ?></h6>
                            <p class="mb-0 fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p class="mb-0"><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        </div>
                        <div class="col-sm-6 text-sm-end">
                            <p class="mt-2 mb-0 small text-muted"><?php echo t('status_label'); ?>: <span class="fw-bold text-uppercase"><?php echo htmlspecialchars($order['order_status']); ?></span></p>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0 compact-receipt-table">
                            <thead class="border-bottom">
                                <tr class="text-uppercase text-muted" style="font-size:0.7rem;letter-spacing:0.5px;">
                                    <th><?php echo t('product'); ?></th>
                                    <th class="text-center"><?php echo t('quantity_label'); ?></th>
                                    <th class="text-end"><?php echo t('price_label'); ?></th>
                                    <th class="text-end"><?php echo t('total_label'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td>
                                        <span class="d-block fw-medium" style="font-size:0.8rem;"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        <span class="text-muted" style="font-size:0.7rem;"><?php echo t('size_label'); ?>: <?php echo $item['size']; ?>, <?php echo t('color_label'); ?>: <?php echo $item['color']; ?></span>
                                    </td>
                                    <td class="text-center" style="font-size:0.8rem;"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end" style="font-size:0.8rem;">Tsh <?php echo number_format($item['price']); ?></td>
                                    <td class="text-end" style="font-size:0.8rem;">Tsh <?php echo number_format($item['price'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="border-top">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold pt-2" style="font-size:0.8rem;"><?php echo t('grand_total'); ?></td>
                                    <td class="text-end fw-bold pt-2 text-primary" style="font-size:1rem;">Tsh <?php echo number_format($order['total_amount']); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light p-4 text-center">
                    <p class="small text-muted mb-0"><?php echo t('thank_you_message'); ?></p>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-3 mt-4 no-print">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-4">
                    <i class="bi bi-printer me-2"></i> <?php echo t('print_receipt'); ?>
                </button>
                <a href="shop.php" class="btn btn-dark rounded-pill px-4">
                    <i class="bi bi-bag me-2"></i> <?php echo t('continue_shopping'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.animated-checkmark {
    animation: checkPop 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55) forwards;
    opacity: 0;
    transform: scale(0);
}
@keyframes checkPop {
    0% { opacity: 0; transform: scale(0); }
    60% { opacity: 1; transform: scale(1.15); }
    100% { opacity: 1; transform: scale(1); }
}
.compact-receipt-table td, .compact-receipt-table th {
    padding: 0.35rem 0.25rem;
}
@media print {
    .no-print, header, footer, nav {
        display: none !important;
    }
    body {
        background-color: white !important;
    }
    #receipt {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
