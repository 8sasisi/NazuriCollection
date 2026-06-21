<?php
// Script hii imeundwa kuendeshwa na Task Scheduler au Cron Job
// Mfano: Run saa 23:59 siku ya mwisho ya mwezi.

if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../includes/admin_auth.php';
}

require_once __DIR__ . '/../config/app_runtime.php';

// Hakikisha tunaweza kupata DB connection hata kama script inarun via CLI
if (php_sapi_name() == 'cli') {
    chdir(__DIR__);
}

require_once __DIR__ . '/../config/db_connect.php';

if (!app_cron_enabled()) {
    echo "Cron report sender imezimwa kwa hosting hii. Tumia admin/reports.php kupakua PDF au kunakili summary.";
    exit();
}
echo "Cron mode imewashwa, lakini report automation sasa inatumia manual workflow ndani ya admin/reports.php badala ya server mail.";
?>
