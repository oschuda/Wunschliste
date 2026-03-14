<?php
/**
 * One-time Fix: Repopulate app_items from wl_wishes
 */
require_once 'inc/config.php';
require_once 'inc/db.php';

try {
    $pdo = Database::get();
    
    // Check tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('wl_wishes', $tables)) {
        echo "Found legacy wl_wishes. Moving items...<br>";
        
        // Structure check for app_items
        $appItemsCols = $pdo->query("PRAGMA table_info(app_items)")->fetchAll(PDO::FETCH_ASSOC);
        $legacyCols = $pdo->query("PRAGMA table_info(wl_wishes)")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Legacy columns: " . count($legacyCols) . "<br>";
        echo "New columns: " . count($appItemsCols) . "<br>";
        
        // Empty app_items to avoid duplicates
        $pdo->exec("DELETE FROM app_items");
        
        // Dynamic insert: map existing columns
        $legacyColNames = array_column($legacyCols, 'name');
        $appItemsColNames = array_column($appItemsCols, 'name');
        
        $commonCols = array_intersect($legacyColNames, $appItemsColNames);
        $colList = implode(", ", $commonCols);
        
        // Insert common data
        $pdo->exec("INSERT INTO app_items ($colList) SELECT $colList FROM wl_wishes");
        
        $count = $pdo->query("SELECT COUNT(*) FROM app_items")->fetchColumn();
        echo "<b>Success!</b> $count items synchronized to app_items.<br>";
    } else {
        echo "wl_wishes table not found. It might have been renamed or data is already in app_items.<br>";
        $count = $pdo->query("SELECT COUNT(*) FROM app_items")->fetchColumn();
        echo "Current items count in app_items: $count<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>