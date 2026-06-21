<?php

// This file defines the entire database schema for the application.
// It is the single source of truth for table structures.
// It is used by db_connect.php to create/update tables and by system_health.php to verify the schema.

return [
    'admins' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'username' => 'varchar(50) NOT NULL',
            'email' => 'varchar(100) NOT NULL',
            'phone' => 'varchar(20) DEFAULT NULL',
            'password' => 'varchar(255) NOT NULL',
            'role' => "varchar(50) DEFAULT 'admin'",
            'created_at' => "timestamp NOT NULL DEFAULT current_timestamp()",
        ],
        'primary_key' => 'id',
        'unique_keys' => [
            'username' => 'username',
            'email' => 'email',
        ],
    ],
    'products' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'name' => 'varchar(255) NOT NULL',
            'description' => 'text NOT NULL',
            'price' => 'decimal(10,2) NOT NULL',
            'category' => 'varchar(100) DEFAULT NULL',
            'available_sizes' => 'varchar(255) DEFAULT NULL',
            'available_colors' => 'varchar(255) DEFAULT NULL',
            'keywords' => 'text',
            'image' => 'varchar(255) DEFAULT NULL',
            'status' => "varchar(50) DEFAULT 'active'",
            'created_at' => "timestamp NOT NULL DEFAULT current_timestamp()",
            'discount_price' => 'INT(11) DEFAULT 0',
            'coupon_code' => 'VARCHAR(50) DEFAULT NULL',
            'product_code' => 'VARCHAR(100) DEFAULT NULL',
            'offer_badge' => 'TINYINT(1) DEFAULT 0',
            'discount_percentage' => 'INT(11) DEFAULT 0',
            'offer_expires_at' => 'DATETIME DEFAULT NULL',
        ],
        'primary_key' => 'id',
    ],
    'orders' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'public_id' => 'varchar(32) DEFAULT NULL',
            'customer_name' => 'varchar(100) NOT NULL',
            'customer_phone' => 'varchar(20) NOT NULL',
            'total_amount' => 'decimal(10,2) NOT NULL',
            'payment_method' => 'varchar(50) NOT NULL',
'order_status' => "varchar(50) DEFAULT 'pending'",
            'created_at' => "timestamp NOT NULL DEFAULT current_timestamp()",
        ],
        'primary_key' => 'id',
        'unique_keys' => [
            'public_id' => 'public_id',
        ],
    ],
    'order_items' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'order_id' => 'int(11) NOT NULL',
            'product_id' => 'int(11) DEFAULT NULL',
            'product_name' => 'varchar(255) NOT NULL',
            'quantity' => 'int(11) NOT NULL',
            'price' => 'decimal(10,2) NOT NULL',
            'size' => 'varchar(50) DEFAULT NULL',
            'color' => 'varchar(50) DEFAULT NULL',
        ],
        'primary_key' => 'id',
        'keys' => [
            'order_id' => 'order_id',
        ],
    ],
    'pre_orders' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'customer_name' => 'varchar(100) NOT NULL',
            'customer_phone' => 'varchar(20) NOT NULL',
            'product_name' => 'varchar(255) NOT NULL',
            'quantity' => 'int(11) NOT NULL',
            'size' => 'varchar(50) DEFAULT NULL',
            'color' => 'varchar(50) DEFAULT NULL',
            'created_at' => "timestamp NOT NULL DEFAULT current_timestamp()",
        ],
        'primary_key' => 'id',
    ],
    'site_settings' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'setting_key' => 'varchar(50) NOT NULL',
            'setting_value' => 'text',
        ],
        'primary_key' => 'id',
        'unique_keys' => [
            'setting_key' => 'setting_key',
        ],
    ],
    'reviews' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'product_id' => 'int(11) NOT NULL',
            'customer_name' => 'varchar(100) NOT NULL',
            'rating' => 'int(1) NOT NULL',
            'comment' => 'text',
            'created_at' => "timestamp NOT NULL DEFAULT current_timestamp()",
            'status' => "ENUM('pending', 'approved') DEFAULT 'pending'",
        ],
        'primary_key' => 'id',
        'keys' => [
            'product_id' => 'product_id',
        ],
    ],
    'sliders' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'title' => 'varchar(255) NOT NULL',
            'subtitle' => 'varchar(255) DEFAULT NULL',
            'button_text' => 'varchar(50) DEFAULT NULL',
            'button_link' => 'varchar(255) DEFAULT NULL',
            'video_path' => 'varchar(255) DEFAULT NULL',
            'sort_order' => 'int(11) DEFAULT 0',
            'status' => 'tinyint(1) DEFAULT 1',
        ],
        'primary_key' => 'id',
    ],
    'activity_logs' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'log_type' => 'varchar(50) NOT NULL',
            'ip_address' => 'varchar(45) DEFAULT NULL',
            'user_agent' => 'text DEFAULT NULL',
            'request_method' => 'varchar(10) DEFAULT NULL',
            'request_uri' => 'varchar(255) DEFAULT NULL',
            'referrer_url' => 'text DEFAULT NULL',
            'details' => 'text',
            'created_at' => "timestamp NOT NULL DEFAULT current_timestamp()",
        ],
        'primary_key' => 'id',
    ],
    'visits' => [
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'visit_date' => 'date NOT NULL',
            'visits_count' => 'int(11) NOT NULL DEFAULT 1',
        ],
        'primary_key' => 'id',
        'unique_keys' => [
            'visit_date' => 'visit_date',
        ],
    ],
    'password_resets' => [
        'columns' => [
            'email' => 'varchar(100) NOT NULL',
            'token' => 'varchar(255) NOT NULL',
'expires_at' => 'datetime NOT NULL',
        ],
        'primary_key' => 'email',
        'keys' => [
            'token' => 'token',
        ],
    ],
     'request_rate_limits' => [
        'columns' => [
            'id' => 'INT AUTO_INCREMENT',
            'ip_address' => 'VARCHAR(45) NOT NULL',
            'action_name' => 'VARCHAR(50) NOT NULL',
            'request_time' => 'INT NOT NULL',
        ],
        'primary_key' => 'id',
        'indexes' => [
            'idx_ip_action_time' => '(`ip_address`, `action_name`, `request_time`)',
        ],
    ],
];

?>
