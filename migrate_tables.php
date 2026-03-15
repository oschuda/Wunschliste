<?php
/**
 * Einmaliges Migrationsskript zur Umbenennung der Tabellen
 */

require_once __DIR__ . '/inc/db.php';

try {
    $pdo = Database::get();
    
    // Prüfen ob die alten Tabellen existieren und umbenennen
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $renamed = false;
    
    if (in_array('app_users', $tables)) {
        if (in_array('users', $tables)) {
            $pdo->exec("DROP TABLE users");
        }
        $pdo->exec("ALTER TABLE app_users RENAME TO users");
        echo "Tabelle app_users zu users umbenannt.\n";
        $renamed = true;
    }
    
    if (in_array('app_items', $tables)) {
        if (in_array('wishes', $tables)) {
            $pdo->exec("DROP TABLE wishes");
        }
        $pdo->exec("ALTER TABLE app_items RENAME TO wishes");
        echo "Tabelle app_items zu wishes umbenannt.\n";
        $renamed = true;
    }
    
    if (!$renamed) {
        echo "Keine Tabellen zum Umbenennen gefunden oder bereits migriert.\n";
    }

} catch (Exception $e) {
    die("Fehler bei der Migration: " . $e->getMessage());
}
