<?php
/**
 * One-time Fix: Repopulate app_users from wl_accounts
 */
require_once 'inc/config.php';
require_once 'inc/db.php';

try {
    $pdo = Database::get();
    
    // Check tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('wl_accounts', $tables)) {
        echo "Found legacy wl_accounts. Moving data...<br>";
        
        // Ensure app_users exists and has correct columns
        // (db.php already handles IF NOT EXISTS, but we make sure here)
        
        // Let's check how many columns app_users has
        $appUsersCols = $pdo->query("PRAGMA table_info(app_users)")->fetchAll(PDO::FETCH_ASSOC);
        $legacyCols = $pdo->query("PRAGMA table_info(wl_accounts)")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Legacy columns: " . count($legacyCols) . "<br>";
        echo "New columns: " . count($appUsersCols) . "<br>";
        
        // Empty app_users to avoid duplicates
        $pdo->exec("DELETE FROM app_users");
        
        // Dynamic insert: map existing columns and add defaults for new ones
        $legacyColNames = array_column($legacyCols, 'name');
        $appUsersColNames = array_column($appUsersCols, 'name');
        
        $commonCols = array_intersect($legacyColNames, $appUsersColNames);
        $colList = implode(", ", $commonCols);
        
        // Insert common data
        $pdo->exec("INSERT INTO app_users ($colList) SELECT $colList FROM wl_accounts");
        
        // Set defaults for role if missing
        if (in_array('role', $appUsersColNames)) {
            $pdo->exec("UPDATE app_users SET role = 'user' WHERE role IS NULL OR role = ''");
            // Set first user to admin as a safety measure
            $pdo->exec("UPDATE app_users SET role = 'admin' WHERE id = (SELECT MIN(id) FROM app_users)");
        }
        
        echo "<b>Success!</b> Data synchronized. Please try logging in now with your old credentials.<br>";
    } else {
        echo "wl_accounts table not found. It might have been renamed already.<br>";
        $count = $pdo->query("SELECT COUNT(*) FROM app_users")->fetchColumn();
        echo "Current user count in app_users: $count<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>