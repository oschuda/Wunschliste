<?php
$phpqrcode_path = __DIR__ . "/phpqrcode-master/phpqrcode.php";
$qr_cache_dir = __DIR__ . "/../data/qr_cache/";
if (file_exists($phpqrcode_path)) {
    require_once $phpqrcode_path;
    require_once __DIR__ . "/i18n.php";
    if (!is_dir($qr_cache_dir)) mkdir($qr_cache_dir, 0755, true);
} else {
    if (!function_exists("getQRCodeHtml")) { function getQRCodeHtml($u, $i = null, $s = null) { return ""; } }
    return;
}
if (!function_exists("getUserQRSettings")) {
function getUserQRSettings($userId) {
    require_once __DIR__ . "/db.php";
    $pdo = Database::get();
    $stmt = $pdo->prepare("SELECT qr_size, qr_use_cache FROM app_users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    // Mapping der numerischen Werte auf die erwarteten String-Keys
    $sizeMap = [1 => "small", 2 => "small", 3 => "medium", 4 => "large", 5 => "xlarge"];
    $sizeKey = $sizeMap[$result["qr_size"] ?? 3] ?? "medium";

    return [
        "size" => $sizeKey,
        "cache_enabled" => ($result["qr_use_cache"] ?? 1) == 1
    ];
}
}
if (!function_exists("getQRSizeConfig")) {
function getQRSizeConfig($sizeName) {
    $sizes = ["small"=>["qr_size"=>2,"display_size"=>60],"medium"=>["qr_size"=>3,"display_size"=>80],"large"=>["qr_size"=>4,"display_size"=>100],"xlarge"=>["qr_size"=>5,"display_size"=>120]];
    return $sizes[$sizeName] ?? $sizes["medium"];
}
}
if (!function_exists("getCachedQRCode")) {
function getCachedQRCode($data, $params = []) {
    global $qr_cache_dir;
    if (empty($data)) return "";
    $cacheKey = md5($data . serialize($params));
    $cacheFile = $qr_cache_dir . $cacheKey . ".png";
    if (($params["cache_enabled"] ?? true) && file_exists($cacheFile)) return "data:image/png;base64," . base64_encode(file_get_contents($cacheFile));
    
    // Check for GD library
    if (!function_exists("imagecreate")) {
        return ""; // Silently fail if GD is missing to prevent Fatal Error
    }

    try {
        QRcode::png($data, $cacheFile, QR_ECLEVEL_M, $params["qr_size"] ?? 3, 2);
        if (file_exists($cacheFile)) {
            return "data:image/png;base64," . base64_encode(file_get_contents($cacheFile));
        }
    } catch (Exception $e) { return ""; }
    return "";
}
}
if (!function_exists("getQRCodeHtml")) {
function getQRCodeHtml(string $url, $userId = null, $size = null): string {
    if (empty($url)) return "";
    if (!preg_match("#^https?://#i", $url)) $url = "https://" . $url;
    $settings = $userId ? getUserQRSettings($userId) : ["size" => "medium", "cache_enabled" => true];
    $sizeConfig = getQRSizeConfig($settings["size"]);
    $qrData = getCachedQRCode($url, ["qr_size" => $sizeConfig["qr_size"], "cache_enabled" => $settings["cache_enabled"]]);
    if (empty($qrData)) return "";
    $displaySize = $size ?? $sizeConfig["display_size"];
    $lbl = gettext("Vergrößern");
    return "<div class=\x27qr-code-container\x27><img src=\x27$qrData\x27 width=\x27$displaySize\x27 height=\x27$displaySize\x27 class=\x27qr-clickable\x27 onclick=\x27showQRModal(this.src)\x27 title=\x27$lbl\x27><span class=\x27qr-label\x27 onclick=\x27showQRModal(this.previousElementSibling.src)\x27>$lbl</span></div>";
}
}
