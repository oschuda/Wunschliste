<?php
declare(strict_types=1);
require_once __DIR__ . "/inc/common.php";
require_once __DIR__ . "/inc/db.php";
if (!empty($_SERVER["HTTP_ORIGIN"])) {
    header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-Wishlist-Key");
}
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }
header("Content-Type: application/json");
$authId = null;
$h = function_exists("getallheaders") ? getallheaders() : [];
$k = $_SERVER["HTTP_X_WISHLIST_KEY"] ?? $h["X-Wishlist-Key"] ?? $h["x-wishlist-key"] ?? "";
if (!empty($k)) {
    $pdo = Database::get();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE api_key = ?");
    $stmt->execute([$k]);
    $authId = $stmt->fetchColumn() ?: null;
}
if (!$authId) {
    session_start(["cookie_samesite" => "None", "cookie_secure" => true]);
    $authId = $_SESSION["id"] ?? null;
}
if (!$authId) { http_response_code(401); echo json_encode(["error" => "No Auth"]); exit; }
$input = json_decode(file_get_contents("php://input"), true);
try {
    $pdo = Database::get();
    $stmt = $pdo->prepare("INSERT INTO wishes (owner, title, url, notes, price, created) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([
        $authId,
        $input["title"] ?? "Unknown",
        $input["url"] ?? "",
        "Addon",
        $input["price"] ?? 0
    ]);
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
