<?php
class Router {
    public function route(string $url) {
        // Split “profile/edit/5” → [‘profile’, ‘edit’, ‘5’]
        $parts = array_filter(explode('/', $url));

        // Controller name (default HomeController)
        $controllerName = !empty($parts[0])
            ? ucfirst($parts[0]) . 'Controller'
            : 'HomeController';

        // Method name (default index)
        $methodName = $parts[1] ?? 'index';

        // Path to your controller
        $controllerFile = __DIR__ . '/controllers/' . $controllerName . '.php';
        if (!file_exists($controllerFile)) {
            http_response_code(404);
            echo "Controller not found.";
            exit;
        }

        require_once $controllerFile;
        $controller = new $controllerName();

        if (!method_exists($controller, $methodName)) {
            http_response_code(404);
            echo "Method $methodName not found.";
            exit;
        }

        // Any further URL segments become method parameters
        $params = array_slice($parts, 2);
        call_user_func_array([$controller, $methodName], $params);
    }
}
