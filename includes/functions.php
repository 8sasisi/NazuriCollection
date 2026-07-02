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
            // Additional admin UI translations
            'Mipangilio ya Tovuti' => 'Site Settings',
            'Taarifa za Duka' => 'Shop Information',
            'Jina la Duka' => 'Shop Name',
            'Anwani / Mahali' => 'Address / Location',
            'Lugha ya Admin (Default Language)' => 'Admin Language (Default Language)',
            'Logo ya Duka' => 'Shop Logo',
            'Logo ya sasa:' => 'Current logo:',
            'Mawasiliano' => 'Contact Information',
            'Namba ya Simu' => 'Phone Number',
            'Barua Pepe (Email)' => 'Email Address',
            'Instagram Link' => 'Instagram Link',
            'Facebook Link' => 'Facebook Link',
            'Hifadhi Mabadiliko' => 'Save Changes',
            'Oda za Wateja' => 'Customer Orders',
            'Tarehe ya Kuanza' => 'Start Date',
            'Tarehe ya Mwisho' => 'End Date',
            'Chuja' => 'Filter',
            'Export to CSV' => 'Export to CSV',
            'Jumla Kuu' => 'Grand Total',
            'Mteja' => 'Customer',
            'Simu' => 'Phone',
            'Simu (Mlipaji)' => 'Phone (Payer)',
            'Jumla' => 'Total',
            'Malipo' => 'Payment',
            'Tarehe' => 'Date',
            'Status' => 'Status',
            'Matendo' => 'Actions',
            'Angalia Maelezo' => 'View Details',
            'Badili' => 'Change',
            'Hakuna oda zilizopatikana.' => 'No orders found.',
            'Hakuna maoni yanayolingana na kichujio hiki.' => 'No reviews match this filter.',
            'Bidhaa' => 'Product',
            'Size' => 'Size',
            'Rangi' => 'Color',
            'Idadi' => 'Quantity',
            'Bei' => 'Price',
            'Jina la Bidhaa' => 'Product Name',
            'Kundi' => 'Category',
            'Hali ya Bidhaa' => 'Product Status',
            'Inakuja' => 'Coming Soon',
            'Mzigo Umefika (Weka Active)' => 'Mark as Arrived (Set Active)',
            'Umefika' => 'Arrived',
            'Hakuna bidhaa za Pre-Order kwa sasa.' => 'No Pre-Order products available.',
            'Oda za Mapema (Pre-Orders)' => 'Pre-Orders',
            'Bidhaa' => 'Product',
            'Jina' => 'Name',
            'Matendo' => 'Actions',
            'Hariri' => 'Edit',
            'Zima Bidhaa' => 'Deactivate Product',
            'Washa Bidhaa' => 'Activate Product',
            'Futa' => 'Delete',
            'Thibitisha Kufuta' => 'Confirm Delete',
            'Je, una uhakika unataka kufuta bidhaa hii? Hatua hii haiwezi kurudishwa.' => 'Are you sure you want to delete this product? This action cannot be undone.',
            'Ghairi' => 'Cancel',
            'Futa Bidhaa' => 'Delete Product',
            'Orodha ya Bidhaa' => 'Products List',
            'Ongeza Mpya' => 'Add New',
            'Picha' => 'Image',
            'Weka Bidhaa ya Pre-Order (Upcoming)' => 'Add Pre-Order Product (Upcoming)',
            'Ongeza Bidhaa Mpya' => 'Add New Product',
            'Jina la Bidhaa' => 'Product Name',
            'Bei (Tsh)' => 'Price (Tsh)',
            'Kundi (Category)' => 'Category',
            'Hali ya Bidhaa' => 'Product Status',
            'Onyesha Badge ya Punguzo?' => 'Show Discount Badge?',
            'Ndiyo (Onyesha \'OFA\')' => 'Yes (Show \'OFFER\')',
            'Asilimia ya Punguzo (%)' => 'Discount Percentage (%)',
            'Mwisho wa Ofa (Tarehe na Muda)' => 'Offer Expiry (Date and Time)',
            'Bei ya Punguzo (Discount Price)' => 'Discount Price',
            'Kodi ya Bidhaa (Product Code)' => 'Product Code',
            'Kodi ya Kuponi (Coupon Code)' => 'Coupon Code',
            'Maelezo' => 'Description',
            'Saizi Zinazopatikana' => 'Available Sizes',
            'Rangi Zinazopatikana' => 'Available Colors',
            'Keywords / Tags (Tenganisha kwa koma)' => 'Keywords / Tags (Separate with commas)',
            'Picha ya Bidhaa' => 'Product Image',
            'Picha za Nyongeza (Gallery)' => 'Additional Images (Gallery)',
            'Unaweza kuchagua picha zaidi ya moja.' => 'You can select more than one image.',
            'Hifadhi Bidhaa' => 'Save Product',
            'Hariri Bidhaa' => 'Edit Product',
            'Picha za Gallery Zilizopo' => 'Existing Gallery Images',
            'Hakuna picha za nyongeza.' => 'No additional images.',
            'Sasisha Bidhaa' => 'Update Product',
            'Rudi' => 'Back',
            'Admin Login | Nazuri Collections' => 'Admin Login | Nazuri Collections',
            'Ingia kwenye paneli ya usimamizi.' => 'Login to the admin panel.',
            'Namba ya Simu / Barua Pepe' => 'Phone / Email',
            'Mfano: 0712345678 au admin@example.com' => 'Example: 0712345678 or admin@example.com',
            'Nenosiri' => 'Password',
            'Onyesha nenosiri' => 'Show password',
            'Ficha nenosiri' => 'Hide password',
            'Ingia' => 'Login',
            'Umesahau nenosiri?' => 'Forgot password?',
            'Taarifa za kuingia si sahihi.' => 'Invalid login credentials.',
            'Tafadhali jaza taarifa zako za kuingia.' => 'Please fill in your login information.',
            'Weka Nenosiri Jipya' => 'Set New Password',
            'Jaza barua pepe yako ili kupata link ya kuweka nenosiri jipya.' => 'Enter your email to get a password reset link.',
            'Barua Pepe (Email)' => 'Email',
            'Tuma Link' => 'Send Link',
            'Rudi kwenye Login' => 'Back to Login',
            'Barua pepe ya kuweka upya nenosiri imetumwa.' => 'Password reset email has been sent.',
            'Tafadhali ingiza anwani sahihi ya barua pepe.' => 'Please enter a valid email address.',
            'Token haipo au si sahihi.' => 'Token is missing or invalid.',
            'Token si sahihi. Tafadhali anza upya mchakato.' => 'Invalid token. Please restart the process.',
            'Muda wa kutumia link hii umekwisha. Tafadhali anza upya.' => 'This link has expired. Please restart the process.',
            'Tafadhali jaza sehemu zote.' => 'Please fill all fields.',
            'Nenosiri linapaswa kuwa na angalau herufi 6.' => 'Password must be at least 6 characters.',
            'Manenosiri hayafanani.' => 'Passwords do not match.',
            'Nenosiri Limebadilishwa' => 'Password Changed',
            'Nenosiri lako limewekwa upya kikamilifu. Sasa unaweza kuingia kwenye akaunti yako.' => 'Your password has been reset successfully. You can now log in to your account.',
            'Ingia Sasa' => 'Login Now',
            'Nenosiri Jipya' => 'New Password',
            'Thibitisha Nenosiri Jipya' => 'Confirm New Password',
            'Weka Nenosiri Jipya' => 'Set New Password',
            'Wasifu Wangu (Profile)' => 'My Profile',
            'Taarifa Binafsi' => 'Personal Information',
            'Jina la Mtumiaji (Username)' => 'Username',
            'Badilisha Nenosiri' => 'Change Password',
            'Acha wazi kama hutaki kubadilisha nenosiri.' => 'Leave blank if you do not want to change the password.',
            'Nenosiri Jipya' => 'New Password',
            'Kima cha chini herufi 6' => 'Minimum 6 characters',
            'Thibitisha Nenosiri' => 'Confirm Password',
            'Rudia nenosiri jipya' => 'Repeat new password',
            'Wasifu umesasishwa kikamilifu.' => 'Profile updated successfully.',
            'Sajili Admin Mpya' => 'Register New Admin',
            'Ukopo Umefikiwa!' => 'Limit Reached!',
            'Tayari umefikisha idadi ya juu ya wasimamizi (admins) ambayo ni 3. Huwezi kuongeza msimamizi mwingine.' => 'You have reached the maximum number of admins (3). You cannot add another admin.',
            'Ili kuongeza msimamizi mpya, tafadhali futa mmoja wa wasimamizi waliopo kwenye orodha hapa chini.' => 'To add a new admin, please delete an existing admin from the list below.',
            'Jina la Msimamizi (Username)' => 'Admin Username',
            'Nenosiri (Password)' => 'Password',
            'Nenosiri liwe na angalau herufi 6.' => 'Password must be at least 6 characters.',
            'Role (Cheo)' => 'Role',
            'Editor (Anaweza kuhariri bidhaa)' => 'Editor (Can edit products)',
            'Super Admin (Anaweza kufuta admins)' => 'Super Admin (Can delete admins)',
            'Sajili Msimamizi' => 'Register Admin',
            'Super Admin:' => 'Super Admin:',
            'Ana uwezo kamili.' => 'Full access.',
            'Editor:' => 'Editor:',
            'Hawezi kufuta au kubadili admins wengine.' => 'Cannot delete or modify other admins.',
            'Orodha ya Wasimamizi' => 'Admin List',
            'Username' => 'Username',
            'Wewe' => 'You',
            'Simu' => 'Phone',
            'Pandisha Cheo' => 'Promote',
            'Shusha Cheo' => 'Demote',
            'Futa' => 'Delete',
            'Una uhakika unataka kumfuta admin huyu?' => 'Are you sure you want to delete this admin?',
            'Locked' => 'Locked',
            'Sajili Admin | Nazuri Admin' => 'Register Admin | Nazuri Admin',
            'Bidhaa Zote | Nazuri Admin' => 'All Products | Nazuri Admin',
            'Ripoti ya Mauzo | Nazuri Admin' => 'Sales Report | Nazuri Admin',
            'Manage Pre-Orders | Nazuri Admin' => 'Manage Pre-Orders | Nazuri Admin',
            'Pre-Orders | Nazuri Admin' => 'Pre-Orders | Nazuri Admin',
            'Maoni ya Wateja | Nazuri Admin' => 'Customer Reviews | Nazuri Admin',
            'Simamia Sliders | Nazuri Admin' => 'Manage Sliders | Nazuri Admin',
            'Afya ya Mfumo | Nazuri Admin' => 'System Health | Nazuri Admin',
            'Activity Logs | Nazuri Admin' => 'Activity Logs | Nazuri Admin',
            'Database Backup | Nazuri Admin' => 'Database Backup | Nazuri Admin',
            'Ripoti ya Mauzo' => 'Sales Report',
            'Jumla ya Mauzo' => 'Total Sales',
            'Idadi ya Oda' => 'Number of Orders',
            'Wastani wa Oda' => 'Average Order',
            'Bidhaa Zinazouzika Zaidi' => 'Top Selling Products',
            'Idadi Iliyouzwa' => 'Quantity Sold',
            'Mapato' => 'Revenue',
            'Oda Zilizokamilika' => 'Completed Orders',
            'Njia ya Malipo' => 'Payment Method',
            'Kiasi' => 'Amount',
            'Hakuna data.' => 'No data.',
            'Hakuna oda zilizokamilika mwezi huu.' => 'No completed orders this month.',
            'Hakuna bidhaa zilizoongezwa bado.' => 'No products have been added yet.',
            'Hakuna data ya mauzo bado.' => 'No sales data yet.',
            'Tafadhali chagua kitendo (action).' => 'Please select an action.',
            'Tafadhali chagua angalau maoni moja.' => 'Please select at least one review.',
            'Una uhakika unataka kutekeleza kitendo hiki kwa maoni' => 'Are you sure you want to apply this action to',
            'Hakuna pre-orders zilizopatikana.' => 'No pre-orders found.',
            'Hakuna sliders zilizoongezwa bado.' => 'No sliders added yet.',
            'Inaisha baada ya:' => 'Expires in:',
            'Tafadhali chagua njia ya malipo kwanza.' => 'Please select a payment method first.',
            'Namba ya simu si sahihi' => 'Invalid phone number',
            // Missing title tags
            'Umesahau Nenosiri | Nazuri Admin' => 'Forgot Password | Nazuri Admin',
            'Weka Nenosiri Jipya | Nazuri Admin' => 'Set New Password | Nazuri Admin',
            'Mipangilio | Nazuri Admin' => 'Settings | Nazuri Admin',
            'Wasifu Wangu | Nazuri Admin' => 'My Profile | Nazuri Admin',
            // Settings page messages
            'Kosa wakati wa kupakia logo.' => 'Error uploading logo.',
            'Mipangilio imesasishwa kikamilifu!' => 'Settings updated successfully!',
            'Kosa: ' => 'Error: ',
            // Settings page placeholders
            'Mfano: 0712345678' => 'Example: 0712345678',
            'Mfano: info@nazuricollections.com' => 'Example: info@nazuricollections.com',
            // Profile page messages
            'Tafadhali jaza jina, barua pepe na simu.' => 'Please fill in name, email and phone.',
            'Jina la mtumiaji au Barua pepe tayari inatumika na admin mwingine.' => 'Username or Email already in use by another admin.',
            'Nenosiri lazima liwe na angalau herufi 6.' => 'Password must be at least 6 characters.',
            'Nenosiri mpya hazifanani.' => 'New passwords do not match.',
            // Add Product page placeholders
            'Mfano: Royal Black Abaya' => 'Example: Royal Black Abaya',
            'Mfano: 45000' => 'Example: 45000',
            'Mfano: 20 (Itaandika 20% OFF)' => 'Example: 20 (Will show 20% OFF)',
            'Acha wazi kama hakuna' => 'Leave blank if none',
            'Mfano: GRANT001' => 'Example: GRANT001',
            'Mfano: SALE2024' => 'Example: SALE2024',
            'Maelezo mafupi kuhusu bidhaa...' => 'Brief description about the product...',
            'Tenganisha kwa koma' => 'Separate with commas',
            'Mfano: L,XL,XXL' => 'Example: L,XL,XXL',
            'Mfano: dera, kaftan, gauni la harusi, trending' => 'Example: dera, kaftan, wedding gown, trending',
            // Edit Product page
            'Futa picha hii?' => 'Delete this image?',
            'Ongeza Picha za Gallery' => 'Add Gallery Images',
            // Orders page
            'Futa Oda' => 'Delete Order',
            'Funga' => 'Close',
            'Hifadhi' => 'Save',
            'Una uhakika unataka kufuta oda hii? Hatua hii haiwezi kurejeshwa.' => 'Are you sure you want to delete this order? This action cannot be undone.',
            // Pre-orders page
            'Una uhakika unataka kufuta pre-order hii?' => 'Are you sure you want to delete this pre-order?',
            'Size & Rangi' => 'Size & Color',
            // Pre-orders manage page
            'Ongeza Upcoming' => 'Add Upcoming',
            'Je, mzigo huu umefika? Bidhaa itahamishiwa kwenye Active.' => 'Has this stock arrived? The product will be moved to Active.',
            'Futa bidhaa hii ya Pre-Order?' => 'Delete this Pre-Order product?',
            // Sliders page
            'Kichwa Kikuu (Title)' => 'Main Title',
            'Maelezo (Subtitle)' => 'Subtitle',
            'Maandishi ya Kitufe' => 'Button Text',
            'Link ya Kitufe' => 'Button Link',
            'Mpangilio (Sort Order)' => 'Sort Order',
            'Faili la Video (MP4 au WEBM)' => 'Video File (MP4 or WEBM)',
            'Ongeza Slider' => 'Add Slider',
            'Sliders Zilizopo' => 'Existing Sliders',
            'Mpangilio' => 'Order',
            'Kichwa' => 'Title',
            // System Health page
            'Database tables na columns zote muhimu zipo.' => 'All required database tables and columns exist.',
            'Baadhi ya columns zinakosekana kwenye tables.' => 'Some columns are missing from tables.',
            'Baadhi ya tables muhimu hazipo.' => 'Some required tables are missing.',
            'Ipo sawa' => 'All good',
            'Columns zinazokosekana:' => 'Missing columns:',
            'Table haipo kwenye database.' => 'Table does not exist in the database.',
            // Reports page
            'Imeshindikana kunakili summary. Nakili kwa mkono kutoka kwenye report.' => 'Failed to copy summary. Copy manually from the report.',
            'Imenakiliwa' => 'Copied',
            // Admin auth pages
            'Kuna tatizo la database limetokea. Jaribu tena.' => 'A database error occurred. Please try again.',
            'Tafadhali weka nenosiri jipya kwa akaunti yako.' => 'Please set a new password for your account.',
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
            'Approved' => 'Imekubaliwa',
            'Previous' => 'Nyuma',
            'Next' => 'Mbele',
            'Rating' => 'Ukadiriaji',
            'All Statuses' => 'Hali Zote',
            'Bulk Actions' => 'Vitendo vya Jumla',
            'Approve Selected' => 'Kubali Zilizochaguliwa',
            'Delete Selected' => 'Futa Zilizochaguliwa',
            'Apply' => 'Tekeleza',
            'Filter by:' => 'Chuja kwa:',
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
