<?php
declare(strict_types=1);

$baseDir = dirname(__FILE__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';

function request_url(string $url, string $acceptHeader): ?string {
    $timeoutSeconds = 12;
    $userAgent = 'MadOfferBot/0.1 (+ops@example.com)';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_HTTPHEADER => ['Accept: ' . $acceptHeader, 'User-Agent: ' . $userAgent],
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false || $httpCode < 200 || $httpCode >= 400) return null;
    return (string) $raw;
}

echo "=== Checking NETTO ===\n";
$html = request_url('https://netto.dk/tilbudsavis/', 'text/html');
if (!$html) {
    echo "Failed to fetch Netto\n";
} else {
    preg_match('/data-publication-id\s*=\s*["\']?([A-Za-z0-9]{6,})["\']?/i', $html, $matches);
    $catalogId = $matches[1] ?? null;
    
    if (!$catalogId) {
        preg_match('/ern:catalog:([A-Za-z0-9]+)/', $html, $matches);
        $catalogId = $matches[1] ?? null;
    }
    
    if ($catalogId) {
        echo "Netto Catalog ID: $catalogId\n";
        $apiUrl = 'https://squid-api.tjek.com/v2/catalogs/' . rawurlencode($catalogId) . '/hotspots';
        $raw = request_url($apiUrl, 'application/json');
        if ($raw) {
            $hotspots = json_decode($raw, true);
            $count = 0;
            foreach ($hotspots as $spot) {
                if (!(is_array($spot) && ($spot['type'] ?? '') === 'offer')) continue;
                $offer = $spot['offer'] ?? [];
                if (!is_array($offer)) continue;
                $name = trim((string) ($offer['heading'] ?? $spot['heading'] ?? ''));
                if (stripos($name, 'mutti') !== false || stripos($name, 'tomat') !== false || stripos($name, 'hakkede') !== false) {
                    echo "  ✓ " . $name . " - " . ($offer['pricing']['price'] ?? 'N/A') . " DKK\n";
                    $count++;
                }
            }
            echo "Netto found: $count products\n";
        }
    } else {
        echo "Could not extract Netto catalog ID\n";
    }
}

echo "\n=== Checking 365discount ===\n";
$html = request_url('https://365discount.coop.dk/365avis/', 'text/html');
if (!$html) {
    echo "Failed to fetch 365discount\n";
} else {
    preg_match('/data-publication-id\s*=\s*["\']?([A-Za-z0-9]{6,})["\']?/i', $html, $matches);
    $catalogId = $matches[1] ?? null;
    
    if ($catalogId) {
        echo "365discount Catalog ID: $catalogId\n";
        $apiUrl = 'https://squid-api.tjek.com/v2/catalogs/' . rawurlencode($catalogId) . '/hotspots';
        $raw = request_url($apiUrl, 'application/json');
        if ($raw) {
            $hotspots = json_decode($raw, true);
            $count = 0;
            foreach ($hotspots as $spot) {
                if (!(is_array($spot) && ($spot['type'] ?? '') === 'offer')) continue;
                $offer = $spot['offer'] ?? [];
                if (!is_array($offer)) continue;
                $name = trim((string) ($offer['heading'] ?? $spot['heading'] ?? ''));
                if (stripos($name, 'mutti') !== false || stripos($name, 'tomat') !== false || stripos($name, 'hakkede') !== false) {
                    echo "  ✓ " . $name . " - " . ($offer['pricing']['price'] ?? 'N/A') . " DKK\n";
                    $count++;
                }
            }
            echo "365discount found: $count products\n";
        }
    } else {
        echo "Could not extract 365discount catalog ID\n";
    }
}
