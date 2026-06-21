<?php

require_once __DIR__ . '/env.php';

if (!function_exists('app_bool_env')) {
    function app_bool_env(string $key, bool $default = false): bool
    {
        $value = env_value($key);
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string
    {
        $appUrl = env_value('APP_URL');
        if (!empty($appUrl)) {
            return rtrim($appUrl, '/');
        }

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') ||
            (strtolower((string)($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on')
        );
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $scriptDir = rtrim($scriptDir, '/');

        return $scheme . '://' . $host . ($scriptDir !== '' ? $scriptDir : '');
    }
}

if (!function_exists('app_mail_enabled')) {
    function app_mail_enabled(): bool
    {
        return app_bool_env('MAIL_ENABLED', false);
    }
}

if (!function_exists('app_cron_enabled')) {
    function app_cron_enabled(): bool
    {
        return app_bool_env('CRON_ENABLED', false);
    }
}

if (!function_exists('app_get_admin_email')) {
    function app_get_admin_email(PDO $conn): string
    {
        try {
            $stmtSettings = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key = 'email'");
            $email = trim((string)$stmtSettings->fetchColumn());
            if ($email !== '') {
                return $email;
            }
        } catch (Throwable $e) {
        }

        try {
            $stmtAdmin = $conn->query("SELECT email FROM admins ORDER BY id ASC LIMIT 1");
            return trim((string)$stmtAdmin->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('app_mailto_link')) {
    function app_mailto_link(string $email, string $subject, string $body): string
    {
        if ($email === '') {
            return '';
        }

        return 'mailto:' . $email
            . '?subject=' . rawurlencode($subject)
            . '&body=' . rawurlencode($body);
    }
}

if (!function_exists('app_log_event')) {
    function app_log_event(PDO $conn, string $type, ?string $details = null): void
    {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO activity_logs (log_type, ip_address, request_uri, details)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $type,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null,
                $details,
            ]);
        } catch (Throwable $e) {
        }
    }
}
