<?php

declare(strict_types=1);

$baseDir = dirname(__FILE__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';

// Include necessary scraper functions from scrape_offers.php
function normalize_text(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = str_replace(['æ', 'ø', 'å'], ['ae', 'oe', 'aa'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

function parse_price($value): ?float {
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) {
        $price = (float) $value;
        return $price > 0 ? $price : null;
    }
    $text = str_replace(',', '.', (string) $value);
    if (preg_match('/\d+(?:\.\d{1,2})?/', $text, $matches) !== 1) return null;
    $price = (float) $matches[0];
    return $price > 0 ? $price : null;
}

function parse_date_value($value): ?string {
    if (!is_string($value) || trim($value) === '') return null;
    $timestamp = strtotime($value);
    if ($timestamp === false) return null;
    return date('Y-m-d', $timestamp);
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
        CURLOPT_HTTPHEADER => ['Accept: ' . $acceptHeader, 'User-Agent: ' . $userAgent],
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false || $httpCode < 200 || $httpCode >= 400) return null;
    return (string) $raw;
}

// Test Kvickly and 365discount scraping
$stores = [
    ['store' => 'Kvickly', 'url' => 'https://kvickly.coop.dk/avis/'],
    ['store' => '365discount', 'url' => 'https://365discount.coop.dk/365avis/'],
];

foreach ($stores as $entry) {
    $store = $entry['store'];
    $url = $entry['url'];
    
    echo "\n=== Checking $store ===\n";
    echo "URL: $url\n";
    
    $html = request_url($url, 'text/html');
    if (!$html) {
        echo "ERROR: Failed to fetch page\n";
        continue;
    }
    
    echo "✓ Page fetched\n";
    
    // Look for catalog ID
    preg_match('/data-publication-id\s*=\s*["\']?([A-Za-z0-9]{6,})["\']?/i', $html, $matches);
    $catalogId = $matches[1] ?? null;
    
    if (!$catalogId) {
        preg_match('/ern:catalog:([A-Za-z0-9]+)/', $html, $matches);
        $catalogId = $matches[1] ?? null;
    }
    
    if (!$catalogId) {
        echo "ERROR: No catalog ID found\n";
        continue;
    }
    
    echo "✓ Catalog ID: $catalogId\n";
    
    // Fetch from Tjek API
    $apiUrl = 'https://squid-api.tjek.com/v2/catalogs/' . rawurlencode($catalogId) . '/hotspots';
    $raw = request_url($apiUrl, 'application/json');
    if (!$raw) {
        echo "ERROR: Failed to fetch from Tjek API\n";
        continue;
    }
    
    echo "✓ Tjek API response received\n";
    
    $hotspots = json_decode($raw, true);
    if (!is_array($hotspots)) {
        echo "ERROR: Invalid JSON\n";
        continue;
    }
    
    echo "✓ " . count($hotspots) . " hotspots decoded\n";
    
    // Count valid offers
    $validCount = 0;
    foreach ($hotspots as $spot) {
        if (!(is_array($spot) && ($spot['type'] ?? '') === 'offer')) continue;
        $offer = $spot['offer'] ?? [];
        if (!is_array($offer)) continue;
        $name = trim((string) ($offer['heading'] ?? $spot['heading'] ?? ''));
        if ($name === '') continue;
        $price = parse_price($offer['pricing']['price'] ?? null);
        if ($price === null) continue;
        $validCount++;
    }
    
    echo "✓ $validCount valid offers extracted\n";
}

echo "\n=== DONE ===\n";
