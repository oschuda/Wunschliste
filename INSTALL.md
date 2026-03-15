# Geschenke - Installation & Setup

## Übersicht

Geschenke ist eine selbst-gehostete Wunschlisten-Anwendung mit QR-Code-Unterstützung, entwickelt für Synology NAS und andere PHP-Umgebungen.

## Systemanforderungen

- **PHP**: 8.0 oder höher (mit PDO SQLite Extension)
- **Webserver**: Apache/Nginx mit PHP-Unterstützung
- **Datenbank**: SQLite (wird automatisch erstellt)
- **Speicher**: ~10MB für Anwendung + Datenbank

## Schnellinstallation

### 1. Dateien hochladen
Laden Sie alle Dateien in Ihr Web-Verzeichnis (z.B. `/volume1/web/wishlist/` auf Synology).

### 2. Datenbank initialisieren
Öffnen Sie in Ihrem Browser:
```
http://ihre-domain/init_db.php
```

Alternativ über SSH:
```bash
cd /pfad/zu/wishlist
php init_db.php
```

### 3. QR-Bibliothek installieren
```bash
cd inc/
wget https://github.com/t0k4rt/phpqrcode/archive/master.zip
unzip master.zip
mv phpqrcode-master/phpqrcode.php .
rm -rf phpqrcode-master*
```

### 4. Berechtigungen setzen
```bash
chmod 755 data/
chmod 755 data/qr_cache/
```

### 5. Anwendung testen
Öffnen Sie `http://ihre-domain/index.php` in Ihrem Browser.

## Standard-Administrator

Nach der Installation können Sie sich mit diesen Daten anmelden:
- **Benutzername**: `admin`
- **Passwort**: `admin123`

⚠️ **Wichtig**: Ändern Sie das Passwort nach der ersten Anmeldung!

## Verzeichnisstruktur

```
wishlist/
├── index.php              # Startseite/Login
├── wishes.php             # Wunschliste
├── addwish.php            # Wunsch hinzufügen
├── editwish.php           # Wunsch bearbeiten
├── claimed.php            # Reservierte Wünsche
├── admin.php              # Administration
├── settings.php           # Benutzereinstellungen
├── init_db.php            # Datenbank-Installation
├── migrate_db.php         # Datenbank-Migration
├── wishlist.sql           # Datenbank-Schema
├── style.css              # Stylesheet
├── inc/
│   ├── config.php         # Konfiguration
│   ├── db.php             # Datenbank-Klasse
│   ├── qr_helper.php      # QR-Code-Funktionen
│   └── phpqrcode.php      # QR-Bibliothek
├── data/
│   ├── wishlist.db        # SQLite-Datenbank
│   └── qr_cache/          # QR-Code-Cache
├── images/                # Bilder & Icons
├── locale/                # Sprachdateien
└── LANGUAGES              # Sprachkonfiguration
```

## Konfiguration

### Webserver (Apache)
```apache
<Directory "/pfad/zu/wishlist">
    AllowOverride All
    Require all granted
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
</Directory>
```

### Webserver (Nginx)
```nginx
location /wishlist {
    alias /pfad/zu/wishlist;
    index index.php;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
}
```

## Funktionen

### ✅ Basis-Funktionen
- Benutzerregistrierung & Login
- Wünsche hinzufügen/bearbeiten/löschen
- Wünsche reservieren (claimed)
- Mehrsprachig (Deutsch/Englisch)

### ✅ Administration
- Benutzerverwaltung
- Rollen-System (User/Moderator/Admin)
- E-Mail-Benachrichtigungen
- Logging von Admin-Aktionen

### ✅ QR-Code-Features
- Automatische QR-Generierung für Links
- Anpassbare Größen (4 Optionen)
- Performance-Caching
- Responsive Design

## Sicherheit

- **CSRF-Schutz**: Alle Formulare geschützt
- **Passwort-Hashing**: bcrypt mit PHP 8.0+
- **Session-Sicherheit**: HttpOnly, Secure, SameSite
- **Input-Validierung**: Alle Eingaben gefiltert
- **SQL-Injection-Schutz**: PDO Prepared Statements

## Fehlerbehebung

### Datenbank-Fehler
```bash
# Datenbank zurücksetzen
rm data/wishlist.db
php init_db.php
```

### QR-Codes funktionieren nicht
1. Prüfen: `inc/phpqrcode.php` existiert?
2. Cache-Verzeichnis: `ls -la data/qr_cache/`
3. PHP-GD aktiv: `php -m | grep gd`

### Berechtigungsfehler
```bash
# Auf Synology
chown -R http:http /volume1/web/wishlist/data/
chmod -R 755 /volume1/web/wishlist/data/
```

## Support

Bei Problemen:
1. Überprüfen Sie die PHP-Fehlerlogs
2. Testen Sie `phpinfo.php` für Systeminfo
3. Prüfen Sie Dateiberechtigungen
4. Datenbank-Integrität: `sqlite3 data/wishlist.db ".schema"`

## Lizenz

Open-Source - keine kommerzielle Nutzung ohne Genehmigung.

---

**Geschenke 2026** - Selbst-gehostete Wunschlisten mit QR-Code-Unterstützung