<?php
/**
 * Wunschliste - Haupt-Dashboard (modernisiert für PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'cookie_samesite' => 'None', // Allow session visibility for Browser Extension
]);

// Gemeinsame Hilfsfunktionen laden
require_once __DIR__ . '/inc/common.php';

if (empty($_SESSION['username']) || empty($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['id'];

require_once 'inc/db.php';

// --------------------------------------------------
// CSRF Schutz
// --------------------------------------------------
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function isValidCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// --------------------------------------------------
// Sortier-Logik
// --------------------------------------------------
$sort_column = 'l_name ASC';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isValidCsrf()) {
    if (isset($_POST['l_name']))  $sort_column = 'l_name ASC, f_name ASC';
    if (isset($_POST['f_name']))  $sort_column = 'f_name ASC, l_name ASC';
    if (isset($_POST['b_date']))  $sort_column = 'b_year ASC, b_month ASC, b_day ASC';
}

// --------------------------------------------------
// Redirect-Logik
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout'])) {
        header('Location: logout.php');
        exit;
    }
    if (isset($_POST['edit_wishes'])) {
        header('Location: list.php');
        exit;
    }
    if (isset($_POST['edit_details'])) {
        header('Location: account.php');
        exit;
    }
    // Sprachumschaltung
    if (isset($_POST['lang_de'])) {
        $_SESSION['lang'] = 'de';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    if (isset($_POST['lang_en'])) {
        $_SESSION['lang'] = 'en';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// --------------------------------------------------
// Alle aktiven Benutzer laden + Wunsch-Zahlen
// --------------------------------------------------
$pdo = Database::get();

$stmt = $pdo->prepare("
    SELECT 
        id, f_name, l_name, u_name, 
        b_day, b_month, b_year
    FROM app_users 
    WHERE enabled = 1 
    ORDER BY $sort_column
");
$stmt->execute();
$users = $stmt->fetchAll();

// Für jede Person Wunschzahlen ermitteln
$userStats = [];
foreach ($users as &$user) {
    $id = (int)$user['id'];

    // Gesamt Wünsche
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM app_items WHERE owner = ?");
    $stmt->execute([$id]);
    $user['total_wishes'] = (int)$stmt->fetchColumn();

    // Reservierte Wünsche (nur für andere sichtbar)
    if ($id !== $currentUserId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM app_items WHERE owner = ? AND claimed IS NOT NULL");
        $stmt->execute([$id]);
        $user['claimed_wishes'] = (int)$stmt->fetchColumn();
    } else {
        $user['claimed_wishes'] = null; // eigene Liste zeigt keine reservierten Zahlen
    }

    // Geburtstag formatieren
    $user['birthdate'] = '';
    if ($user['b_day'] && $user['b_month'] && $user['b_year']) {
        $user['birthdate'] = sprintf("%02d/%02d/%d", $user['b_day'], $user['b_month'], $user['b_year']);
    }
}
unset($user); // Referenz auflösen
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wunschliste - Übersicht</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<table class="main_table">
    <tr>
        <td width="5%"></td>
        <td width="90%"></td>
        <td width="5%"></td>
    </tr>
    <tr height="100%">
        <td></td>
        <td valign="center" align="center">
            <table cellspacing="0" cellpadding="5" class="main_box">
                <form method="post" action="">
                    <tr height="20">
                        <td class="heading_left">
                            <?= gettext("Wunschlisten") ?><br>
                            [<?= gettext("Angemeldet als") ?> <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>]
                        </td>
                        <td class="heading_right">
                            <input type="submit" class="button" name="lang_de" value="Deutsch">
                            <input type="submit" class="button" name="lang_en" value="English">
                            <?php if (!empty($_SESSION['admin'])): ?>
                                <a href="admin.php" class="button" style="background: var(--color-accent-secondary); color: white;">Admin</a>
                            <?php endif; ?>
                            <a href="tools.php" class="button" style="background: #4CAF50; color: white; text-decoration: none;">🚀 Shop-Addon</a>
                            <input type="submit" class="button" name="logout" value="<?= gettext("Abmelden") ?>">
                        </td>
                    </tr>

                    <tr>
                        <td valign="top" colspan="2" width="100%" style="border-left: 1px solid; border-right: 1px solid;">
                            <table width="100%" cellspacing="1" cellpadding="2" align="center" class="main_list" border="0">
                                <tr bgcolor="#EEEEEE">
                                    <td align="left" width="11%">
                                        <input type="submit" class="button" name="l_name" value="<?= gettext("Nachname") ?>">
                                    </td>
                                    <td align="left" width="11%">
                                        <input type="submit" class="button" name="f_name" value="<?= gettext("Vorname") ?>">
                                    </td>
                                    <td align="right" width="11%">
                                        <input type="submit" class="button" name="b_date" value="<?= gettext("Geburtstag") ?>">
                                    </td>
                                    <td align="right"><b><?= gettext("Wünsche gesamt") ?></b></td>
                                    <td align="right" width="11%"><b><?= gettext("Reservierte Wünsche") ?></b></td>
                                </tr>

                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $isOwn = ((int)$user['id'] === $currentUserId);
                                    $rowBg = $isOwn ? '#E0F7FA' : '#F0F8FF'; 
                                    $linkStart = '<a href="viewperson.php?viewID=' . (int)$user['id'] . '">';
                                    $linkEnd   = '</a>';
                                    ?>
                                    <tr bgcolor="<?= $rowBg ?>">
                                        <td><?= $linkStart . htmlspecialchars($user['l_name'] ?? '') . $linkEnd ?></td>
                                        <td><?= $linkStart . htmlspecialchars($user['f_name'] ?? '') . $linkEnd ?></td>
                                        <td align="right"><?= htmlspecialchars($user['birthdate']) ?></td>
                                        <td align="right"><?= $user['total_wishes'] ?></td>
                                        <td align="right" <?= $isOwn ? 'bgcolor="#CCCCCC"' : '' ?>>
                                            <?= $isOwn ? '' : $user['claimed_wishes'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </td>
                    </tr>

                    <tr height="10" valign="bottom">
                        <td class="footer_left">
                            <input type="submit" class="button" name="edit_wishes" value="<?= gettext("Wünsche bearbeiten") ?>">
                        </td>
                        <td class="footer_right">
                            <input type="submit" class="button" name="edit_details" value="<?= gettext("Details bearbeiten") ?>">
                        </td>
                    </tr>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                </form>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
