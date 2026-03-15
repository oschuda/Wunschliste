<?php
/**
 * Wunschliste - moderne Registrierungsseite (PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'None',
]);

require_once 'inc/config.php';

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
// Validierungs- & Registrierungs-Logik
// --------------------------------------------------
$errors = [];
$success = '';
$formData = []; // für Wert-Erhaltung bei Fehlern

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sprache umschalten
    if (isset($_POST['set_lang'])) {
        $l = $_POST['set_lang'];
        if (in_array($l, ['de', 'en'])) {
            $_SESSION['lang'] = $l;
        }
        // Seite neu laden um Änderungen zu übernehmen
        header('Location: register.php');
        exit;
    }

    if (isset($_POST['back'])) {
        header('Location: login.php');
        exit;
    }

    if (isset($_POST['register_submit'])) {

        if (!isValidCsrf()) {
            $errors[] = translate('security_error');
        } else {
            // Eingaben holen & bereinigen
            $f_name    = trim($_POST['f_name']    ?? '');
            $l_name    = trim($_POST['l_name']    ?? '');
            $u_name    = trim($_POST['u_name']    ?? '');
            $p_word    = $_POST['p_word']         ?? '';
            $email     = trim($_POST['email']     ?? '');
            $b_day     = (int)($_POST['b_day']    ?? 0);
            $b_month   = (int)($_POST['b_month']  ?? 0);
            $b_year    = (int)($_POST['b_year']   ?? 0);
            $p_address = trim($_POST['p_address'] ?? '');
            $suburb    = trim($_POST['suburb']    ?? '');
            $state     = trim($_POST['state']     ?? '');
            $postcode  = trim($_POST['postcode']  ?? '');
            $country   = trim($_POST['country']   ?? '');
            $phone     = trim($_POST['phone']     ?? '');
            $s_details = isset($_POST['s_details']) ? 1 : 0;

            $formData = [
                'f_name'    => $f_name,
                'l_name'    => $l_name,
                'u_name'    => $u_name,
                'email'     => $email,
                'b_day'     => $b_day,
                'b_month'   => $b_month,
                'b_year'    => $b_year,
                'p_address' => $p_address,
                'suburb'    => $suburb,
                'state'     => $state,
                'postcode'  => $postcode,
                'country'   => $country,
                'phone'     => $phone,
                's_details' => $s_details,
            ];

            // Pflichtfelder prüfen
            if (empty($f_name) || empty($l_name) || empty($u_name) || empty($p_word)) {
                $errors[] = translate('fill_required');
            }

            if (strlen($p_word) < 6) {
                $errors[] = translate('password_too_short');
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = translate('invalid_email');
            }

            if (empty($errors)) {
                try {
                    $pdo = Database::get();

                    // Username bereits vergeben?
                    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE u_name = ?");
                    $stmt->execute([$u_name]);
                    if ($stmt->fetch()) {
                        $errors[] = translate('username_taken');
                    } else {
                        // Neuer Benutzer
                        $hashed_password = password_hash($p_word, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("
                            INSERT INTO users (
                                f_name, l_name, u_name, p_word, enabled, s_details,
                                b_day, b_month, b_year, p_address, suburb, state,
                                postcode, country, email, phone, role
                            ) VALUES (
                                ?, ?, ?, ?, 1, ?,
                                ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?, 'user'
                            )
                        ");

                        $stmt->execute([
                            $f_name, $l_name, $u_name, $hashed_password, $s_details,
                            $b_day, $b_month, $b_year, $p_address, $suburb, $state,
                            $postcode, $country, $email, $phone
                        ]);

                        $success = translate('registration_success');
                    }
                } catch (PDOException $e) {
                    $errors[] = translate('db_error');
                    error_log($e->getMessage()); // für Debugging
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate('register') ?> - Wunschliste</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<header class="main-header">
    <div class="header-left">
        <h1><?= translate('register') ?></h1>
    </div>
    <nav class="header-right">
        <form method="POST" style="display:inline;">
            <button type="submit" name="set_lang" value="de" style="background:none; border:none; cursor:pointer; padding:0; filter: <?= ($_SESSION['lang'] ?? 'de') === 'de' ? 'none' : 'grayscale(100%) opacity(0.5)' ?>;">
                <img src="images/de.gif" alt="Deutsch" title="Deutsch" style="width:24px; vertical-align:middle;">
            </button>
            <button type="submit" name="set_lang" value="en" style="background:none; border:none; cursor:pointer; padding:0; filter: <?= ($_SESSION['lang'] ?? 'de') === 'en' ? 'none' : 'grayscale(100%) opacity(0.5)' ?>;">
                <img src="images/en.gif" alt="English" title="English" style="width:24px; vertical-align:middle;">
            </button>
            <button type="submit" name="back" class="button button-secondary" formnovalidate style="margin-left: 10px;">
                <?= translate('back') ?>
            </button>
        </form>
    </nav>
</header>

<main class="container">
    <?php if (!empty($errors)): ?>
        <div style="color: #e74c3c; font-weight: bold; padding: 1rem; background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 8px; margin-bottom: 1.5rem;">
            <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="padding:10px; background: rgba(39, 174, 96, 0.1); border: 1px solid var(--success-color); color: var(--success-color); border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($success) ?>
            <br><br>
            <a href="login.php" class="button"><?= translate('login') ?></a>
        </div>
    <?php endif; ?>

    <?php if (empty($success)): ?>
    <section class="card">
        <form method="POST" action="register.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label><?= translate('first_name') ?> *</label>
                    <input type="text" name="f_name" value="<?= htmlspecialchars($formData['f_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label><?= translate('last_name') ?> *</label>
                    <input type="text" name="l_name" value="<?= htmlspecialchars($formData['l_name'] ?? '') ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;">
                <div class="form-group">
                    <label><?= translate('username') ?> *</label>
                    <input type="text" name="u_name" value="<?= htmlspecialchars($formData['u_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label><?= translate('password') ?> *</label>
                    <input type="password" name="p_word" required>
                </div>
            </div>

            <h3 style="margin: 1.5rem 0 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">
                <?= translate('birthday') ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 2fr 1.5fr; gap: 1rem;">
                <select name="b_day">
                    <option value="0"><?= translate('day') ?></option>
                    <?php for ($x = 1; $x <= 31; $x++): ?>
                        <option value="<?= $x ?>" <?= ($formData['b_day'] ?? 0) == $x ? 'selected' : '' ?>><?= $x ?></option>
                    <?php endfor; ?>
                </select>
                <select name="b_month">
                    <option value="0"><?= translate('month') ?></option>
                    <?php
                    $months = [
                        1 => translate('january'), 2 => translate('february'), 3 => translate('march'),
                        4 => translate('april'), 5 => translate('may'), 6 => translate('june'),
                        7 => translate('july'), 8 => translate('august'), 9 => translate('september'),
                        10 => translate('october'), 11 => translate('november'), 12 => translate('december')
                    ];
                    foreach ($months as $num => $name):
                    ?>
                        <option value="<?= $num ?>" <?= ($formData['b_month'] ?? 0) == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="b_year">
                    <option value="0"><?= translate('year') ?></option>
                    <?php for ($x = date('Y'); $x >= 1920; $x--): ?>
                        <option value="<?= $x ?>" <?= ($formData['b_year'] ?? 0) == $x ? 'selected' : '' ?>><?= $x ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <h3 style="margin: 1.5rem 0 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">
                <?= translate('address') ?>
            </h3>
            <div class="form-group">
                <input type="text" name="p_address" value="<?= htmlspecialchars($formData['p_address'] ?? '') ?>" placeholder="<?= translate('Straße / Hausnummer') ?>">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                <input type="text" name="postcode" placeholder="PLZ" value="<?= htmlspecialchars($formData['postcode'] ?? '') ?>">
                <input type="text" name="suburb" placeholder="Ort" value="<?= htmlspecialchars($formData['suburb'] ?? '') ?>">
                <input type="text" name="state" placeholder="B-Land" value="<?= htmlspecialchars($formData['state'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-top: 0.5rem;">
                <input type="text" name="country" placeholder="<?= translate('country') ?>" value="<?= htmlspecialchars($formData['country'] ?? '') ?>">
            </div>

            <h3 style="margin: 1.5rem 0 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">
                <?= translate('Kontakt') ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1rem;">
                <input type="email" name="email" placeholder="<?= translate('email') ?>" value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                <input type="tel" name="phone" placeholder="<?= translate('phone') ?>" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
            </div>

            <div class="form-group" style="margin-top: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="s_details" value="1" <?= ($formData['s_details'] ?? 1) ? "checked" : "" ?> style="width: auto;">
                <label style="margin: 0;"><?= translate('show_details') ?> (<?= translate('allow_others_view') ?>)</label>
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <button type="submit" name="register_submit" class="button" style="flex: 2;"><?= translate('register') ?></button>
                <button type="reset" class="button button-secondary" style="flex: 1;"><?= translate('reset') ?></button>
            </div>
        </form>
    </section>
    <?php endif; ?>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> <?= translate("Wunschliste") ?></div>
</footer>

</body>
</html>
