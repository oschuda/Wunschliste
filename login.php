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
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="card" style="width: 100%; max-width: 400px; padding: var(--spacing-lg);">
        <header style="background: none; border: none; padding: 0; margin-bottom: var(--spacing-md); justify-content: center;">
            <h1 style="font-size: 1.5rem; text-align: center; color: var(--primary-color);"><?= translate('wishlist') ?></h1>
        </header>

        <?php if (!empty($errors)): ?>
            <div style="background: var(--accent-color); color: white; padding: var(--spacing-sm); border-radius: var(--border-radius); margin-bottom: var(--spacing-md); font-size: 0.9rem;">
                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <div class="form-group">
                <label for="username"><?= translate('Benutzer') ?></label>
                <input type="text" name="username" id="username" required autofocus placeholder="<?= translate('Benutzer') ?>">
            </div>
            <div class="form-group">
                <label for="password"><?= translate('Kennwort') ?></label>
                <input type="password" name="password" id="password" required placeholder="******">
            </div>
            
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            
            <button type="submit" name="login" class="btn" style="width: 100%; margin-top: var(--spacing-sm);"><?= translate('login') ?></button>
        </form>

        <hr style="margin: var(--spacing-md) 0; border: 0; border-top: 1px solid var(--border-color);">

        <div style="display: flex; justify-content: center; gap: var(--spacing-md);">
            <form method="POST" style="flex-direction: row; gap: var(--spacing-sm);">
                <button type="submit" name="set_lang" value="de" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.7rem;">DE</button>
                <button type="submit" name="set_lang" value="en" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.7rem;">EN</button>
            </form>
        </div>
    </div>
</main>

</body>
</html>
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
