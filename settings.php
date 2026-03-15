<?php
declare(strict_types=1);
session_start();
require_once 'inc/config.php';
require_once 'inc/db.php';
if (empty($_SESSION['id'])) { header('Location: login.php'); exit; }
$pdo = Database::get();
$userId = (int)$_SESSION['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE users SET email = ?, qr_size = ?, qr_use_cache = ?, notify_claimed = ? WHERE id = ?');
    $stmt->execute([
        trim($_POST['email'] ?? ''),
        (int)$_POST['qr_size'], 
        isset($_POST['qr_use_cache']) ? 1 : 0, 
        isset($_POST['notify_claimed']) ? 1 : 0,
        $userId
    ]);
    $_SESSION['lang'] = $_POST['lang'];
    header('Location: settings.php?success=1');
    exit;
}
$stmt = $pdo->prepare('SELECT email, qr_size, qr_use_cache, notify_claimed FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userSettings = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen | Wunschliste</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body class="is-centered">

<header class="main-header">
    <div class="header-content">
        <h1><?= gettext("Einstellungen") ?></h1>
        <div class="header-actions">
            <a href="index.php" class="button">« <?= gettext("Zurück") ?></a>
        </div>
    </div>
</header>

<main class="container">
    <section class="card" style="max-width: 600px; margin: 0 auto;">
        <form method="POST">
            <?php if (isset($_GET['success'])): ?>
                <div class="status-message success">
                    <?= gettext("Einstellungen wurden erfolgreich gespeichert.") ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="lang">Sprache:</label>
                <select name="lang" id="lang">
                    <option value="de" <?= ($_SESSION['lang'] ?? 'de') === 'de' ? 'selected' : '' ?>>Deutsch (DE)</option>
                    <option value="en" <?= ($_SESSION['lang'] ?? 'de') === 'en' ? 'selected' : '' ?>>English (EN)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">E-Mail-Adresse (für Benachrichtigungen):</label>
                <input type="email" name="email" id="email" value="<?= htmlspecialchars($userSettings['email'] ?? '') ?>" placeholder="ihre@email.de">
            </div>

            <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px;">
                <input type="checkbox" name="notify_claimed" id="notify_claimed" <?= ($userSettings['notify_claimed'] ?? 0) ? 'checked' : '' ?> style="width: auto;">
                <label for="notify_claimed" style="margin-bottom: 0;">E-Mail bei reservierten Wünschen erhalten</label>
            </div>

            <div class="form-group">
                <label for="qr_size">QR-Code Größe (Faktor):</label>
                <input type="number" name="qr_size" id="qr_size" value="<?= $userSettings['qr_size'] ?? 3 ?>" min="1" max="10">
            </div>

            <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px;">
                <input type="checkbox" name="qr_use_cache" id="qr_use_cache" <?= ($userSettings['qr_use_cache'] ?? 1) ? 'checked' : '' ?> style="width: auto;">
                <label for="qr_use_cache" style="margin-bottom: 0;">QR-Code Cache verwenden</label>
            </div>

            <div class="button-group mt-20">
                <button type="submit" class="button button-success" style="width: 100%;"><?= gettext("Speichern") ?></button>
            </div>
        </form>
    </section>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> Wunschliste</div>
</footer>

</body>
</html>
