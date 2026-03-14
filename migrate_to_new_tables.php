<?php
/**
 * Database Migration Script
 * Renames legacy wl_ tables to modern names
 */

require_once 'inc/config.php';
require_once 'inc/db.php';

try {
    $pdo = Database::get();
    
    echo "<h1>Database Migration</h1>";
    
    // Check if old tables exist and new ones don't
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $migrations = [
        'wl_accounts' => 'app_users',
        'wl_wishes'   => 'app_items'
    ];
    
    foreach ($migrations as $old => $new) {
        if (in_array($old, $tables)) {
            if (!in_array($new, $tables)) {
                echo "Renaming $old to $new... ";
                $pdo->exec("ALTER TABLE $old RENAME TO $new");
                echo "Done.<br>";
            } else {
                echo "Table $new already exists. Skipping $old.<br>";
            }
        } else {
            echo "Table $old does not exist. Skipping.<br>";
        }
    }
    
    echo "<p>Migration completed successfully.</p>";
    
    // Neue Spalten sicherstellen, falls sie fehlen (Mapping-Korrektur)
    echo "Checking for missing columns in app_users... ";
    $cols = $pdo->query("PRAGMA table_info(app_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $needed = [
        'role' => "TEXT DEFAULT 'user'",
        'qr_size' => "INTEGER DEFAULT 3",
        'qr_use_cache' => "INTEGER DEFAULT 1"
    ];
    foreach ($needed as $col => $def) {
        if (!in_array($col, $cols)) {
            $pdo->exec("ALTER TABLE app_users ADD COLUMN $col $def");
            echo "Added $col. ";
        }
    }
    echo "Done.<br>";

    echo "<a href='index.php'>Go to Home</a>";

} catch (Exception $e) {
    echo "<p style='color:red;'>Error during migration: " . htmlspecialchars($e->getMessage()) . "</p>";
}
