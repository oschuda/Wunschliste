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
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Shop-Addon</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="is-centered">

<header class="main-header">
    <div class="header-content">
        <h1>Profil - Shop-Addon</h1>
        <div class="header-actions">
            <a href="index.php" class="button button-secondary">Dashboard</a>
        </div>
    </div>
</header>

<main class="container">
    <section class="card">
        <div class="section-header">
            <h2>Hallo <?= htmlspecialchars($userData["u_name"]) ?></h2>
        </div>
        
        <div class="text-center">
            <h3>Dein persönlicher Shop-Addon Key</h3>
            <p>Diesen Key benötigst du für das Browser-Addon:</p>
            
            <div style="font-family: monospace; background: #000; color: #00FF00; padding: 20px; border-radius: 8px; margin: 20px 0; display: inline-block; border: 2px solid #00FF00; font-weight: bold; font-size: 1.4em; letter-spacing: 2px; filter: drop-shadow(0 0 5px rgba(0,255,0,0.3));">
                <?= htmlspecialchars($userData["api_key"]) ?>
            </div>

            <div class="status-message info" style="text-align: left; background: rgba(52, 152, 219, 0.1); border-left: 5px solid #3498db; padding: 15px; border-radius: 4px; color: white;">
               <strong>💡 Anleitung:</strong><br>
               Kopiere diesen Code und füge ihn im Browser-Addon (Tampermonkey) bei <code>const API_KEY = "..."</code> ein.
            </div>

            <div class="button-group align-center mt-20">
                <a href="tools.php" class="button button-success">Zur Installation</a>
                <a href="index.php" class="button button-secondary">Dashboard</a>
            </div>
        </div>
    </section>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> Wunschliste</div>
</footer>

</body>
</html>