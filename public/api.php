<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/api/Router.php';
require_once dirname(__DIR__) . '/src/handlers/ScanHandler.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $router = new ApiRouter();

    // Register routes
    $router->post('/api/scan', fn() => handleScan($pdo));
    $router->get('/api/products', fn() => handleProductList($pdo));

    // Dispatch
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Remove /api.php prefix if present
    $path = str_replace('/api.php', '', $path);
    if ($path === '') {
        $path = '/';
    }

    $router->dispatch($method, $path);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
