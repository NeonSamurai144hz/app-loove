// backend/api.php
<?php
require_once(__DIR__ . '/Router.php');
require_once(__DIR__ . '/Database.php');
require_once(__DIR__ . '/controllers/AuthController.php');

// Get URL from request
$url = isset($_GET['url']) ? $_GET['url'] : '';
$url = rtrim($url, '/');

header('Content-Type: application/json');

// Handle API routes
if ($url === 'auth/login') {
    $controller = new AuthController(Database::getInstance());
    $controller->login();
} else if ($url === 'auth/register') {
    $controller = new AuthController(Database::getInstance());
    $controller->register();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
}