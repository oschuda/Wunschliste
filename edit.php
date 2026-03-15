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
$stmt = $pdo->prepare("SELECT id, title, url, notes, price FROM wishes WHERE id = ? AND owner = ?");
$stmt->execute([$wishId, $currentUserId]);
$wish = $stmt->fetch();
if (!$wish) { header("Location: list.php"); exit; }
$errors = []; $success = false;
$formData = ["title" => $wish["title"] ?? "", "url" => $wish["url"] ?? "", "notes" => $wish["notes"] ?? "", "price" => (float)($wish["price"] ?? 0)];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["back"])) { header("Location: list.php"); exit; }
    if (!isValidCsrf()) { $errors[] = "Sicherheitsfehler."; } else {
        $title = trim($_POST["title"] ?? ""); $url = trim($_POST["url"] ?? ""); $notes = trim($_POST["notes"] ?? ""); $price = (float)str_replace(",", ".", $_POST["price"] ?? "0");
        $formData = ["title" => $title, "url" => $url, "notes" => $notes, "price" => $price];
        if (empty($title)) { $errors[] = "Bitte Beschreibung eingeben."; }
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE wishes SET title = ?, url = ?, notes = ?, price = ? WHERE id = ? AND owner = ?");
                $stmt->execute([$title, $url ?: null, $notes ?: null, $price, $wishId, $currentUserId]);
                header("Location: list.php"); exit;
            } catch (PDOException $e) { $errors[] = "Datenbankfehler."; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate("Wunsch bearbeiten") ?> | <?= translate("Wunschliste") ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<header class="main-header">
    <div class="header-content">
        <h1><?= translate("Wunsch bearbeiten") ?></h1>
        <div class="header-actions">
            <form method="post" style="display:inline;">
                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                 <button type="submit" name="back" class="button"><?= translate("Zurück") ?></button>
            </form>
        </div>
    </div>
</header>

<main class="container">
    <section class="card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            
            <?php if ($errors): ?>
                <div class="status-message error">
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="title"><?= translate("Wunsch / Beschreibung") ?> *</label>
                <input name="title" id="title" type="text" value="<?= htmlspecialchars($formData["title"]) ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="url"><?= translate("Link / Verknüpfung") ?></label>
                    <input name="url" id="url" type="text" value="<?= htmlspecialchars($formData["url"]) ?>" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label for="price"><?= translate("Preis (ca.)") ?></label>
                    <input name="price" id="price" type="text" value="<?= number_format((float)$formData["price"], 2, ",", "") ?>" placeholder="0,00 €">
                </div>
            </div>

            <div class="form-group">
                <label for="notes"><?= translate("Notizen / Details") ?></label>
                <textarea name="notes" id="notes" rows="4"><?= htmlspecialchars($formData["notes"]) ?></textarea>
            </div>

            <div class="button-group">
                <button type="submit" name="save_wish" value="1" class="button button-success" style="flex: 2;">
                    <?= translate("Speichern") ?>
                </button>
                <button type="submit" name="back" class="button button-secondary" style="flex: 1;">
                    <?= translate("Abbrechen") ?>
                </button>
            </div>
        </form>
    </section>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> <?= translate("Wunschliste") ?></div>
</footer>

</body>
</html>
