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
    'title' => '',
    'url'   => '',
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
        $title = trim($_POST['title'] ?? '');
        $url   = trim($_POST['url']   ?? '');
        $price = trim($_POST['price'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Preis-Formatierung
        $cleanPrice = str_replace(',', '.', $price);
        $priceFloat = (!empty($cleanPrice)) ? (float)$cleanPrice : null;

        $formData = [
            'title' => $title,
            'url'   => $url,
            'price' => $price,
            'notes' => $notes,
        ];

        // 4. Pflichtfeld-Check (Nur wenn wir speichern wollen)
        if (isset($_POST['add_wish'])) {
            if (empty($title)) {
                $errors[] = translate('Bitte eine Beschreibung/Wunsch eingeben (Pflichtfeld).');
            }

            if (empty($errors)) {
                try {
                    $pdo = Database::get();
                    
                    // URL-Anpassung
                    if (!empty($url) && !preg_match('#^https?://#i', $url)) {
                        $url = 'https://' . $url;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO wishes 
                        (owner, title, url, price, notes, claimed) 
                        VALUES (?, ?, ?, ?, ?, NULL)
                    ");

                    $stmt->execute([
                        $currentUserId,
                        $title,
                        $url ?: null,
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

<header class="main-header">
    <div class="header-left">
        <h1><?= translate("Wunsch hinzufügen") ?></h1>
    </div>
    <nav class="header-right">
        <form method="post" action="add.php" style="display:inline;">
             <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
             <button type="submit" name="back" class="button"><?= translate("Zurück") ?></button>
        </form>
    </nav>
</header>

<main class="container">
    <section class="card">
        <form method="post" action="add.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
            
            <?php if ($errors): ?>
                <div style="color: #e74c3c; font-weight: bold; padding: 1rem; background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 8px; margin-bottom: 1.5rem;">
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="title"><?= translate("Wunsch / Beschreibung") ?> *</label>
                <input name="title" id="title" type="text" value="<?= htmlspecialchars($formData['title']) ?>" required placeholder="<?= translate("Was wünschst Du Dir?") ?>">
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="url"><?= translate("Link / Verknüpfung") ?></label>
                    <input name="url" id="url" type="text" value="<?= htmlspecialchars($formData['url']) ?>" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label for="price"><?= translate("Preis (ca.)") ?></label>
                    <input name="price" id="price" type="text" value="<?= htmlspecialchars($formData['price']) ?>" placeholder="0,00 €">
                </div>
            </div>

            <div class="form-group">
                <label for="notes"><?= translate("Notizen / Details") ?></label>
                <textarea name="notes" id="notes" rows="4" placeholder="<?= translate("Größe, Farbe, Shop-Details...") ?>"><?= htmlspecialchars($formData['notes']) ?></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="submit" name="add_wish" value="1" class="button" style="flex: 2;">
                    <?= translate("übernehmen") ?>
                </button>
                <button type="reset" class="button button-secondary" style="flex: 1;">
                    <?= translate("Abbrechen") ?>
                </button>
            </div>
        </form>
    </section>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> <?= translate("Wunschliste") ?></div>
</footer>

</body>
</html>
