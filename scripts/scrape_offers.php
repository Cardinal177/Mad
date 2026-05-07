<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';

function normalize_text(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(['æ', 'ø', 'å'], ['ae', 'oe', 'aa'], $value);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

function parse_price($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        $price = (float) $value;
        return $price > 0 ? $price : null;
    }

    $text = str_replace(',', '.', (string) $value);
    if (preg_match('/\d+(?:\.\d{1,2})?/', $text, $matches) !== 1) {
        return null;
    }

    $price = (float) $matches[0];
    return $price > 0 ? $price : null;
}

function parse_date_value($value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

function request_url(string $url, string $acceptHeader): ?string
{
    $timeoutSeconds = (int) (env_value('OFFERS_SCRAPE_TIMEOUT_SECONDS', '12') ?? '12');
    if ($timeoutSeconds < 3 || $timeoutSeconds > 30) {
        $timeoutSeconds = 12;
    }

    $userAgent = (string) (env_value('OFFERS_SCRAPE_USER_AGENT', 'MadOfferBot/0.1 (+ops@example.com)') ?? 'MadOfferBot/0.1 (+ops@example.com)');

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

function fetch_html(string $url): ?string
{
    return request_url($url, 'text/html,application/xhtml+xml');
}

function fetch_json(string $url): ?array
{
    $raw = request_url($url, 'application/json');
    if ($raw === null) {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function unescape_json_string(string $value): string
{
    $decoded = json_decode('"' . addcslashes($value, "\"\\") . '"');
    if (is_string($decoded)) {
        return $decoded;
    }

    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function extract_offers_from_embedded_payload(string $html): array
{
    $rows = [];

    // Netto teaser offers embedded directly on the landing page.
    $decodedHtml = stripcslashes($html);

    preg_match_all('/"title":"([^"]*)"/s', $decodedHtml, $titleMatches);
    preg_match_all('/"price":([0-9]+(?:\.[0-9]+)?)/s', $decodedHtml, $priceMatches);
    preg_match_all('/"endTime":"([^"]+)"/s', $decodedHtml, $endTimeMatches);
    preg_match_all('/"sales_EAN":"([0-9]{8,14})"/s', $decodedHtml, $eanMatches);

    $titles = $titleMatches[1] ?? [];
    $prices = $priceMatches[1] ?? [];
    $endTimes = $endTimeMatches[1] ?? [];
    $eans = $eanMatches[1] ?? [];

    $count = min(count($titles), count($prices), count($endTimes));
    for ($i = 0; $i < $count; $i++) {
        $name = trim((string) ($titles[$i] ?? ''));
        $price = parse_price($prices[$i] ?? null);
        $validTo = parse_date_value($endTimes[$i] ?? null);
        $ean = trim((string) ($eans[$i] ?? ''));

        if ($name === '' || $price === null) {
            continue;
        }

        $rows[] = [
            'name' => $name,
            'brand' => null,
            'price' => $price,
            'valid_from' => date('Y-m-d'),
            'valid_to' => $validTo,
            'ean' => $ean,
        ];
    }

    $dedup = [];
    foreach ($rows as $row) {
        $key = normalize_text((string) ($row['name'] ?? '')) . '|' . (string) ($row['price'] ?? '') . '|' . (string) ($row['valid_to'] ?? '');
        $dedup[$key] = $row;
    }

    return array_values($dedup);
}

function extract_catalog_ids(string $html): array
{
    $decodedHtml = stripcslashes($html);
    preg_match_all('/ern:catalog:([A-Za-z0-9]+)/', $decodedHtml, $matches);
    $ids = array_values(array_unique($matches[1] ?? []));

    // Kvickly/Coop pages often expose catalog id as data-publication-id.
    preg_match_all('/data-publication-id\s*=\s*["\']?([A-Za-z0-9]{6,})["\']?/i', $decodedHtml, $publicationMatches);
    if (!empty($publicationMatches[1])) {
        $ids = array_values(array_unique(array_merge($ids, $publicationMatches[1])));
    }

    return array_values(array_filter($ids, static fn(string $id): bool => strlen($id) >= 6));
}

function extract_offers_from_tjek_catalog(string $catalogId): array
{
    $rows = [];
    $hotspots = fetch_json('https://squid-api.tjek.com/v2/catalogs/' . rawurlencode($catalogId) . '/hotspots');
    if (!is_array($hotspots)) {
        return $rows;
    }

    foreach ($hotspots as $spot) {
        if (!is_array($spot) || ($spot['type'] ?? '') !== 'offer' || !is_array($spot['offer'] ?? null)) {
            continue;
        }

        $offer = $spot['offer'];
        $name = trim((string) ($offer['heading'] ?? $spot['heading'] ?? ''));
        $price = parse_price($offer['pricing']['price'] ?? null);
        $validFrom = parse_date_value($offer['run_from'] ?? null);
        $validTo = parse_date_value($offer['run_till'] ?? null);

        if ($name === '' || $price === null) {
            continue;
        }

        $rows[] = [
            'name' => $name,
            'brand' => null,
            'price' => $price,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'ean' => '',
        ];
    }

    $dedup = [];
    foreach ($rows as $row) {
        $key = normalize_text((string) ($row['name'] ?? '')) . '|' . (string) ($row['price'] ?? '') . '|' . (string) ($row['valid_to'] ?? '');
        $dedup[$key] = $row;
    }

    return array_values($dedup);
}

function extract_brand($brandField): ?string
{
    if (is_array($brandField)) {
        $name = $brandField['name'] ?? null;
        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    if (is_string($brandField) && trim($brandField) !== '') {
        return trim($brandField);
    }

    return null;
}

function collect_jsonld_nodes($node, array &$rows): void
{
    if (is_array($node)) {
        $isAssoc = array_keys($node) !== range(0, count($node) - 1);

        if ($isAssoc && isset($node['name']) && isset($node['offers'])) {
            $offers = $node['offers'];
            $offersList = is_array($offers) && array_keys($offers) === range(0, count($offers) - 1) ? $offers : [$offers];

            foreach ($offersList as $offer) {
                if (!is_array($offer)) {
                    continue;
                }

                $price = parse_price($offer['price'] ?? $offer['lowPrice'] ?? null);
                if ($price === null) {
                    continue;
                }

                $rows[] = [
                    'name' => trim((string) $node['name']),
                    'brand' => extract_brand($node['brand'] ?? null),
                    'price' => $price,
                    'valid_from' => parse_date_value($offer['validFrom'] ?? $offer['priceValidFrom'] ?? null),
                    'valid_to' => parse_date_value($offer['validThrough'] ?? $offer['priceValidUntil'] ?? null),
                    'ean' => trim((string) ($node['gtin13'] ?? $node['gtin12'] ?? $node['gtin'] ?? '')),
                ];
            }
        }

        foreach ($node as $child) {
            collect_jsonld_nodes($child, $rows);
        }
    }
}

function extract_offers_from_html(string $html): array
{
    $rows = [];
    if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches) === 1 && !empty($matches[1])) {
        foreach ($matches[1] as $payload) {
            $payload = trim((string) $payload);
            if ($payload === '') {
                continue;
            }

            $decoded = json_decode($payload, true);
            if (!is_array($decoded)) {
                continue;
            }

            collect_jsonld_nodes($decoded, $rows);
        }
    }

    if ($rows === []) {
        $rows = extract_offers_from_embedded_payload($html);
    }

    $dedup = [];
    foreach ($rows as $row) {
        $key = normalize_text((string) ($row['name'] ?? '')) . '|' . (string) ($row['price'] ?? '') . '|' . (string) ($row['valid_to'] ?? '');
        $dedup[$key] = $row;
    }

    return array_values($dedup);
}

function load_products(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, barcode, name, brand FROM products');
    $rows = $stmt->fetchAll() ?: [];

    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'barcode' => (string) ($row['barcode'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'name_norm' => normalize_text((string) ($row['name'] ?? '')),
            'brand_norm' => normalize_text((string) ($row['brand'] ?? '')),
        ];
    }, $rows);
}

function token_set(string $value): array
{
    $tokens = array_filter(explode(' ', normalize_text($value)), static fn($t) => strlen($t) >= 2);
    return array_values(array_unique($tokens));
}

function overlap_score(array $a, array $b): float
{
    if ($a === [] || $b === []) {
        return 0.0;
    }

    $intersection = array_intersect($a, $b);
    return count($intersection) / max(count($a), count($b));
}

function match_offer_to_product(array $offer, array $products): ?int
{
    $nameNorm = normalize_text((string) ($offer['name'] ?? ''));
    if ($nameNorm === '') {
        return null;
    }

    $ean = preg_replace('/\D+/', '', (string) ($offer['ean'] ?? '')) ?? '';
    if ($ean !== '') {
        foreach ($products as $product) {
            if ($product['barcode'] !== '' && $product['barcode'] === $ean) {
                return (int) $product['id'];
            }
        }
    }

    $offerTokens = token_set($nameNorm);
    $offerBrandNorm = normalize_text((string) ($offer['brand'] ?? ''));

    $bestId = null;
    $bestScore = 0.0;

    foreach ($products as $product) {
        $score = 0.0;

        if ($product['name_norm'] === $nameNorm) {
            $score += 100;
        } elseif (str_contains($product['name_norm'], $nameNorm) || str_contains($nameNorm, $product['name_norm'])) {
            $score += 70;
        }

        $productTokens = token_set($product['name_norm']);
        $score += overlap_score($offerTokens, $productTokens) * 50;

        if ($offerBrandNorm !== '' && $product['brand_norm'] !== '' && str_contains($nameNorm . ' ' . $offerBrandNorm, $product['brand_norm'])) {
            $score += 10;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestId = (int) $product['id'];
        }
    }

    return $bestScore >= 45 ? $bestId : null;
}

function offer_exists(PDO $pdo, string $storeName, ?int $productId, float $price, ?string $validTo, string $sourceUrl): bool
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM store_offers
         WHERE store_name = ?
           AND title = "Tilbud"
           AND product_id <=> ?
           AND ABS(price - ?) < 0.001
           AND valid_to <=> ?
           AND source_url <=> ?
           AND created_at >= (NOW() - INTERVAL 30 DAY)
         LIMIT 1'
    );
    $stmt->execute([$storeName, $productId, $price, $validTo, $sourceUrl]);
    return (bool) $stmt->fetch();
}

function insert_offer(PDO $pdo, string $storeName, ?int $productId, array $offer, string $sourceUrl): bool
{
    $price = (float) ($offer['price'] ?? 0);
    if ($price <= 0) {
        return false;
    }

    $validFrom = $offer['valid_from'] ?? date('Y-m-d');
    $validTo = $offer['valid_to'] ?? null;

    if (offer_exists($pdo, $storeName, $productId, $price, $validTo, $sourceUrl)) {
        return false;
    }

    $title = 'Tilbud: ' . mb_substr((string) ($offer['name'] ?? 'Ukendt vare'), 0, 160);

    $stmt = $pdo->prepare(
        'INSERT INTO store_offers (store_name, product_id, title, price, valid_from, valid_to, source_url)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $storeName,
        $productId,
        $title,
        $price,
        $validFrom,
        $validTo,
        $sourceUrl,
    ]);

    return true;
}

function load_sources(): array
{
    $raw = (string) (env_value('OFFERS_SCRAPE_SOURCES', '[]') ?? '[]');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $sources = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $store = trim((string) ($entry['store'] ?? ''));
        $url = trim((string) ($entry['url'] ?? ''));
        $enabled = !isset($entry['enabled']) || (bool) $entry['enabled'];
        if (!$enabled || $store === '' || $url === '') {
            continue;
        }
        $sources[] = ['store' => $store, 'url' => $url];
    }

    if ($sources === []) {
        // Safe defaults so scraper works out-of-the-box for current store rollout.
        $sources = [
            ['store' => 'Netto', 'url' => 'https://netto.dk/tilbudsavis/'],
            ['store' => 'Kvickly', 'url' => 'https://kvickly.coop.dk/avis/'],
            ['store' => '365discount', 'url' => 'https://365discount.coop.dk/365avis/'],
        ];
    }

    // Ensure required rollout stores exist even if env list is partial.
    $required = [
        ['store' => 'Netto', 'url' => 'https://netto.dk/tilbudsavis/'],
        ['store' => 'Kvickly', 'url' => 'https://kvickly.coop.dk/avis/'],
        ['store' => '365discount', 'url' => 'https://365discount.coop.dk/365avis/'],
    ];
    $existingStores = array_map(static fn(array $source): string => mb_strtolower((string) ($source['store'] ?? '')), $sources);
    foreach ($required as $entry) {
        if (!in_array(mb_strtolower($entry['store']), $existingStores, true)) {
            $sources[] = $entry;
        }
    }

    return $sources;
}

$pdo = db();
$sources = load_sources();

if ($sources === []) {
    fwrite(STDOUT, "No offer sources configured. Set OFFERS_SCRAPE_SOURCES in .env\n");
    exit(0);
}

$products = load_products($pdo);
$totalFetched = 0;
$totalMatched = 0;
$totalInserted = 0;

foreach ($sources as $source) {
    $storeName = (string) $source['store'];
    $url = (string) $source['url'];

    fwrite(STDOUT, "Scraping {$storeName}: {$url}\n");
    $html = fetch_html($url);
    if ($html === null) {
        fwrite(STDOUT, "  - failed to fetch\n");
        continue;
    }

    $offers = extract_offers_from_html($html);

    // If page contains catalog ids (Netto/Tjek), fetch full catalog offers from hotspots API.
    $catalogIds = extract_catalog_ids($html);
    if ($catalogIds !== []) {
        $fullOffers = [];
        foreach ($catalogIds as $catalogId) {
            $catalogOffers = extract_offers_from_tjek_catalog($catalogId);
            if ($catalogOffers !== []) {
                $fullOffers = array_merge($fullOffers, $catalogOffers);
            }
        }
        if ($fullOffers !== []) {
            $offers = $fullOffers;
        }
    }

    fwrite(STDOUT, '  - extracted offers: ' . count($offers) . "\n");
    $totalFetched += count($offers);

    foreach ($offers as $offer) {
        $productId = match_offer_to_product($offer, $products);
        if ($productId !== null) {
            $totalMatched++;
        }

        if (insert_offer($pdo, $storeName, $productId, $offer, $url)) {
            $totalInserted++;
        }
    }
}

fwrite(STDOUT, "Done. Extracted={$totalFetched}, matched={$totalMatched}, inserted={$totalInserted}\n");
