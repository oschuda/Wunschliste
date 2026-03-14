<?php
/**
 * Wunschliste - Admin-Bereich (modernisiert für PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Lax',
]);

// Nur Admins erlauben
if (empty($_SESSION['admin'])) {
    header('Location: logout.php');
    exit;
}

require_once 'inc/db.php';

// Gemeinsame Hilfsfunktionen laden
require_once __DIR__ . '/inc/common.php';
require_once __DIR__ . '/inc/config.php';

// Datenbankverbindung herstellen
$pdo = Database::get();

// --------------------------------------------------
// Logging-Funktion
// --------------------------------------------------
function logAdminAction(string $action, string $details): void {
    $logFile = __DIR__ . '/logs/admin.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        $timestamp = date('Y-m-d H:i:s');
        $adminUser = $_SESSION['admin_user'] ?? 'unknown';
        $logEntry = "[$timestamp] $adminUser: $action - $details\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
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
$sort_column = 'l_name';
$sort_direction = 'ASC';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['l_name']))  $sort_column = 'l_name';
    if (isset($_POST['f_name']))  $sort_column = 'f_name';
    if (isset($_POST['u_name']))  $sort_column = 'u_name';
    if (isset($_POST['b_date']))  $sort_column = "b_year, b_month, b_day";
}

// --------------------------------------------------
// Massen-Aktionen
// --------------------------------------------------
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isValidCsrf()) {
    $pdo = Database::get();

    // Abmelden-Logik
    if (isset($_POST['logout'])) {
        header('Location: logout.php');
        exit;
    }

    // Zurück-Logik
    if (isset($_POST['back'])) {
        header('Location: index.php');
        exit;
    }

    // Debug-Ausgabe für Testzwecke (kann später entfernt werden)
    if (!empty($_POST)) {
        error_log("Admin POST data: " . print_r($_POST, true));
    }

    if (isset($_POST['tog_enable']) && !empty($_POST['u_selected'])) {
        error_log("tog_enable Button gedrückt mit " . count($_POST['u_selected']) . " ausgewählten Benutzern");
        foreach ($_POST['u_selected'] as $userId) {
            $userId = (int)$userId;
            $stmt = $pdo->prepare("SELECT enabled, u_name FROM app_users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row) {
                $newEnabled = $row['enabled'] ? 0 : 1;
                $pdo->prepare("UPDATE app_users SET enabled = ? WHERE id = ?")
                    ->execute([$newEnabled, $userId]);

                // E-Mail-Benachrichtigung
                $subject = $newEnabled ? gettext("Ihr Konto wurde aktiviert") : gettext("Ihr Konto wurde deaktiviert");
                $message = sprintf(
                    gettext("Hallo %s,\n\nIhr Konto wurde %s.\n\nMit freundlichen Grüßen,\nDas Admin-Team"),
                    $row['u_name'],
                    $newEnabled ? gettext("aktiviert") : gettext("deaktiviert")
                );
                send_email($row['u_name'], $subject, $message);

                // Logging
                logAdminAction('User ' . ($newEnabled ? 'enabled' : 'disabled'), "User: {$row['u_name']} (ID: $userId)");
            }
        }
        $messages[] = 'Status der ausgewählten Konten wurde geändert.';
        error_log("Statusänderung erfolgreich für " . count($_POST['u_selected']) . " Benutzer");
    }

    if (isset($_POST['change_role']) && !empty($_POST['u_selected']) && isset($_POST['new_role'])) {
        $newRole = $_POST['new_role'];
        error_log("change_role Button gedrückt mit Rolle: $newRole und " . count($_POST['u_selected']) . " ausgewählten Benutzern");
        if (in_array($newRole, ['user', 'moderator', 'admin'])) {
            foreach ($_POST['u_selected'] as $userId) {
                $userId = (int)$userId;
                $stmt = $pdo->prepare("SELECT u_name FROM app_users WHERE id = ?");
                $stmt->execute([$userId]);
                $row = $stmt->fetch();

                if ($row) {
                    $pdo->prepare("UPDATE app_users SET role = ? WHERE id = ?")
                        ->execute([$newRole, $userId]);

                    // E-Mail-Benachrichtigung
                    $roleNames = [
                        'user' => gettext("Benutzer"),
                        'moderator' => gettext("Moderator"),
                        'admin' => gettext("Admin")
                    ];
                    $subject = gettext("Ihre Rolle wurde geändert");
                    $message = sprintf(
                        gettext("Hallo %s,\n\nIhre Rolle wurde zu '%s' geändert.\n\nMit freundlichen Grüßen,\nDas Admin-Team"),
                        $row['u_name'],
                        $roleNames[$newRole]
                    );
                    send_email($row['u_name'], $subject, $message);

                    // Logging
                    logAdminAction('Role changed', "User: {$row['u_name']} (ID: $userId) to role: $newRole");
                }
            }
            $messages[] = 'Rolle der ausgewählten Konten wurde geändert.';
            error_log("Rollenänderung erfolgreich für " . count($_POST['u_selected']) . " Benutzer zu Rolle: $newRole");
        }
    }

    if (isset($_POST['change_password']) && !empty($_POST['u_selected']) && !empty($_POST['new_password'])) {
        $newPassword = $_POST['new_password'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        foreach ($_POST['u_selected'] as $userId) {
            $userId = (int)$userId;
            $stmt = $pdo->prepare("SELECT u_name FROM app_users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row) {
                $pdo->prepare("UPDATE app_users SET p_word = ? WHERE id = ?")
                    ->execute([$hashedPassword, $userId]);

                // E-Mail-Benachrichtigung
                $subject = gettext("Ihr Passwort wurde geändert");
                $message = sprintf(
                    gettext("Hallo %s,\n\nIhr Passwort wurde von einem Administrator geändert.\n\nMit freundlichen Grüßen,\nDas Admin-Team"),
                    $row['u_name']
                );
                send_email($row['u_name'], $subject, $message);

                // Logging
                logAdminAction('Password changed', "User: {$row['u_name']} (ID: $userId)");
            }
        }
        $messages[] = 'Passwort der ausgewählten Konten wurde geändert.';
    }

    if (isset($_POST['remove']) && !empty($_POST['u_selected'])) {
        foreach ($_POST['u_selected'] as $userId) {
            $userId = (int)$userId;

            // Benutzername f�r Logging abrufen
            $stmt = $pdo->prepare("SELECT u_name FROM app_users WHERE id = ?");
            $stmt->execute([$userId]);
            $userRow = $stmt->fetch();

            $pdo->beginTransaction();
            try {
                // Account l�schen
                $pdo->prepare("DELETE FROM app_users WHERE id = ?")->execute([$userId]);
                // Alle W�nsche des Users löschen
                $pdo->prepare("DELETE FROM app_items WHERE owner = ?")->execute([$userId]);
                // Claims freigeben
                $pdo->prepare("UPDATE app_items SET claimed = NULL WHERE claimed = ?")->execute([$userId]);
                $pdo->commit();

                // Logging
                if ($userRow) {
                    logAdminAction('User deleted', "User: {$userRow['u_name']} (ID: $userId)");
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $messages[] = 'Fehler beim Löschen eines Benutzers.';
            }
        }
        $messages[] = 'Ausgewählte Konten wurden gelöscht.';
    }

    if (isset($_POST['delete_wish']) && !empty($_POST['w_selected'])) {
        foreach ($_POST['w_selected'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("SELECT title FROM app_items WHERE id = ?");
            $stmt->execute([$wishId]);
            $wish = $stmt->fetch();
            $pdo->prepare("DELETE FROM app_items WHERE id = ?")->execute([$wishId]);
            if ($wish) {
                logAdminAction('Wish deleted', "Wish: {$wish['title']} (ID: $wishId)");
            }
        }
        $messages[] = 'Ausgewählte Wünsche wurden gelöscht.';
    }
    }

    if (isset($_POST['unclaim']) && !empty($_POST['w_selected'])) {
        foreach ($_POST['w_selected'] as $wishId) {
            $wishId = (int)$wishId;
            $stmt = $pdo->prepare("SELECT title FROM app_items WHERE id = ?");
            $stmt->execute([$wishId]);
            $wish = $stmt->fetch();
            $pdo->prepare("UPDATE app_items SET claimed = NULL WHERE id = ?")->execute([$wishId]);
            if ($wish) {
                logAdminAction('Wish unclaimed', "Wish: {$wish['title']} (ID: $wishId)");
            }
        }
        $messages[] = 'Reservierungen wurden freigegeben.';
    }

// --------------------------------------------------
// Suchfunktion für Benutzer
// --------------------------------------------------
$search = trim($_GET['search'] ?? '');
$searchCondition = '';
$params = [];

if ($search) {
    $searchCondition = "WHERE u_name LIKE ? OR f_name LIKE ? OR l_name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// --------------------------------------------------
// Paginierung
// --------------------------------------------------
$perPage = 20; // Benutzer pro Seite
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Gesamtanzahl der Benutzer (mit Suche)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users $searchCondition");
$stmt->execute($params);
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// --------------------------------------------------
// Alle Benutzer laden (für Verwaltung)
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, u_name, f_name, l_name, b_day, b_month, b_year, enabled, role
    FROM app_users
    $searchCondition
    ORDER BY $sort_column $sort_direction
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$users = $stmt->fetchAll();

// --------------------------------------------------
// Alle Wünsche laden (für Moderation)
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT w.id, w.title, w.owner, w.claimed, a.u_name AS owner_name, c.u_name AS claimed_name
    FROM app_items w
    LEFT JOIN app_users a ON w.owner = a.id
    LEFT JOIN app_users c ON w.claimed = c.id
    ORDER BY w.id DESC
");
$stmt->execute();
$wishes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Wunschliste</title>
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
                <tr height="20">
                    <td class="heading_left">
                        <p><b><?= gettext("Administratorbereich") ?></b></p>
                    </td>
                    <td class="heading_right">
                        <form method="post" action="">
                            <input type="submit" class="button" name="logout" value="<?= gettext("Abmelden") ?>" style="background: var(--color-accent-secondary);">
                            <input type="submit" class="button" name="back" value="<?= gettext("Zurück >>") ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        </form>
                    </td>
                </tr>

                <?php if ($messages): ?>
                        <tr>
                            <td colspan="2" style="padding:10px; background:#e8f5e9; border:1px solid #4caf50; color:#006400;">
                                <?= implode('<br>', array_map('htmlspecialchars', $messages)) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr height="100%">
                        <td colspan="2" valign="top" align="left" style="border-left: 1px solid; border-right: 1px solid;">
                            <div style="margin-bottom: 10px;">
                                <form method="get" action="" style="display: inline-block;">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= gettext("Suche nach Name oder Benutzername") ?>" style="width: 200px;">
                                    <input type="submit" class="button" value="<?= gettext("Suchen") ?>">
                                    <?php if ($search): ?>
                                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="button"><?= gettext("Suche zurücksetzen") ?></a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <form method="post" action="">
                                <table cellspacing="1" cellpadding="2" class="main_list">
                                    <tr class="header_row">
                                        <td><input type="submit" class="button" name="l_name"  value="<?= gettext("Nachname") ?>"></td>
                                        <td><input type="submit" class="button" name="f_name"  value="<?= gettext("Vorname") ?>"></td>
                                        <td><input type="submit" class="button" name="u_name"  value="<?= gettext("Benutzer") ?>"></td>
                                        <td><input type="submit" class="button" name="b_date"  value="<?= gettext("Geburtstag") ?>"></td>
                                        <td width="10%"><b><?= gettext("Rolle") ?></b></td>
                                        <td width="10%"><b><?= gettext("Aktiviert") ?></b></td>
                                        <td align="center" width="10%"><b><?= gettext("Auswählen") ?></b></td>
                                    </tr>

                                    <?php foreach ($users as $user): ?>
                                        <?php
                                        $enabledText = $user['enabled'] ? gettext("Ja") : gettext("Nein");
                                        $roleText = match($user['role']) {
                                            'admin' => gettext("Admin"),
                                            'moderator' => gettext("Moderator"),
                                            default => gettext("Benutzer")
                                        };
                                        $birthdate = '';
                                        if ($user['b_day'] && $user['b_month'] && $user['b_year']) {
                                            $birthdate = sprintf("%02d/%02d/%d", $user['b_day'], $user['b_month'], $user['b_year']);
                                        }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['l_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($user['f_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($user['u_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($birthdate) ?></td>
                                            <td><?= $roleText ?></td>
                                            <td><?= $enabledText ?></td>
                                            <td align="center">
                                                <input type="checkbox" name="u_selected[]" value="<?= (int)$user['id'] ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>

                                <?php if ($totalPages > 1): ?>
                                <div style="text-align: center; margin: 10px 0;">
                                    <?php
                                    $baseUrl = $_SERVER['PHP_SELF'];
                                    $queryString = $_SERVER['QUERY_STRING'];
                                    parse_str($queryString, $p_params);
                                    unset($p_params['page']);
                                    $baseQuery = http_build_query($p_params);
                                    $separator = $baseQuery ? '&' : '?';
                                    ?>
                                    <?php if ($page > 1): ?>
                                        <a href="<?= $baseUrl . $baseQuery . $separator ?>page=<?= $page - 1 ?>" class="button">← <?= gettext("Zurück") ?></a>
                                    <?php endif; ?>
                                    Seite <?= $page ?> von <?= $totalPages ?>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= $baseUrl . $baseQuery . $separator ?>page=<?= $page + 1 ?>" class="button"><?= gettext("Weiter") ?> →</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <div class="footer_1_pce" style="padding: 10px 0;">
                                    <input type="submit" class="button" name="tog_enable" value="<?= gettext("Aktivieren/Blockieren") ?>">
                                    <span style="margin: 0 10px; border-left: 1px solid #ccc; padding-left: 10px;">
                                        <select name="new_role">
                                            <option value="user"><?= gettext("Benutzer") ?></option>
                                            <option value="moderator"><?= gettext("Moderator") ?></option>
                                            <option value="admin"><?= gettext("Admin") ?></option>
                                        </select>
                                        <input type="submit" class="button" name="change_role" value="<?= gettext("Rolle ändern") ?>">
                                    </span>
                                    <span style="margin: 0 10px; border-left: 1px solid #ccc; padding-left: 10px;">
                                        <input type="text" name="new_password" placeholder="<?= gettext("Neues Passwort") ?>" style="width: 120px;">
                                        <input type="submit" class="button" name="change_password" value="<?= gettext("Passwort ändern") ?>">
                                    </span>
                                    <input type="submit" class="button" name="remove" value="<?= gettext("Löschen") ?>" onclick="return confirm('<?= gettext("Wirklich löschen?") ?>')" style="margin-left: 10px;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                </div>
                            </form>
                        </td>
                    </tr>

                    <tr height="20">
                        <td colspan="2" class="heading_left">
                            <p><b><?= gettext("Wunschverwaltung") ?></b></p>
                        </td>
                    </tr>

                    <tr height="100%">
                        <td colspan="2" valign="top" align="left" style="border-left: 1px solid; border-right: 1px solid;">
                            <form method="post" action="">
                                <table cellspacing="1" cellpadding="2" class="main_list">
                                    <tr class="header_row">
                                        <td><b><?= gettext("Titel") ?></b></td>
                                        <td><b><?= gettext("Besitzer") ?></b></td>
                                        <td><b><?= gettext("Reserviert von") ?></b></td>
                                        <td align="center" width="10%"><b><?= gettext("Auswählen") ?></b></td>
                                    </tr>

                                    <?php foreach ($wishes as $wish): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($wish['title'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($wish['owner_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($wish['claimed_name'] ?? gettext("Frei")) ?></td>
                                            <td align="center">
                                                <input type="checkbox" name="w_selected[]" value="<?= (int)$wish['id'] ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                                <div class="footer_1_pce" style="padding: 10px 0;">
                                    <input type="submit" class="button" name="unclaim" value="<?= gettext("Freigeben") ?>">
                                    <input type="submit" class="button" name="delete_wish" value="<?= gettext("Löschen") ?>" onclick="return confirm('<?= gettext("Wirklich löschen?") ?>')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
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
