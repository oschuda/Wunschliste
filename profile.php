<?php
declare(strict_types=1);
session_start([
    "cookie_httponly" => true,
    "cookie_secure"   => true,
    "cookie_samesite" => "None",
]);
require_once "inc/common.php";
require_once "inc/db.php";
if (empty($_SESSION["id"])) { header("Location: login.php"); exit; }
$pdo = Database::get();
$user = $pdo->prepare("SELECT u_name, api_key FROM app_users WHERE id = ?");
$user->execute([$_SESSION["id"]]);
$userData = $user->fetch();
if (!$userData["api_key"]) {
    $newKey = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE app_users SET api_key = ? WHERE id = ?")->execute([$newKey, $_SESSION["id"]]);
    $userData["api_key"] = $newKey;
}
?>
<!DOCTYPE html><html><head><title>Profil</title><link rel="stylesheet" href="style.css"></head><body>
<div class="box" style="max-width: 600px; margin: 40px auto; text-align: center;">
    <h1>Hallo <?= htmlspecialchars($userData["u_name"]) ?></h1>
    <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 20px 0;">
    
    <h3>Dein persönlicher Shop-Addon Key</h3>
    <p>Diesen Key benötigst du für das Browser-Addon:</p>
    
    <div style="font-family: monospace; background: #000; color: #00FF00; padding: 20px; border-radius: 8px; margin: 20px 0; display: inline-block; border: 2px solid #00FF00; font-weight: bold; font-size: 1.4em; letter-spacing: 2px; filter: drop-shadow(0 0 5px rgba(0,255,0,0.3));">
        <?= htmlspecialchars($userData["api_key"]) ?>
    </div>

    <div style="background: rgba(52, 152, 219, 0.1); color: var(--color-text); padding: 15px; border-left: 5px solid #3498db; margin: 20px 0; text-align: left; border-radius: 4px;">
       <strong>💡 Anleitung:</strong><br>
       Kopiere diesen Code und füge ihn im Browser-Addon (Tampermonkey) bei <code>const API_KEY = "..."</code> ein.
    </div>

    <div style="margin-top: 30px; display: flex; justify-content: center; gap: 15px;">
        <a href="tools.php" class="btn" style="background: #27ae60; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Zur Installation</a>
        <a href="index.php" class="btn" style="background: #34495e; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Dashboard</a>
    </div>
</div></body></html>