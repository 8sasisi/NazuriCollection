<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
start_admin_ui_translation_buffer();

$raw_token = $_GET['token'] ?? ($_POST['raw_token'] ?? null);
$message = "";
$msg_type = "";
$show_form = false;

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!$raw_token) {
    $message = "Token haipo au si sahihi.";
    $msg_type = "danger";
} else {
    // 1. Compute HMAC and look up by hashed token
    $hmacToken = hash_hmac('sha256', $raw_token, getenv('APP_KEY'));
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE hmac_token = ?");
    $stmt->execute([$hmacToken]);
    $reset_request = $stmt->fetch();

    if (!$reset_request) {
        $message = "Token si sahihi. Tafadhali anza upya mchakato.";
        $msg_type = "danger";
    } else {
        // 2. Check if token has expired
        $expires = new DateTime($reset_request['expires_at']);
        $now = new DateTime('now');

        if ($now > $expires) {
            $message = "Muda wa kutumia link hii umekwisha. Tafadhali anza upya.";
            $msg_type = "danger";
            // Clean up expired token
            $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_delete->execute([$reset_request['email']]);
        } else {
            // Token is valid, show the form
            $show_form = true;
        }
    }
}

// Handle form submission for new password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $show_form) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Error: Invalid CSRF Token.");
    }

    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (empty($password) || empty($password_confirm)) {
        $message = "Tafadhali jaza sehemu zote.";
        $msg_type = "danger";
    } elseif (strlen($password) < 6) {
        $message = "Nenosiri linapaswa kuwa na angalau herufi 6.";
        $msg_type = "danger";
    } elseif ($password !== $password_confirm) {
        $message = "Manenosiri hayafanani.";
        $msg_type = "danger";
    } else {
        // All good, update the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $email = $reset_request['email'];

        try {
            // Update admin password
            $stmt_update = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $stmt_update->execute([$hashed_password, $email]);

            // Delete the token
            $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_delete->execute([$email]);

            $message = "Nenosiri Limebadilishwa";
            $msg_type = "success";
            $show_form = false; // Hide form after success

        } catch (PDOException $e) {
            $message = "Kuna tatizo la database limetokea. Jaribu tena.";
            $msg_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weka Nenosiri Jipya | Nazuri Admin</title>
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
            <?php if (!$show_form && $msg_type === 'success'): ?>
                <div class="text-center mb-4">
                    <div class="mb-3 text-success display-4"><i class="bi bi-check-circle-fill"></i></div>
                    <h2 class="fw-bold" style="font-family: 'Playfair Display', serif;">Nenosiri Limebadilishwa</h2>
                    <p class="text-muted mt-3 mb-4">Nenosiri lako limewekwa upya kikamilifu. Sasa unaweza kuingia kwenye akaunti yako.</p>
                    <a href="login.php" class="btn btn-dark btn-lg rounded-pill px-5">Ingia Sasa</a>
                </div>
            <?php elseif ($show_form): ?>
                <div class="text-center mb-4">
                    <h2 class="fw-bold" style="font-family: 'Playfair Display', serif;">Weka Nenosiri Jipya</h2>
                    <p class="text-muted">Tafadhali weka nenosiri jipya kwa akaunti yako.</p>
                </div>
                <?php if(!empty($message)): ?><div class="alert alert-<?php echo $msg_type; ?>" role="alert"><?php echo $message; ?></div><?php endif; ?>
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($raw_token); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="raw_token" value="<?php echo htmlspecialchars($raw_token); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label fw-bold">Nenosiri Jipya</label>
                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    </div>
                    <div class="mb-4">
                        <label for="password_confirm" class="form-label fw-bold">Thibitisha Nenosiri Jipya</label>
                        <input type="password" class="form-control form-control-lg" id="password_confirm" name="password_confirm" required>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-dark btn-lg rounded-pill">Weka Nenosiri Jipya</button></div>
                </form>
            <?php else: ?>
                <?php if(!empty($message)): ?><div class="alert alert-<?php echo $msg_type; ?>" role="alert"><?php echo $message; ?></div><?php endif; ?>
                <div class="text-center mt-3"><a href="login.php" class="btn btn-dark rounded-pill px-4">Ingia Sasa</a></div>
            <?php endif; ?>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
