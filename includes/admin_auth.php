<?php
/**
 * This file handles session start and admin authentication.
 * It should be included at the very top of every protected admin page.
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/functions.php';

// Load site settings for all admin pages
$stmt_settings = $conn->query("SELECT * FROM site_settings");
$settings_rows = $stmt_settings->fetchAll(PDO::FETCH_ASSOC);
$site_settings = [];
foreach ($settings_rows as $row) {
    $site_settings[$row['setting_key']] = $row['setting_value'];
}
$shop_name = !empty($site_settings['shop_name']) ? $site_settings['shop_name'] : 'Grant Fashions';
$shop_phone = !empty($site_settings['phone']) ? $site_settings['phone'] : '0767557234';
$shop_currency = 'Tsh';
$shop_address = !empty($site_settings['address']) ? $site_settings['address'] : 'Dar es Salaam, Tanzania';
$shop_email = !empty($site_settings['email']) ? $site_settings['email'] : '';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Check if the current script is inside the 'admin' directory
    $is_in_admin_dir = basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin';
    $login_path = $is_in_admin_dir ? 'login.php' : '../admin/login.php';
    
    header("Location: " . $login_path);
    exit();
}

start_admin_ui_translation_buffer();
?>
