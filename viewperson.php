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

    <section class="card-plain">
        <form method="post" action="" id="claim-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
            
            <?php foreach ($groupedWishes as $categoryName => $catWishes): ?>
                <div class="category-group" style="margin-bottom: 50px;">
                    <div class="category-header">
                        <h2>📂 <?= htmlspecialchars($categoryName) ?></h2>
                    </div>
                    
                    <div class="wish-grid">
                        <?php foreach ($catWishes as $wish): ?>
                            <?php 
                            $claimantId = !empty($wish['claimed']) ? (int)$wish['claimed'] : 0;
                            $isClaimed = ($claimantId > 0);
                            $isByMe = ($claimantId === $currentUserId);
                            $isPurchased = (int)($wish['is_purchased'] ?? 0) === 1;
                            
                            $statusClass = 'available';
                            $statusText = translate("Verfügbar");
                            if ($isClaimed) {
                                if ($isByMe) {
                                    $statusClass = $isPurchased ? 'purchased' : 'reserved';
                                    $statusText = $isPurchased ? translate("Gekauft") : translate("Reserviert");
                                } else {
                                    $statusClass = 'purchased';
                                    $statusText = translate("Vergeben");
                                }
                            }
                            ?>
                            <div class="wish-card <?= $isPurchased ? 'is-purchased' : '' ?>" tabindex="0">
                                <div class="wish-card-image" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fdfdfd;">
                                    <?php 
                                        $imgUrl = '';
                                        if (!empty($wish['notes']) && preg_match('/Bild:\s*(https?:\/\/\S+)/i', $wish['notes'], $m)) {
                                            $imgUrl = $m[1];
                                        }
                                    ?>
                                    <?php if ($imgUrl): ?>
                                        <img src="<?= htmlspecialchars($imgUrl) ?>" style="width: 100%; height: 100%; object-fit: contain; padding: 5px;">
                                    <?php else: ?>
                                        <span style="font-size: 3rem;">🎁</span>
                                    <?php endif; ?>

                                    <?php if ($wish['price'] > 0): ?>
                                        <div class="price-badge"><?= number_format((float)$wish['price'], 2, ',', '.') ?> €</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="wish-card-body">
                                    <h3>
                                        <?php if (!empty($wish['url'])): ?>
                                            <a href="<?= htmlspecialchars($wish['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($wish['title']) ?></a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($wish['title']) ?>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="wish-card-notes">
                                        <?php 
                                            $cleanNotes = preg_replace('/Bild:\s*(https?:\/\/\S+)/i', '', $wish['notes'] ?? '');
                                            echo nl2br(htmlspecialchars(trim($cleanNotes)));
                                        ?>
                                    </div>
                                </div>

                                <div class="wish-card-footer">
                                    <span class="wish-status-pill <?= $statusClass ?>"><?= $statusText ?></span>
                                    <?php if ($isClaimed && !$isByMe): ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">🔒 <?= translate("Reserviert") ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Hover Actions -->
                                <div class="wish-card-actions">
                                    <?php if (!$isClaimed): ?>
                                        <label>
                                            <input type="checkbox" name="claim_items[]" value="<?= (int)$wish['id'] ?>" style="width: auto; margin-right: 10px;">
                                            <span>📌 <?= translate("Reservieren") ?></span>
                                        </label>
                                    <?php elseif ($isByMe): ?>
                                        <?php if (!$isPurchased): ?>
                                            <label>
                                                <input type="checkbox" name="purchase_items[]" value="<?= (int)$wish['id'] ?>" style="width: auto; margin-right: 10px;">
                                                <span>✅ <?= translate("Gekauft") ?></span>
                                            </label>
                                        <?php endif; ?>
                                        <label>
                                            <input type="checkbox" name="unclaim_items[]" value="<?= (int)$wish['id'] ?>" style="width: auto; margin-right: 10px;">
                                            <span>❌ <?= translate("Stornieren") ?></span>
                                        </label>
                                    <?php else: ?>
                                        <div style="color: white; text-align: center; font-size: 0.9rem;">
                                            <?= translate("Dieser Wunsch wird bereits erfüllt.") ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($wish['url'])): ?>
                                        <a href="<?= htmlspecialchars($wish['url']) ?>" target="_blank" class="button button-outline" style="width: 80%; margin-top: 10px;">
                                            🔗 <?= translate("Shop öffnen") ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="position: fixed; bottom: 30px; right: 30px; z-index: 1000;">
                <button type="submit" name="do_claim" class="button button-success" style="box-shadow: 0 4px 15px rgba(0,0,0,0.5); padding: 1rem 2rem; border-radius: 30px;">
                    🚀 <?= translate("Aktionen ausführen") ?>
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
