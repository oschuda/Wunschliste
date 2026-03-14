<?php
/**
 * Wishlist Application - Common Helpers
 */

// UTF-8 Header setzen
header('Content-Type: text/html; charset=UTF-8');

// --------------------------------------------------
// I18n - PHP based Internationalization
// --------------------------------------------------
function translate(string $text): string {
    static $translations = null;
    
    if ($translations === null) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $lang = $_SESSION['lang'] ?? 'de';
        // Erlaubte Sprachen validieren (Sicherheit)
        if (!in_array($lang, ['de', 'en'])) {
            $lang = 'de';
        }
        
        $langFile = __DIR__ . "/../langs/{$lang}.php";
        if (file_exists($langFile)) {
            $translations = include($langFile);
        } else {
            $translations = [];
        }
    }
    
    return $translations[$text] ?? $text;
}

/**
 * Fallback for legacy gettext calls (only if not already provided by PHP extension)
 */
if (!function_exists('gettext')) {
    function gettext(string $text): string {
        return translate($text);
    }
}

if (!function_exists('_')) {
    function _(string $text): string {
        return translate($text);
    }
}

function sanitize_output(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function format_date(?string $date): string {
    if (!$date) return '';
    return date('d.m.Y', strtotime($date));
}
?>