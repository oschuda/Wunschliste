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
        header('Location: signup.php');
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
                    $stmt = $pdo->prepare("SELECT 1 FROM app_users WHERE u_name = ?");
                    $stmt->execute([$u_name]);
                    if ($stmt->fetch()) {
                        $errors[] = translate('username_taken');
                    } else {
                        // Neuer Benutzer
                        $hashed_password = password_hash($p_word, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("
                            INSERT INTO app_users (
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

<div class="main-container">

    <div style="text-align: right; margin-bottom: 10px;">
        <form method="POST" style="display:inline;">
            <button type="submit" name="set_lang" value="de" style="background:none; border:none; cursor:pointer; padding:0; filter: <?= ($_SESSION['lang'] ?? 'de') === 'de' ? 'none' : 'grayscale(100%) opacity(0.5)' ?>;">
                <img src="images/de.gif" alt="Deutsch" title="Deutsch" style="width:24px; vertical-align:middle;">
            </button>
            <button type="submit" name="set_lang" value="en" style="background:none; border:none; cursor:pointer; padding:0; filter: <?= ($_SESSION['lang'] ?? 'de') === 'en' ? 'none' : 'grayscale(100%) opacity(0.5)' ?>;">
                <img src="images/en.gif" alt="English" title="English" style="width:24px; vertical-align:middle;">
            </button>
        </form>
    </div>

    <form method="POST" action="signup.php">
        <table class="main_list" cellpadding="5" cellspacing="0">
            <tr>
                <td class="heading_left">
                    <?= translate('register') ?>
                </td>
                <td class="heading_right">
                    <button type="submit" name="back" class="button" formnovalidate>
                        <?= translate('back') ?> »
                    </button>
                </td>
            </tr>

            <?php if (!empty($errors)): ?>
                <tr>
                    <td colspan="2" class="error-box">
                        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                    </td>
                </tr>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <tr>
                    <td colspan="2" class="success-box">
                        <?= htmlspecialchars($success) ?>
                    </td>
                </tr>
            <?php endif; ?>

            <tr>
                <td class="label"><?= translate('first_name') ?>:</td>
                <td>
                    <input type="text" name="f_name" size="30" value="<?= htmlspecialchars($formData['f_name'] ?? '') ?>" required>
                    <span class="required">*</span>
                </td>
            </tr>
            <tr>
                <td class="label"><?= translate('last_name') ?>:</td>
                <td>
                    <input type="text" name="l_name" size="30" value="<?= htmlspecialchars($formData['l_name'] ?? '') ?>" required>
                    <span class="required">*</span>
                </td>
            </tr>
            <tr>
                <td class="label"><?= translate('username') ?>:</td>
                <td>
                    <input type="text" name="u_name" size="30" value="<?= htmlspecialchars($formData['u_name'] ?? '') ?>" required>
                    <span class="required">*</span>
                </td>
            </tr>
            <tr>
                <td class="label"><?= translate('password') ?>:</td>
                <td>
                    <input type="password" name="p_word" size="30" required>
                    <span class="required">*</span>
                </td>
            </tr>

            <!-- Geburtstag -->
            <tr>
                <td class="label"><?= translate('birthday') ?>:</td>
                <td>
                    <select name="b_day">
                        <option value="0">-- <?= translate('day') ?> --</option>
                        <?php for ($x = 1; $x <= 31; $x++): ?>
                            <option value="<?= $x ?>" <?= ($formData['b_day'] ?? 0) == $x ? 'selected' : '' ?>><?= $x ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="b_month">
                        <option value="0">-- <?= translate('month') ?> --</option>
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
                        <option value="0">-- <?= translate('year') ?> --</option>
                        <?php for ($x = date('Y'); $x >= 1920; $x--): ?>
                            <option value="<?= $x ?>" <?= ($formData['b_year'] ?? 0) == $x ? 'selected' : '' ?>><?= $x ?></option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>

            <!-- Adresse & Kontakt -->
            <tr>
                <td class="label"><?= translate('address') ?>:</td>
                <td>
                    <input type="text" name="p_address" size="30" value="<?= htmlspecialchars($formData['p_address'] ?? '') ?>" placeholder="Strasse"><br>
                    <input type="text" name="postcode" size="5" value="<?= htmlspecialchars($formData['postcode'] ?? '') ?>" placeholder="PLZ">
                    <input type="text" name="suburb" size="15" value="<?= htmlspecialchars($formData['suburb'] ?? '') ?>" placeholder="Ort">
                    <input type="text" name="state" size="7" value="<?= htmlspecialchars($formData['state'] ?? '') ?>" placeholder="Bundesland">
                </td>
            </tr>
            <tr>
                <td class="label"><?= translate('country') ?>:</td>
                <td>
                    <input type="text" name="country" size="30" value="<?= htmlspecialchars($formData['country'] ?? '') ?>">
                </td>
            </tr>
            <tr>
                <td class="label"><?= translate('email') ?>:</td>
                <td>
                    <input type="email" name="email" size="30" value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                </td>
            </tr>
            <tr>
                <td class="label"><?= translate('phone') ?>:</td>
                <td>
                    <input type="tel" name="phone" size="30" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
                </td>
            </tr>

            <tr>
                <td class="label"><?= translate('show_details') ?>:</td>
                <td>
                    <input type="checkbox" name="s_details" <?= !empty($formData['s_details']) ? 'checked' : '' ?>>
                    <small>(<?= translate('allow_others_view') ?>)</small>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="footer_1_pce">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                    <button type="submit" class="button" name="register_submit">
                        <?= translate('register') ?>
                    </button>
                    <button type="reset" class="button">
                        <?= translate('reset') ?>
                    </button>
                </td>
            </tr>
        </table>
    </form>
</div>

</body>
</html>
