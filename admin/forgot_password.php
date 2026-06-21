<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/app_runtime.php';
require_once __DIR__ . '/../includes/functions.php';
start_admin_ui_translation_buffer();

$message = "";
$msg_type = "";

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Create password_resets table if it doesn't exist
try {
    $conn->query("SELECT 1 FROM password_resets LIMIT 1");
} catch (PDOException $e) {
    $sql = "
    CREATE TABLE `password_resets` (
      `email` varchar(100) NOT NULL,
      `token` varchar(255) NOT NULL,
      `expires_at` datetime NOT NULL,
      PRIMARY KEY (`email`),
      KEY `token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->exec($sql);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Tafadhali ingiza anwani sahihi ya barua pepe.";
        $msg_type = "danger";
    } else {
        // 1. Check if email exists in admins table
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin) {
            // 2. Generate a secure token
            $token = bin2hex(random_bytes(32));
            
            // 3. Set expiry time (1 hour from now)
            $expires = new DateTime('now');
            $expires->add(new DateInterval('PT1H')); // 1 Hour
            $expires_at = $expires->format('Y-m-d H:i:s');

            // 4. Store token in database (delete old one if exists)
            $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_delete->execute([$email]);

            $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt_insert->execute([$email, $token, $expires_at]);

            $reset_link = app_base_url() . "/admin/reset_password.php?token=" . $token;
            
            $subject = "Rejesha Nenosiri | Grant Admin";
            $mail_body = "Habari,\n\nTumepokea ombi la kubadilisha nenosiri la akaunti yako. Tafadhali bonyeza link ifuatayo ili kuweka nenosiri jipya:\n\n" . $reset_link . "\n\nLink hii itafanya kazi kwa muda wa saa 1.\n\nAsante.";
            $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            
            // Send email silently
            @mail($email, $subject, $mail_body, $headers);

            app_log_event($conn, 'password_reset_requested', 'Password reset link sent to admin email.');
            $message = "Ikiwa barua pepe yako ipo kwenye mfumo wetu, tumekutumia link ya kuweka upya nenosiri.";
            $msg_type = "success";

        } else {
            $message = "Ikiwa barua pepe yako ipo kwenye mfumo wetu, tumekutumia link ya kuweka upya nenosiri.";
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
    <title>Umesahau Nenosiri | Grant Admin</title>
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
