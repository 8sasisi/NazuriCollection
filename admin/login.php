<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
start_admin_ui_translation_buffer();

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$maxAttempts = 5;
$lockTime = 5 * 60; // dakika 5
$currentTime = time();

function normalize_admin_identifier($value)
{
    $value = trim((string) $value);
    $value = preg_replace('/[\s\-\.\(\)]/', '', $value);

    if ($value === '') {
        return '';
    }

    if (strpos($value, '+') === 0) {
        $value = substr($value, 1);
    }

    if (preg_match('/^255(7\d{8})$/', $value, $matches)) {
        return '0' . $matches[1];
    }

    return $value;
}

// Hakikisha table ya login attempts ipo kabla ya kuitumia.
$conn->exec("
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time INT NOT NULL,
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Hii ni kwa ajili ya setup ya mara ya kwanza tu.
// 1. Angalia kama table ya 'admins' ipo, kama haipo, itengeneze.
try {
    $conn->query("SELECT 1 FROM admins LIMIT 1");
} catch (PDOException $e) {
    // Table haipo, tengeneza
    $sql = "
    CREATE TABLE `admins` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `email` varchar(100) NOT NULL,
      `phone` varchar(20) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` varchar(20) NOT NULL DEFAULT 'Editor',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->exec($sql);
}

// Hakikisha column ya role ipo
try {
    $conn->query("SELECT role FROM admins LIMIT 1");
} catch (PDOException $e) {
    $conn->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'Editor'");
    // Mpe admin wa kwanza role ya Super Admin
    $conn->exec("UPDATE admins SET role = 'Super Admin' ORDER BY id ASC LIMIT 1");
}

// 2. Angalia kama kuna admin yeyote, kama hakuna, ongeza default admin
$stmt_check = $conn->query("SELECT COUNT(*) FROM admins");
$admin_exists = $stmt_check->fetchColumn();

if ($admin_exists == 0) {
    // Ongeza admin wa kwanza (default)
     $default_username = 'testadmin';
     $default_email = 'testadmin@example.local';
     $default_phone = '0712345678';
     // Stronger test password for local testing. Change after use.
     $default_password = 'Test@1234';
     $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

     $insert_sql = "INSERT INTO admins (username, email, phone, password, role) VALUES (?, ?, ?, ?, 'Super Admin')";
     $stmt_insert = $conn->prepare($insert_sql);
     $stmt_insert->execute([$default_username, $default_email, $default_phone, $hashed_password]);
 };

// Ikiwa admin tayari ameingia, mpeleke kwenye dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$error_message = "";

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Angalia attempts ndani ya dakika 15.
    $timeLimit = $currentTime - $lockTime;
    $stmtRateLimit = $conn->prepare(
        "SELECT COUNT(*) as total
         FROM login_attempts
         WHERE ip_address = :ip
           AND attempt_time > :time_limit"
    );
    $stmtRateLimit->execute([
        ':ip' => $ip,
        ':time_limit' => $timeLimit
    ]);
    $attemptResult = $stmtRateLimit->fetch(PDO::FETCH_ASSOC);

    if ((int)($attemptResult['total'] ?? 0) >= $maxAttempts) {
        die("Too many login attempts. Try again after 5 minutes.");
    }

    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    $normalizedPhone = $isEmail ? '' : normalize_admin_identifier($identifier);

    if (empty($identifier) || empty($password)) {
        $error_message = "Tafadhali jaza taarifa zako za kuingia.";
    } else {
        // Login kwa kutumia email au namba ya simu pekee
        if ($isEmail) {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$identifier]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE phone = ? LIMIT 1");
            $stmt->execute([$identifier]);
            if ($stmt->rowCount() === 0 && $normalizedPhone !== '' && $normalizedPhone !== $identifier) {
                $stmt = $conn->prepare("SELECT * FROM admins WHERE phone = ? LIMIT 1");
                $stmt->execute([$normalizedPhone]);
            }
        }
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            // Nenosiri ni sahihi. Login moja kwa moja.
            $deleteAttempts = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
            $deleteAttempts->execute([':ip' => $ip]);

            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            
            session_write_close();
            header("Location: index.php");
            exit();
        } else {
            // Taarifa si sahihi
            $insertAttempt = $conn->prepare(
                "INSERT INTO login_attempts (ip_address, attempt_time) VALUES (:ip, :attempt_time)"
            );
            $insertAttempt->execute([
                ':ip' => $ip,
                ':attempt_time' => $currentTime
            ]);

            $error_message = "Taarifa za kuingia si sahihi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Nazuri Collections</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { width: 100%; max-width: 450px; border: none; border-radius: 20px; }
        .password-toggle-btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
    </style>
</head>
<body>
    <div class="card login-card shadow-lg">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h2 class="fw-bold" style="font-family: 'Playfair Display', serif;">GRANT <span class="text-warning">ADMIN</span></h2>
                <p class="text-muted">Ingia kwenye paneli ya usimamizi.</p>
            </div>
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3"><label for="identifier" class="form-label fw-bold">Namba ya Simu / Barua Pepe</label><input type="text" class="form-control form-control-lg" id="identifier" name="identifier" required placeholder="Mfano: 0712345678 au admin@example.com"></div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-bold">Nenosiri</label>
                    <div class="input-group input-group-lg">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" id="togglePassword" aria-label="Onyesha nenosiri" aria-pressed="false">
                            <i class="bi bi-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="d-grid"><button type="submit" class="btn btn-dark btn-lg rounded-pill">Ingia</button></div>
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="text-muted small">Umesahau nenosiri?</a>
                </div>
            </form>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('togglePassword');
        const toggleIcon = document.getElementById('passwordToggleIcon');

        toggleButton.addEventListener('click', () => {
            const isHidden = passwordInput.type === 'password';
            passwordInput.type = isHidden ? 'text' : 'password';
            toggleIcon.classList.toggle('bi-eye', !isHidden);
            toggleIcon.classList.toggle('bi-eye-slash', isHidden);
            toggleButton.setAttribute('aria-pressed', String(isHidden));
            toggleButton.setAttribute('aria-label', isHidden ? 'Ficha nenosiri' : 'Onyesha nenosiri');
        });
    });
</script>
</body>
</html>
