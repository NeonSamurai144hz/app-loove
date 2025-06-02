<?php
namespace App;

class Router {
    public function route(string $url) {
        require_once __DIR__ . '/../vendor/autoload.php';

        $parts = array_filter(explode('/', $url));
        $controllerName = !empty($parts[0])
            ? 'App\\Controllers\\' . ucfirst($parts[0]) . 'Controller'
            : 'App\\Controllers\\HomeController';
        $methodName = $parts[1] ?? 'index';

        // Whitelist public methods
        $publicRoutes = [
            'AuthController' => ['login', 'register']
        ];

        // Check if route is protected
        $controllerShort = substr($controllerName, strrpos($controllerName, '\\') + 1);
        if (
            !isset($publicRoutes[$controllerShort]) ||
            !in_array($methodName, $publicRoutes[$controllerShort])
        ) {
            session_start();
            if (empty($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Not authenticated']);
                exit;
            }
        }

        if (!class_exists($controllerName)) {
            http_response_code(404);
            echo "Controller not found.";
            exit;
        }

        $controller = new $controllerName();

        if (!method_exists($controller, $methodName)) {
            http_response_code(404);
            echo "Method $methodName not found.";
            exit;
        }

        $params = array_slice($parts, 2);
        call_user_func_array([$controller, $methodName], $params);
    }
}