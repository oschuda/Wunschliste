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
            $stmt = $pdo->prepare("SELECT enabled, u_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row) {
                $newEnabled = $row['enabled'] ? 0 : 1;
                $pdo->prepare("UPDATE users SET enabled = ? WHERE id = ?")
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
                $stmt = $pdo->prepare("SELECT u_name FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $row = $stmt->fetch();

                if ($row) {
                    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
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
            $stmt = $pdo->prepare("SELECT u_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            if ($row) {
                $pdo->prepare("UPDATE users SET p_word = ? WHERE id = ?")
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
            $stmt = $pdo->prepare("SELECT u_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userRow = $stmt->fetch();

            $pdo->beginTransaction();
            try {
                // Account l�schen
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                // Alle W�nsche des Users löschen
                $pdo->prepare("DELETE FROM wishes WHERE owner = ?")->execute([$userId]);
                // Claims freigeben
                $pdo->prepare("UPDATE wishes SET claimed = NULL WHERE claimed = ?")->execute([$userId]);
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
            $stmt = $pdo->prepare("SELECT title FROM wishes WHERE id = ?");
            $stmt->execute([$wishId]);
            $wish = $stmt->fetch();
            $pdo->prepare("DELETE FROM wishes WHERE id = ?")->execute([$wishId]);
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
            $stmt = $pdo->prepare("SELECT title FROM wishes WHERE id = ?");
            $stmt->execute([$wishId]);
            $wish = $stmt->fetch();
            $pdo->prepare("UPDATE wishes SET claimed = NULL WHERE id = ?")->execute([$wishId]);
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
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users $searchCondition");
$stmt->execute($params);
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// --------------------------------------------------
// Alle Benutzer laden (für Verwaltung)
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, u_name, f_name, l_name, b_day, b_month, b_year, enabled, role
    FROM users
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
    FROM wishes w
    LEFT JOIN users a ON w.owner = a.id
    LEFT JOIN users c ON w.claimed = c.id
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

<header class="main-header">
    <div class="header-content">
        <div class="header-left">
            <h1><?= gettext("Administratorbereich") ?></h1>
        </div>
        <nav class="header-right header-actions">
            <form method="post" action="" style="display:flex; gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <button type="submit" name="back" class="button"><?= gettext("Zurück") ?></button>
                <button type="submit" name="logout" class="button button-danger"><?= gettext("Abmelden") ?></button>
            </form>
        </nav>
    </div>
</header>

<main class="container">
    <?php if ($messages): ?>
        <div style="padding:10px; background: rgba(39, 174, 96, 0.1); border: 1px solid var(--success-color); color: var(--success-color); border-radius: 8px; margin-bottom: 20px;">
            <?= implode('<br>', array_map('htmlspecialchars', $messages)) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h2><?= gettext("Benutzerverwaltung") ?></h2>
            <form method="get" action="" style="display: flex; gap: 5px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= gettext("Suche...") ?>" style="width: 200px;">
                <button type="submit" class="button"><?= gettext("Suchen") ?></button>
                <?php if ($search): ?>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="button button-secondary"><?= gettext("Reset") ?></a>
                <?php endif; ?>
            </form>
        </div>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            
            <div class="list-table admin-users-table">
                <div class="list-header">
                    <div><button type="submit" name="l_name" style="color:white; background:none; border:none; font-weight:bold; cursor:pointer; padding:0;"><?= gettext("Nachname") ?></button></div>
                    <div><button type="submit" name="f_name" style="color:white; background:none; border:none; font-weight:bold; cursor:pointer; padding:0;"><?= gettext("Vorname") ?></button></div>
                    <div><button type="submit" name="u_name" style="color:white; background:none; border:none; font-weight:bold; cursor:pointer; padding:0;"><?= gettext("Benutzer") ?></button></div>
                    <div><button type="submit" name="b_date" style="color:white; background:none; border:none; font-weight:bold; cursor:pointer; padding:0;"><?= gettext("Geburtstag") ?></button></div>
                    <div><?= gettext("Rolle") ?></div>
                    <div><?= gettext("Aktiv") ?></div>
                    <div style="text-align: center;">#</div>
                </div>

                <?php foreach ($users as $user): ?>
                    <?php
                    $enabledText = $user['enabled'] ? gettext("Ja") : gettext("Nein");
                    $roleText = match($user['role']) {
                        'admin' => gettext("Admin"),
                        'moderator' => gettext("Moderator"),
                        default => gettext("Benutzer")
                    };
                    $birthdate = ($user['b_day'] && $user['b_month'] && $user['b_year']) 
                        ? sprintf("%02d/%02d/%d", $user['b_day'], $user['b_month'], $user['b_year']) 
                        : '-';
                    ?>
                    <div class="list-row alternating-row">
                        <div><?= htmlspecialchars($user['l_name'] ?? '') ?></div>
                        <div><?= htmlspecialchars($user['f_name'] ?? '') ?></div>
                        <div><?= htmlspecialchars($user['u_name'] ?? '') ?></div>
                        <div><?= htmlspecialchars($birthdate) ?></div>
                        <div><?= $roleText ?></div>
                        <div style="color: <?= $user['enabled'] ? 'var(--success-color)' : 'var(--accent-color)' ?>;"><?= $enabledText ?></div>
                        <div style="text-align: center;"><input type="checkbox" name="u_selected[]" value="<?= (int)$user['id'] ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div style="text-align: center; margin: 20px 0; display: flex; justify-content: center; gap: 10px; align-items: center;">
                    <?php
                    parse_str($_SERVER['QUERY_STRING'], $p_params);
                    unset($p_params['page']);
                    $baseQuery = http_build_query($p_params);
                    $sep = $baseQuery ? '&' : '?';
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] . '?' . $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="button button-secondary">←</a>
                    <?php endif; ?>
                    <span><?= $page ?> / <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] . '?' . $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="button button-secondary">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <button type="submit" class="button" name="tog_enable"><?= gettext("An/Aus") ?></button>
                
                <div style="display: flex; gap: 5px; align-items: center; border-left: 1px solid var(--border-color); padding-left: 10px;">
                    <select name="new_role" style="width: auto;">
                        <option value="user"><?= gettext("Benutzer") ?></option>
                        <option value="moderator"><?= gettext("Moderator") ?></option>
                        <option value="admin"><?= gettext("Admin") ?></option>
                    </select>
                    <button type="submit" class="button" name="change_role"><?= gettext("Rolle") ?></button>
                </div>

                <div style="display: flex; gap: 5px; align-items: center; border-left: 1px solid var(--border-color); padding-left: 10px;">
                    <input type="text" name="new_password" placeholder="<?= gettext("Neues PW") ?>" style="width: 120px;">
                    <button type="submit" class="button" name="change_password"><?= gettext("PW ändern") ?></button>
                </div>

                <button type="submit" class="button button-danger" name="remove" onclick="return confirm('<?= gettext("Wirklich löschen?") ?>')" style="margin-left: auto;"><?= gettext("Löschen") ?></button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2 style="margin-bottom: 20px;"><?= gettext("Wunschverwaltung") ?></h2>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <div class="list-table admin-wishes-table">
                <div class="list-header">
                    <div><?= gettext("Titel") ?></div>
                    <div><?= gettext("Besitzer") ?></div>
                    <div><?= gettext("Reserviert von") ?></div>
                    <div style="text-align: center;">#</div>
                </div>

                <?php foreach ($wishes as $wish): ?>
                    <div class="list-row alternating-row">
                        <div><?= htmlspecialchars($wish['title'] ?? '') ?></div>
                        <div><?= htmlspecialchars($wish['owner_name'] ?? '') ?></div>
                        <div style="color: <?= $wish['claimed'] ? 'var(--accent-color)' : 'var(--text-muted)' ?>;">
                            <?= htmlspecialchars($wish['claimed_name'] ?? gettext("Frei")) ?>
                        </div>
                        <div style="text-align: center;"><input type="checkbox" name="w_selected[]" value="<?= (int)$wish['id'] ?>"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="button" name="unclaim"><?= gettext("Freigeben") ?></button>
                <button type="submit" class="button button-danger" name="delete_wish" onclick="return confirm('<?= gettext("Wirklich löschen?") ?>')" style="margin-left: auto;"><?= gettext("Löschen") ?></button>
            </div>
        </form>
    </section>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> <?= translate("Wunschliste") ?> - Admin Mode</div>
</footer>

</body>
</html>
