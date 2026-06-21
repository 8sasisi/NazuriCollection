<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
// Accept POST with theme and csrf_token. Only logged-in admins allowed to persist.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error'=>'Method Not Allowed']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
$theme = $_POST['theme'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';
if (!in_array($theme, ['light','dark'])) {
    echo json_encode(['error'=>'Invalid theme']); exit;
}
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    echo json_encode(['error'=>'Invalid CSRF']); exit;
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    echo json_encode(['error'=>'Not authenticated']); exit;
}
$admin_id = intval($_SESSION['admin_id']);
$key = 'admin_theme_' . $admin_id;
try {
    $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = :v2");
    $stmt->execute([':k'=>$key, ':v'=>$theme, ':v2'=>$theme]);
    echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
    error_log('save_theme error: ' . $e->getMessage());
    echo json_encode(['error'=>'DB error']);
}
