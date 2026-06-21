<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    if ($lang == 'en' || $lang == 'sw') {
        $_SESSION['lang'] = $lang;
    }
}

$current_lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';

include_once __DIR__ . '/../languages/' . $current_lang . '.php';

if (!function_exists('t')) {
    function t($key) {
        global $lang;
        if (isset($lang[$key])) {
            return $lang[$key];
        } else {
            return $key;
        }
    }
}

if (!function_exists('frontend_ui_translation_map')) {
    function frontend_ui_translation_map()
    {
        return [
            'Nyumbani' => 'Home',
            'Duka' => 'Shop',
            'Kikapu la Manunuzi' => 'Shopping Cart',
            'Kapu lako ni tupu.' => 'Your cart is empty.',
            'Anza Kununua' => 'Start Shopping',
            'Bidhaa' => 'Products',
            'Bei' => 'Price',
            'Idadi' => 'Quantity',
            'Jumla' => 'Total',
            'Endelea Kununua' => 'Continue Shopping',
            'Futa Kapu Lote' => 'Clear Cart',
            'Muhtasari wa Oda' => 'Order Summary',
            'Kuponi ya Punguzo' => 'Discount Coupon',
            'Tumia' => 'Apply',
            'Jumla Ndogo' => 'Subtotal',
            'Usafiri' => 'Shipping',
            'Inalipwa ukipokea' => 'Paid on delivery',
            'Jumla Kuu' => 'Grand Total',
            'Chagua Njia ya Malipo' => 'Choose Payment Method',
            'Mitandao ya Simu:' => 'Mobile Networks:',
            'Benki / Wakala:' => 'Bank / Agent:',
            'Weka Oda Yako' => 'Place Your Order',
            'Jina Kamili' => 'Full Name',
            'Namba ya Simu' => 'Phone Number',
            'Funga' => 'Close',
            'Thibitisha Oda' => 'Confirm Order',
            'Hakuna bidhaa ya kuonyesha' => 'No products to display',
            'Panga kwa: Jipya zaidi' => 'Sort by: Newest',
            'Panga kwa bei: Chini kwenda juu' => 'Sort by price: Low to high',
            'Panga kwa bei: Juu kwenda chini' => 'Sort by price: High to low',
            'Angalia' => 'View',
            'Hakuna Bidhaa Zilizopatikana' => 'No Products Found',
            'Jaribu kutumia maneno mengine ya utafutaji au ondoa vichujio.' => 'Try different keywords or clear filters.',
            'Ondoa Vichujio' => 'Clear Filters',
            'Nyuma' => 'Previous',
            'Mbele' => 'Next',
            "Karibu Grant Fashions" => 'Welcome to Grant Fashions',
            'Nenda Duka' => 'Go to Shop',
            "Ng'ara na Grant Fashions" => 'Shine with Grant Fashions',
            'Tunakujali na kukuthamini. Jipatie mavazi yenye heshima na mvuto wa kipekee kwa bei rafiki.' => 'We value you. Get elegant, attractive fashion at friendly prices.',
            'Angalia zaidi' => 'View More',
            'Bidhaa Mpya' => 'New Products',
            'Ona Zote' => 'View All',
            'Hakuna bidhaa kwa sasa.' => 'No products available right now.',
            'Pre-Order Collections' => 'Pre-Order Collections',
            'Wahi mapema bidhaa hizi kali zinazokuja hivi karibuni.' => 'Reserve these upcoming products early.',
            'Inakuja' => 'Coming Soon',
            'Weka Oda' => 'Place Order',
            'Asante kwa Oda Yako!' => 'Thank You for Your Order!',
            'Oda yako imepokelewa kikamilifu. Hii hapa ni risiti yako.' => 'Your order has been received successfully. Here is your receipt.',
            'Risiti ya Oda' => 'Order Receipt',
            'Taarifa za Mteja' => 'Customer Details',
            'Njia ya Malipo' => 'Payment Method',
            'Status:' => 'Status:',
            'Rangi' => 'Color',
            'Asante kwa kununua na Grant Fashions. Tunathamini sana wateja wetu.' => 'Thank you for shopping with Grant Fashions. We appreciate our customers.',
            'Ofa inaisha baada ya:' => 'Offer ends in:',
            'SIKU' => 'DAYS',
            'SAA' => 'HOURS',
            'DAKIKA' => 'MINUTES',
            'SEKUNDE' => 'SECONDS',
            'Chagua Size' => 'Choose Size',
            'Chagua Rangi' => 'Choose Color',
            '-- Chagua Rangi --' => '-- Choose Color --',
            'Maoni ya Wateja' => 'Customer Reviews',
            'Hakuna maoni bado. Kuwa wa kwanza kutoa maoni!' => 'No reviews yet. Be the first to review!',
            'Andika Maoni Yako' => 'Write Your Review',
            'Jina Lako' => 'Your Name',
            'Maoni' => 'Review',
            'Tuma Maoni' => 'Submit Review',
            'Bora Sana' => 'Excellent',
            'Nzuri' => 'Good',
            'Tazama kile wateja wetu wanasema kuhusu bidhaa na huduma zetu.' => 'See what our customers say about our products and services.',
            'Bado hakuna maoni ya kuonyesha.' => 'No reviews to display yet.',
            'Maoni yako yana maneno yasiyofaa. Tafadhali rekebisha.' => 'Your review contains inappropriate words. Please revise.',
            'Wastani' => 'Average',
            'Mbaya' => 'Poor',
            'Mbaya Sana' => 'Very Poor',
            'Ofa imekwisha!' => 'Offer has ended!',
            'Mawasiliano' => 'Contact',
            'Tunakuletea muonekano wa kisasa na wa heshima. Abaya na Magauni bora mjini.' => 'We bring modern and elegant fashion. Premium abayas and gowns in town.',
            'All Rights Reserved.' => 'All Rights Reserved.',
            'Una uhakika unataka kufuta bidhaa zote kwenye kapu?' => 'Are you sure you want to clear the cart?',
            'Tafadhali chagua njia ya malipo kwanza.' => 'Please choose a payment method first.',
            'Namba ya simu si sahihi' => 'Invalid phone number',
            'Tafadhali jaza taarifa zote (Jina, Simu, na Njia ya Malipo).' => 'Please fill all fields (Name, Phone, and Payment Method).',
            'Namba ya simu si sahihi. Tafadhali tumia format: 07xxxxxxxx au +2557xxxxxxxx' => 'Invalid phone number. Please use: 07xxxxxxxx or +2557xxxxxxxx',
            'Samahani, oda hii haikupatikana.' => 'Sorry, this order was not found.',
        ];
    }
}

if (!function_exists('frontend_translate_ui_buffer')) {
    function frontend_translate_ui_buffer($buffer)
    {
        $current_lang_local = $_SESSION['lang'] ?? 'en';
        $map_sw_en = frontend_ui_translation_map();

        if ($current_lang_local === 'en') {
            return strtr($buffer, $map_sw_en);
        }

        $map_en_sw = array_flip($map_sw_en);
        return strtr($buffer, $map_en_sw);
    }
}

if (!defined('FRONTEND_UI_TRANSLATION_BUFFER_STARTED')) {
    $is_admin_request = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
    if (!$is_admin_request && PHP_SAPI !== 'cli') {
        define('FRONTEND_UI_TRANSLATION_BUFFER_STARTED', true);
        ob_start('frontend_translate_ui_buffer');
    }
}
?>
