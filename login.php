<?php
/**
 * Wunschliste - modernisierte Login-Seite (PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'cookie_samesite' => 'None', // Allow cross-site requests from Browser Extension (Ebay/Amazon)
]);

require_once 'inc/config.php';
require_once 'inc/db.php';           // PDO + DB-Setup

// --------------------------------------------------
// CSRF Token generieren / validieren
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
// Login-Logik
// --------------------------------------------------
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sprache umschalten
    if (isset($_POST['set_lang'])) {
        $l = $_POST['set_lang'];
        if (in_array($l, ['de', 'en'])) {
            $_SESSION['lang'] = $l;
        }
        header('Location: login.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    if (!isValidCsrf()) {
        $errors[] = translate('security_error');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Benutzername und Kennwort sind erforderlich.';
        } else {
            // Hardcoded Admin-Login (SICHERHEIT: später entfernen oder auslagern!)
            $admin_user = 'admin';          // ? aus config laden!
            $admin_pass = 'geheim123';      // ? aus .env laden!

            if ($username === $admin_user && $password === $admin_pass) {
                $_SESSION['admin'] = true;
                header('Location: ./admin.php');
                exit;
            }

            // Normaler Benutzer-Login
            try {
                $pdo = Database::get();

                $stmt = $pdo->prepare("SELECT id, u_name, p_word, enabled, role FROM app_users WHERE u_name = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && $user['enabled'] && password_verify($password, $user['p_word'])) {
                    // Erfolgreich
                    $_SESSION['username'] = $user['u_name'];
                    $_SESSION['id']       = (int)$user['id'];
                    $_SESSION['role']     = $user['role'] ?? 'user';
                    $_SESSION['admin']    = ($user['role'] === 'admin');
                    $_SESSION['admin_user'] = $user['u_name'];
                    // Passwort NICHT in Session speichern!

                    // Optional: last_login updaten
                    $pdo->prepare("UPDATE app_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$user['id']]);

                    // Admin weiterleiten
                    if ($user['role'] === 'admin') {
                        header('Location: ./admin.php');
                    } else {
                        header('Location: ./index.php');
                    }
                    exit;
                } else {
                    $errors[] = translate('auth_error');
                }
            } catch (PDOException $e) {
                $errors[] = translate('db_error');
                error_log($e->getMessage());
            }
        }
    }
}

// --------------------------------------------------
// HTML-Ausgabe
// --------------------------------------------------
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'de' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wunschliste - <?= translate('login_title') ?></title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; padding:20px;">

<div style="width: 100%; max-width: 400px; text-align: right; margin-bottom: 10px;">
    <form method="POST" style="display:inline;">
        <button type="submit" name="set_lang" value="de" style="background:none; border:none; cursor:pointer; padding:0; filter: <?= ($_SESSION['lang'] ?? 'de') === 'de' ? 'none' : 'grayscale(100%) opacity(0.5)' ?>;">
            <img src="images/de.gif" alt="Deutsch" title="Deutsch" style="width:24px; vertical-align:middle;">
        </button>
        <button type="submit" name="set_lang" value="en" style="background:none; border:none; cursor:pointer; padding:0; filter: <?= ($_SESSION['lang'] ?? 'de') === 'en' ? 'none' : 'grayscale(100%) opacity(0.5)' ?>;">
            <img src="images/en.gif" alt="English" title="English" style="width:24px; vertical-align:middle;">
        </button>
    </form>
</div>

<table class="main_table" style="width: 100%; max-width: 400px;">
    <form method="post" action="login.php" class="form">
        <tr>
            <td width="0%"></td>
            <td width="100%"></td>
            <td width="0%"></td>
        </tr>
        <tr>
            <td></td>
            <td height="40%" valign="middle" align="center">
                <table border="0" width="100%" cellpadding="5" cellspacing="0" class="main_login">
                    <tr>
                        <td class="heading_left">
                            <?= translate('wishlist') ?>
                        </td>
                        <td class="heading_right">
                            <a href="./signup.php"><?= translate('register') ?></a>
                        </td>
                    </tr>

                    <?php if ($errors): ?>
                        <tr>
                            <td colspan="2" class="error-box">
                                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td align="right" style="border-left: 1px solid;">
                            <?= translate('username') ?>:
                        </td>
                        <td style="border-right: 1px solid;">
                            <input type="text" name="username" class="login" required autofocus>
                        </td>
                    </tr>
                    <tr>
                        <td align="right" style="border-left: 1px solid;">
                            <?= translate('password') ?>:
                        </td>
                        <td style="border-right: 1px solid;">
                            <input type="password" name="password" class="login" required>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="footer_1_pce">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                            <input type="submit" class="button" value="<?= translate('login') ?>" name="login">
                            <input type="reset"  class="button" value="<?= translate('reset') ?>">
                        </td>
                    </tr>
                </table>
            </td>
            <td></td>
        </tr>
    </form>
</table>

</body>
</html>
