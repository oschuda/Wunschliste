<?php
/**
 * phpWishlist � Internationalisierung / gettext-Setup
 * Modernisiert 2026 � mit Fallback und robusterem Pfad-Handling
 */

declare(strict_types=1);

/**
 * Initialisiert gettext oder stellt Fallback bereit.
 * Wird einmalig beim Start aufgerufen (z. B. in config.php).
 */
function init_i18n(): void
{
    // Sprache aus Session holen (mit Fallback)
    $language = $_SESSION['lang'] ?? 'de';

    // M�gliche Locale-Varianten (falling back)
    $locales = [
        $language . '.UTF-8',
        $language . '.utf8',
        $language,
        substr($language, 0, 2), // z. B. 'de'
    ];

    $localeSet = false;

    foreach ($locales as $loc) {
        if (setlocale(LC_MESSAGES, $loc) !== false) {
            $localeSet = true;
            break;
        }
    }

    if (!$localeSet) {
        // Fallback: Englisch oder einfach gar nichts setzen
        setlocale(LC_MESSAGES, 'C');
        error_log("i18n: Keine passende Locale f�r '$language' gefunden. Fallback auf C.");
    }

    // gettext-Domain einrichten
    $domain = 'messages';

    // Pfad zur locale-Ordnerstruktur: projekt/locale/de/LC_MESSAGES/messages.mo
    // __DIR__ ist hier inc/, also zwei Ebenen hoch + /locale
    $localeDir = dirname(__DIR__, 2) . '/locale';

    if (function_exists('bindtextdomain') && function_exists('textdomain')) {
        bindtextdomain($domain, $localeDir);
        bind_textdomain_codeset($domain, 'UTF-8');
        textdomain($domain);
    } else {
        // Fallback-Funktionen definieren
        if (!function_exists('gettext')) {
            function gettext(string $msg): string
            {
                return $msg;
            }
        }
        if (!function_exists('ngettext')) {
            function ngettext(string $singular, string $plural, int $n): string
            {
                return $n === 1 ? $singular : $plural;
            }
        }
        error_log("i18n: gettext-Erweiterung fehlt � alle Texte bleiben englisch/original.");
    }
}

// Einmalig aufrufen (am besten in config.php oder ganz oben in index.php)
init_i18n();