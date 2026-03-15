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
    FROM users 
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
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM wishes WHERE owner = ?");
    $stmt->execute([$id]);
    $user['total_wishes'] = (int)$stmt->fetchColumn();

    // Reservierte Wünsche (nur für andere sichtbar)
    if ($id !== $currentUserId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM wishes WHERE owner = ? AND claimed IS NOT NULL");
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
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#3498db">
    <link rel="apple-touch-icon" href="images/icon-192.png">
</head>
<body class="is-centered">

<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
}
</script>

<header class="main-header">
    <div class="header-content">
        <h1><?= gettext("Wunschlisten") ?></h1>
        <div class="header-actions">
            <span class="user-info"><?= gettext("Angemeldet als") ?> <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
            
            <button onclick="copyShareUrl()" class="button button-outline" title="Link zu deiner Wunschliste kopieren">
                🔗 Teilen
            </button>
            
            <a href="settings.php" class="button button-outline" title="<?= gettext("Einstellungen") ?>">⚙️</a>

            <form method="post" style="display:inline-flex; gap: 5px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <button type="submit" name="lang_de" class="button button-secondary">DE</button>
                <button type="submit" name="lang_en" class="button button-secondary">EN</button>
                <?php if (!empty($_SESSION['admin'])): ?>
                    <a href="admin.php" class="button button-danger">Admin</a>
                <?php endif; ?>
                <a href="tools.php" class="button button-success">🚀 Shop-Addon</a>
                <button type="submit" name="logout" class="button button-secondary"><?= gettext("Abmelden") ?></button>
            </form>
        </div>

        <script>
        function copyShareUrl() {
            const baseUrl = window.location.origin + window.location.pathname.replace('index.php', '');
            const shareUrl = baseUrl + 'viewperson.php?viewID=<?= $currentUserId ?>';
            
            navigator.clipboard.writeText(shareUrl).then(() => {
                alert('Dein persönlicher Wunschlisten-Link wurde kopiert:\n' + shareUrl);
            }).catch(err => {
                alert('Kopieren fehlgeschlagen. Dein Link ist:\n' + shareUrl);
            });
        }
        </script>
    </div>
</header>

<main class="container">
    <div class="user-grid">
        <?php foreach ($users as $user): ?>
            <?php
            $isOwn = ((int)$user['id'] === $currentUserId);
            $link = 'viewperson.php?viewID=' . (int)$user['id'];
            $firstName = $user['f_name'] ?? '';
            $lastName = $user['l_name'] ?? '';
            $fullName = htmlspecialchars(trim($firstName . ' ' . $lastName) ?: $user['u_name']);
            $initial = mb_substr($firstName ?: $user['u_name'], 0, 1);
            ?>
            <a href="<?= $link ?>" class="user-card <?= $isOwn ? 'is-own' : '' ?>">
                <div class="user-card-header">
                    <div class="user-avatar"><?= $isOwn ? '👤' : htmlspecialchars($initial) ?></div>
                    <h3><?= $isOwn ? '⭐ ' . translate("Mein Profil") : $fullName ?></h3>
                </div>
                
                <div class="user-card-body">
                    <div class="user-meta">
                        <?php if (!empty($user['b_day'])): ?>
                            📅 <?= (int)$user['b_day'] ?>.<?= (int)$user['b_month'] ?>.<?= (int)$user['b_year'] ?>
                        <?php else: ?>
                            🎁 <?= gettext("Kein Geburtsdatum") ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="user-stats">
                        <div class="stat-group">
                            <span class="stat-label"><?= gettext("Wünsche") ?></span>
                            <span class="stat-value"><?= (int)$user['total_wishes'] ?></span>
                        </div>
                        <?php if (!$isOwn): ?>
                        <div class="stat-group">
                            <span class="stat-label"><?= gettext("Reserviert") ?></span>
                            <span class="stat-value"><?= (int)$user['claimed_wishes'] ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer-overlay">
                    <?= translate("Wunschliste öffnen") ?> →
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="button-group align-center mt-20">
        <form method="post" style="display: flex; gap: 10px; width: 100%; justify-content: center;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <button type="submit" name="edit_wishes" class="button"><?= gettext("Meine Wünsche bearbeiten") ?></button>
            <button type="submit" name="edit_details" class="button"><?= gettext("Mein Profil bearbeiten") ?></button>
        </form>
    </div>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> <?= gettext("Wunschliste") ?></div>
</footer>

</body>
</html>
