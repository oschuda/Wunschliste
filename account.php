<?php
/**
 * Wunschliste - Persönliche Details bearbeiten (modernisiert für PHP 8.4 + SQLite)
 */

declare(strict_types=1);

session_start([
    "cookie_httponly" => true,
    "cookie_secure"   => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"),
    "cookie_samesite" => "Lax",
]);

// Gemeinsame Hilfsfunktionen laden
require_once __DIR__ . "/inc/common.php";

if (empty($_SESSION["username"]) || empty($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}

$currentUserId = (int)$_SESSION["id"];

require_once "inc/config.php";
require_once "inc/db.php";
require_once "inc/i18n.php";

// --------------------------------------------------
// CSRF Schutz
// --------------------------------------------------
function getCsrfToken(): string {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function isValidCsrf(): bool {
    $token = $_POST["csrf_token"] ?? "";
    return hash_equals($_SESSION["csrf_token"] ?? "", $token);
}

// --------------------------------------------------
// Daten laden
// --------------------------------------------------
$pdo = Database::get();

$stmt = $pdo->prepare("SELECT * FROM app_users WHERE id = ?");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit;
}

// --------------------------------------------------
// Formular-Verarbeitung
// --------------------------------------------------
$errors   = [];
$messages = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    if (isset($_POST["back"])) {
        header("Location: list.php");
        exit;
    }

    if (!isValidCsrf()) {
        $errors[] = translate("security_error");
    } else {
        $f_name      = trim($_POST["f_name"] ?? "");
        $l_name      = trim($_POST["l_name"] ?? "");
        $b_day       = (int)($_POST["b_day"] ?? 0);
        $b_month     = (int)($_POST["b_month"] ?? 0);
        $b_year      = (int)($_POST["b_year"] ?? 0);
        $p_address   = trim($_POST["p_address"] ?? "");
        $suburb      = trim($_POST["suburb"] ?? "");
        $state       = trim($_POST["state"] ?? "");
        $postcode    = trim($_POST["postcode"] ?? "");
        $country     = trim($_POST["country"] ?? "");
        $email       = trim($_POST["email"] ?? "");
        $phone       = trim($_POST["phone"] ?? "");
        $s_details   = (isset($_POST["s_details"]) && $_POST["s_details"] === "1") ? 1 : 0;
        $password    = $_POST["password"] ?? "";

        if (empty($f_name) || empty($l_name)) {
            $errors[] = translate("Vorname und Nachname sind Pflichtfelder.");
        }

        if (empty($errors)) {
            try {
                $updateFields = [
                    "f_name"    => $f_name,
                    "l_name"    => $l_name,
                    "b_day"     => $b_day,
                    "b_month"   => $b_month,
                    "b_year"    => $b_year,
                    "p_address" => $p_address,
                    "suburb"    => $suburb,
                    "state"     => $state,
                    "postcode"  => $postcode,
                    "country"   => $country,
                    "email"     => $email,
                    "phone"     => $phone,
                    "s_details" => $s_details
                ];

                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $errors[] = translate("password_too_short");
                    } else {
                        $updateFields["p_word"] = password_hash($password, PASSWORD_DEFAULT);
                    }
                }

                if (empty($errors)) {
                    $setClause = [];
                    $params    = [];
                    foreach ($updateFields as $field => $value) {
                        $setClause[] = "$field = ?";
                        $params[]    = $value;
                    }
                    $params[] = $currentUserId;
                    
                    $stmt = $pdo->prepare("UPDATE app_users SET " . implode(", ", $setClause) . " WHERE id = ?");
                    $stmt->execute($params);

                    $messages[] = translate("save_success") ?: "Daten erfolgreich aktualisiert.";
                }
            } catch (PDOException $e) {
                $errors[] = translate("db_error");
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION["lang"] ?? "de" ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= translate("Details bearbeiten") ?> - Wunschliste</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

<header class="main-header">
    <div class="header-left">
        <h1><?= translate("Details bearbeiten") ?></h1>
    </div>
    <nav class="header-right">
        <form method="POST" style="display:inline;">
            <button type="submit" name="back" class="button"><?= translate("Zurück") ?></button>
        </form>
    </nav>
</header>

<main class="container">
    <?php if ($errors): ?>
        <div style="color: #e74c3c; font-weight: bold; padding: 1rem; background: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; border-radius: 8px; margin-bottom: 1.5rem;">
            <?= implode("<br>", array_map("htmlspecialchars", $errors)) ?>
        </div>
    <?php endif; ?>

    <?php if ($messages): ?>
        <div style="padding:10px; background: rgba(39, 174, 96, 0.1); border: 1px solid var(--success-color); color: var(--success-color); border-radius: 8px; margin-bottom: 20px;">
            <?= implode("<br>", array_map("htmlspecialchars", $messages)) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label><?= translate("Vorname") ?> *</label>
                    <input type="text" name="f_name" value="<?= htmlspecialchars($user["f_name"] ?? "") ?>" required>
                </div>
                <div class="form-group">
                    <label><?= translate("Nachname") ?> *</label>
                    <input type="text" name="l_name" value="<?= htmlspecialchars($user["l_name"] ?? "") ?>" required>
                </div>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label><?= translate("Kennwort") ?> <small>(<?= translate("Zum Ändern ausfüllen") ?>)</small></label>
                <input type="password" name="password" placeholder="******">
            </div>

            <h3 style="margin: 1.5rem 0 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">
                <?= translate("Geburtstag") ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <input type="number" name="b_day" placeholder="TT" min="1" max="31" value="<?= $user["b_day"] ?: "" ?>">
                <input type="number" name="b_month" placeholder="MM" min="1" max="12" value="<?= $user["b_month"] ?: "" ?>">
                <input type="number" name="b_year" placeholder="JJJJ" min="1900" max="2100" value="<?= $user["b_year"] ?: "" ?>">
            </div>

            <h3 style="margin: 1.5rem 0 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">
                <?= translate("Postadresse") ?>
            </h3>
            <div class="form-group">
                <input type="text" name="p_address" placeholder="<?= translate("Straße / Hausnummer") ?>" value="<?= htmlspecialchars($user["p_address"] ?? "") ?>">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1rem; margin-top: 0.5rem;">
                <input type="text" name="postcode" placeholder="PLZ" value="<?= htmlspecialchars($user["postcode"] ?? "") ?>">
                <input type="text" name="suburb" placeholder="Ort" value="<?= htmlspecialchars($user["suburb"] ?? "") ?>">
            </div>

            <h3 style="margin: 1.5rem 0 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">
                <?= translate("Kontakt") ?>
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label><?= translate("Email") ?></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user["email"] ?? "") ?>">
                </div>
                <div class="form-group">
                    <label><?= translate("Telefon") ?></label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user["phone"] ?? "") ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="s_details" value="1" <?= ($user["s_details"] ?? 1) ? "checked" : "" ?> style="width: auto;">
                <label style="margin: 0;"><?= translate("Zeige Details für andere") ?></label>
            </div>

            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <button type="submit" name="submit" class="button" style="flex: 2;"><?= translate("Aktualisieren") ?></button>
                <button type="submit" name="back" class="button button-secondary" style="flex: 1;"><?= translate("Abbrechen") ?></button>
            </div>
        </form>
    </section>
</main>

<footer class="main-footer">
    <div>&copy; <?= date("Y") ?> <?= translate("Wunschliste") ?></div>
</footer>

</body>
</html>
