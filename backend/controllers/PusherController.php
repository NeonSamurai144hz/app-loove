<?php
namespace App\Controllers;

use Pusher\Pusher;

class PusherController {
    private $pusher;

    public function __construct() {
        // Initialize Pusher
        $config = include(__DIR__ . '/../config/pusher.php');
        $this->pusher = new Pusher(
            $config['key'],
            $config['secret'],
            $config['app_id'],
            ['cluster' => $config['cluster'], 'useTLS' => $config['useTLS']]
        );
    }

    public function auth() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            $this->respondJson(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $socketId = $_POST['socket_id'];
        $channel = $_POST['channel_name'];

        // Only authenticate private channels for this user
        if (strpos($channel, 'private-user-' . $userId) === 0 ||
            strpos($channel, 'private-chat-') === 0) {
            $auth = $this->pusher->authorizeChannel($socketId, $channel);
            echo $auth;
        } else {
            $this->respondJson(['success' => false, 'message' => 'Unauthorized channel'], 403);
        }
    }

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}