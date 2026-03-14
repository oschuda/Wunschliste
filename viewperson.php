<?php
declare(strict_types=1);
/**
 * Wunschliste - Wünsche einer anderen Person ansehen (PHP 8.4 + SQLite)
 */
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Lax',
]);

require_once 'inc/common.php';
require_once 'inc/db.php';
require_once 'inc/config.php';

if (empty($_SESSION['username']) || empty($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['id'];
$viewID = isset($_GET['viewID']) ? (int)$_GET['viewID'] : 0;

if ($viewID <= 0 || $viewID === $currentUserId) {
    header('Location: index.php');
    exit;
}

$pdo = Database::get();

// --------------------------------------------------
// Aktionen verarbeiten (Reservieren / Stornieren)
// --------------------------------------------------
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? null)) {
    // 1. Reservieren
    if (isset($_POST['do_claim']) && !empty($_POST['claim_items'])) {
        $count = 0;
        foreach ($_POST['claim_items'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("UPDATE app_items SET claimed = ? WHERE id = ? AND claimed IS NULL AND owner = ?");
            $stmt->execute([$currentUserId, $wishId, $viewID]);
            if ($stmt->rowCount() > 0) $count++;
        }
        if ($count > 0) $messages[] = "$count Wunsch/Wünsche erfolgreich reserviert.";
    }

    // 2. Reservierung stornieren (nur eigene)
    if (isset($_POST['do_unclaim']) && !empty($_POST['unclaim_items'])) {
        $count = 0;
        foreach ($_POST['unclaim_items'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("UPDATE app_items SET claimed = NULL WHERE id = ? AND claimed = ? AND owner = ?");
            $stmt->execute([$wishId, $currentUserId, $viewID]);
            if ($stmt->rowCount() > 0) $count++;
        }
        if ($count > 0) $messages[] = "$count Reservierung(en) storniert.";
    }
}

// --------------------------------------------------
// Personendaten laden
// --------------------------------------------------
$stmt = $pdo->prepare("SELECT f_name, l_name, u_name FROM app_users WHERE id = ? AND enabled = 1");
$stmt->execute([$viewID]);
$userToView = $stmt->fetch();

if (!$userToView) {
    die("Benutzer nicht gefunden oder deaktiviert.");
}

// --------------------------------------------------
// Wünsche laden
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, title, url, price, notes, claimed 
    FROM app_items 
    WHERE owner = ? 
    ORDER BY id DESC
");
$stmt->execute([$viewID]);
$wishes = $stmt->fetchAll();

$fullName = trim(($userToView['f_name'] ?? '') . ' ' . ($userToView['l_name'] ?? ''));
if (empty($fullName)) $fullName = $userToView['u_name'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Wünsche von <?= htmlspecialchars($fullName) ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<table class="main_table">
    <tr>
        <td valign="center" align="center">
            <table cellspacing="0" cellpadding="5" class="main_box">
                <tr height="20">
                    <td class="heading_left">
                        <b><?= gettext("Wünsche von") ?> <?= htmlspecialchars($fullName) ?></b>
                    </td>
                    <td class="heading_right">
                        <a href="index.php" class="button"><?= gettext("Zurück") ?></a>
                    </td>
                </tr>

                <?php if ($messages): ?>
                    <tr>
                        <td colspan="2" style="background:#e8f5e9; padding:10px; border:1px solid #4caf50; color:green;">
                            <?= implode('<br>', $messages) ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <td colspan="2" style="border-left:1px solid; border-right:1px solid; padding:0;">
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                            <table width="100%" cellspacing="1" cellpadding="2" class="main_list">
                                <tr class="header_row">
                                    <td width="30%"><b><?= gettext("Wunsch") ?></b></td>
                                    <td width="10%"><b><?= gettext("Preis") ?></b></td>
                                    <td width="35%"><b><?= gettext("Notizen") ?></b></td>
                                    <td width="15%"><b><?= gettext("Status") ?></b></td>
                                    <td align="center" width="10%"><b><?= gettext("Wählen") ?></b></td>
                                </tr>

                                <?php foreach ($wishes as $wish): ?>
                                    <?php 
                                    $claimantId = !empty($wish['claimed']) ? (int)$wish['claimed'] : 0;
                                    $isClaimed = ($claimantId > 0);
                                    $isByMe = ($claimantId === $currentUserId);
                                    $rowBg = $isClaimed ? ($isByMe ? 'var(--color-bg-accent, #e8f4f8)' : 'var(--color-bg-alt, #ecf0f1)') : 'var(--color-bg, #ffffff)';
                                    ?>
                                    <tr style="background: <?= $rowBg ?>; color: var(--color-text);">
                                        <td style="padding: 10px;">
                                            <?php if (!empty($wish['url'])): ?>
                                                <a href="<?= htmlspecialchars($wish['url']) ?>" target="_blank" style="color: var(--color-link);"><?= htmlspecialchars($wish['title']) ?></a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($wish['title']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px;"><?= number_format((float)($wish['price'] ?? 0), 2, ',', '.') ?> €</td>
                                        <td style="padding: 10px;"><?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?></td>
                                        <td style="padding: 10px;">
                                            <?php if (!$isClaimed): ?>
                                                <span style="color: var(--color-success, green); font-weight: bold;"><?= gettext("Verfügbar") ?></span>
                                            <?php elseif ($isByMe): ?>
                                                <span style="color: var(--color-info, blue); font-weight: bold;"><?= gettext("Von dir reserviert") ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--color-error, red);"><?= gettext("Bereits reserviert") ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td align="center" style="padding: 10px;">
                                            <?php if (!$isClaimed): ?>
                                                <input type="checkbox" name="claim_items[]" value="<?= (int)$wish['id'] ?>">
                                            <?php elseif ($isByMe): ?>
                                                <input type="checkbox" name="unclaim_items[]" value="<?= (int)$wish['id'] ?>">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <div style="padding: 15px; text-align: right; border-top: 1px solid var(--color-border); background: var(--color-bg-light);">
                                <input type="submit" name="do_claim" class="button" value="<?= gettext("Markierte Wünsche reservieren") ?>" style="background: var(--color-success); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">
                                <input type="submit" name="do_unclaim" class="button" value="<?= gettext("Reservierung stornieren") ?>" style="background: var(--color-error); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-left: 10px;">
                            </div>
                        </form>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>