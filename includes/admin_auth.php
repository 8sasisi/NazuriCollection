<?php
/**
 * This file handles session start and admin authentication.
 * It should be included at the very top of every protected admin page.
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Check if the current script is inside the 'admin' directory
    $is_in_admin_dir = basename(dirname($_SERVER['SCRIPT_FILENAME'])) === 'admin';
    $login_path = $is_in_admin_dir ? 'login.php' : '../admin/login.php';
    
    header("Location: " . $login_path);
    exit();
}

start_admin_ui_translation_buffer();
?>
