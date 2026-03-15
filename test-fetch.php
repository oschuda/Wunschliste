<?php
/**
 * Wunschliste - Debug Tool für URL-Fetch
 * Hilft bei der Diagnose von Netzwerkfehlern oder Blockaden durch Shops
 */

$url = $_GET['url'] ?? 'https://www.amazon.de/dp/B0C7C7QJ7Q'; // Standard-Beispiel falls keine URL übergeben wurde

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
    ],
    CURLOPT_HEADER         => true, // ← Header sichtbar machen zum Debug
    CURLOPT_ENCODING       => 'gzip, deflate',
]);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

// Header vom Body trennen
$header_size = $info['header_size'];
$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);

echo "<!DOCTYPE html><html><head><title>Fetch Debug</title><style>body{font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;}pre{background:#2d2d2d;padding:15px;border-radius:5px;border:1px solid #444;white-space:pre-wrap;word-break:break-all;}h2{color:#569cd6; border-bottom:1px solid #444; padding-bottom:5px;}</style></head><body>";
echo "<h1>🔍 URL Fetch Debugger</h1>";
echo "<form><input type='text' name='url' value='".htmlspecialchars($url)."' style='width:80%;padding:10px;background:#333;color:white;border:1px solid #555;'><button type='submit' style='padding:10px 20px;background:#007acc;color:white;border:none;cursor:pointer;margin-left:5px;'>Testen</button></form>";

echo "<h2>📊 Request Info</h2>";
echo "<pre>";
echo "URL:       " . htmlspecialchars($url) . "\n";
echo "HTTP Code: " . $info['http_code'] . "\n";
echo "cURL Error: " . ($error ?: '✅ Kein Fehler') . "\n";
echo "Total Time: " . $info['total_time'] . " Sekunden\n";
echo "IP Adresse: " . $info['primary_ip'] . "\n";
echo "</pre>";

echo "<h2>📨 Response Header</h2>";
echo "<pre>" . htmlspecialchars($header) . "</pre>";

echo "<h2>📄 Body-Vorschau (Erste 1000 Zeichen)</h2>";
echo "<pre>" . htmlspecialchars(substr($body, 0, 1000)) . "</pre>";

echo "</body></html>";
