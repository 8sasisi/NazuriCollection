<?php
// Lightweight DB seed runner (config folder)
// Security: only allowed from CLI or local requests
if (PHP_SAPI !== 'cli') {
    $allowed = ['127.0.0.1', '::1'];
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remote, $allowed, true)) {
        echo "Access denied. Run this script from CLI on the server.\n";
        exit(1);
    }
}

require_once __DIR__ . '/db_connect.php';
$seedFile = __DIR__ . '/seed_initial_data.sql';
if (!file_exists($seedFile)) {
    echo "Seed file not found: $seedFile\n";
    exit(1);
}
$sql = file_get_contents($seedFile);
if ($sql === false) {
    echo "Failed to read seed file.\n";
    exit(1);
}
try {
    $conn->beginTransaction();
    // Split statements safely by semicolon followed by newline
    $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($stmts as $stmt) {
        if ($stmt === '') continue;
        $conn->exec($stmt);
    }
    $conn->commit();
    echo "Seed completed successfully.\n";
} catch (PDOException $e) {
    $conn->rollBack();
    echo "Seed failed: " . $e->getMessage() . "\n";
    exit(1);
}
