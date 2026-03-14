<?php
/**
 * Wunschliste - Neuen Wunsch hinzufügen (modernisiert für PHP 8.4 + SQLite)
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

require_once 'inc/config.php';
require_once 'inc/i18n.php';  // Enthält Database::get() etc.

$errors   = [];
$formData = [
    'desc'  => '',
    'link'  => '',
    'price' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Zurück-Button (Abbruch)
    if (isset($_POST['back'])) {
        header('Location: list.php');
        exit;
    }

    // 2. CSRF Validierung
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = translate('Sicherheitsfehler: Bitte Seite neu laden und erneut versuchen.');
    } else {
        // 3. Daten einlesen
        $desc  = trim($_POST['desc']  ?? '');
        $link  = trim($_POST['link']  ?? '');
        $price = trim($_POST['price'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Preis-Formatierung
        $cleanPrice = str_replace(',', '.', $price);
        $priceFloat = (!empty($cleanPrice)) ? (float)$cleanPrice : null;

        $formData = [
            'desc'  => $desc,
            'link'  => $link,
            'price' => $price,
            'notes' => $notes,
        ];

        // 4. Pflichtfeld-Check (Nur wenn wir speichern wollen)
        if (isset($_POST['add_wish'])) {
            if (empty($desc)) {
                $errors[] = translate('Bitte eine Beschreibung/Wunsch eingeben (Pflichtfeld).');
            }

            if (empty($errors)) {
                try {
                    $pdo = Database::get();
                    
                    // URL-Anpassung
                    if (!empty($link) && !preg_match('#^https?://#i', $link)) {
                        $link = 'https://' . $link;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO app_items 
                        (owner, title, url, price, notes, claimed) 
                        VALUES (?, ?, ?, ?, ?, NULL)
                    ");

                    $stmt->execute([
                        $currentUserId,
                        $desc,
                        $link ?: null,
                        $priceFloat,
                        $notes ?: null
                    ]);

                    header('Location: list.php');
                    exit;

                } catch (PDOException $e) {
                    $errors[] = translate('Datenbankfehler - bitte später erneut versuchen.') . " (" . $e->getCode() . ")";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate("Wunsch hinzufügen") ?> | <?= translate("Wunschliste") ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<table class="main_table">
    <tr>
        <td width="35%"></td>
        <td width="30%"></td>
        <td width="35%"></td>
    </tr>
    <tr>
        <td></td>
        <td align="center" valign="center">
            <table cellpadding="5" cellspacing="0" class="main_list">
                <!-- Formular beginnt hier, um ALLES einzuschließen -->
                <form method="post" action="add-wish.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                    
                    <tr>
                        <td class="heading_left">
                            <?= translate("Wunsch hinzufügen") ?><br><br>
                        </td>
                        <td class="heading_right">
                            <button type="submit" name="back" class="button" style="cursor: pointer; padding: 5px 15px;"><?= translate("Zurück") ?> >></button>
                        </td>
                    </tr>

                    <?php if ($errors): ?>
                        <tr>
                            <td colspan="2" style="color:#c00; font-weight:bold; padding:10px; background:#ffebee; border:1px solid #c00;">
                                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr align="center">
                        <td colspan="2" style="border-right:1px solid; border-left:1px solid; padding: 20px;">
                            <div style="margin-bottom: 20px; text-align: left;">
                                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #ffffff;"><?= translate("Wunsch / Beschreibung") ?> *</label>
                                <input name="desc" type="text" style="width: 100%; box-sizing: border-box; padding: 8px;" value="<?= htmlspecialchars($formData['desc']) ?>" required placeholder="<?= translate("Was wünschst Du Dir?") ?>">
                            </div>

                            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <div style="flex: 1.5; text-align: left;">
                                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #ffffff; white-space: nowrap;"><?= translate("Link / Verknüpfung") ?></label>
                                    <input name="link" type="text" style="width: 100%; box-sizing: border-box; padding: 8px;" value="<?= htmlspecialchars($formData['link']) ?>" placeholder="https://...">
                                </div>
                                <div style="flex: 1; text-align: left;">
                                    <label style="display: block; font-weight: bold; margin-bottom: 5px; white-space: nowrap; color: #ffffff;"><?= translate("Unverbindlicher Preis") ?></label>
                                    <input name="price" type="text" style="width: 100%; box-sizing: border-box; padding: 8px;" value="<?= htmlspecialchars($formData['price']) ?>" placeholder="0,00 €">
                                </div>
                            </div>

                            <div style="text-align: left;">
                                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #ffffff;"><?= translate("Notizen / Details") ?></label>
                                <textarea name="notes" rows="4" style="width: 100%; box-sizing: border-box; padding: 8px;" placeholder="<?= translate("Größe, Farbe, Shop-Details...") ?>"><?= htmlspecialchars($formData['notes']) ?></textarea>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2" class="footer_1_pce">
                            <button type="submit" name="add_wish" value="1" class="button" style="cursor: pointer; padding: 10px 20px; font-weight: bold;">
                                <?= translate("übernehmen") ?>
                            </button>
                            <input type="reset" class="button" value="<?= translate("Löschen") ?>" style="padding: 10px 20px;">
                        </td>
                    </tr>
                </form>
            </table>
        </td>
        <td></td>
    </tr>
</table>

</body>
</html>
