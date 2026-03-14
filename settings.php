<?php
declare(strict_types=1);
session_start();
require_once 'inc/config.php';
require_once 'inc/db.php';
if (empty($_SESSION['id'])) { header('Location: login.php'); exit; }
$pdo = Database::get();
$userId = (int)$_SESSION['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('UPDATE app_users SET qr_size = ?, qr_use_cache = ? WHERE id = ?');
    $stmt->execute([(int)$_POST['qr_size'], isset($_POST['qr_use_cache']) ? 1 : 0, $userId]);
    $_SESSION['lang'] = $_POST['lang'];
    header('Location: settings.php?success=1');
    exit;
}
$stmt = $pdo->prepare('SELECT qr_size, qr_use_cache FROM app_users WHERE id = ?');
$stmt->execute([$userId]);
$userSettings = $stmt->fetch();
?>
<!DOCTYPE html>
<html>
<head><link rel='stylesheet' href='style.css'></head>
<body><div class='main-container'><a href='index.php' class='button'>« Zurück</a><h1>Einstellungen</h1>
<form method='POST'>Sprache: <select name='lang'><option value='de' <?= ($_SESSION['lang']??'de')=='de'?'selected':'' ?>>DE</option><option value='en' <?= ($_SESSION['lang']??'de')=='en'?'selected':'' ?>>EN</option></select><br>
QR-Größe: <input type='number' name='qr_size' value='<?= $userSettings['qr_size']??3 ?>'><br>
Cache: <input type='checkbox' name='qr_use_cache' <?= ($userSettings['qr_use_cache']??1)?'checked':'' ?>><br>
<button type='submit'>Speichern</button></form></div></body></html>
