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
        CURLOPT_HTTPHEADER => [
            'Accept: ' . $acceptHeader,
            'User-Agent: ' . $userAgent,
        ],
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

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
preg_match('/data-publication-id\s*=\s*["\']?([A-Za-z0-9]{6,})["\']?/i', $html, $matches);
$catalogId = $matches[1] ?? null;

if (!$catalogId) {
    fwrite(STDERR, "No catalog ID found.\n");
    exit(1);
}

fwrite(STDERR, "Catalog ID: " . $catalogId . "\n");

// Fetch from Tjek API
$apiUrl = 'https://squid-api.tjek.com/v2/catalogs/' . rawurlencode($catalogId) . '/hotspots';
$raw = request_url($apiUrl, 'application/json');
if (!$raw) {
    fwrite(STDERR, "Failed to fetch Tjek API.\n");
    exit(1);
}

$hotspots = json_decode($raw, true);
if (!is_array($hotspots)) {
    fwrite(STDERR, "Failed to decode JSON.\n");
    exit(1);
}

fwrite(STDERR, "Total hotspots: " . count($hotspots) . "\n\n");

// Search for all tomato-like products
$results = [
    'mutti' => [],
    'tomat' => [],
    'hakkede' => [],
];

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

    $nameLower = mb_strtolower($name);
    $price = $offer['pricing']['price'] ?? 'N/A';

    if (stripos($name, 'mutti') !== false) {
        $results['mutti'][] = ['name' => $name, 'price' => $price];
    }
    if (stripos($name, 'tomat') !== false) {
        $results['tomat'][] = ['name' => $name, 'price' => $price];
    }
    if (stripos($name, 'hakkede') !== false) {
        $results['hakkede'][] = ['name' => $name, 'price' => $price];
    }
}

echo "=== SEARCH RESULTS ===\n\n";

echo "MUTTI products:\n";
if (empty($results['mutti'])) {
    echo "  (none found)\n";
} else {
    foreach ($results['mutti'] as $item) {
        echo "  ✓ " . $item['name'] . " - " . $item['price'] . " DKK\n";
    }
}

echo "\nTOMAT products:\n";
if (empty($results['tomat'])) {
    echo "  (none found)\n";
} else {
    foreach ($results['tomat'] as $item) {
        echo "  ✓ " . $item['name'] . " - " . $item['price'] . " DKK\n";
    }
}

echo "\nHAKKEDE products:\n";
if (empty($results['hakkede'])) {
    echo "  (none found)\n";
} else {
    foreach ($results['hakkede'] as $item) {
        echo "  ✓ " . $item['name'] . " - " . $item['price'] . " DKK\n";
    }
}

$total = count($results['mutti']) + count($results['tomat']) + count($results['hakkede']);
echo "\n=== TOTAL MATCHING PRODUCTS: $total ===\n";
