<?php
/**
 * Wunschliste - Wunsch bearbeiten (modernisiert für PHP 8.4 + SQLite)
 */
declare(strict_types=1);
session_start([
    "cookie_httponly" => true,
    "cookie_secure"   => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"),
    "cookie_samesite" => "Lax",
]);
if (empty($_SESSION["username"]) || empty($_SESSION["id"])) { header("Location: login.php"); exit; }
$currentUserId = (int)$_SESSION["id"];
require_once "inc/config.php";
require_once "inc/i18n.php";
function getCsrfToken(): string { if (empty($_SESSION["csrf_token"])) { $_SESSION["csrf_token"] = bin2hex(random_bytes(32)); } return $_SESSION["csrf_token"]; }
function isValidCsrf(): bool { $token = $_POST["csrf_token"] ?? ""; return hash_equals($_SESSION["csrf_token"] ?? "", $token); }
$wishId = filter_input(INPUT_GET, "editID", FILTER_VALIDATE_INT);
if ($wishId === false || $wishId <= 0) { header("Location: list.php"); exit; }
$pdo = Database::get();
$stmt = $pdo->prepare("SELECT id, title, url, notes, price FROM app_items WHERE id = ? AND owner = ?");
$stmt->execute([$wishId, $currentUserId]);
$wish = $stmt->fetch();
if (!$wish) { header("Location: list.php"); exit; }
$errors = []; $success = false;
$formData = ["title" => $wish["title"] ?? "", "url" => $wish["url"] ?? "", "notes" => $wish["notes"] ?? "", "price" => (float)($wish["price"] ?? 0)];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["back"])) { header("Location: list.php"); exit; }
    if (!isValidCsrf()) { $errors[] = "Sicherheitsfehler."; } else {
        $title = trim($_POST["desc"] ?? ""); $url = trim($_POST["link"] ?? ""); $notes = trim($_POST["notes"] ?? ""); $price = (float)str_replace(",", ".", $_POST["price"] ?? "0");
        $formData = ["title" => $title, "url" => $url, "notes" => $notes, "price" => $price];
        if (empty($title)) { $errors[] = "Bitte Beschreibung eingeben."; }
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE app_items SET title = ?, url = ?, notes = ?, price = ? WHERE id = ? AND owner = ?");
                $stmt->execute([$title, $url ?: null, $notes ?: null, $price, $wishId, $currentUserId]);
                header("Location: list.php"); exit;
            } catch (PDOException $e) { $errors[] = "Datenbankfehler."; }
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Wunsch bearbeiten</title><link rel="stylesheet" href="style.css"></head><body>
<div class="box" style="max-width:500px; margin: 40px auto; padding: 20px;">
   <h2>Wunsch bearbeiten</h2>
   <form method="post">
       <p>Beschreibung (Pflicht):<br><textarea name="desc" style="width:100%; height:80px;" required><?= htmlspecialchars($formData["title"]) ?></textarea></p>
       <p>Preis (&euro;):<br><input name="price" type="text" value="<?= number_format($formData["price"], 2, ",", "") ?>"></p>
       <p>Link (optional):<br><input name="link" type="url" style="width:100%;" value="<?= htmlspecialchars($formData["url"]) ?>"></p>
       <p>Notizen:<br><textarea name="notes" style="width:100%; height:80px;"><?= htmlspecialchars($formData["notes"]) ?></textarea></p>
       <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
       <button type="submit" name="save_wish" class="btn">Speichern</button>
       <button type="submit" name="back" class="btn secondary">Abbrechen</button>
   </form>
</div></body></html>