<?php
// Handle Language Switch Request immediately to prevent lag
require_once __DIR__ . '/../config/session_bootstrap.php';
if (isset($_GET['admin_lang']) && in_array($_GET['admin_lang'], ['en', 'sw'])) {
    $_SESSION['admin_lang'] = $_GET['admin_lang'];
}

// Centralized translation function for Admin Panel
if (!function_exists('__')) {
    function __($key) {
        global $lang, $conn;
        if (!isset($lang)) {
            $selected_lang = 'sw'; // Default fallback language

            // Priority 1: Check for language set in session for admin
            if (isset($_SESSION['admin_lang']) && in_array($_SESSION['admin_lang'], ['en', 'sw'])) {
                $selected_lang = $_SESSION['admin_lang'];
            }
            // Priority 2: Try to fetch default language from DB settings
            else if (isset($conn)) {
                try {
                    $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'default_language'");
                    $stmt->execute();
                    $db_lang = $stmt->fetchColumn();
                    if ($db_lang && in_array($db_lang, ['en', 'sw'])) {
                        $selected_lang = $db_lang;
                        $_SESSION['admin_lang'] = $db_lang; // Set it in session for subsequent requests
                    }
                } catch (Exception $e) { /* Ignore */ }
            }

            $lang_file = __DIR__ . '/../languages/' . $selected_lang . '.php';
            if (file_exists($lang_file)) {
                include_once $lang_file;
            }
        }
        return isset($lang[$key]) ? $lang[$key] : $key;
    }
}

// Define 't' function as alias for '__' in admin context to prevent errors if sidebar uses it
if (!function_exists('t')) {
    function t($key) {
        return __($key);
    }
}

if (!function_exists('admin_ui_translation_map')) {
    function admin_ui_translation_map()
    {
        return [
            'Oda za Mapema (Wateja)' => 'Pre-Orders (Customers)',
            'Simamia Bidhaa (Pre-order)' => 'Manage Pre-Order Products',
            'Bidhaa Zote' => 'All Products',
            'Weka Bidhaa' => 'Add Product',
            'Sajili Admin' => 'Register Admin',
            'Wasifu Wangu' => 'My Profile',
            'Mipangilio' => 'Settings',
            'Ondoka (Logout)' => 'Logout',
            'Rudi Website' => 'Back to Website',
            'Hero Sliders' => 'Hero Sliders',
            'System Health' => 'System Health',
            'Oda (WhatsApp)' => 'Orders (WhatsApp)',
            'Fungua WhatsApp Web' => 'Open WhatsApp Web',
            'Maoni (Reviews)' => 'Reviews',
            'Hifadhi Nakala (Backup)' => 'Database Backup',
            'Pakua Nakala ya Database' => 'Download Database Backup',
            'Kumbukumbu za Matukio (Activity Logs)' => 'Activity Logs',
            'Matukio Yote' => 'All Events',
            'Idadi ya Matukio' => 'Event Count',
            'Ziara ya Mwisho' => 'Last Visit',
            'Ukurasa Uliokosewa' => 'Missing Page',
            'Maelezo ya Shambulio' => 'Attack Details',
            'Afya ya Mfumo (System Health)' => 'System Health',
            'Mfumo Uko Salama' => 'System is Healthy',
            'Kuna Mapungufu' => 'Issues Detected',
            'Tatizo la Kiufundi' => 'Technical Issue',
            'Ukaguzi wa Database' => 'Database Check',
            'Jina la Table' => 'Table Name',
            'Columns zinazokosekana' => 'Missing columns',
            'Missing Table' => 'Table Missing',
            'Missing Columns' => 'Columns Missing',
            'Maoni ya Wateja' => 'Customer Reviews',
            'Hakuna maoni bado.' => 'No reviews yet.',
            'Futa maoni haya?' => 'Delete this review?',
            'Oda za Wateja' => 'Customer Orders',
            'Tarehe ya Kuanza' => 'Start Date',
            'Tarehe ya Mwisho' => 'End Date',
            'Badili Status ya Oda' => 'Change Order Status',
            'Chagua Status Mpya:' => 'Select New Status:',
            'Maelezo ya Oda' => 'Order Details',
            'Bidhaa Zilizonunuliwa' => 'Purchased Products',
            'Jumla Kuu' => 'Grand Total',
            'Namba ya Simu' => 'Phone Number',
            'Jina la Mteja' => 'Customer Name',
            'Jina la Bidhaa' => 'Product Name',
            'Hali ya Bidhaa' => 'Product Status',
            'Maelezo' => 'Description',
            'Rangi Zinazopatikana' => 'Available Colors',
            'Saizi Zinazopatikana' => 'Available Sizes',
            'Kodi ya Kuponi (Coupon Code)' => 'Coupon Code',
            'Bei ya Punguzo (Discount Price)' => 'Discount Price',
            'Asilimia ya Punguzo (%)' => 'Discount Percentage (%)',
            "Ndiyo (Onyesha 'OFA')" => "Yes (Show 'OFFER')",
            'Hapana' => 'No',
            'Ipo (Active)' => 'Available (Active)',
            'Imeisha (Not Active)' => 'Out of Stock (Not Active)',
            'Pre-Order (Inakuja)' => 'Pre-Order (Upcoming)',
            'Hifadhi Bidhaa' => 'Save Product',
            'Sasisha Bidhaa' => 'Update Product',
            'Rudi' => 'Back',
        ];
    }
}

if (!function_exists('admin_ui_translation_map_en_sw')) {
    function admin_ui_translation_map_en_sw()
    {
        return [
            'Active' => 'Inatumika',
            'Not Active' => 'Haitumiki',
            'Pending' => 'Inasubiri',
            'Confirmed' => 'Imethibitishwa',
            'Cancelled' => 'Imeghairiwa',
            'System Health' => 'Afya ya Mfumo',
            'Logs' => 'Kumbukumbu',
            'Backup' => 'Hifadhi Nakala',
            'Reviews' => 'Maoni',
            'Settings' => 'Mipangilio',
            'Profile' => 'Wasifu',
            'Logout' => 'Ondoka',
            'Dashboard' => 'Dashibodi',
            'Orders' => 'Oda',
            'Reports' => 'Ripoti',
        ];
    }
}

if (!function_exists('admin_translate_ui_buffer')) {
    function admin_translate_ui_buffer($buffer)
    {
        $current_lang = $_SESSION['admin_lang'] ?? 'sw';
        $map_sw_en = admin_ui_translation_map();

        if ($current_lang === 'en') {
            return strtr($buffer, $map_sw_en);
        }

        $map_en_sw = array_merge(array_flip($map_sw_en), admin_ui_translation_map_en_sw());
        return strtr($buffer, $map_en_sw);
    }
}

if (!function_exists('start_admin_ui_translation_buffer')) {
    function start_admin_ui_translation_buffer()
    {
        if (defined('ADMIN_UI_TRANSLATION_BUFFER_STARTED')) {
            return;
        }

        define('ADMIN_UI_TRANSLATION_BUFFER_STARTED', true);
        if (PHP_SAPI !== 'cli') {
            ob_start('admin_translate_ui_buffer');
        }
    }
}

if (!function_exists('validate_upload_file')) {
    /**
     * Validate uploaded file securely.
     * Returns array: [bool $ok, string $message, string $detected_mime]
     */
    function validate_upload_file(array $file, array $allowed_mimes, int $max_bytes = 5242880) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return [false, 'No file uploaded or upload error.', ''];
        }

        if ($file['size'] > $max_bytes) {
            return [false, 'File too large. Max ' . ($max_bytes / 1048576) . ' MB allowed.', ''];
        }

        // Use finfo for reliable MIME detection
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        } else {
            $mime = $file['type'] ?? '';
        }

        if (!in_array($mime, $allowed_mimes, true)) {
            return [false, 'Invalid file type: ' . htmlspecialchars($mime), $mime];
        }

        // Extension check (best-effort)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext_map = [
            'image/jpeg' => ['jpg','jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'video/mp4' => ['mp4'],
            'video/webm' => ['webm'],
            'application/mp4' => ['mp4']
        ];
        if (isset($ext_map[$mime]) && !in_array($ext, $ext_map[$mime], true)) {
            // not strictly fatal but warn
            return [false, 'File extension does not match MIME type.', $mime];
        }

        return [true, 'OK', $mime];
    }
}

    if (!function_exists('ensure_product_code_column')) {
        function ensure_product_code_column(PDO $conn) {
            try {
                $stmt = $conn->query("SHOW COLUMNS FROM products LIKE 'product_code'");
                if ($stmt && $stmt->rowCount() === 0) {
                    $conn->exec("ALTER TABLE products ADD COLUMN product_code VARCHAR(100) DEFAULT NULL");
                }
            } catch (PDOException $e) {
                // Ignore if column cannot be created or if using a database that does not support ALTER TABLE this way.
            }
        }
    }

    if (!function_exists('ensure_customer_email_column')) {
        function ensure_customer_email_column(PDO $conn) {
            try {
                $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'customer_email'");
                if ($stmt && $stmt->rowCount() === 0) {
                    $conn->exec("ALTER TABLE orders ADD COLUMN customer_email VARCHAR(150) DEFAULT NULL AFTER customer_name");
                }
            } catch (PDOException $e) {
            }
        }
    }

    if (!function_exists('ensure_payer_phone_column')) {
        function ensure_payer_phone_column(PDO $conn) {
            try {
                // Ensure the payer_phone column exists on orders table (not products)
                $stmt = $conn->query("SHOW COLUMNS FROM orders LIKE 'payer_phone'");
                if ($stmt && $stmt->rowCount() === 0) {
                    $conn->exec("ALTER TABLE orders ADD COLUMN payer_phone VARCHAR(30) DEFAULT NULL");
                }
            } catch (PDOException $e) {
                // Ignore if column cannot be created (insufficient privileges or unsupported DB)
            }
        }
    }

    if (!function_exists('delete_item')) {
    /**
     * Handles generic delete operations with CSRF protection.
     *
     * @param PDO $conn Database connection
     * @param string $table Database table name
     * @param string $redirectUrl URL to redirect after deletion
     * @param string $postKey The $_POST key containing the ID (default: 'delete_id')
     * @param callable|null $callback Optional callback to run before deletion (e.g. for unlink images)
     */
    function delete_item(PDO $conn, $table, $redirectUrl, $postKey = 'delete_id', $callback = null) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$postKey])) {
            
            // Verify CSRF
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                die("Security Error: Invalid CSRF Token.");
            }

            $id = $_POST[$postKey];

            if (is_callable($callback)) {
                $callback($id);
            }

            // Simple table name sanitization
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

            try {
                $stmt = $conn->prepare("DELETE FROM `$safeTable` WHERE id = ?");
                $stmt->execute([$id]);
                
                header("Location: " . $redirectUrl);
                exit();
            } catch (PDOException $e) {
                die("Database Error: " . $e->getMessage());
            }
        }
    }
}
?>
