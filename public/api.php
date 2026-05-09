<?php

declare(strict_types=1);

$baseDir = dirname(__DIR__);
if (!is_dir($baseDir . '/src') && is_dir(__DIR__ . '/src')) {
    $baseDir = __DIR__;
}

require_once $baseDir . '/src/bootstrap.php';
require_once $baseDir . '/config/database.php';
require_once $baseDir . '/src/api/Router.php';
require_once $baseDir . '/src/handlers/ScanHandler.php';
require_once $baseDir . '/src/handlers/AuthHandler.php';
require_once $baseDir . '/src/handlers/AdminHandler.php';
require_once $baseDir . '/src/handlers/AiHandler.php';
require_once $baseDir . '/src/handlers/ConfigHandler.php';
require_once $baseDir . '/src/handlers/NutritionHandler.php';
require_once $baseDir . '/src/handlers/IngredientHandler.php';
require_once $baseDir . '/src/handlers/DeviceHandler.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $endpoint = (string) ($_GET['endpoint'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'scan') {
        handleScan($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'products') {
        handleProductList($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'recent') {
        handleRecentScans($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'locations') {
        handleLocationList($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'recipes') {
        handleRecipeList($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'shopping.candidates') {
        handleShoppingCandidates($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'shopping.list') {
        handleShoppingList($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'shopping.offer_feed') {
        handleShoppingOfferFeed($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'shopping.list.add_items') {
        handleShoppingListAddItems($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'shopping.list.set_item_checked') {
        handleShoppingListSetItemChecked($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'shopping.list.remove_item') {
        handleShoppingListRemoveItem($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'inventory.delete') {
        handleInventoryDelete($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'auth.request_code') {
        handleAuthRequestCode($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'auth.verify_code') {
        handleAuthVerifyCode($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'auth.me') {
        handleAuthMe($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'auth.test_sms') {
        handleAuthTestSms($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'admin.users') {
        handleAdminListUsers($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'admin.users.create') {
        handleAdminCreateUser($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'admin.households') {
        handleAdminListHouseholds($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'admin.households.create') {
        handleAdminCreateHousehold($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'admin.households.assign_user') {
        handleAdminAssignUserToHousehold($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'ai.meal_ideas') {
        handleAiMealIdeas($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'config.get') {
        handleConfigGet($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'config.set') {
        handleConfigSet($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'nutrition.quality') {
        handleNutritionQualitySummary($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'nutrition.match.run') {
        handleNutritionMatchRun($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'ingredients.lookup') {
        handleIngredientLookup($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'ingredients.create') {
        handleIngredientCreate($pdo);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'device.set_mode') {
        handleDeviceSetMode();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endpoint === 'device.set_context') {
        handleDeviceSetContext();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'device.get_mode') {
        handleDeviceGetMode();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $endpoint === 'device.get_last_scan') {
        handleDeviceGetLastScan();
        exit;
    }

    $result = $pdo->query('SELECT NOW() AS server_time')->fetch();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $writeTest = null;
    if (($_GET['test'] ?? null) === 'write') {
        $stmt = $pdo->prepare('INSERT INTO households (name) VALUES (?)');
        $stmt->execute(['API Test ' . date('Y-m-d H:i:s')]);
        $id = $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT id, name FROM households WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $writeTest = $stmt->fetch();
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Mad API is running (database fully operational)',
        'db_server_time' => $result['server_time'],
        'tables' => $tables,
        'tables_count' => count($tables),
        'test_write' => $writeTest,
        'endpoints' => [
            '/api.php' => 'This status page',
            '/api.php?test=write' => 'Test database write',
            '/api.php?endpoint=scan' => 'POST scan ingest endpoint',
            '/api.php?endpoint=products' => 'Current inventory product list',
            '/api.php?endpoint=recent' => 'Recent inventory scan movements',
            '/api.php?endpoint=locations' => 'Household locations for authenticated user',
            '/api.php?endpoint=recipes' => 'Household recipes for authenticated user',
            '/api.php?endpoint=shopping.candidates' => 'GET shopping candidates (minimum reached + manual list) with active offers',
            '/api.php?endpoint=shopping.list' => 'GET active shopping list for authenticated household',
            '/api.php?endpoint=shopping.offer_feed' => 'GET all scraped leaflet offers (also unmatched products)',
            '/api.php?endpoint=shopping.list.add_items' => 'POST add items to active shopping list from leaflet offers',
            '/api.php?endpoint=shopping.list.set_item_checked' => 'POST set checked/unchecked state for a shopping list item',
            '/api.php?endpoint=shopping.list.remove_item' => 'POST remove one shopping list item',
            '/api.php?endpoint=auth.request_code' => 'POST request 2FA code by initials',
            '/api.php?endpoint=auth.verify_code' => 'POST verify 2FA code and issue access token',
            '/api.php?endpoint=auth.me' => 'GET current user from bearer token',
            '/api.php?endpoint=auth.test_sms' => 'POST send one test SMS by initials or phone number',
            '/api.php?endpoint=admin.users' => 'GET platform admin user overview',
            '/api.php?endpoint=admin.users.create' => 'POST create a user as platform admin',
            '/api.php?endpoint=admin.households' => 'GET platform admin household overview',
            '/api.php?endpoint=admin.households.create' => 'POST create a household as platform admin',
            '/api.php?endpoint=admin.households.assign_user' => 'POST assign a user to a household as platform admin',
            '/api.php?endpoint=ai.meal_ideas' => 'POST AI meal ideas from household inventory (Anthropic)',
            '/api.php?endpoint=nutrition.quality' => 'GET nutrition data quality summary (platform admin)',
            '/api.php?endpoint=nutrition.match.run' => 'POST run Frida-style nutrition auto match (platform admin)',
            '/api.php?endpoint=ingredients.lookup&barcode=...' => 'GET ingredient lookup by barcode (authenticated)',
            '/api.php?endpoint=ingredients.create' => 'POST create ingredient with inventory + price/offer metadata (authenticated)',
            '/' => 'Live test dashboard',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
