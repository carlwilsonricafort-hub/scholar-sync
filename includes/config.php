<?php
/**
 * Application configuration.
 *
 * Keep credentials in the web-server environment (or a local, ignored .env
 * file loaded by deployment tooling). Never add real credentials here.
 */

declare(strict_types=1);

function envValue(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS'));
define('DB_NAME', envValue('DB_NAME', 'student_portal'));

define('API_BASE', rtrim(envValue('API_BASE', 'http://localhost/student-portal/api'), '/'));

define('PAYPAL_CLIENT_ID', envValue('PAYPAL_CLIENT_ID'));
define('PAYPAL_CLIENT_SECRET', envValue('PAYPAL_CLIENT_SECRET'));
define('PAYPAL_MODE', envValue('PAYPAL_MODE', 'sandbox'));
define('PAYPAL_API_BASE', rtrim(envValue('PAYPAL_API_BASE', 'https://api-m.sandbox.paypal.com'), '/'));
define('APP_URL', rtrim(envValue('APP_URL', 'http://localhost/student-portal'), '/'));

const DUES = [
    'Prelim' => [
        'amount' => 1500.00,
        'due_date' => '2025-09-30',
    ],
    'Midterm' => [
        'amount' => 1500.00,
        'due_date' => '2025-11-15',
    ],
    'Finals' => [
        'amount' => 1500.00,
        'due_date' => '2026-01-10',
    ],
];
