<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/encryption.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$order_id = (int)($_POST['order_id'] ?? 0);
$new_status = $_POST['status'] ?? '';
$allowed = ['Pending', 'Processing', 'Completed', 'Cancelled'];

if (!in_array($new_status, $allowed, true)) {
    http_response_code(400);
    exit('Invalid status');
}

// Update order status
$stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
$stmt->execute([$new_status, $order_id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    exit('Order not found');
}

// Send notification to customer
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if ($order) {
    decrypt_order_pii($order);
}

if ($order && !empty($order['customer_email'])) {
    $orderData = [
        'id' => $order['id'],
        'customer_name' => $order['customer_name'],
        'customer_email' => $order['customer_email'],
        'total_amount' => $order['total_amount'],
    ];
    sendOrderStatusUpdateCustomer($orderData, $new_status);
}

app_log_event($conn, 'order_status_updated', "Order #$order_id status changed to $new_status");

echo json_encode(['success' => true, 'message' => 'Status updated to ' . $new_status]);
