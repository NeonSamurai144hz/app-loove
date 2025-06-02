<?php
namespace App\Controllers;

class MatchController {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function matches() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            $this->respondJson(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }
        // TODO: Replace with real matching logic
        $matches = [
            [
                'name' => 'Alex',
                'age' => 25,
                'description' => 'Loves hiking',
                'photo' => '/assets/img/sample-profile.jpg'
            ],
            [
                'name' => 'Jamie',
                'age' => 28,
                'description' => 'Coffee enthusiast',
                'photo' => '/assets/img/sample-profile.jpg'
            ]
        ];
        $this->respondJson(['success' => true, 'matches' => $matches]);
    }

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}