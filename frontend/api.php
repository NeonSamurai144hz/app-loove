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
} else if ($url === 'matches/suggest') {
    $controller = new \App\Controllers\MatchController(\App\Database::getInstance());
    $controller->suggest();
} else if (preg_match('#^chats/(\d+)$#', $url, $m)) {
    $matchId = (int)$m[1];
    $controller = new \App\Controllers\ChatController(\App\Database::getInstance());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->getMessages($matchId);
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->sendMessage($matchId);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} else if ($url === 'chats') {
    session_start(); // <-- Make sure this is here
    $controller = new \App\Controllers\ChatController(\App\Database::getInstance());
    $controller->getUserChats();
} else if ($url === 'match/video') {
    $controller = new \App\Controllers\MatchController(\App\Database::getInstance());
    $controller->videoMatch();
} else if ($url === 'user/video-status') {
    $controller = new \App\Controllers\UserController(\App\Database::getInstance());
    $controller->updateVideoStatus();
} else if (preg_match('#^chats/create/(\d+)$#', $url, $m)) {
    $matchId = (int)$m[1];
    $controller = new \App\Controllers\ChatController(\App\Database::getInstance());
    $controller->createChat($matchId);
} else if ($url === 'matches/recommended') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $controller = new \App\Controllers\MatchController(\App\Database::getInstance());
    $controller->getRecommendedMatches();
} else if ($url === 'pusher/auth') {
    session_start();

    // Check if user is authenticated
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $socketId = $_POST['socket_id'] ?? null;
    $channelName = $_POST['channel_name'] ?? null;

    if (!$socketId || !$channelName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing socket_id or channel_name']);
        exit;
    }

    // Initialize Pusher with your app credentials
    $pusher = new \Pusher\Pusher(
        getenv('PUSHER_APP_KEY') ?: '51d11d1b9e8bac345975',
        getenv('PUSHER_APP_SECRET') ?: '09db0a5618d545eca200',
        getenv('PUSHER_APP_ID') ?: '2010940',
        ['cluster' => getenv('PUSHER_CLUSTER') ?: 'eu', 'useTLS' => true]
    );

    // For private channels
    if (strpos($channelName, 'private-') === 0) {
        // Check if user has access to this channel
        $auth = $pusher->socket_auth($channelName, $socketId);
        echo $auth;
        exit;
    }

    // For presence channels
    if (strpos($channelName, 'presence-') === 0) {
        $userId = $_SESSION['user_id'];

        // Get user data from database for presence channel
        $db = \App\Database::getInstance();
        $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $presenceData = [
            'user_id' => $userId,
            'user_info' => [
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'id' => $user['id']
            ]
        ];

        $auth = $pusher->presence_auth($channelName, $socketId, $userId, $presenceData);
        echo $auth;
        exit;
    }

    // If we get here, it's an invalid channel format
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid channel name']);
} else if ($url === 'chats') {
    error_log("Routing to chats\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\ChatController();
        $controller->getUserChats();
    } catch (Exception $e) {
        error_log("Error in chats: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
} else if ($url === 'matches/create-or-get') {
    error_log("Routing to create-or-get match\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\ChatController();
        $controller->createOrGetMatch();
    } catch (Exception $e) {
        error_log("Error in create-or-get match: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && $url === 'messages') {
    error_log("Routing to send message\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\ChatController();
        $controller->sendMessage();
    } catch (Exception $e) {
        error_log("Error sending message: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
} else if (strpos($url, 'messages') === 0) {
    error_log("Routing to messages endpoint: $url\n", 3, __DIR__ . '/debug.log');
    try {
        $controller = new \App\Controllers\ChatController();

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['matchId'])) {
            // GET /api/messages?matchId=123
            $matchId = $_GET['matchId'];
            error_log("Getting messages for match ID: $matchId\n", 3, __DIR__ . '/debug.log');
            $controller->getMessages($matchId);
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // POST /api/messages
            error_log("Sending new message\n", 3, __DIR__ . '/debug.log');
            $controller->sendMessage();
        } else {
            error_log("Invalid messages request\n", 3, __DIR__ . '/debug.log');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
    } catch (Exception $e) {
        error_log("Error in messages endpoint: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    }
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