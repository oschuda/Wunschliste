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
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wunschliste - Übersicht</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <div>
        <h1><?= gettext("Wunschlisten") ?></h1>
        <small><?= gettext("Angemeldet als") ?> <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></small>
    </div>
    <form method="post" style="flex-direction: row; align-items: center;">
        <button type="submit" name="lang_de" class="btn btn-secondary">DE</button>
        <button type="submit" name="lang_en" class="btn btn-secondary">EN</button>
        <?php if (!empty($_SESSION['admin'])): ?>
            <a href="admin.php" class="btn btn-danger">Admin</a>
        <?php endif; ?>
        <a href="tools.php" class="btn" style="background: var(--success-color);">🚀 Shop-Addon</a>
        <button type="submit" name="logout" class="btn btn-secondary"><?= gettext("Abmelden") ?></button>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
    </form>
</header>

<main>
    <section class="card">
        <div class="list-table">
            <header class="list-row list-header">
                <div><?= gettext("Nachname") ?></div>
                <div><?= gettext("Vorname") ?></div>
                <div class="text-right"><?= gettext("Geburtstag") ?></div>
                <div class="text-right"><?= gettext("Wünsche gesamt") ?></div>
                <div class="text-right"><?= gettext("Reservierte Wünsche") ?></div>
            </header>

            <?php foreach ($users as $user): ?>
                <?php
                $isOwn = ((int)$user['id'] === $currentUserId);
                $link = 'viewperson.php?viewID=' . (int)$user['id'];
                ?>
                <div class="list-row" style="<?= $isOwn ? 'border: 2px solid var(--primary-color);' : '' ?>">
                    <div><a href="<?= $link ?>"><?= htmlspecialchars($user['l_name'] ?? '') ?></a></div>
                    <div><a href="<?= $link ?>"><?= htmlspecialchars($user['f_name'] ?? '') ?></a></div>
                    <div class="text-right"><?= htmlspecialchars($user['birthdate'] ?? '') ?></div>
                    <div class="text-right"><?= (int)$user['total_wishes'] ?></div>
                    <div class="text-right">
                        <?= $isOwn ? '—' : (int)$user['claimed_wishes'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<footer>
    <form method="post" style="flex-direction: row; width: 100%; justify-content: space-between;">
        <button type="submit" name="edit_wishes" class="btn"><?= gettext("Wünsche bearbeiten") ?></button>
        <button type="submit" name="edit_details" class="btn"><?= gettext("Details bearbeiten") ?></button>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
    </form>
</footer>

</body>
</html>
