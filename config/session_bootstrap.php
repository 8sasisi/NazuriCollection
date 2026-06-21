<?php
// Session hardening before session_start
$httpsFlag = strtolower((string)($_SERVER['HTTPS'] ?? ''));
$forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$frontEndHttps = strtolower((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''));
$is_https = (
    ($httpsFlag !== '' && $httpsFlag !== 'off') ||
    $forwardedProto === 'https' ||
    $frontEndHttps === 'on'
);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(0);
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    $sessionScriptBase = dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $sessionScope = preg_replace('/[^a-z0-9]+/i', '_', $sessionHost . '_' . $sessionScriptBase);
    $sessionScope = trim((string)$sessionScope, '_');
    if ($sessionScope === '') {
        $sessionScope = 'nazuri_collections';
    }
    session_name('GFSESSID_' . substr($sessionScope, 0, 24));

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate once per session to avoid regenerating every request
if (empty($_SESSION['__session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['__session_regenerated'] = time();
}

if (!defined('APP_ERROR_HANDLERS_REGISTERED')) {
    define('APP_ERROR_HANDLERS_REGISTERED', true);

    function app_unavailable_message()
    {
        return (isset($_SESSION['lang']) && $_SESSION['lang'] === 'sw')
            ? 'Hakuna Huduma kwa sasa'
            : 'Currently Unavailable';
    }

    function app_render_unavailable_and_exit($statusCode = 503)
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo app_unavailable_message();
        exit;
    }

    set_exception_handler(function (Throwable $e) {
        error_log('Unhandled Exception: ' . $e->getMessage());
        app_render_unavailable_and_exit(503);
    });

    register_shutdown_function(function () {
        $error = error_get_last();
        if (!$error) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (in_array($error['type'], $fatalTypes, true)) {
            error_log('Fatal Error: ' . $error['message']);
            app_render_unavailable_and_exit(503);
        }
    });
}
