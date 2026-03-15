<?php
declare(strict_types=1);
session_start(["cookie_httponly" => true, "cookie_secure" => true, "cookie_samesite" => "None"]);
require_once "inc/common.php";
require_once "inc/db.php";
if (empty($_SESSION["id"])) { header("Location: login.php"); exit; }
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Addon Installation</title>
<style>
    body { background-color: #000 !important; background-image: none !important; color: #fff !important; font-family: sans-serif; padding: 40px; text-align: center; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; }
    .container { background: #1a1a1a !important; padding: 30px; border-radius: 12px; margin: 20px auto; max-width: 600px; border: 2px solid #333; box-shadow: 0 15px 40px rgba(0,0,0,0.6); }
    h1 { color: #fff !important; margin-bottom: 20px; font-size: 2em; }
    h3 { color: #fff !important; margin-bottom: 10px; border-bottom: 1px solid #444; padding-bottom: 5px; }
    p { color: #ddd !important; line-height: 1.6; margin: 10px 0; }
    .key-box { font-family: 'Courier New', monospace; background: #000 !important; color: #00FF00 !important; padding: 18px; border-radius: 8px; margin: 15px 0; display: inline-block; border: 2px solid #00FF00; font-weight: bold; font-size: 1.2em; filter: drop-shadow(0 0 5px #00FF00); word-break: break-all; }
    .info-box { font-size: 0.95em; color: #fff !important; margin: 20px 0; border: 1px dashed #555; padding: 15px; background: #222 !important; border-radius: 6px; text-align: left; }
    .button-install { background: #27ae60; color: #fff !important; padding: 12px 25px; text-decoration: none; border-radius: 6px; display: inline-block; margin: 15px 0; font-weight: bold; font-size: 1.1em; transition: transform 0.2s, background 0.3s; border: none; }
    .button-install:hover { background: #2ecc71; transform: scale(1.05); }
    code { background: #444; color: #f9ca24; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
    a { color: #3498db; text-decoration: none; }
    a:hover { text-decoration: underline; }
</style>
</head>
<body>
<h1>?? Shop-Connector Setup</h1>
<div class="container">
    <h3>Schritt 1: Plugin installieren</h3>
    <p>Klicke auf den Button unten, um das Script in Tampermonkey zu laden.</p>
    <a href="wishlist-adder.user.js" class="button-install">?? Script jetzt installieren</a>
    
    <div class="info-box">
        <strong style="color: #ff4757;">?? WICHTIG:</strong><br>
        Falls Tampermonkey den Code nur als Text anzeigt:<br>
        1. Kopiere den gesamten Text (Strg+A, Strg+C).<br>
        2. In Tampermonkey: "Neues Script" erstellen.<br>
        3. Den alten Inhalt komplett l�schen und "Einf�gen".<br>
        4. Wichtig: <strong>Datei &rarr; Speichern</strong> klicken.
    </div>

    <hr style="border: 0; border-top: 1px solid #444; margin: 30px 0;">

    <h3>Schritt 2: Dein pers�nlicher Key</h3>
    <p>Suche im Script nach <code>DEIN_KEY_HIER</code> und ersetze es durch diesen Code:</p>
    
    <div class="key-box">
        const API_KEY = "<?php
        try {
            $db = Database::get();
            $stmt = $db->prepare("SELECT api_key FROM app_users WHERE id = ?");
            $stmt->execute([$_SESSION["id"]]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            echo htmlspecialchars($u["api_key"] ?? "Profil �ffnen!");
        } catch (Exception $e) { echo "Fehler!"; }
        ?>";
    </div>

    <p style="margin-top: 20px; font-size: 0.9em;">
        Hinterlegt in deinem <a href="profile.php">Nutzer-Profil</a>.
    </p>
</div>
<p style="margin-top: 30px;"><a href="index.php" style="color: #666;">&larr; Zur�ck zum Dashboard</a></p>
</body></html>
