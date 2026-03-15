<?php
/**
 * Wunschliste - Logout-Skript (modernisiert für PHP 8.4)

 */

declare(strict_types=1);

// Session starten (mit denselben sicheren Optionen wie in login/register)
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Lax',
]);

// Alle Session-Daten löschen
$_SESSION = [];

// Session-Cookie ungültig machen (optional, aber gute Praxis)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Session komplett zerstören
session_destroy();

// Redirect mit HTTP 303 (See Other) - sauberer als 302 bei POST ? GET
header('Location: login.php', true, 303);
exit;
