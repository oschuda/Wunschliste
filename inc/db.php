<?php
/**
 * Moderne PDO-Datenbankverbindung f�r phpWishlist (SQLite)
 * Original: class.db.inc.php ~2004 ? modernisiert 2026
 */

declare(strict_types=1);

final class Database
{
    private const DB_PATH = __DIR__ . '/../data/wishlist.sqlite';

    private static ?PDO $instance = null;

    /**
     * Gibt die PDO-Instanz zur�ck (Singleton-Pattern)
     * Erstellt die Verbindung + Tabellen bei Bedarf nur einmal
     */
    public static function get(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = 'sqlite:' . self::DB_PATH;

                self::$instance = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,          // echte Prepared Statements
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
                // UTF-8 Encoding für SQLite setzen
                self::$instance->exec('PRAGMA encoding = "UTF-8";');
                // SQLite-spezifische Optimierungen
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::$instance->exec('PRAGMA journal_mode = WAL;');     // bessere Concurrency
                self::$instance->exec('PRAGMA synchronous = NORMAL;');

                // Tabellen einmalig anlegen (Idempotent)
                self::$instance->exec(<<<SQL
                    CREATE TABLE IF NOT EXISTS app_users (
                        id          INTEGER PRIMARY KEY AUTOINCREMENT,
                        f_name      TEXT,
                        l_name      TEXT,
                        u_name      TEXT NOT NULL UNIQUE,
                        p_word      TEXT NOT NULL,
                        enabled     INTEGER DEFAULT 1 CHECK (enabled IN (0,1)),
                        s_details   INTEGER DEFAULT 0 CHECK (s_details IN (0,1)),
                        b_day       INTEGER,
                        b_month     INTEGER,
                        b_year      INTEGER,
                        p_address   TEXT,
                        suburb      TEXT,
                        state       TEXT,
                        postcode    TEXT,
                        country     TEXT,
                        email       TEXT,
                        phone       TEXT,
                        role        TEXT DEFAULT 'user',
                        qr_size     INTEGER DEFAULT 3,
                        qr_use_cache INTEGER DEFAULT 1,
                        created     DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_login  DATETIME
                    );

                    CREATE TABLE IF NOT EXISTS app_items (
                        id          INTEGER PRIMARY KEY AUTOINCREMENT,
                        owner       INTEGER NOT NULL,
                        title       TEXT NOT NULL,
                        url         TEXT,
                        notes       TEXT,
                        claimed     INTEGER,
                        price       REAL,
                        created     DATETIME DEFAULT CURRENT_TIMESTAMP,

                        FOREIGN KEY (owner)  REFERENCES app_users(id) ON DELETE CASCADE,
                        FOREIGN KEY (claimed) REFERENCES app_users(id) ON DELETE SET NULL
                    );
SQL
                );

            } catch (PDOException $e) {
                // Bei Fehlern Details für Admin ausgeben (nur für Debugging!)
                error_log("Database Error: " . $e->getMessage());
                http_response_code(500);
                die('Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()));
            }
        }

        return self::$instance;
    }

    /**
     * Explizites Schlie�en (meist nicht n�tig, aber f�r Konsistenz)
     */
    public static function close(): void
    {
        self::$instance = null;
    }

    // Verhindert Klonen & Instanziieren von au�en
    private function __construct() {}
    private function __clone() {}
}

/**
 * Hilfsfunktion f�r schnelle Queries (optional � empfohlen: Prepared Statements nutzen)
 * Beispiel: Database::query("SELECT * FROM app_users WHERE id = ?", [$id]);
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = Database::get()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
