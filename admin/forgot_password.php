<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_runtime.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/mailer.php';
start_admin_ui_translation_buffer();

$message = "";
$msg_type = "";

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting IP-based (max 3 requests per 15 minutes per IP + action)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$action = 'password_reset_request';
$rateLimitWindow = 900; // 15 minutes
$maxRequests = 3;
$currentTime = time();

$stmtCleanup = $conn->prepare("DELETE FROM request_rate_limits WHERE request_time < ?");
$stmtCleanup->execute([$currentTime - 3600]); // keep last hour

$stmtRateCheck = $conn->prepare(
    "SELECT COUNT(*) FROM request_rate_limits
     WHERE ip_address = ? AND action_name = ? AND request_time > ?"
);
$stmtRateCheck->execute([$ip, $action, $currentTime - $rateLimitWindow]);
$recentCount = (int)$stmtRateCheck->fetchColumn();

// Create password_resets table if it doesn't exist (with hmac_token column)
try {
    $conn->query("SELECT hmac_token FROM password_resets LIMIT 1");
} catch (PDOException $e) {
    // Recreate with hmac_token
    $conn->exec("DROP TABLE IF EXISTS password_resets");
    $sql = "
    CREATE TABLE `password_resets` (
      `email` varchar(100) NOT NULL,
      `token` varchar(255) NOT NULL,
      `hmac_token` varchar(64) NOT NULL,
      `expires_at` datetime NOT NULL,
      PRIMARY KEY (`email`),
      KEY `hmac_token` (`hmac_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->exec($sql);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    // Rate limit check
    if ($recentCount >= $maxRequests) {
        error_log("Rate limit hit for password reset: IP $ip");
        die("Too many reset requests. Try again later.");
    }

    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Tafadhali ingiza anwani sahihi ya barua pepe.";
        $msg_type = "danger";
    } else {
        // Log this attempt for rate limiting
        $stmtLog = $conn->prepare(
            "INSERT INTO request_rate_limits (ip_address, action_name, request_time) VALUES (?, ?, ?)"
        );
        $stmtLog->execute([$ip, $action, $currentTime]);

        // 1. Check if email exists in admins table
        $stmt = $conn->prepare("SELECT id, username, email FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            // 2. Generate a secure token and its HMAC
            $token = bin2hex(random_bytes(32));
            $hmacToken = hash_hmac('sha256', $token, getenv('APP_KEY'));
            
            // 3. Set expiry time (1 hour from now)
            $expires = new DateTime('now');
            $expires->add(new DateInterval('PT1H')); // 1 Hour
            $expires_at = $expires->format('Y-m-d H:i:s');

            // 4. Store HMAC in database (not raw token), delete old one if exists
            $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_delete->execute([$email]);

            $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, hmac_token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt_insert->execute([$email, $token, $hmacToken, $expires_at]);

            $reset_link = app_base_url() . "/admin/reset_password.php?token=" . urlencode($token);
            
            // 5. Send email via PHPMailer
            $mailResult = sendAdminPasswordReset(
                ['email' => $admin['email'], 'name' => $admin['username']],
                $reset_link
            );

            if ($mailResult) {
                app_log_event($conn, 'password_reset_requested', 'Password reset link sent to ' . $admin['email']);
            } else {
                error_log("Failed to send password reset email to {$admin['email']}");
            }

            $message = "Barua pepe ya kuweka upya nenosiri imetumwa.";
            $msg_type = "success";

        } else {
            $message = "Barua pepe ya kuweka upya nenosiri imetumwa.";
            $msg_type = "success";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umesahau Nenosiri | Nazuri Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { width: 100%; max-width: 480px; border: none; border-radius: 20px; }
    </style>
</head>
<body>
    <div class="card login-card shadow-lg">
        <div class="card-body p-5">
            <div class="text-center mb-4"><h2 class="fw-bold" style="font-family: 'Playfair Display', serif;">Weka Nenosiri Jipya</h2><p class="text-muted">Jaza barua pepe yako ili kupata link ya kuweka nenosiri jipya.</p></div>
            <?php if(!empty($message)): ?><div class="alert alert-<?php echo $msg_type; ?>" role="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3"><label for="email" class="form-label fw-bold">Barua Pepe (Email)</label><input type="email" class="form-control form-control-lg" id="email" name="email" required></div>
                <div class="d-grid mt-4"><button type="submit" class="btn btn-dark btn-lg rounded-pill">Tuma Link</button></div>
                <div class="text-center mt-3"><a href="login.php" class="text-muted small"><i class="bi bi-arrow-left"></i> Rudi kwenye Login</a></div>
            </form>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
