<?php
error_log("=== DEBUG START ===\n", 3, __DIR__ . '/debug.log');
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n", 3, __DIR__ . '/debug.log');
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n", 3, __DIR__ . '/debug.log');
error_log("SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n", 3, __DIR__ . '/debug.log');
error_log("All GET params: " . print_r($_GET, true) . "\n", 3, __DIR__ . '/debug.log');
error_log("All SERVER vars (relevant): \n", 3, __DIR__ . '/debug.log');
error_log("  HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n", 3, __DIR__ . '/debug.log');
error_log("  SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n", 3, __DIR__ . '/debug.log');
error_log("  DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n", 3, __DIR__ . '/debug.log');

// check if .htaccess is working
if (!isset($_GET['url'])) {
    error_log("ERROR: .htaccess rewrite not working - 'url' parameter not found in GET\n", 3, __DIR__ . '/debug.log');

    $request_uri = $_SERVER['REQUEST_URI'];
    error_log("Trying to manually parse REQUEST_URI: $request_uri\n", 3, __DIR__ . '/debug.log');

    if (($pos = strpos($request_uri, '?')) !== false) {
        $request_uri = substr($request_uri, 0, $pos);
    }

    if (strpos($request_uri, '/api/') === 0) {
        $url = substr($request_uri, 5); // Remove '/api/'
        error_log("Manually extracted URL: '$url'\n", 3, __DIR__ . '/debug.log');
    } else {
        $url = '';
        error_log("REQUEST_URI doesn't start with /api/\n", 3, __DIR__ . '/debug.log');
    }
} else {
    $url = $_GET['url'];
    error_log(".htaccess rewrite working - URL from GET: '$url'\n", 3, __DIR__ . '/debug.log');
}

$url = rtrim($url, '/');
error_log("Final URL after processing: '$url'\n", 3, __DIR__ . '/debug.log');

// Include backend files
require_once __DIR__ . '/../vendor/autoload.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Route the requests
if ($url === 'auth/login') {
    error_log("Routing to login\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\AuthController(\App\Database::getInstance());
        $controller->login();
    } catch (Exception $e) {
        error_log("Error creating AuthController for login: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
} else if ($url === 'auth/register') {
    error_log("Routing to register\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\AuthController(\App\Database::getInstance());
        $controller->register();
    } catch (Exception $e) {
        error_log("Error creating AuthController for register: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
} else if ($url === 'auth/me') {
    error_log("Routing to me\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\AuthController(\App\Database::getInstance());
        $controller->me();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);}
} else if ($url === 'auth/logout') {
    error_log("Routing to logout\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\AuthController(\App\Database::getInstance());
        $controller->logout();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
} else if ($url === 'auth/updateProfile') {
    $controller = new \App\Controllers\ProfileController(\App\Database::getInstance());
    $controller->updateProfile();
} else if ($url === 'matches') {
    $controller = new \App\Controllers\MatchController(\App\Database::getInstance());
    $controller->matches();
} else {
    error_log("404 - endpoint '$url' not found\n", 3, __DIR__ . '/debug.log');
    http_response_code(404);
    echo json_encode([
        'error' => 'API endpoint not found',
        'requested_url' => $url,
        'debug_info' => [
            'request_uri' => $_SERVER['REQUEST_URI'],
            'get_params' => $_GET,
            'htaccess_working' => isset($_GET['url'])
        ]
    ]);
}

error_log("=== DEBUG END ===\n", 3, __DIR__ . '/debug.log');
?>