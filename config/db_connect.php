<?php

require_once __DIR__ . '/env.php';

// Load environment variables from a .env file located in the project's root directory
$dotenv_path = dirname(__DIR__) . '/.env';
load_env_file($dotenv_path);

$servername = env_value(/*'DB_HOST', 'sql301.infinityfree.com'*/'DB_HOST', 'localhost');
$username = env_value(/*'DB_USERNAME', 'if0_41351924'*/'DB_USERNAME', 'root');
$password = env_value(/*'DB_PASSWORD', 'rC9m64bxuJa'*/'DB_PASSWORD', '');
$dbname = env_value(/*'DB_DATABASE', 'if0_41351924_grant_fashions_db'*/'DB_DATABASE', 'grant_fashions_db');
$dbport = env_value('DB_PORT', '3306');

try {
    $dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";
    if ($dbport !== null && $dbport !== '') {
        $dsn .= ";port={$dbport}";
    }

    $conn = new PDO($dsn, (string)$username, (string)$password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    // Security Headers
    if (!headers_sent()) {
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: no-referrer-when-downgrade");
        header("Permissions-Policy: geolocation=(), camera=()");

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }

        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "base-uri 'self'; " .
            "frame-ancestors 'none'; " .
            "object-src 'none'; " .
            "img-src 'self' data: https:; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
            "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
            "connect-src 'self'; " .
            "form-action 'self';"
        );
    }
} catch(PDOException $e) {
    // Security: Log error badala ya kuonyesha kwa user
    error_log("Database Connection Error: " . $e->getMessage());
    die("Kuna tatizo la kiufundi. Tafadhali jaribu tena baadaye.");
}

// --- Centralized Schema Management ---
$schema = require_once __DIR__ . '/schema.php';

foreach ($schema as $tableName => $tableDef) {
    // 1. Check if table exists
    try {
        $conn->query("SELECT 1 FROM `$tableName` LIMIT 1");
        
        // 2. Table exists, check for missing columns
        $stmt = $conn->query("SHOW COLUMNS FROM `$tableName`");
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tableDef['columns'] as $colName => $colDef) {
            if (!in_array($colName, $existing_columns)) {
                try {
                    $conn->exec("ALTER TABLE `$tableName` ADD COLUMN `$colName` $colDef");
                } catch (PDOException $e) {
                    error_log("Failed to add column '$colName' to table '$tableName': " . $e->getMessage());
                }
            }
        }

    } catch (PDOException $e) {
        // 3. Table does not exist, create it
        $columnsSql = [];
        foreach ($tableDef['columns'] as $colName => $colDef) {
            $columnsSql[] = "`$colName` $colDef";
        }
        
        if (!empty($tableDef['primary_key'])) {
            $columnsSql[] = "PRIMARY KEY (`{$tableDef['primary_key']}`)";
        }
        
        if (!empty($tableDef['unique_keys'])) {
            foreach ($tableDef['unique_keys'] as $keyName => $colName) {
                $columnsSql[] = "UNIQUE KEY `$keyName` (`$colName`)";
            }
        }

        if (!empty($tableDef['keys'])) {
            foreach ($tableDef['keys'] as $keyName => $colName) {
                $columnsSql[] = "KEY `$keyName` (`$colName`)";
            }
        }

        if (!empty($tableDef['indexes'])) {
            foreach ($tableDef['indexes'] as $indexName => $indexDef) {
                $columnsSql[] = "INDEX `$indexName` $indexDef";
            }
        }
        
        $createSql = "CREATE TABLE `$tableName` (" . implode(', ', $columnsSql) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        try {
            $conn->exec($createSql);
        } catch (PDOException $e) {
            error_log("Failed to create table '$tableName': " . $e->getMessage());
        }
    }
}

