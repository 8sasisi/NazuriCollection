<?php
// This file provides common action buttons for the admin top bar.
// It should be included inside a flex container like <div class="d-flex gap-2">

// --- Language Switcher ---
// This logic preserves existing GET parameters when switching languages.
$query_params = $_GET;
$current_page_for_lang = basename($_SERVER['PHP_SELF']);
$logout_link = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? 'logout.php' : '../admin/logout.php';

// Build link for Swahili
$query_params['admin_lang'] = 'sw';
$sw_link = $current_page_for_lang . '?' . http_build_query($query_params);

// Build link for English
$query_params['admin_lang'] = 'en';
$en_link = $current_page_for_lang . '?' . http_build_query($query_params);
?>

<!-- Language Dropdown -->
<div class="dropdown">
    <button class="btn btn-outline-dark rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo __('change_language_title'); ?>">
        <i class="bi bi-translate"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
        <li><a class="dropdown-item <?php echo (($_SESSION['admin_lang'] ?? 'sw') == 'sw') ? 'active' : ''; ?>" href="<?php echo $sw_link; ?>"><img src="../assets/img/tz.png" class="me-2" alt="TZ Flag"> Kiswahili</a></li>
        <li><a class="dropdown-item <?php echo (($_SESSION['admin_lang'] ?? 'sw') == 'en') ? 'active' : ''; ?>" href="<?php echo $en_link; ?>"><img src="../assets/img/gb.png" class="me-2" alt="GB Flag"> English</a></li>
    </ul>
</div>

<!-- Theme Toggle Button -->
<button class="btn btn-outline-dark rounded-circle shadow-sm" id="admin-theme-toggle" title="<?php echo __('toggle_theme_title'); ?>">
    <i class="bi bi-moon-stars-fill"></i>
</button>

<!-- Logout Button -->
<a href="<?php echo $logout_link; ?>" class="btn btn-outline-danger rounded-circle shadow-sm" title="<?php echo __('logout_nav'); ?>" aria-label="<?php echo __('logout_nav'); ?>">
    <i class="bi bi-box-arrow-right"></i>
</a>
