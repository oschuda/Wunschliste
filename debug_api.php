<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'username' => $_SESSION['username'] ?? 'MISSING',
    'id' => $_SESSION['id'] ?? 'MISSING',
    'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookies' => $_COOKIE,
    'server' => $_SERVER['SERVER_NAME'] ?? 'local'
]);
