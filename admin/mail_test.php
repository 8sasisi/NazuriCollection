<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/mailer.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$result = '';
$success = false;

if (isset($_POST['test_mail'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $testEmail = trim($_POST['test_email'] ?: getAdminEmails()[0] ?? '');
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $result = 'Invalid email address';
    } else {
        try {
            $mail = getMailer();
            $mail->addAddress($testEmail);
            $mail->Subject = 'Nazuri Collections - SMTP Test';
            $mail->Body = 'This is a test email to confirm SMTP is working correctly.';
            $mail->send();
            $result = "Test email sent successfully to $testEmail";
            $success = true;
        } catch (Exception $e) {
            $result = 'FAILED: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Test | Nazuri Admin</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4">SMTP Mail Test</h4>
                    
                    <div class="mb-4 p-3 bg-dark text-white rounded-3 small">
                        <strong>Config:</strong><br>
                        Host: <?php echo htmlspecialchars(getenv('SMTP_HOST') ?: 'smtp.gmail.com'); ?><br>
                        User: <?php echo htmlspecialchars(getenv('SMTP_USER') ?: '(not set)'); ?><br>
                        Port: <?php echo htmlspecialchars(getenv('SMTP_PORT') ?: '587'); ?><br>
                        From: <?php echo htmlspecialchars(getenv('MAIL_FROM') ?: '(not set)'); ?>
                    </div>

                    <?php if ($result): ?>
                        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($result); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Send test to:</label>
                            <input type="email" name="test_email" class="form-control" 
                                   value="<?php echo htmlspecialchars(getAdminEmails()[0] ?? ''); ?>" required>
                        </div>
                        <button type="submit" name="test_mail" class="btn btn-primary w-100">
                            Send Test Email
                        </button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="index.php" class="text-muted small">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
