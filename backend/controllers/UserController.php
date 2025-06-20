<?php
namespace App\Controllers;

class UserController {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function updateVideoStatus() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            $this->respondJson(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['status']) || !in_array($data['status'], ['available', 'busy', 'offline'])) {
            $this->respondJson(['success' => false, 'message' => 'Invalid status'], 400);
            return;
        }

        $stmt = $this->db->prepare("UPDATE users SET video_chat_status = ? WHERE id = ?");
        $success = $stmt->execute([$data['status'], $userId]);

        if ($success) {
            $this->respondJson(['success' => true]);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Failed to update status']);
        }
    }

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}