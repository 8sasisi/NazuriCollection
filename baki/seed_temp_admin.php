<?php
require_once 'config/db_connect.php';

$username = 'temp_test_admin';
$email = 'tempadmin@example.local';
$phone = '0777000000';
$password = 'TempPass!234';

try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ? OR phone = ?");
    $stmt->execute([$username, $email, $phone]);
    $exists = (int)$stmt->fetchColumn();

    if ($exists === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO admins (username, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, 'Editor', NOW())");
        $ins->execute([$username, $email, $phone, $hash]);
        echo json_encode(['created' => true, 'username' => $username, 'password' => $password]);
    } else {
        echo json_encode(['created' => false, 'message' => 'Admin already exists']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
