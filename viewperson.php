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

/**
 * Hilfsfunktion zum Senden der Reservierungs-E-Mail
 */
function notifyOwnerOfClaim(PDO $pdo, int $ownerId, int $wishId, string $claimerName): void {
    $stmt = $pdo->prepare("SELECT u_name, email, f_name, notify_claimed FROM users WHERE id = ?");
    $stmt->execute([$ownerId]);
    $owner = $stmt->fetch();

    if ($owner && !empty($owner['email']) && (int)$owner['notify_claimed'] === 1) {
        $stmtWish = $pdo->prepare("SELECT title FROM wishes WHERE id = ?");
        $stmtWish->execute([$wishId]);
        $wish = $stmtWish->fetch();

        if ($wish) {
            $subject = "Reservierung: " . $wish['title'];
            $message = sprintf(
                "Hallo %s,\n\nein Wunsch von deiner Liste wurde gerade reserviert.\n\nWunsch: %s\nReserviert von: %s\n\nDu kannst dies in deinem Profil (Reservierungen) einsehen.\n\nViele Grüße,\nDeine Wunschliste",
                $owner['f_name'] ?: $owner['u_name'],
                $wish['title'],
                $claimerName
            );
            send_email($owner['email'], $subject, $message);
        }
    }
}

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
            $stmt = $pdo->prepare("UPDATE wishes SET claimed = ?, is_purchased = 0 WHERE id = ? AND claimed IS NULL AND owner = ?");
            $stmt->execute([$currentUserId, $wishId, $viewID]);
            if ($stmt->rowCount() > 0) {
                $count++;
                notifyOwnerOfClaim($pdo, $viewID, $wishId, $_SESSION['username']);
            }
        }
        if ($count > 0) $messages[] = "$count Wunsch/Wünsche erfolgreich reserviert.";
    }

    // 2. Als gekauft markieren (nur wenn von mir reserviert)
    if (isset($_POST['do_purchase']) && !empty($_POST['purchase_items'])) {
        $count = 0;
        foreach ($_POST['purchase_items'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("UPDATE wishes SET is_purchased = 1 WHERE id = ? AND claimed = ? AND owner = ?");
            $stmt->execute([$wishId, $currentUserId, $viewID]);
            if ($stmt->rowCount() > 0) $count++;
        }
        if ($count > 0) $messages[] = "$count Wunsch/Wünsche als 'Gekauft' markiert.";
    }

    // 3. Reservierung stornieren (nur eigene)
    if (isset($_POST['do_unclaim']) && !empty($_POST['unclaim_items'])) {
        $count = 0;
        foreach ($_POST['unclaim_items'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("UPDATE wishes SET claimed = NULL, is_purchased = 0 WHERE id = ? AND claimed = ? AND owner = ?");
            $stmt->execute([$wishId, $currentUserId, $viewID]);
            if ($stmt->rowCount() > 0) $count++;
        }
        if ($count > 0) $messages[] = "$count Reservierung(en) storniert.";
    }
}

// --------------------------------------------------
// Personendaten laden
// --------------------------------------------------
$stmt = $pdo->prepare("SELECT f_name, l_name, u_name FROM users WHERE id = ? AND enabled = 1");
$stmt->execute([$viewID]);
$userToView = $stmt->fetch();

if (!$userToView) {
    die("Benutzer nicht gefunden oder deaktiviert.");
}

// --------------------------------------------------
// Wünsche laden und nach Kategorie gruppieren
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, title, url, price, notes, claimed, is_purchased, category 
    FROM wishes 
    WHERE owner = ? 
    ORDER BY category ASC, id DESC
");
$stmt->execute([$viewID]);
$wishes = $stmt->fetchAll();

$groupedWishes = [];
foreach ($wishes as $wish) {
    $cat = $wish['category'] ?: 'Standard';
    $groupedWishes[$cat][] = $wish;
}

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

<header class="main-header">
    <div class="header-left">
        <h1><?= translate("Wünsche von") ?> <?= htmlspecialchars($fullName) ?></h1>
    </div>
    <nav class="header-right">
        <a href="index.php" class="button"><?= translate("Zurück") ?></a>
    </nav>
</header>

<main class="container">
    <?php if ($messages): ?>
        <div style="padding:10px; background: rgba(39, 174, 96, 0.1); border: 1px solid var(--success-color); color: var(--success-color); border-radius: 8px; margin-bottom: 20px;">
            <?= implode('<br>', array_map('htmlspecialchars', $messages)) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
            
            <?php foreach ($groupedWishes as $categoryName => $catWishes): ?>
                <div class="category-group" style="margin-bottom: 30px;">
                    <h2 style="border-bottom: 2px solid var(--primary-color); padding-bottom: 5px; margin-bottom: 15px; color: var(--primary-color);">
                        📂 <?= htmlspecialchars($categoryName) ?>
                    </h2>
                    
                    <div class="list-table">
                        <div class="list-row list-header" style="grid-template-columns: 2fr 0.8fr 2fr 1.5fr 1fr;">
                            <div><?= translate("Wunsch") ?></div>
                            <div><?= translate("Preis") ?></div>
                            <div><?= translate("Notizen") ?></div>
                            <div><?= translate("Status") ?></div>
                            <div style="text-align: center;">Aktion</div>
                        </div>

                        <?php foreach ($catWishes as $wish): ?>
                            <?php 
                            $claimantId = !empty($wish['claimed']) ? (int)$wish['claimed'] : 0;
                            $isClaimed = ($claimantId > 0);
                            $isByMe = ($claimantId === $currentUserId);
                            $isPurchased = (int)($wish['is_purchased'] ?? 0) === 1;
                            
                            // Status styling
                            $statusText = translate("Verfügbar");
                            $statusColor = "var(--success-color)";
                            
                            if ($isClaimed) {
                                if ($isByMe) {
                                    $statusText = $isPurchased ? "✅ " . translate("Gekauft") : "📌 " . translate("Von dir reserviert");
                                    $statusColor = $isPurchased ? "var(--success-color)" : "var(--primary-color)";
                                } else {
                                    $statusText = $isPurchased ? "✅ " . translate("Bereits gekauft") : "🚫 " . translate("Bereits reserviert");
                                    $statusColor = "var(--accent-color)";
                                }
                            }
                            ?>
                            <div class="list-row" style="grid-template-columns: 2fr 0.8fr 2fr 1.5fr 1fr; <?= $isPurchased ? 'opacity: 0.7;' : '' ?>">
                                <div>
                                    <?php if (!empty($wish['url'])): ?>
                                        <a href="<?= htmlspecialchars($wish['url']) ?>" target="_blank" style="<?= $isPurchased ? 'text-decoration: line-through;' : '' ?>"><?= htmlspecialchars($wish['title']) ?></a>
                                    <?php else: ?>
                                        <span style="<?= $isPurchased ? 'text-decoration: line-through;' : '' ?>"><?= htmlspecialchars($wish['title']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div><?= number_format((float)($wish['price'] ?? 0), 2, ',', '.') ?> €</div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);"><?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?></div>
                                <div style="color: <?= $statusColor ?>; font-weight: bold; font-size: 0.85rem;">
                                    <?= $statusText ?>
                                </div>
                                <div style="text-align: center; display: flex; gap: 5px; justify-content: center;">
                                    <?php if (!$isClaimed): ?>
                                        <label title="Reservieren">
                                            <input type="checkbox" name="claim_items[]" value="<?= (int)$wish['id'] ?>"> 📌
                                        </label>
                                    <?php elseif ($isByMe): ?>
                                        <?php if (!$isPurchased): ?>
                                            <label title="Als gekauft markieren">
                                                <input type="checkbox" name="purchase_items[]" value="<?= (int)$wish['id'] ?>"> ✅
                                            </label>
                                        <?php endif; ?>
                                        <label title="Stornieren">
                                            <input type="checkbox" name="unclaim_items[]" value="<?= (int)$wish['id'] ?>"> ❌
                                        </label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                <button type="submit" name="do_unclaim" class="button button-outline" style="border-color: var(--accent-color); color: var(--accent-color);">
                    <?= translate("Markierte stornieren") ?>
                </button>
                <button type="submit" name="do_purchase" class="button button-success">
                    <?= translate("Markierte als GEKAUFT markieren") ?>
                </button>
                <button type="submit" name="do_claim" class="button">
                    <?= translate("Markierte reservieren") ?>
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
