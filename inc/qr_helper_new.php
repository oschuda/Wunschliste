<?php
/**
 * QR-Code Hilfsfunktionen für phpWishlist
 * Verwendet phpqrcode Bibliothek mit Caching und anpassbaren Größen
 */

// Pfad zur phpqrcode Bibliothek (muss vom Benutzer heruntergeladen werden)
$phpqrcode_path = __DIR__ . '/phpqrcode.php';
$qr_cache_dir = __DIR__ . '/../data/qr_cache/';

if (file_exists($phpqrcode_path)) {
    require_once $phpqrcode_path;

    // Cache-Verzeichnis erstellen falls nicht vorhanden
    if (!is_dir($qr_cache_dir)) {
        mkdir($qr_cache_dir, 0755, true);
    }
} else {
    // Fallback falls Bibliothek nicht vorhanden
    function generateQRCode($data, $size = 4, $margin = 2) { return ''; }
    function getQRCodeHtml($url, $size = 80) { return ''; }
    function getCachedQRCode($data, $params = []) { return ''; }
    function getUserQRSettings($userId) { return ['size' => 'medium', 'cache_enabled' => true]; }
    function saveUserQRSettings($userId, $settings) { return false; }
    return;
}

/**
 * Benutzer-QR-Einstellungen laden
 */
function getUserQRSettings($userId) {
    // Datenbank-Verbindung
    require_once __DIR__ . '/db.php';
    $pdo = Database::get();

    $stmt = $pdo->prepare("SELECT qr_settings FROM app_users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();

    if ($result && $result['qr_settings']) {
        return json_decode($result['qr_settings'], true) ?: ['size' => 'medium', 'cache_enabled' => true];
    }

    return ['size' => 'medium', 'cache_enabled' => true];
}

/**
 * Benutzer-QR-Einstellungen speichern
 */
function saveUserQRSettings($userId, $settings) {
    require_once __DIR__ . '/db.php';
    $pdo = Database::get();

    $jsonSettings = json_encode($settings);
    $stmt = $pdo->prepare("UPDATE app_users SET qr_settings = ? WHERE id = ?");
    return $stmt->execute([$jsonSettings, $userId]);
}

/**
 * QR-Code-Größen-Mapping
 */
function getQRSizeConfig($sizeName) {
    $sizes = [
        'small' => ['qr_size' => 2, 'display_size' => 60],
        'medium' => ['qr_size' => 3, 'display_size' => 80],
        'large' => ['qr_size' => 4, 'display_size' => 100],
        'xlarge' => ['qr_size' => 5, 'display_size' => 120]
    ];

    return $sizes[$sizeName] ?? $sizes['medium'];
}

/**
 * Generiert einen QR-Code als Base64-String für Inline-Darstellung
 */
function generateQRCode(string $data, int $size = 4, int $margin = 2): string {
    if (empty($data)) {
        return '';
    }

    // Temporäre Datei für QR-Code Generierung
    $tempFile = tempnam(sys_get_temp_dir(), 'qr_');

    try {
        // QR-Code generieren
        QRcode::png($data, $tempFile, QR_ECLEVEL_M, $size, $margin);

        // Als Base64 encodieren
        $imageData = base64_encode(file_get_contents($tempFile));

        return 'data:image/png;base64,' . $imageData;
    } catch (Exception $e) {
        return '';
    } finally {
        // Temporäre Datei löschen
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

/**
 * Generiert einen gecachten QR-Code
 */
function getCachedQRCode($data, $params = []) {
    global $qr_cache_dir;

    if (empty($data)) {
        return '';
    }

    // Cache-Key generieren
    $cacheKey = md5($data . serialize($params));
    $cacheFile = $qr_cache_dir . $cacheKey . '.png';

    // Prüfen ob Cache aktiviert ist
    $cacheEnabled = $params['cache_enabled'] ?? true;

    if ($cacheEnabled && file_exists($cacheFile)) {
        // Cache-Datei verwenden
        $imageData = base64_encode(file_get_contents($cacheFile));
        return 'data:image/png;base64,' . $imageData;
    }

    // Neu generieren
    $qrSize = $params['qr_size'] ?? 3;
    $margin = $params['margin'] ?? 2;

    try {
        // QR-Code generieren
        QRcode::png($data, $cacheFile, QR_ECLEVEL_M, $qrSize, $margin);

        // Als Base64 zurückgeben
        $imageData = base64_encode(file_get_contents($cacheFile));
        return 'data:image/png;base64,' . $imageData;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Generiert HTML für QR-Code Anzeige mit Benutzereinstellungen
 */
function getQRCodeHtml(string $url, $userId = null, $size = null): string {
    if (empty($url)) {
        return '';
    }

    // URL normalisieren (http hinzufügen falls nötig)
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    // Benutzereinstellungen laden
    $settings = $userId ? getUserQRSettings($userId) : ['size' => 'medium', 'cache_enabled' => true];
    $sizeConfig = getQRSizeConfig($settings['size']);

    // Parameter für QR-Generierung
    $params = [
        'qr_size' => $sizeConfig['qr_size'],
        'margin' => 2,
        'cache_enabled' => $settings['cache_enabled']
    ];

    $qrData = getCachedQRCode($url, $params);

    if (empty($qrData)) {
        return '';
    }

    $displaySize = $size ?? $sizeConfig['display_size'];

    return '<div class="qr-code-container">
                <img src="' . $qrData . '" alt="QR-Code" width="' . $displaySize . '" height="' . $displaySize . '">
                <small>Scannen</small>
            </div>';
}