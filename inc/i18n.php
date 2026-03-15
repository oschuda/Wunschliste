<?php
declare(strict_types=1);

function init_i18n(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $language = $_SESSION["lang"] ?? "de";

    $locales = [
        $language . ".UTF-8",
        $language . ".utf8",
        ($language == "en" ? "en_US.UTF-8" : "de_DE.UTF-8"),
        $language
    ];

    foreach ($locales as $loc) {
        if (setlocale(LC_MESSAGES, $loc) !== false) break;
    }

    $domain = "messages";
    $localeDir = dirname(__DIR__) . "/locale"; 
    
    if (function_exists("bindtextdomain")) {
        bindtextdomain($domain, $localeDir);
        bind_textdomain_codeset($domain, "UTF-8");
        textdomain($domain);
    }
}
init_i18n();
