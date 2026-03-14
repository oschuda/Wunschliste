<?php
/**
 * Safe Item Synchronization with Temporary Foreign Key Disabling
 */
require_once 'inc/config.php';
require_once 'inc/db.php';

try {
    $pdo = Database::get();
    
    // Check tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('wl_wishes', $tables)) {
        echo "Found legacy wl_wishes. Starting safe transfer...<br>";

        // 1. Disable Foreign Keys temporarily
        $pdo->exec("PRAGMA foreign_keys = OFF;");
        
        // 2. Clear new table
        $pdo->exec("DELETE FROM app_items");
        echo "Cleaned app_items.<br>";

        // 3. Define columns
        $appItemsCols = $pdo->query("PRAGMA table_info(app_items)")->fetchAll(PDO::FETCH_ASSOC);
        $legacyCols = $pdo->query("PRAGMA table_info(wl_wishes)")->fetchAll(PDO::FETCH_ASSOC);
        
        $legacyColNames = array_column($legacyCols, 'name');
        $appItemsColNames = array_column($appItemsCols, 'name');
        $commonCols = array_intersect($legacyColNames, $appItemsColNames);
        $colList = implode(", ", $commonCols);
        
        // 4. Copy data
        $pdo->exec("INSERT INTO app_items ($colList) SELECT $colList FROM wl_wishes");
        
        // 5. Re-enable Foreign Keys
        $pdo->exec("PRAGMA foreign_keys = ON;");
        
        // 6. Fix potential orphans (if owner doesn't exist anymore)
        // Just in case some users were deleted in the past but their wishes stayed
        $stmt = $pdo->prepare("DELETE FROM app_items WHERE owner NOT IN (SELECT id FROM app_users)");
        $stmt->execute();
        $orphansDeleted = $stmt->rowCount();
        
        if ($orphansDeleted > 0) {
            echo "Cleaned up $orphansDeleted orphaned items.<br>";
        }

        $count = $pdo->query("SELECT COUNT(*) FROM app_items")->fetchColumn();
        echo "<b>Success!</b> $count items synchronized safely to app_items.<br>";
    } else {
        echo "wl_wishes table not found.<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>