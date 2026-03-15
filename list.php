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
        header('Location: add-wish.php');
        exit;
    }

    // Eigene Wünsche löschen
    if (isset($_POST['del_wish']) && !empty($_POST['w_selected'])) {
        $deleted = 0;
        foreach ($_POST['w_selected'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("DELETE FROM app_items WHERE id = ? AND owner = ?");
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
            $stmt = $pdo->prepare("UPDATE app_items SET claimed = NULL WHERE id = ? AND claimed = ?");
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
    SELECT id, title, url, price, notes
    FROM app_items 
    WHERE owner = ?
    ORDER BY id DESC
");
$ownWishesStmt->execute([$currentUserId]);
$ownWishes = $ownWishesStmt->fetchAll();

// --------------------------------------------------
// Reservierte Wünsche laden (von anderen)
// --------------------------------------------------
$claimedWishesStmt = $pdo->prepare("
    SELECT 
        w.id, w.title, w.url, w.price, w.notes, 
        a.f_name AS owner_name
    FROM app_items w
    JOIN app_users a ON w.owner = a.id
    WHERE w.claimed = ?
    ORDER BY w.id DESC
");
$claimedWishesStmt->execute([$currentUserId]);
$claimedWishes = $claimedWishesStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= gettext("Wunschliste & Reservierungen") ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body class="is-centered">

<header class="main-header">
    <div class="header-content">
        <h1><?= gettext("Wunschliste & Reservierungen") ?></h1>
        <div class="header-actions">
            <a href="settings.php" class="button"><?= gettext("Einstellungen") ?></a>
            <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <button type="submit" name="back" class="button"><?= gettext("Zurück") ?> >></button>
            </form>
        </div>
    </div>
</header>

<main class="container">
    <section class="card full-width">
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

            <?php if (!empty($error)): ?>
                <div class="status-message error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($messages)): ?>
                <div class="status-message success">
                    <?= implode('<br>', array_map('htmlspecialchars', $messages)) ?>
                </div>
            <?php endif; ?>

            <!-- Meine Wünsche -->
            <div class="section-header">
                <h2><?= gettext("Meine Wünsche") ?></h2>
            </div>

            <div class="list-table own-wishes-table">
                <div class="list-header">
                    <div class="col-id">#</div>
                    <div class="col-title"><?= gettext("Wunsch") ?></div>
                    <div class="col-price"><?= gettext("Preis") ?></div>
                    <div class="col-notes"><?= gettext("Notizen") ?></div>
                    <div class="col-qr">QR</div>
                    <div class="col-select"><?= gettext("Auswählen") ?></div>
                </div>

                <?php if (empty($ownWishes)): ?>
                    <div class="list-row empty">
                        <div class="col-full"><?= gettext("Noch keine Wünsche vorhanden.") ?></div>
                    </div>
                <?php else: ?>
                    <?php $x = 1; foreach ($ownWishes as $wish): ?>
                        <div class="list-row alternating-row">
                            <div class="col-id"><?= $x++ ?></div>
                            <div class="col-title">
                                <div class="item-title">
                                    <?php if (!empty($wish['url'])): ?>
                                        <a href="<?= htmlspecialchars($wish['url'] ?: '') ?>" target="_blank" class="link-external">
                                            <?= htmlspecialchars($wish['title'] ?: '') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($wish['title'] ?: '') ?>
                                    <?php endif; ?>
                                </div>
                                <div class="item-actions">
                                    <a href="edit-wish.php?editID=<?= (int)$wish['id'] ?>" class="link-edit">[<?= gettext("bearbeiten") ?>]</a>
                                </div>
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
                                    <input type="checkbox" name="w_selected[]" value="<?= (int)$wish['id'] ?>">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="button-group align-right">
                <button type="submit" class="button button-success" name="add_wish">+ <?= gettext("Wunsch hinzufügen") ?></button>
                <button type="submit" class="button button-danger" name="del_wish" onclick="return confirm('Wünsche wirklich löschen?');">X <?= gettext("Markierte löschen") ?></button>
            </div>

            <!-- Reservierte Wünsche -->
            <div class="section-header mt-30">
                <h2><?= gettext("Reservierte Wünsche") ?></h2>
                <a href="reserved.php" target="_blank" class="link-secondary">[<?= gettext("Druckerfreundlich") ?>]</a>
            </div>

            <div class="list-table claimed-wishes-table">
                <div class="list-header">
                    <div class="col-id">#</div>
                    <div class="col-owner"><?= gettext("Besitzer") ?></div>
                    <div class="col-title"><?= gettext("Wunsch") ?></div>
                    <div class="col-price"><?= gettext("Preis") ?></div>
                    <div class="col-notes"><?= gettext("Notizen") ?></div>
                    <div class="col-qr">QR</div>
                    <div class="col-select"><?= gettext("Auswählen") ?></div>
                </div>

                <?php if (empty($claimedWishes)): ?>
                    <div class="list-row empty">
                        <div class="col-full"><?= gettext("Du hast noch keine Wünsche reserviert.") ?></div>
                    </div>
                <?php else: ?>
                    <?php $x = 1; foreach ($claimedWishes as $wish): ?>
                        <div class="list-row alternating-row">
                            <div class="col-id"><?= $x++ ?></div>
                            <div class="col-owner"><?= htmlspecialchars($wish['owner_name'] ?? gettext('Unbekannt')) ?></div>
                            <div class="col-title">
                                <?php if (!empty($wish['url'])): ?>
                                    <a href="<?= htmlspecialchars($wish['url'] ?: '') ?>" target="_blank" class="link-external">
                                        <?= htmlspecialchars($wish['title'] ?: '') ?>
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
