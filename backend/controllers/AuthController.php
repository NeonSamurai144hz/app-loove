<?php
require_once(__DIR__ . '/../models/User.php');

class AuthController {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function login() {
        // Existing login code...
    }

    public function register() {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $requiredFields = ['firstName', 'lastName', 'email', 'password', 'confirmPassword'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->respondJson(['success' => false, 'message' => 'All fields are required']);
                return;
            }
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->respondJson(['success' => false, 'message' => 'Invalid email format']);
            return;
        }

        // Validate password match
        if ($data['password'] !== $data['confirmPassword']) {
            $this->respondJson(['success' => false, 'message' => 'Passwords do not match']);
            return;
        }

        // Check if email already exists
        if (User::findByEmail($data['email'])) {
            $this->respondJson(['success' => false, 'message' => 'Email already registered']);
            return;
        }

        // Create new user
        $userId = User::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'date_birth' => $data['birthDate'] ?? '2000-01-01', // Default or from form
            'gender' => $data['gender'] ?? 'other', // Default or from form
            'gender_attraction' => $data['genderAttraction'] ?? 'all', // Default or from form
            'age_attraction_min' => $data['ageMin'] ?? 18, // Default or from form
            'age_attraction_max' => $data['ageMax'] ?? 99 // Default or from form
        ]);

        if ($userId) {
            // Generate session token
            $token = bin2hex(random_bytes(32));

            $this->respondJson([
                'success' => true,
                'message' => 'Registration successful',
                'token' => $token,
                'user_id' => $userId
            ]);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Registration failed']);
        }
    }

    private function respondJson($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}