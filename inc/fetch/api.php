<?php
/**
 * Wunschliste - API Endpunkt für URL-Fetch
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Lax',
]);

// Nur angemeldete Benutzer erlauben
if (empty($_SESSION['username'])) {
    http_response_code(403);
    die('Unauthorized');
}

require_once __DIR__ . '/fetcher.php';
require_once __DIR__ . '/../common.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? null)) {
    $url = trim($_POST['url'] ?? '');
    
    if (empty($url)) {
        echo json_encode(['error' => 'No URL provided']);
        exit;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    try {
        $metadata = UrlMetadataFetcher::fetch($url);
        echo json_encode($metadata);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request or CSRF failed']);
}
