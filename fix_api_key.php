<?php
require_once "inc/db.php";
try {
    $pdo = Database::get();
    // Versuche, die Spalte ohne UNIQUE Constraint hinzuzufügen, falls der UNIQUE Constraint das Problem mit existierenden Daten ist
    $pdo->exec("ALTER TABLE app_users ADD COLUMN api_key TEXT;");
    echo "Spalte api_key wurde erfolgreich hinzugefügt.";
} catch (Exception $e) {
    echo "Fehler beim Hinzufügen (evtl. existiert sie doch schon): " . $e->getMessage();
}