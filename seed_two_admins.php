<?php
require_once 'config/db_connect.php';

$admins = [
    [
        'username' => 'admin1',
        'email' => 'admin1@example.local',
        'phone' => '0712345678',
        'password' => 'Admin1@2024!',
        'role' => 'Super Admin'
    ],
    [
        'username' => 'admin2',
        'email' => 'admin2@example.local',
        'phone' => '0798765432',
        'password' => 'Admin2@2024!',
        'role' => 'Editor'
    ]
];

$created = [];
$skipped = [];

foreach ($admins as $admin) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ? OR phone = ?");
        $stmt->execute([$admin['username'], $admin['email'], $admin['phone']]);
        $exists = (int)$stmt->fetchColumn();

        if ($exists === 0) {
            $hash = password_hash($admin['password'], PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO admins (username, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $ins->execute([$admin['username'], $admin['email'], $admin['phone'], $hash, $admin['role']]);
            $created[] = $admin['username'];
        } else {
            $skipped[] = $admin['username'];
        }
    } catch (Exception $e) {
        echo "Error for {$admin['username']}: " . $e->getMessage() . "\n";
    }
}

echo json_encode(['created' => $created, 'skipped' => $skipped], JSON_PRETTY_PRINT);