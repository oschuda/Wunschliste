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
        $title    = trim($_POST['title'] ?? '');
        $url      = trim($_POST['url']   ?? '');
        $price    = trim($_POST['price'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        $category = trim($_POST['category'] ?? 'Standard');

        // Preis-Formatierung
        $cleanPrice = str_replace(',', '.', $price);
        $priceFloat = (!empty($cleanPrice)) ? (float)$cleanPrice : null;

        $formData = [
            'title'    => $title,
            'url'      => $url,
            'price'    => $price,
            'notes'    => $notes,
            'category' => $category,
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
                        (owner, title, url, price, notes, category, claimed) 
                        VALUES (?, ?, ?, ?, ?, ?, NULL)
                    ");

                    $stmt->execute([
                        $currentUserId,
                        $title,
                        $url ?: null,
                        $priceFloat,
                        $notes ?: null,
                        $category ?: 'Standard'
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
                    <div style="display: flex; gap: 5px;">
                        <input name="url" id="url" type="text" value="<?= htmlspecialchars($formData['url']) ?>" placeholder="https://..." style="flex: 1;">
                        <button type="button" id="btn-fetch" class="button button-success" title="<?= translate("Details automatisch abrufen") ?>">🔍</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="category"><?= translate("Liste / Gruppe") ?></label>
                    <input name="category" id="category" type="text" list="category-list" value="<?= htmlspecialchars($formData['category'] ?? 'Standard') ?>" placeholder="z.B. Geburtstag, Hochzeit...">
                    <datalist id="category-list">
                        <option value="Standard">
                        <option value="Geburtstag">
                        <option value="Weihnachten">
                        <option value="Hochzeit">
                        <option value="Familie">
                    </datalist>
                </div>
            </div>

            <div class="form-group">
                <label for="price"><?= translate("Preis (ca.)") ?></label>
                <input name="price" id="price" type="text" value="<?= htmlspecialchars($formData['price']) ?>" placeholder="0,00 €">
            </div>

            <div id="fetch-loader" style="display:none; color: var(--primary-color); font-size: 0.9rem; margin-bottom: 10px;">
                ⏳ <?= translate("Lade Produktdaten...") ?>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnFetch = document.getElementById('btn-fetch');
    const urlInput = document.getElementById('url');
    const titleInput = document.getElementById('title');
    const priceInput = document.getElementById('price');
    const notesInput = document.getElementById('notes');
    const loader = document.getElementById('fetch-loader');
    const csrfToken = '<?= get_csrf_token() ?>';

    btnFetch.addEventListener('click', async () => {
        const url = urlInput.value.trim();
        if (!url) return alert('<?= translate("Bitte zuerst einen Link einfügen!") ?>');

        btnFetch.disabled = true;
        loader.style.display = 'block';

        const formData = new FormData();
        formData.append('url', url);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('inc/fetch/api.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                if (data.title) titleInput.value = data.title;
                if (data.price) priceInput.value = data.price;
                if (data.image && !notesInput.value.includes('Bild:')) {
                    notesInput.value = 'Bild: ' + data.image + '\n' + notesInput.value;
                }
            } else if (data.error) {
                console.error('Fetch Error:', data.error);
                alert('<?= translate("Konnte Daten nicht automatisch abrufen.") ?>');
            }
        } catch (error) {
            console.error('Fetch Failed:', error);
            alert('<?= translate("Netzwerkfehler beim Abrufen der Daten.") ?>');
        } finally {
            btnFetch.disabled = false;
            loader.style.display = 'none';
        }
    });
});
</script>
</body>
</html>
