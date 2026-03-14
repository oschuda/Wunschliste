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
    FROM wl_wishes w
    JOIN wl_accounts a ON w.owner = a.id
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
    <title><?= gettext("Meine reservierten Wünsche") ?>  Druckerfreundlich</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-family: Arial, sans-serif; margin: 1cm; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 8px; }
            th { background: #eee; }
        }
    </style>
</head>
<body>

<table width="100%" border="0" cellspacing="0" cellpadding="0" class="no-print">
    <tr>
        <td align="center">
            <h1><?= gettext("Meine reservierten Wünsche") ?></h1>
            <p><?= gettext("Angemeldet als") ?>: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
            <p><small>Version <?= htmlspecialchars(APP_VERSION) ?>  Druckdatum: <?= date('d.m.Y H:i') ?></small></p>
            <hr>
        </td>
    </tr>
</table>

<table width="100%" border="0" cellspacing="1" cellpadding="3" class="main_list">
    <tr bgcolor="#EDEDED">
        <th width="15%"><?= gettext("Besitzer") ?></th>
        <th><?= gettext("Wunsch") ?></th>
        <th><?= gettext("Notizen") ?></th>
        <th width="20%"><?= gettext("Verknüpfung") ?></th>
    </tr>

    <?php if (empty($reservedWishes)): ?>
        <tr>
            <td colspan="4" align="center" style="padding:20px;">
                <?= gettext("Du hast noch keine Wünsche reserviert.") ?>
            </td>
        </tr>
    <?php else: ?>
        <?php foreach ($reservedWishes as $wish): ?>
            <tr bgcolor="#F0F8FF">
                <td><?= htmlspecialchars($wish['owner_name'] ?? '�') ?></td>
                <td><?= htmlspecialchars($wish['title'] ?? '') ?></td>
                <td><?= nl2br(htmlspecialchars($wish['notes'] ?? '')) ?></td>
                <td>
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
                        �
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

</body>
</html>