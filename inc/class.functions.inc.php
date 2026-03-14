<?php
/**
 * phpWishlist � Moderne Hilfsfunktionen (ehemals class.functions.inc.php)
 * Original ~2004 ? �berarbeitet 2026
 */

declare(strict_types=1);

/**
 * Bereinigt User-Input f�r sichere Verwendung (HTML, SQL, etc.)
 *
 * @param string $input Der zu bereinigende String
 * @return string Bereinigter String
 */
function cleanup_input(string $input): string
{
    // strip_tags entfernt HTML/PHP-Tags
    $input = strip_tags($input);

    // htmlspecialchars sch�tzt vor XSS
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // trim entfernt unn�tige Leerzeichen
    $input = trim($input);

    // addslashes ist hier NICHT mehr n�tig, da wir Prepared Statements nutzen!
    // Es wird nur noch f�r sehr spezielle F�lle ben�tigt (z. B. LIKE mit %)

    return $input;
}

/**
 * Stellt sicher, dass ein Link mit http:// oder https:// beginnt
 *
 * @param string|null $input URL oder leer
 * @return string|null Bereinigte URL oder null
 */
function normalize_url(?string $input): ?string
{
    if (empty($input)) {
        return null;
    }

    $input = trim($input);

    // Bereits mit Protokoll ? unver�ndert zur�ckgeben
    if (preg_match('#^https?://#i', $input)) {
        return $input;
    }

    // Kein Protokoll ? https:// voranstellen (sicherer als http)
    return 'https://' . $input;
}

/**
 * Alias f�r sichere gettext-Ausgabe (print escaped gettext)
 *
 * @param string $message Die zu �bersetzende Nachricht
 * @return void
 */
function p(string $message): void
{
    echo htmlspecialchars(gettext($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Moderner E-Mail-Versand (empfohlen: sp�ter PHPMailer oder Symfony Mailer nutzen)
 *
 * @param string $to Empf�nger
 * @param string $subject Betreff
 * @param string $message Inhalt
 * @param string $from Absender-Name + E-Mail (Format: "Name <email@domain.de>")
 * @return bool Erfolg
 */
function send_simple_email(string $to, string $subject, string $message, string $from): bool
{
    $headers = [
        'From'         => $from,
        'Reply-To'     => $from,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer'     => 'phpWishlist (PHP ' . PHP_VERSION . ')',
    ];

    return mail($to, $subject, $message, $headers);
}