<?php
/**
 * Wunschliste - Druckerfreundliche Ansicht meiner Reservierungen (PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'Lax',
]);

// Gemeinsame Hilfsfunktionen laden
require_once __DIR__ . '/inc/common.php';

if (empty($_SESSION['username']) || empty($_SESSION['id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['id'];

require_once 'inc/config.php';
require_once 'inc/qr_helper.php'; // QR-Code Hilfsfunktionen

// --------------------------------------------------
// Reservierte Wünsche laden
// --------------------------------------------------
$pdo = Database::get();

$stmt = $pdo->prepare("
    SELECT 
        w.id, w.title, w.url, w.notes,
        a.f_name AS owner_name
    FROM wishes w
    JOIN users a ON w.owner = a.id
    WHERE w.claimed = ?
    ORDER BY w.id DESC
");
$stmt->execute([$currentUserId]);
$reservedWishes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= gettext("Meine reservierten Wünsche") ?> - Druckerfreundlich</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body class="is-centered">

<header class="main-header no-print">
    <div class="header-content">
        <h1><?= gettext("Meine reservierten Wünsche") ?></h1>
        <div class="header-actions">
            <button onclick="window.print();" class="button no-print"><?= gettext("Drucken") ?></button>
            <button onclick="window.close();" class="button no-print"><?= gettext("Schließen") ?></button>
        </div>
    </div>
</header>

<main class="container">
    <section class="card full-width">
        <div class="list-table reserved-list">
            <div class="list-header">
                <div><?= gettext("Besitzer") ?></div>
                <div><?= gettext("Wunsch") ?></div>
                <div><?= gettext("Notizen") ?></div>
                <div><?= gettext("Verknüpfung") ?></div>
            </div>

            <?php if (empty($reservedWishes)): ?>
                <div class="list-row">
                    <div style="grid-column: span 4; text-align: center; padding: 20px;">
                        <?= gettext("Du hast noch keine Wünsche reserviert.") ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($reservedWishes as $wish): ?>
                    <div class="list-row">
                        <div><?= htmlspecialchars($wish['owner_name'] ?? '') ?></div>
                        <div><?= htmlspecialchars($wish['title'] ?? '') ?></div>
                        <div><?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?></div>
                        <div>
                            <?php if (!empty($wish['url'])): ?>
                                <?php
                                $url = $wish['url'];
                                if (!preg_match('#^https?://#i', $url)) {
                                    $url = 'https://' . $url;
                                }
                                ?>
                                <div class="wish-item-with-qr">
                                    <div class="wish-content">
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank">
                                            <?= htmlspecialchars($wish['url']) ?>
                                        </a>
                                    </div>
                                    <?= getQRCodeHtml($url, $currentUserId) ?>
                                </div>
                            <?php else: ?>
                                
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

</body>
</html>
