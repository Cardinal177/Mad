<?php

declare(strict_types=1);

$baseDir = dirname(__FILE__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';

function normalize_text(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = str_replace(['æ', 'ø', 'å'], ['ae', 'oe', 'aa'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

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
        CURLOPT_HTTPHEADER => [
            'Accept: ' . $acceptHeader,
            'User-Agent: ' . $userAgent,
        ],
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpCode < 200 || $httpCode >= 400) {
        return null;
    }

    return (string) $raw;
}

// Fetch Kvickly avis page
fwrite(STDERR, "Fetching Kvickly avis...\n");
$html = request_url('https://kvickly.coop.dk/avis/', 'text/html');
if (!$html) {
    fwrite(STDERR, "Failed to fetch Kvickly page.\n");
    exit(1);
}

// Extract catalog ID
preg_match('/data-publication-id\s*=\s*["\'`]?([A-Za-z0-9]{6,})["\'`]?/i', $html, $matches);
$catalogId = $matches[1] ?? null;

if (!$catalogId) {
    fwrite(STDERR, "No catalog ID found in HTML.\n");
    exit(1);
}

fwrite(STDERR, "Found catalog ID: " . $catalogId . "\n");

// Fetch from Tjek API
$apiUrl = 'https://squid-api.tjek.com/v2/catalogs/' . rawurlencode($catalogId) . '/hotspots';
fwrite(STDERR, "Fetching from: " . $apiUrl . "\n");
$raw = request_url($apiUrl, 'application/json');
if (!$raw) {
    fwrite(STDERR, "Failed to fetch Tjek hotspots.\n");
    exit(1);
}

$hotspots = json_decode($raw, true);
if (!is_array($hotspots)) {
    fwrite(STDERR, "Failed to decode JSON response.\n");
    exit(1);
}

fwrite(STDERR, "\nFound " . count($hotspots) . " hotspots.\n");
fwrite(STDERR, "Searching for Mutti / hakkede / tomat...\n\n");

$foundCount = 0;
$allProducts = [];

foreach ($hotspots as $spot) {
    if (!(is_array($spot) && ($spot['type'] ?? '') === 'offer')) {
        continue;
    }

    $offer = $spot['offer'] ?? [];
    if (!is_array($offer)) {
        continue;
    }

    $name = trim((string) ($offer['heading'] ?? $spot['heading'] ?? ''));
    if (empty($name)) {
        continue;
    }

    $allProducts[] = $name;

    if (stripos($name, 'mutti') !== false || stripos($name, 'hakkede') !== false || stripos($name, 'tomat') !== false) {
        $price = $offer['pricing']['price'] ?? 'N/A';
        echo "✓ FOUND: " . $name . " - Price: " . $price . "\n";
        $foundCount++;
    }
}

if ($foundCount === 0) {
    echo "\n❌ No Mutti/tomato products found in current Kvickly leaflet.\n";
    echo "\nFirst 20 products in catalog:\n";
    for ($i = 0; $i < min(20, count($allProducts)); $i++) {
        echo "  " . ($i + 1) . ". " . $allProducts[$i] . "\n";
    }
} else {
    echo "\n✓ Found $foundCount matching products!\n";
}
