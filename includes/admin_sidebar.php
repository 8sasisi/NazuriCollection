<?php
// Determine path prefix based on where this file is included from
$current_script_path = $_SERVER['SCRIPT_NAME'];
$link_prefix = (strpos($current_script_path, '/admin/') !== false) ? '' : '../admin/';

$current_page = basename($_SERVER['PHP_SELF']);

$admin_nav_items = [
    ['page' => 'index.php', 'href' => 'index.php', 'icon' => 'bi-grid-fill', 'label' => __('dashboard')],
    ['page' => 'orders.php', 'href' => 'orders.php', 'icon' => 'bi-cart-check-fill', 'label' => __('orders_nav')],
    ['page' => 'preorders.php', 'href' => 'preorders.php', 'icon' => 'bi-clock-history', 'label' => __('preorders_customers_nav')],
    ['page' => 'preorders_manage.php', 'href' => 'preorders_manage.php', 'icon' => 'bi-truck', 'label' => __('manage_preorders_nav')],
    ['page' => 'reports.php', 'href' => 'reports.php', 'icon' => 'bi-file-earmark-bar-graph-fill', 'label' => __('reports_nav')],
    ['page' => 'products.php', 'href' => 'products.php', 'icon' => 'bi-bag-fill', 'label' => __('all_products_nav'), 'active_pages' => ['products.php', 'edit_product.php']],
    ['page' => 'add_product.php', 'href' => 'add_product.php', 'icon' => 'bi-plus-circle-fill', 'label' => __('add_product_nav')],
    ['page' => 'sliders.php', 'href' => 'sliders.php', 'icon' => 'bi-collection-play-fill', 'label' => __('hero_sliders_nav')],
    ['page' => 'backup.php', 'href' => 'backup.php', 'icon' => 'bi-database-down', 'label' => __('backup_nav')],
    ['page' => 'profile.php', 'href' => 'profile.php', 'icon' => 'bi-person-circle', 'label' => __('my_profile_nav')],
    ['page' => 'settings.php', 'href' => 'settings.php', 'icon' => 'bi-gear-fill', 'label' => __('settings_nav')],
];

$system_nav_items = [
    ['page' => 'register.php', 'href' => 'register.php', 'icon' => 'bi-person-plus-fill', 'label' => __('register_admin_nav')],
    ['page' => 'system_health.php', 'href' => 'system_health.php', 'icon' => 'bi-heart-pulse-fill', 'label' => __('system_health_nav')],
    ['page' => 'mail_test.php', 'href' => 'mail_test.php', 'icon' => 'bi-envelope-fill', 'label' => __('mail_test')],
    ['page' => 'logs.php', 'href' => 'logs.php', 'icon' => 'bi-shield-check', 'label' => __('logs_nav')],
];

$system_active_pages = [];
foreach ($system_nav_items as $item) {
    $active_pages = $item['active_pages'] ?? [$item['page']];
    $system_active_pages = array_merge($system_active_pages, $active_pages);
}
$system_menu_active = in_array($current_page, $system_active_pages, true);

// Count Pending Reviews if not already set by the parent page
if (!isset($pending_reviews)) {
    if (isset($conn)) {
        $stmt_count = $conn->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
        $pending_reviews = $stmt_count->fetchColumn();
    } else {
        $pending_reviews = 0;
    }
}

if (!function_exists('render_admin_navigation_items')) {
    function render_admin_navigation_items($navItems, $currentPage, $linkPrefix, $linkClass = '')
    {
        foreach ($navItems as $item) {
            $activePages = $item['active_pages'] ?? [$item['page']];
            $isActive = in_array($currentPage, $activePages, true);
            $classes = trim('nav-link ' . $linkClass . ($isActive ? ' active' : ''));
            echo '<li class="nav-item">';
            echo '<a class="' . $classes . '" href="' . $linkPrefix . $item['href'] . '">';
            echo '<i class="bi ' . $item['icon'] . ' me-2"></i> ' . $item['label'];
            echo '</a>';
            echo '</li>';
        }
    }
}

if (!defined('ADMIN_LAYOUT_STYLES_RENDERED')) {
    define('ADMIN_LAYOUT_STYLES_RENDERED', true);
    ?>
    <style>
        .admin-mobile-topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            margin: -1rem -1rem 1rem;
            background: rgba(248, 249, 250, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(26, 29, 32, 0.08);
        }
        .admin-mobile-brand {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            margin: 0;
        }
        .admin-main-content {
            min-height: 100vh;
            padding: 1rem !important;
        }
        .admin-page-header {
            gap: 1rem;
            flex-wrap: wrap;
        }
        .admin-page-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            width: 100%;
        }
        .admin-page-actions .btn.rounded-pill {
            width: 100%;
            justify-content: center;
        }
        .admin-card-scroll {
            overflow-x: auto;
        }
        .sidebar {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar-menu {
            padding-right: 0.3rem;
        }
        .system-submenu {
            margin-top: 0.35rem;
            margin-bottom: 0.4rem;
        }
        .system-submenu .nav-link {
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
            padding-left: 2.25rem;
            font-size: 0.95rem;
            border-radius: 0.65rem;
        }
        .system-toggle-icon {
            transition: transform 0.2s ease;
        }
        .nav-link[aria-expanded="true"] .system-toggle-icon {
            transform: rotate(180deg);
        }
        @media (min-width: 576px) {
            .admin-main-content {
                padding: 1.5rem !important;
            }
            .admin-page-actions {
                width: auto;
                justify-content: flex-end;
            }
            .admin-page-actions .btn.rounded-pill {
                width: auto;
            }
        }
        @media (min-width: 768px) {
            .admin-mobile-topbar {
                display: none;
            }
            .admin-main-content {
                padding: 2rem !important;
            }
        }
        [data-bs-theme="dark"] .admin-mobile-topbar {
            background: rgba(33, 37, 41, 0.92);
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }
        [data-bs-theme="dark"] .admin-mobile-topbar .btn-dark {
            background-color: #f8f9fa;
            border-color: #f8f9fa;
            color: #212529;
        }
        [data-bs-theme="dark"] .admin-page-actions .btn-outline-dark {
            color: #f8f9fa !important;
            border-color: #f8f9fa !important;
        }
        [data-bs-theme="dark"] .admin-page-actions .btn-outline-dark i {
            color: #f8f9fa;
        }
    </style>
    <?php
}
?>
<div class="admin-mobile-topbar d-md-none">
    <div>
        <p class="admin-mobile-brand">Nazuri <span class="text-warning">Collection</span></p>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></small>
    </div>
    <button class="btn btn-dark rounded-circle shadow-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar" aria-controls="adminMobileSidebar" aria-label="Open admin menu">
        <i class="bi bi-list fs-5"></i>
    </button>
</div>

<div class="col-md-3 col-lg-2 px-0 sidebar d-none d-md-block position-fixed">
    <div class="p-4 border-bottom border-secondary">
        <h4 class="fw-bold mb-0" style="font-family: 'Playfair Display', serif;">Nazuri <span class="text-warning">Collection</span></h4>
    </div>
    <div class="p-3 mt-3 sidebar-menu">
        <ul class="nav flex-column">
            <?php render_admin_navigation_items($admin_nav_items, $current_page, $link_prefix); ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?> d-flex justify-content-between align-items-center" href="<?php echo $link_prefix; ?>reviews.php">
                    <span><i class="bi bi-chat-quote-fill me-2"></i> <?php echo __('reviews_nav'); ?></span>
                    <?php if(isset($pending_reviews) && $pending_reviews > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $pending_reviews; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $system_menu_active ? 'active' : ''; ?> d-flex justify-content-between align-items-center"
                   data-bs-toggle="collapse"
                   href="#adminSystemMenuDesktop"
                   role="button"
                   aria-expanded="<?php echo $system_menu_active ? 'true' : 'false'; ?>"
                   aria-controls="adminSystemMenuDesktop">
                    <span><i class="bi bi-hdd-rack-fill me-2"></i> <?php echo __('system_menu_label'); ?></span>
                    <i class="bi bi-chevron-down small system-toggle-icon"></i>
                </a>
                <div class="collapse <?php echo $system_menu_active ? 'show' : ''; ?>" id="adminSystemMenuDesktop">
                    <ul class="nav flex-column system-submenu">
                        <?php render_admin_navigation_items($system_nav_items, $current_page, $link_prefix); ?>
                    </ul>
                </div>
            </li>
            <li class="nav-item mt-4 pt-3 border-top border-secondary"><a class="nav-link text-danger" href="<?php echo $link_prefix; ?>logout.php"><i class="bi bi-box-arrow-left me-2"></i> <?php echo __('logout_nav'); ?></a></li>
        </ul>
    </div>
</div>

<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminMobileSidebar" aria-labelledby="adminMobileSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title fw-bold" id="adminMobileSidebarLabel" style="font-family: 'Playfair Display', serif;">Nazuri <span class="text-warning">Collection</span></h5>
            <small class="text-muted"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-3">
        <ul class="nav flex-column">
            <?php render_admin_navigation_items($admin_nav_items, $current_page, $link_prefix); ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?> d-flex justify-content-between align-items-center" href="<?php echo $link_prefix; ?>reviews.php">
                    <span><i class="bi bi-chat-quote-fill me-2"></i> <?php echo __('reviews_nav'); ?></span>
                    <?php if(isset($pending_reviews) && $pending_reviews > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $pending_reviews; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $system_menu_active ? 'active' : ''; ?> d-flex justify-content-between align-items-center"
                   data-bs-toggle="collapse"
                   href="#adminSystemMenuMobile"
                   role="button"
                   aria-expanded="<?php echo $system_menu_active ? 'true' : 'false'; ?>"
                   aria-controls="adminSystemMenuMobile">
                    <span><i class="bi bi-hdd-rack-fill me-2"></i> <?php echo __('system_menu_label'); ?></span>
                    <i class="bi bi-chevron-down small system-toggle-icon"></i>
                </a>
                <div class="collapse <?php echo $system_menu_active ? 'show' : ''; ?>" id="adminSystemMenuMobile">
                    <ul class="nav flex-column system-submenu">
                        <?php render_admin_navigation_items($system_nav_items, $current_page, $link_prefix); ?>
                    </ul>
                </div>
            </li>
            <li class="nav-item mt-3 pt-3 border-top">
                <a class="nav-link text-danger" href="<?php echo $link_prefix; ?>logout.php"><i class="bi bi-box-arrow-left me-2"></i> <?php echo __('logout_nav'); ?></a>
            </li>
        </ul>
    </div>
</div>

<!-- Idle Timeout Modal & Script -->
<div class="modal fade" id="idleLogoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <span class="display-1 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></span>
                </div>
                <h4 class="fw-bold mb-2">Una muda mchache uliobaki!</h4>
                <p class="text-muted mb-1">Hujafanya shughuli yoyote kwa muda mrefu.</p>
                <p class="text-muted mb-4">Utaondolewa kiotomatiki baada ya <strong id="idleCountdown">5</strong> sekunde.</p>
                <button id="idleStayLoggedIn" class="btn btn-lg btn-dark rounded-pill px-5 fw-semibold">
                    <i class="bi bi-hand-index-thumb me-2"></i> Nipo Hapa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const IDLE_TIMEOUT = 5 * 60 * 1000; // dakika 5
    const COUNTDOWN_SECONDS = 5;

    let idleTimer = null;
    let countdownTimer = null;
    let countdownValue = COUNTDOWN_SECONDS;

    const modalEl = document.getElementById('idleLogoutModal');
    const countdownEl = document.getElementById('idleCountdown');
    const stayBtn = document.getElementById('idleStayLoggedIn');

    let modal = null;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    }

    function resetIdleTimer() {
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
        if (modal && modal._isShown) {
            modal.hide();
        }
        if (idleTimer) clearTimeout(idleTimer);
        idleTimer = setTimeout(showWarning, IDLE_TIMEOUT);
    }

    function showWarning() {
        countdownValue = COUNTDOWN_SECONDS;
        countdownEl.textContent = countdownValue;
        if (modal) modal.show();

        countdownTimer = setInterval(function() {
            countdownValue--;
            countdownEl.textContent = countdownValue;
            if (countdownValue <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                if (modal) modal.hide();
                fetch('logout.php?idle=1').then(function() {
                    window.location.href = 'login.php?idle=1';
                }).catch(function() {
                    window.location.href = 'login.php?idle=1';
                });
            }
        }, 1000);
    }

    stayBtn.addEventListener('click', resetIdleTimer);

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(ev) {
        document.addEventListener(ev, resetIdleTimer, { passive: true });
    });

    resetIdleTimer();
})();
</script>
