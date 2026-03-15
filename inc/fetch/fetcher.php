<?php
/**
 * Wunschliste - Metadaten Extraktion Service
 */

declare(strict_types=1);

class UrlMetadataFetcher {
    /**
     * Extrahiert Metadaten (Titel, Preis, Bild) aus einer URL
     */
    public static function fetch(string $url): array {
        $data = [
            'title' => '',
            'price' => '',
            'image' => '',
            'success' => false
        ];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $data;
        }

        $html = self::getHtml($url);
        if (!$html) return $data;

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($doc);

        $data['title'] = self::extractTitle($xpath);
        $data['price'] = self::extractPrice($html, $xpath, $url);
        $data['image'] = self::extractImage($xpath);
        
        if (!empty($data['title'])) {
            $data['success'] = true;
        }

        return $data;
    }

    private static function getHtml(string $url): ?string
    {
        $ch = curl_init($url);

        // Sehr browser-ähnliche Header-Kombination (Dein Vorschlag)
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // Nur temporär – später auf true setzen
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => 'gzip, deflate', // Komprimierung akzeptieren
            CURLOPT_HEADER         => false,
        ]);

        $html = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html === false || $httpCode >= 400) {
            error_log("Fetch-Fehler für $url | HTTP $httpCode | cURL: $error");
            return null;
        }

        // Encoding korrigieren (Verbesserte Version deines Vorschlags)
        if (!empty($html)) {
            $encoding = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $encoding);
            }
        }

        return $html;
    }

    private static function extractTitle(DOMXPath $xpath): string {
        $queries = [
            "//meta[@property='og:title']/@content",
            "//meta[@name='twitter:title']/@content",
            "//title"
        ];
        foreach ($queries as $query) {
            $node = $xpath->query($query)->item(0);
            if ($node) {
                return trim($node->nodeValue);
            }
        }
        return '';
    }

    private static function extractPrice(string $html, DOMXPath $xpath, string $url): string {
        // Amazon-spezifische Logik (CSS Klassen variieren oft)
        if (str_contains($url, 'amazon')) {
            $amazonQueries = [
                "//span[contains(@class, 'a-price-whole')]",
                "//span[@id='priceblock_ourprice']",
                "//span[@id='priceblock_dealprice']"
            ];
            foreach ($amazonQueries as $query) {
                $node = $xpath->query($query)->item(0);
                if ($node) {
                    $price = trim($node->nodeValue);
                    if (str_contains($html, 'a-price-fraction')) {
                        $fraction = $xpath->query("//span[contains(@class, 'a-price-fraction')]")->item(0);
                        if ($fraction) $price .= ',' . trim($fraction->nodeValue);
                    }
                    return $price;
                }
            }
        }

        // Kaufland.de spezifische Logik
        if (str_contains($url, 'kaufland.de')) {
            $kauflandQueries = [
                "//div[contains(@class, 'rd-price-information__price')]",
                "//span[contains(@class, 'rd-price-information__price')]",
                "//meta[@property='product:price:amount']/@content"
            ];
            foreach ($kauflandQueries as $query) {
                $node = $xpath->query($query)->item(0);
                if ($node) return trim($node->nodeValue);
            }
        }

        // eBay spezifische Logik
        if (str_contains($url, 'ebay.')) {
            $ebayQueries = [
                "//div[contains(@class, 'x-price-primary')]/span",
                "//span[@itemprop='price']",
                "//div[@class='display-price']"
            ];
            foreach ($ebayQueries as $query) {
                $node = $xpath->query($query)->item(0);
                if ($node) return trim($node->nodeValue);
            }
        }

        // Temu spezifische Logik (oft via OpenGraph oder spezifische Preis-Tags)
        if (str_contains($url, 'temu.')) {
            $temuQueries = [
                "//meta[@property='og:price:amount']/@content",
                "//div[contains(@class, 'price-container')]",
                "//span[contains(@class, 'price-text')]"
            ];
            foreach ($temuQueries as $query) {
                $node = $xpath->query($query)->item(0);
                if ($node) return trim($node->nodeValue);
            }
        }

        // MediaMarkt / Saturn spezifische Logik
        if (str_contains($url, 'mediamarkt') || str_contains($url, 'saturn')) {
            $mmQueries = [
                "//span[@data-test='mw-price-label']",
                "//div[contains(@class, 'price-tag-transformed')]",
                "//meta[@property='og:price:amount']/@content"
            ];
            foreach ($mmQueries as $query) {
                $node = $xpath->query($query)->item(0);
                if ($node) return trim($node->nodeValue);
            }
        }

        // Otto.de spezifische Logik
        if (str_contains($url, 'otto.de')) {
            $ottoQueries = [
                "//span[contains(@class, 'p_price__retail')]",
                "//span[contains(@class, 'pd_price__retail')]",
                "//meta[@itemprop='price']/@content"
            ];
            foreach ($ottoQueries as $query) {
                $node = $xpath->query($query)->item(0);
                if ($node) return trim($node->nodeValue);
            }
        }

        // Standard Metadaten
        $metaPrice = $xpath->query("//meta[@property='product:price:amount']/@content")->item(0);
        if ($metaPrice) return trim($metaPrice->nodeValue);

        // Regex-Suche als Fallback (Euro/Dollar Symbole)
        if (preg_match('/(\d+[\.,]\d{2})\s*(€|EUR|\$|USD)/i', $html, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function extractImage(DOMXPath $xpath): string {
        $queries = [
            "//meta[@property='og:image']/@content",
            "//meta[@name='twitter:image']/@content",
            "//link[@rel='image_src']/@href"
        ];
        foreach ($queries as $query) {
            $node = $xpath->query($query)->item(0);
            if ($node) return trim($node->nodeValue);
        }
        return '';
    }
}
