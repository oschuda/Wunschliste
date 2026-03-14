<?php
/**
 * Auth Debugger - Check credentials in app_users
 */
require_once 'inc/config.php';
require_once 'inc/db.php';

echo "<h1>Auth Debugger</h1>";

if (isset($_POST['check'])) {
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    
    try {
        $pdo = Database::get();
        $stmt = $pdo->prepare("SELECT id, u_name, p_word, enabled, role FROM app_users WHERE u_name = ?");
        $stmt->execute([$user]);
        $row = $stmt->fetch();
        
        if ($row) {
            echo "User found: " . htmlspecialchars($row['u_name']) . "<br>";
            echo "Enabled: " . ($row['enabled'] ? "Yes" : "No") . "<br>";
            echo "Role: " . htmlspecialchars($row['role']) . "<br>";
            
            if (password_verify($pass, $row['p_word'])) {
                echo "<b style='color:green;'>Password matches!</b><br>";
            } else {
                echo "<b style='color:red;'>Password DOES NOT match.</b><br>";
                // Test old MD5 if applicable (legacy phpWishlist used MD5 sometimes)
                if (md5($pass) === $row['p_word']) {
                    echo "Found MD5 match! Auto-migrating to modern hash...<br>";
                    $newHash = password_hash($pass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE app_users SET p_word = ? WHERE id = ?")->execute([$newHash, $row['id']]);
                    echo "Migration success. Please try logging in again.";
                }
            }
        } else {
            echo "<b style='color:red;'>User not found in app_users.</b><br>";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
<form method="POST">
    User: <input type="text" name="user"><br>
    Pass: <input type="password" name="pass"><br>
    <input type="submit" name="check" value="Check Credentials">
</form>
<a href="login.php">Back to Login</a>
