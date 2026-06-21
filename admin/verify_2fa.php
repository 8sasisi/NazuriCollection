<!-- <?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
start_admin_ui_translation_buffer();

// Hakikisha mtumiaji amepitia login.php na ana pending 2FA
if (!isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true || !isset($_SESSION['temp_admin_id'])) {
    header("Location: login.php");
    exit();
}

$error_message = "";

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $otp_input = trim($_POST['otp']);
    $admin_id = $_SESSION['temp_admin_id'];

    // Vuta taarifa za admin na OTP
    $stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        // Hakiki OTP na Muda wake
        if ($admin['otp_code'] === $otp_input && strtotime($admin['otp_expires_at']) > time()) {
            // OTP ni sahihi
            
            // Futa OTP kwenye database
            $stmt = $conn->prepare("UPDATE admins SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
            $stmt->execute([$admin_id]);

            // Kamilisha Login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];

            // Futa temp session variables
            unset($_SESSION['2fa_pending']);
            unset($_SESSION['temp_admin_id']);

            header("Location: index.php");
            exit();
        } else {
            $error_message = "Kodi si sahihi au muda wake umekwisha.";
        }
    } else {
        // Admin hajulikani (Error case)
        session_destroy();
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uhakiki wa Hatua Mbili | Nazuri Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { width: 100%; max-width: 450px; border: none; border-radius: 20px; }
    </style>
</head>
<body>
    <div class="card login-card shadow-lg">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h2 class="fw-bold" style="font-family: 'Playfair Display', serif;">Uhakiki wa <span class="text-warning">2FA</span></h2>
                <p class="text-muted">Tumaingiza kodi ya tarakimu 6 kwenye barua pepe yako.</p>
            </div>
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>
            <form method="POST" action="verify_2fa.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-4">
                    <label for="otp" class="form-label fw-bold">Ingiza Kodi ya OTP</label>
                    <input type="text" class="form-control form-control-lg text-center tracking-widest" id="otp" name="otp" required placeholder="XXXXXX" maxlength="6" style="letter-spacing: 5px; font-weight: bold;">
                </div>
                <div class="d-grid"><button type="submit" class="btn btn-dark btn-lg rounded-pill">Thibitisha</button></div>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-muted small">Rudi Kwenye Login</a>
                </div>
            </form>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> -->
