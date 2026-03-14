# QR-Code Funktionen in phpWishlist

## Übersicht

phpWishlist bietet einzigartige QR-Code-Funktionen für Wunschlisten, die es Benutzern ermöglichen, Links direkt mit dem Smartphone zu scannen.

## Features

### ✅ Automatische QR-Code Generierung
- QR-Codes erscheinen automatisch neben Links in Wunschlisten
- Unterstützt sowohl eigene Wünsche als auch reservierte Wünsche
- Responsive Design für mobile Geräte

### ✅ Performance-Optimierung (Caching)
- QR-Codes werden gecacht, um Ladezeiten zu verbessern
- Cache-Verzeichnis: `data/qr_cache/`
- Automatische Cache-Verwaltung

### ✅ Anpassbare Größen
- 4 Größenoptionen: Klein, Mittel, Groß, Extra Groß
- Individuelle Einstellungen pro Benutzer
- Live-Vorschau in den Einstellungen

## Installation

### 1. phpqrcode Bibliothek herunterladen
```bash
cd inc/
wget https://github.com/t0k4rt/phpqrcode/archive/master.zip
unzip master.zip
mv phpqrcode-master/phpqrcode.php .
rm -rf phpqrcode-master master.zip
```

### 2. Datenbank aktualisieren
```sql
ALTER TABLE wl_accounts ADD COLUMN qr_settings TEXT DEFAULT '{"size": "medium", "cache_enabled": true}';
```

### 3. Cache-Verzeichnis erstellen
```bash
mkdir -p data/qr_cache
chmod 755 data/qr_cache
```

## Verwendung

### Für Benutzer
1. **Einstellungen anpassen**: Über "Einstellungen" in der Wunschliste
2. **QR-Codes verwenden**: Scannen Sie QR-Codes mit der Kamera-App
3. **Direkter Zugriff**: QR-Codes führen direkt zu den Produktseiten

### Für Administratoren
- QR-Codes sind automatisch aktiviert
- Cache kann über Benutzereinstellungen deaktiviert werden
- Keine zusätzliche Konfiguration nötig

## Technische Details

### Cache-System
- QR-Codes werden als PNG-Dateien im Cache-Verzeichnis gespeichert
- Cache-Key basiert auf URL und Parametern (MD5-Hash)
- Automatische Bereinigung nicht implementiert (Cache wächst mit der Zeit)

### Größen-Optionen
- **Klein**: 60x60px (QR-Size: 2)
- **Mittel**: 80x80px (QR-Size: 3) - Standard
- **Groß**: 100x100px (QR-Size: 4)
- **Extra Groß**: 120x120px (QR-Size: 5)

### Sicherheit
- QR-Codes werden serverseitig generiert
- Keine sensiblen Daten in QR-Codes
- URLs werden normalisiert (http → https falls nötig)

## Alleinstellungsmerkmale

Im Vergleich zu anderen Wunschlisten-Tools bietet phpWishlist:

- **Lokale Generierung**: Keine externen APIs oder Cloud-Dienste
- **Datenschutz**: QR-Codes bleiben auf Ihrem Server
- **Anpassung**: Individuelle Größen und Caching-Optionen
- **Integration**: Nahtlose Integration in bestehende Wunschlisten

## Fehlerbehebung

### QR-Codes werden nicht angezeigt
1. Prüfen Sie, ob `inc/phpqrcode.php` vorhanden ist
2. Überprüfen Sie die Dateiberechtigungen für `data/qr_cache/`
3. Aktivieren Sie PHP-GD Extension falls deaktiviert

### Cache funktioniert nicht
1. Prüfen Sie Schreibrechte für `data/qr_cache/`
2. Deaktivieren Sie Caching in den Einstellungen zum Testen

### QR-Codes sind zu klein/groß
1. Passen Sie die Größe in den Benutzereinstellungen an
2. Überprüfen Sie responsive CSS-Regeln

## Zukunft

Mögliche Erweiterungen:
- QR-Codes für ganze Listen
- Druckbare QR-Karten
- Scan-Tracking und Analytics
- Verschlüsselte QR-Codes für temporären Zugriff