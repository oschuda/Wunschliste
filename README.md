# 🎁 Geschenke - Die moderne Wunschliste

Eine schlanke, privatsphäre-freundliche Web-Anwendung zum Verwalten und Teilen von Wunschlisten. Modernisiert für **PHP 8.4** und **SQLite**.

## 🚀 Features

- **Self-Hosted**: Volle Kontrolle über deine Daten (ideal für Synology NAS, Raspberry Pi oder Webhosting).
- **Universal Userscript 2.0**: Füge Artikel direkt aus jedem Online-Shop (Amazon, eBay, Etsy, etc.) mit einem Klick hinzu – inklusive automatischer Preis-Erkennung.
- **Multi-User**: Jeder Benutzer hat seine eigene Wunschliste.
- **Reservierungssystem**: Freunde und Familie können Wünsche anonym reservieren (der Besitzer sieht nicht, was reserviert wurde – Überraschungseffekt!).
- **Reaktives Design**: Optimiert für Desktop und Mobile mit Dark-Mode Unterstützung.
- **API-Anbindung**: Einfache Integration externer Tools über API-Keys.

## 🛠 Installation

1. Kopiere alle Dateien auf deinen Webserver.
2. Benenne `inc/config.php.example` (falls vorhanden) in `inc/config.php` um und passe die Daten an.
3. Die SQLite-Datenbank wird beim ersten Aufruf automatisch initialisiert.
4. Alle weiteren Details findest du in der `INSTALL.md`.

## 🛒 Userscript (Browser-Addon)

Das Herzstück für den Komfort ist das Userscript. Installiere **Tampermonkey** in deinem Browser und füge das Skript aus `wishlist-adder.user.js` hinzu. 
Trage deinen persönlichen API-Key aus deinem Profil in der Web-Oberfläche ein, um Artikel direkt beim Shoppen auf deine Liste zu setzen.

## ⚖️ Lizenz

Dieses Projekt ist Open Source. Bitte beachte die Lizenzbedingungen im Projektordner.
