<?php
require_once __DIR__ . '/language_switcher.php';
require_once __DIR__ . '/../config/session_bootstrap.php';

// --- Activity Logging (Visits & Bots) ---
require_once __DIR__ . '/../config/db_connect.php';

$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$referrer_url = $_SERVER['HTTP_REFERER'] ?? null;
$log_type = 'visit';
$details = null;

// Bot Detection (Simple check)
if (preg_match('/bot|crawl|slurp|spider|mediapartners/i', $user_agent)) {
    $log_type = 'bot_visit';
    $details = 'Bot detected via User-Agent';
}

// Log to activity_logs table
try {
    $log_stmt = $conn->prepare("INSERT INTO activity_logs (ip_address, user_agent, request_method, request_uri, referrer_url, log_type, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $log_stmt->execute([$ip_address, $user_agent, $request_method, $request_uri, $referrer_url, $log_type, $details]);
} catch (PDOException $e) {
    // Silently fail logging to avoid breaking the site
}

// --- End Activity Logging ---

// Fetch Site Settings
$stmt_settings = $conn->query("SELECT * FROM site_settings");
$settings_rows = $stmt_settings->fetchAll(PDO::FETCH_ASSOC);
$site_settings = [];
foreach ($settings_rows as $row) {
    $site_settings[$row['setting_key']] = $row['setting_value'];
}

$shop_name = !empty($site_settings['shop_name']) ? $site_settings['shop_name'] : 'Nazuri Collections';
$shop_logo = !empty($site_settings['logo']) ? $site_settings['logo'] : '';
$shop_phone = !empty($site_settings['phone']) ? $site_settings['phone'] : '0767557234';

// Fuatilia wageni (Unique Daily Visits for Stats)
if (!isset($_SESSION['site_visited']) && $log_type == 'visit') {
    $today_visit = date("Y-m-d");
    $stmt = $conn->prepare("INSERT INTO visits (visit_date, visits_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE visits_count = visits_count + 1");
    $stmt->execute([$today_visit]);
    $_SESSION['site_visited'] = true;
}

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<?php
// Determine initial theme server-side for accessibility testing and persisted preferences
$initial_theme = null;
// Priority: simulate_theme from localhost (for visual tests)
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (($remote_ip === '127.0.0.1' || $remote_ip === '::1') && isset($_GET['simulate_theme']) && in_array($_GET['simulate_theme'], ['light','dark'])) {
    $initial_theme = $_GET['simulate_theme'];
}
// If admin logged in and a user-specific setting exists, load it
if ($initial_theme === null && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && !empty($_SESSION['admin_id'])) {
    try {
        $stmtTheme = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :k LIMIT 1");
        $key = 'admin_theme_' . intval($_SESSION['admin_id']);
        $stmtTheme->execute([':k' => $key]);
        $val = $stmtTheme->fetchColumn();
        if ($val && in_array($val, ['light','dark'])) $initial_theme = $val;
    } catch (Exception $e) { /* ignore */ }
}
// Fallback to default site setting
if ($initial_theme === null) {
    $defaultTheme = $site_settings['default_theme'] ?? null;
    if ($defaultTheme && in_array($defaultTheme, ['light','dark'])) $initial_theme = $defaultTheme;
}
// Final fallback: null => JS will decide
?>
<html lang="<?php echo $current_lang; ?>" <?php echo $initial_theme ? 'data-bs-theme="'.htmlspecialchars($initial_theme).'" class="theme-'.htmlspecialchars($initial_theme).'"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop_name); ?> | Abaya & Magauni</title>

    <?php
    // Secure defaults and social metadata (security by design)
    $meta_description = !empty($site_settings['meta_description']) ? $site_settings['meta_description'] : 'Buy elegant Abaya & Gowns at Nazuri Collections. Quality fabrics, responsive service.';
    $meta_image = (!empty($shop_logo) && file_exists(__DIR__ . '/../uploads/' . $shop_logo)) ? ('uploads/' . htmlspecialchars($shop_logo)) : 'https://via.placeholder.com/1200x630?text=' . urlencode($shop_name);
    $site_url = !empty($site_settings['site_url']) ? $site_settings['site_url'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''));
    ?>

    <meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index, follow">

    <!-- Open Graph / Social -->
    <meta property="og:title" content="<?php echo htmlspecialchars($shop_name, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($meta_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    <?php
    echo json_encode([
        "@context" => "https://schema.org",
        "@type" => "Store",
        "name" => $shop_name,
        "url" => $site_url,
        "logo" => $meta_image,
        "telephone" => $shop_phone
    ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    ?>
    </script>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        // Check local storage or system preference immediately to prevent flash
        const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
        
        // Add attribute to enable transition disabling during theme changes
        document.documentElement.setAttribute('data-bs-theme-transition', 'true');
    </script>
    <style>
        /* Custom styles for mobile layout requirement */
        .dropdown-toggle.no-caret::after { display: none; }
        .navbar-brand { font-size: 1.1rem; }
        .customer-action-icons .btn-link { color: inherit !important; }
        [data-bs-theme="dark"] .customer-action-icons i { color: #f8f9fa; }
        @media (max-width: 991.98px) {
            .navbar-brand { text-align: center; margin-right: 0; }
            .customer-action-icons .bi { font-size: 1.25rem; }
        }
    </style>
</head>
<body>

<div id="theme-status" class="visually-hidden" aria-live="polite" aria-atomic="true"></div>

<script>
    // Expose whether current user is admin and csrf token for save API
    window.isAdmin = <?php echo (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ? 'true' : 'false'; ?>;
    window.csrfToken = <?php echo isset($_SESSION['csrf_token']) ? json_encode($_SESSION['csrf_token']) : '""'; ?>;
</script>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
  <div class="container d-flex align-items-center">
    <!-- 1. Mobile Menu Toggle (Visible only below LG) -->
    <button class="navbar-toggler border-0 p-0 me-2 shadow-none d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
      <span class="navbar-toggler-icon" style="width: 1.25rem; height: 1.25rem;"></span>
    </button>

    <!-- 2. Brand Name (Centered on mobile) -->
    <a class="navbar-brand d-flex align-items-center m-0 flex-grow-1 flex-lg-grow-0 text-center text-lg-start" href="index.php">
        <?php if($shop_logo && file_exists(__DIR__ . '/../uploads/' . $shop_logo)): ?>
            <img src="uploads/<?php echo htmlspecialchars($shop_logo); ?>" alt="Logo" height="30" class="me-2 rounded d-none d-md-block">
        <?php endif; ?>
        <span class="fw-bold brand-text-responsive" style="font-family: 'Playfair Display', serif;"><?php echo htmlspecialchars($shop_name); ?></span>
    </a>

    <!-- 3. Desktop Navigation Links (Hidden on Mobile) -->
    <div class="collapse navbar-collapse d-none d-lg-flex ms-lg-4" id="desktopNav">
      <ul class="navbar-nav gap-lg-4">
        <li class="nav-item"><a class="nav-link" href="index.php"><?php echo t('home'); ?></a></li>
        
        <!-- Shop Dropdown for Desktop -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="shopDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?php echo t('shop'); ?>
          </a>
          <ul class="dropdown-menu border-0 shadow-sm mt-2" aria-labelledby="shopDropdown">
            <li><a class="dropdown-item py-2" href="shop.php"><?php echo t('all_products'); ?></a></li>
            <li><hr class="dropdown-divider opacity-25"></li>
            <li><a class="dropdown-item py-2" href="shop.php?category=abaya"><?php echo t('abaya'); ?></a></li>
            <li><a class="dropdown-item py-2" href="shop.php?category=gown"><?php echo t('gowns'); ?></a></li>
            <li><a class="dropdown-item py-2" href="shop.php?category=guberi"><?php echo t('guberi'); ?></a></li>
            <li><a class="dropdown-item py-2" href="shop.php?category=two_pieces"><?php echo t('two_pieces'); ?></a></li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link fw-bold text-gold" href="index.php#preorder"><?php echo t('pre_order'); ?></a></li>
        <li class="nav-item"><a class="nav-link" href="about.php"><?php echo t('about'); ?></a></li>
      </ul>
    </div>

    <!-- 4. Quick Actions (Right - Always visible on all devices) -->
    <div class="customer-action-icons d-flex align-items-center gap-2 gap-md-3 ms-auto order-lg-last">
        <button class="btn btn-link text-dark p-1 border-0 shadow-none" id="theme-toggle" title="Badili Muonekano">
            <i class="bi bi-moon-stars-fill"></i>
        </button>
        <a href="cart.php" class="btn btn-link text-dark p-1 border-0 shadow-none position-relative">
            <i class="bi bi-bag"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.55rem;"><?php echo $cart_count; ?></span>
        </a>
        <div class="dropdown">
            <button class="btn btn-link text-dark p-1 border-0 shadow-none dropdown-toggle no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-globe"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><a class="dropdown-item" href="?lang=en">English</a></li>
                <li><a class="dropdown-item" href="?lang=sw">Kiswahili</a></li>
            </ul>
        </div>
    </div>

    <!-- 5. The Modern Mobile Sidebar (Offcanvas - Hidden on Desktop) -->
    <div class="offcanvas offcanvas-start border-0 d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
      <div class="offcanvas-header border-bottom py-3">
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        <h5 class="offcanvas-title fw-bold mx-auto ps-0 text-uppercase tracking-wider" id="mobileSidebarLabel" style="font-family: 'Playfair Display', serif;">
            <?php echo htmlspecialchars($shop_name); ?>
        </h5>
      </div>
      
      <div class="offcanvas-body d-flex flex-column p-0">
        <ul class="navbar-nav sidebar-nav">
          <li class="nav-item">
            <a class="nav-link px-4 py-3" href="index.php"><i class="bi bi-house-door me-3"></i> <?php echo t('home'); ?></a>
          </li>
          
          <!-- Shop with Sub-categories (Collapsible for Mobile) -->
          <li class="nav-item">
            <a class="nav-link px-4 py-3 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#shopMobileCollapse" role="button" aria-expanded="false" aria-controls="shopMobileCollapse">
                <span><i class="bi bi-bag me-3"></i> <?php echo t('shop'); ?></span>
                <i class="bi bi-chevron-down small transition-icon"></i>
            </a>
            <div class="collapse" id="shopMobileCollapse">
                <ul class="list-unstyled ps-5 pb-2 sidebar-sub-nav">
                    <li><a href="shop.php" class="nav-link py-2"><?php echo t('all_products'); ?></a></li>
                    <li><a href="shop.php?category=abaya" class="nav-link py-2"><?php echo t('abaya'); ?></a></li>
                    <li><a href="shop.php?category=gown" class="nav-link py-2"><?php echo t('gowns'); ?></a></li>
                    <li><a href="shop.php?category=two_pieces" class="nav-link py-2"><?php echo t('two_pieces'); ?></a></li>
                    <li><a href="shop.php?category=guberi" class="nav-link py-2"><?php echo t('guberi'); ?></a></li>
                </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link px-4 py-3 fw-bold text-gold" href="index.php#preorder"><i class="bi bi-star me-3"></i> <?php echo t('pre_order'); ?></a>
          </li>
          <li class="nav-item">
            <a class="nav-link px-4 py-3" href="about.php"><i class="bi bi-info-circle me-3"></i> <?php echo t('about'); ?></a>
          </li>

          <!-- Language Section at Bottom -->
          <li class="mt-auto border-top pt-3 pb-4">
            <div class="px-4 mb-2 small text-muted text-uppercase fw-bold tracking-wider">
                <i class="bi bi-globe me-2"></i> <?php echo t('language_label'); ?>
            </div>
            <div class="d-flex px-4 gap-3">
                <a href="?lang=en" class="btn btn-sm <?php echo $current_lang == 'en' ? 'btn-dark' : 'btn-outline-dark'; ?> rounded-pill px-3">English</a>
                <a href="?lang=sw" class="btn btn-sm <?php echo $current_lang == 'sw' ? 'btn-dark' : 'btn-outline-dark'; ?> rounded-pill px-3">Kiswahili</a>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div> <!-- End Container -->
</nav>
<div style="margin-top: 80px;"></div> <!-- Spacer for fixed navbar -->

<script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('theme-toggle');
            const icon = themeToggle.querySelector('i');
            const html = document.documentElement;
            const themeStatus = document.getElementById('theme-status');
            const navbar = document.querySelector('.navbar');

            // Normalize initial state from data attribute or localStorage
            const saved = localStorage.getItem('theme');
            if (saved) {
                html.setAttribute('data-bs-theme', saved);
                html.classList.toggle('theme-dark', saved === 'dark');
                html.classList.toggle('theme-light', saved === 'light');
            }

            // Set initial icon and navbar classes
            if (html.getAttribute('data-bs-theme') === 'dark' || html.classList.contains('theme-dark')) {
                try { icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill'); } catch(e){}
                if (navbar) navbar.classList.add('navbar-dark', 'bg-dark');
                if (navbar) navbar.classList.remove('navbar-light', 'bg-white');
                document.querySelectorAll('.customer-action-icons .text-dark').forEach(el => el.classList.replace('text-dark', 'text-light'));
            }

            themeToggle.addEventListener('click', () => {
                // Disable transitions during theme change
                html.setAttribute('data-bs-theme-transition', 'true');
                
                const currentTheme = html.getAttribute('data-bs-theme') || (html.classList.contains('theme-dark') ? 'dark' : 'light');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                const actionIcons = document.querySelectorAll('.customer-action-icons .btn-link, .customer-action-icons a');
                
                html.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                // Maintain class-based toggle for older CSS selectors
                html.classList.toggle('theme-dark', newTheme === 'dark');
                html.classList.toggle('theme-light', newTheme === 'light');

                // Toggle Icon and navbar
                if (newTheme === 'dark') {
                    try { icon.classList.replace('bi-moon-stars-fill', 'bi-sun-fill'); } catch(e){}
                    if (navbar) { navbar.classList.add('navbar-dark', 'bg-dark'); navbar.classList.remove('navbar-light', 'bg-white'); }
                    actionIcons.forEach(el => el.classList.replace('text-dark', 'text-light'));
                    if (themeStatus) themeStatus.textContent = 'Dark mode activated';
                } else {
                    try { icon.classList.replace('bi-sun-fill', 'bi-moon-stars-fill'); } catch(e){}
                    if (navbar) { navbar.classList.remove('navbar-dark', 'bg-dark'); navbar.classList.add('navbar-light', 'bg-white'); }
                    actionIcons.forEach(el => el.classList.replace('text-light', 'text-dark'));
                    if (themeStatus) themeStatus.textContent = 'Light mode activated';
                }

                // Persist preference server-side for logged-in admins
                try {
                    if (window.isAdmin) {
                        fetch('includes/save_theme.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'theme=' + encodeURIComponent(newTheme) + '&csrf_token=' + encodeURIComponent(window.csrfToken || '')
                        }).then(r => r.json()).then(j => {
                            if (!j.ok) console.warn('Theme save:', j);
                        }).catch(e => console.warn('Theme save failed', e));
                    }
                } catch(e){}
                
                // Re-enable transitions after a short delay and clear announcement
                setTimeout(() => {
                    html.removeAttribute('data-bs-theme-transition');
                    if (themeStatus) themeStatus.textContent = '';
                }, 300);
            });
        });
</script>
