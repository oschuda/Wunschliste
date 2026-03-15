<?php
/**
 * Wunschliste - Meine Wünsche & Reservierungen (modernisiert für PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Lax',
]);

if (empty($_SESSION['username']) || empty($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['id'];

require_once 'inc/config.php';  // enthält Database::get(), cleanup_input(), csrf-Helfer usw.
require_once 'inc/qr_helper.php'; // QR-Code Hilfsfunktionen
require_once 'inc/qr_helper_modal.php'; // Modal
echo getQRModalHtml();

// --------------------------------------------------
// CSRF Schutz
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf_token($_POST['csrf_token'] ?? null)) {
    $error = 'Sicherheitsfehler: Bitte Seite neu laden und erneut versuchen.';
}

// --------------------------------------------------
// Aktionen verarbeiten
// --------------------------------------------------
$messages = [];
$error    = '';

// CSRF-Helfer (lokal falls nicht in config.php)
if (!function_exists('getCsrfToken')) {
    function getCsrfToken(): string {
        return get_csrf_token();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf_token($_POST['csrf_token'] ?? null)) {
    $pdo = Database::get();

    // Zurück
    if (isset($_POST['back'])) {
        header('Location: index.php');
        exit;
    }

    // Wunsch hinzufügen
    if (isset($_POST['add_wish'])) {
        header('Location: add.php');
        exit;
    }

    // Eigene Wünsche löschen
    if (isset($_POST['del_wish']) && !empty($_POST['w_selected'])) {
        $deleted = 0;
        foreach ($_POST['w_selected'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("DELETE FROM wishes WHERE id = ? AND owner = ?");
            $stmt->execute([$wishId, $currentUserId]);
            if ($stmt->rowCount() > 0) $deleted++;
        }
        if ($deleted > 0) {
            $messages[] = "$deleted Wunsch/Wünsche gelöscht.";
        }
    }

    // Reservierung stornieren
    if (isset($_POST['rel_claimed']) && !empty($_POST['d_selected'])) {
        $unstored = 0;
        foreach ($_POST['d_selected'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("UPDATE wishes SET claimed = NULL WHERE id = ? AND claimed = ?");
            $stmt->execute([$wishId, $currentUserId]);
            if ($stmt->rowCount() > 0) $unstored++;
        }
        if ($unstored > 0) {
            $messages[] = "$unstored Reservierung(en) storniert.";
        }
    }
}

// --------------------------------------------------
// Eigene Wünsche laden
// --------------------------------------------------
$pdo = Database::get();
$ownWishesStmt = $pdo->prepare("
    SELECT id, title, url, price, notes, category, claimed, is_purchased
    FROM wishes 
    WHERE owner = ?
    ORDER BY category ASC, id DESC
");
$ownWishesStmt->execute([$currentUserId]);
$ownWishes = $ownWishesStmt->fetchAll();

$groupedOwn = [];
foreach ($ownWishes as $ow) {
    $cat = $ow['category'] ?: 'Standard';
    $groupedOwn[$cat][] = $ow;
}

// --------------------------------------------------
// Reservierte Wünsche laden (von anderen)
// --------------------------------------------------
$claimedWishesStmt = $pdo->prepare("
    SELECT 
        w.id, w.title, w.url, w.price, w.notes, w.is_purchased,
        a.f_name AS owner_name, a.id AS owner_id
    FROM wishes w
    JOIN users a ON w.owner = a.id
    WHERE w.claimed = ?
    ORDER BY w.id DESC
");
$claimedWishesStmt->execute([$currentUserId]);
$claimedWishes = $claimedWishesStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate("Wunschliste & Reservierungen") ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body class="is-centered">

<header class="main-header">
    <div class="header-content">
        <h1><?= translate("Meine Wünsche") ?></h1>
        <div class="header-actions">
            <a href="add.php" class="button button-success">+ <?= translate("Wunsch hinzufügen") ?></a>
            <a href="settings.php" class="button button-outline">⚙️</a>
            <a href="index.php" class="button"><?= translate("Dashboard") ?></a>
        </div>
    </div>
</header>

<main class="container">
    <section class="card-plain">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

            <?php if (!empty($messages)): ?>
                <div class="status-message success">
                    <?= implode('<br>', array_map('htmlspecialchars', $messages)) ?>
                </div>
            <?php endif; ?>

            <!-- Meine Wünsche -->
            <?php foreach ($groupedOwn as $catName => $items): ?>
                <div class="category-header">
                    <h2>📂 <?= htmlspecialchars($catName) ?></h2>
                </div>
                <div class="wish-grid">
                    <?php foreach ($items as $item): ?>
                        <div class="wish-card <?= $item['is_purchased'] ? 'is-purchased' : '' ?>" tabindex="0">
                            <div class="wish-card-image">
                                🎁
                                <?php if ($item['price'] > 0): ?>
                                    <div class="price-badge"><?= number_format((float)$item['price'], 2, ',', '.') ?> €</div>
                                <?php endif; ?>
                            </div>
                            <div class="wish-card-body">
                                <h3><?= htmlspecialchars($item['title']) ?></h3>
                                <div class="wish-card-notes"><?= nl2br(htmlspecialchars($item['notes'] ?? '')) ?></div>
                            </div>
                            <div class="wish-card-footer">
                                <?php if ($item['claimed']): ?>
                                    <span class="wish-status-pill reserved">🔒 <?= translate("Reserviert") ?></span>
                                <?php else: ?>
                                    <span class="wish-status-pill available">✨ <?= translate("Verfügbar") ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="wish-card-actions">
                                <a href="edit.php?editID=<?= (int)$item['item_id'] ?? (int)$item['id'] ?>" class="button"><?= translate("Bearbeiten") ?></a>
                                <label style="border: 1px solid var(--accent-color); color: var(--accent-color);">
                                    <input type="checkbox" name="w_selected[]" value="<?= (int)$item['id'] ?>" style="width: auto;">
                                    <span>🗑️ <?= translate("Löschen") ?></span>
                                </label>
                                <button type="button" class="button button-outline" onclick="showQRCode('<?= htmlspecialchars($item['title']) ?>', '<?= (int)$item['id'] ?>')">📱 QR Code</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($groupedOwn)): ?>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" name="del_wish" class="button button-danger" onclick="return confirm('Markierte Wünsche wirklich löschen?')">
                        🗑️ <?= translate("Markierte löschen") ?>
                    </button>
                </div>
            <?php endif; ?>
        </form>

        <!-- Reservierte Wünsche (von anderen) -->
        <?php if (!empty($claimedWishes)): ?>
            <div class="category-header" style="margin-top: 5rem; border-color: var(--accent-color);">
                <h2>📌 <?= translate("Meine Reservierungen für andere") ?></h2>
            </div>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <div class="wish-grid">
                    <?php foreach ($claimedWishes as $wish): ?>
                        <div class="wish-card <?= $wish['is_purchased'] ? 'is-purchased' : '' ?>" tabindex="0">
                            <div class="wish-card-image" style="background: #2c3e50;">
                                👤
                            </div>
                            <div class="wish-card-body">
                                <h3><?= htmlspecialchars($wish['title']) ?></h3>
                                <div style="font-size: 0.8rem; color: var(--primary-color); margin-bottom: 10px;">
                                    Für: <strong><?= htmlspecialchars($wish['owner_name']) ?></strong>
                                </div>
                                <div class="wish-card-notes"><?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?></div>
                            </div>
                            <div class="wish-card-footer">
                                <span class="wish-status-pill reserved"><?= $wish['is_purchased'] ? translate("Gekauft") : translate("Reserviert") ?></span>
                            </div>
                            <div class="wish-card-actions">
                                <?php if (!empty($wish['url'])): ?>
                                    <a href="<?= htmlspecialchars($wish['url']) ?>" target="_blank" class="button">🔗 <?= translate("Shop") ?></a>
                                <?php endif; ?>
                                <label>
                                    <input type="checkbox" name="d_selected[]" value="<?= (int)$wish['id'] ?>" style="width: auto;">
                                    <span>❌ <?= translate("Freigeben") ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" name="rel_claimed" class="button button-danger">
                        ❌ <?= translate("Markierte Reservierungen stornieren") ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>

<script>
function showQRCode(title, id) {
    alert("QR Code für " + title + " (ID: " + id + ")");
}
</script>

</body>
</html>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($wish['title'] ?: '') ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-price">
                                <?= $wish['price'] ? number_format((float)$wish['price'], 2, ',', '.') . ' €' : '-' ?>
                            </div>
                            <div class="col-notes">
                                <?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?>
                            </div>
                            <div class="col-qr">
                                <?php if (!empty($wish['url'])): ?>
                                    <?= getQRCodeHtml($wish['url'], $currentUserId) ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-select">
                                <label class="checkbox-container">
                                    <input type="checkbox" name="d_selected[]" value="<?= (int)$wish['id'] ?>">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="button-group align-right">
                <button type="submit" class="button button-warning" name="rel_claimed" onclick="return confirm('<?= gettext("Sicher, dass Du diese Reservierung(en) stornieren willst?") ?>');">↶ <?= gettext("Reservierung stornieren") ?></button>
            </div>
        </form>
    </section>
</main>

</body>
</html>
<?php exit; ?>
