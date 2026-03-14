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
<body>

<table class="main_table">
    <tr>
        <td width="3%"></td>
        <td width="94%"></td>
        <td width="3%"></td>
    </tr>
    <tr height="100%">
        <td></td>
        <td valign="center" align="center">
            <table cellspacing="0" cellpadding="5" class="main_box" style="width: 100%; max-width: 1100px;">
                <form method="post" action="">
                    <tr>
                        <td class="heading_left">
                            <?= gettext("Wunschliste & Reservierungen") ?><br><br>
                        </td>
                        <td class="heading_right">
                            <a href="settings.php" class="button" style="margin-right: 10px;"><?= gettext("Einstellungen") ?></a>
                            <button type="submit" name="back" class="button" style="cursor: pointer;"><?= gettext("Zurück") ?> >></button>
                        </td>
                    </tr>

                    <?php if (!empty($error)): ?>
                        <tr>
                            <td colspan="2" style="color:#c00; font-weight:bold; padding:10px; background:#ffebee;">
                                <?= htmlspecialchars($error) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if (!empty($messages)): ?>
                        <tr>
                            <td colspan="2" style="color:#006400; font-weight:bold; padding:10px; background:#e8f5e9;">
                                <?= implode('<br>', array_map('htmlspecialchars', $messages)) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <!-- Meine Wünsche -->
                    <tr>
                        <td colspan="2" align="center" style="border-right:1px solid; border-left:1px solid; padding-top: 20px;">
                            <b style="font-size: 1.2em;"><?= gettext("Meine Wünsche") ?></b>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" valign="top" style="border-right:1px solid; border-left:1px solid; padding: 10px;">
                            <table cellspacing="0" cellpadding="8" class="main_list" width="100%" style="border-collapse: collapse;">
                                <tr style="background-color: var(--color-bg-alt); color: var(--color-text);">
                                    <th width="3%" align="left">#</th>
                                    <th width="25%" align="left"><?= gettext("Wunsch") ?></th>
                                    <th width="10%" align="right"><?= gettext("Preis") ?></th>
                                    <th align="left"><?= gettext("Notizen") ?></th>
                                    <th width="8%" align="center">QR</th>
                                    <th width="8%" align="center"><?= gettext("Auswählen") ?></th>
                                </tr>

                                <?php if (empty($ownWishes)): ?>
                                    <tr><td colspan="6" align="center" style="padding: 20px;"><?= gettext("Noch keine Wünsche vorhanden.") ?></td></tr>
                                <?php else: ?>
                                    <?php $x = 1; foreach ($ownWishes as $wish): ?>
                                        <tr class="alternating-row">
                                            <td valign="top"><?= $x++ ?></td>
                                            <td valign="top">
                                                <div style="font-weight: bold; margin-bottom: 5px;">
                                                    <?php if (!empty($wish['url'])): ?>
                                                        <a href="<?= htmlspecialchars($wish['url'] ?: '') ?>" target="_blank" style="color: #64B5F6;">
                                                            <?= htmlspecialchars($wish['title'] ?: '') ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($wish['title'] ?: '') ?>
                                                    <?php endif; ?>
                                                </div>
                                                <small><a href="edit-wish.php?editID=<?= (int)$wish['id'] ?>" style="color: #81C784;">[<?= gettext("bearbeiten") ?>]</a></small>
                                            </td>
                                            <td valign="top" align="right" style="white-space: nowrap;">
                                                <?= $wish['price'] ? number_format((float)$wish['price'], 2, ',', '.') . ' €' : '-' ?>
                                            </td>
                                            <td valign="top" style="font-size: 0.95em;">
                                                <?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?>
                                            </td>
                                            <td align="center" valign="middle">
                                                <?php if (!empty($wish['url'])): ?>
                                                    <?= getQRCodeHtml($wish['url'], $currentUserId) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td align="center" valign="middle">
                                                <input type="checkbox" name="w_selected[]" value="<?= (int)$wish['id'] ?>" style="transform: scale(1.5);">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                            <div style="text-align: right; padding: 10px;">
                                <input type="submit" class="button" name="add_wish" value="+ <?= gettext("Wunsch hinzufügen") ?>">
                                <input type="submit" class="button" name="del_wish" value="X <?= gettext("Markierte löschen") ?>" onclick="return confirm('Wünsche wirklich löschen?');">
                            </div>
                        </td>
                    </tr>

                    <!-- Reservierte Wünsche -->
                    <tr>
                        <td colspan="2" align="center" style="border-right:1px solid; border-left:1px solid; padding-top: 30px;">
                            <b style="font-size: 1.2em;"><?= gettext("Reservierte Wünsche") ?></b>
                            <div style="margin-top: 5px;">
                                <a href="reserved.php" target="_blank" style="font-size: 0.85em; color: #bbb;">[<?= gettext("Druckerfreundlich") ?>]</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" valign="top" style="border-right:1px solid; border-left:1px solid; padding: 10px;">
                            <table cellspacing="0" cellpadding="8" class="main_list" width="100%" style="border-collapse: collapse;">
                                <tr style="background-color: var(--color-bg-alt); color: var(--color-text);">
                                    <th width="3%" align="left">#</th>
                                    <th width="15%" align="left"><?= gettext("Besitzer") ?></th>
                                    <th width="20%" align="left"><?= gettext("Wunsch") ?></th>
                                    <th width="10%" align="right"><?= gettext("Preis") ?></th>
                                    <th align="left"><?= gettext("Notizen") ?></th>
                                    <th width="8%" align="center">QR</th>
                                    <th width="8%" align="center"><?= gettext("Auswählen") ?></th>
                                </tr>

                                <?php if (empty($claimedWishes)): ?>
                                    <tr><td colspan="7" align="center" style="padding: 20px;"><?= gettext("Du hast noch keine Wünsche reserviert.") ?></td></tr>
                                <?php else: ?>
                                    <?php $x = 1; foreach ($claimedWishes as $wish): ?>
                                        <tr class="alternating-row">
                                            <td valign="top"><?= $x++ ?></td>
                                            <td valign="top" style="font-weight: bold;"><?= htmlspecialchars($wish['owner_name'] ?? gettext('Unbekannt')) ?></td>
                                            <td valign="top">
                                                <?php if (!empty($wish['url'])): ?>
                                                    <a href="<?= htmlspecialchars($wish['url'] ?: '') ?>" target="_blank" style="color: #64B5F6;">
                                                        <?= htmlspecialchars($wish['title'] ?: '') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($wish['title'] ?: '') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td valign="top" align="right" style="white-space: nowrap;">
                                                <?= $wish['price'] ? number_format((float)$wish['price'], 2, ',', '.') . ' €' : '-' ?>
                                            </td>
                                            <td valign="top" style="font-size: 0.95em;">
                                                <?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?>
                                            </td>
                                            <td align="center" valign="middle">
                                                <?php if (!empty($wish['url'])): ?>
                                                    <?= getQRCodeHtml($wish['url'], $currentUserId) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td align="center" valign="middle">
                                                <input type="checkbox" name="d_selected[]" value="<?= (int)$wish['id'] ?>" style="transform: scale(1.5);">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </table>
                            <div style="text-align: right; padding: 10px;">
                                <input type="submit" class="button" name="rel_claimed" value="↶ <?= gettext("Reservierung stornieren") ?>" onclick="return confirm('<?= gettext("Sicher, dass Du diese Reservierung(en) stornieren willst?") ?>');">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" class="footer_1_pce">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        </td>
                    </tr>
                </form>
            </table>
        </td>
        <td></td>
    </tr>
    <tr height="40">
        <td colspan="3"></td>
    </tr>
</table>

</body>
</html>
<?php exit; // Ende des modernisierten Layouts ?>
        </td>
    </tr>
</table>

</body>
</html>
