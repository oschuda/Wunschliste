<?php
/**
 * Data Recovery & Cleanup Tool
 */
require_once 'inc/config.php';
require_once 'inc/db.php';

echo "<h1>Data Recovery & System Check</h1>";

try {
    $pdo = Database::get();
    
    // 1. Check existing tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "<b>Tables present in database:</b> " . implode(", ", $tables) . "<br><br>";

    // 2. Identify the source table for users
    $sourceTable = null;
    if (in_array('wl_accounts', $tables)) {
        $sourceTable = 'wl_accounts';
    } elseif (in_array('app_users', $tables)) {
        // Double check if app_users is empty
        $count = $pdo->query("SELECT COUNT(*) FROM app_users")->fetchColumn();
        if ($count == 0 && in_array('wl_accounts', $tables)) {
            $sourceTable = 'wl_accounts';
        }
    }

    if ($sourceTable == 'wl_accounts') {
        echo "Found source data in <b>wl_accounts</b>. Copying to app_users...<br>";
        
        // Ensure app_users is clean
        $pdo->exec("DELETE FROM app_users");
        
        // Map columns correctly
        $appCols = $pdo->query("PRAGMA table_info(app_users)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $srcCols = $pdo->query("PRAGMA table_info(wl_accounts)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $common = array_intersect($appCols, $srcCols);
        $colList = implode(", ", $common);
        
        $pdo->exec("INSERT INTO app_users ($colList) SELECT $colList FROM wl_accounts");
        
        // Fix defaults
        $pdo->exec("UPDATE app_users SET role = 'user' WHERE role IS NULL OR role = ''");
        $pdo->exec("UPDATE app_users SET enabled = 1 WHERE enabled IS NULL");
        
        echo "<b>Success:</b> User data restored from wl_accounts.<br>";
    } else {
        echo "No legacy 'wl_accounts' found or 'app_users' already contains data.<br>";
        $count = $pdo->query("SELECT COUNT(*) FROM app_users")->fetchColumn();
        echo "Users in app_users: $count<br>";
        
        if ($count > 0) {
            echo "<b>Sample Usernames in database:</b><br>";
            $users = $pdo->query("SELECT u_name FROM app_users LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
            foreach($users as $u) echo "- " . htmlspecialchars($u) . "<br>";
        }
    }

    // 3. Same for items
    if (in_array('wl_wishes', $tables)) {
        echo "<br>Found legacy <b>wl_wishes</b>. Copying to app_items...<br>";
        $pdo->exec("PRAGMA foreign_keys = OFF;");
        $pdo->exec("DELETE FROM app_items");
        
        $appICols = $pdo->query("PRAGMA table_info(app_items)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $srcICols = $pdo->query("PRAGMA table_info(wl_wishes)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $commonI = array_intersect($appICols, $srcICols);
        $colListI = implode(", ", $commonI);
        
        $pdo->exec("INSERT INTO app_items ($colListI) SELECT $colListI FROM wl_wishes");
        $pdo->exec("PRAGMA foreign_keys = ON;");
        echo "<b>Success:</b> Item data restored.<br>";
    }

} catch (Exception $e) {
    echo "<b style='color:red;'>Error:</b> " . $e->getMessage();
}

echo "<br><br><a href='debug_auth.php'>Go back to Auth Debugger</a> | <a href='login.php'>Go to Login</a>";
?>