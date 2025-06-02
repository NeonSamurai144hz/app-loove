<?php
namespace App\Controllers;

use App\Models\User;

class AuthController {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    // input sanitization helper
    private function cleanInput($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    public function login() {
        error_log("AuthController: login() called\n", 3, __DIR__ . '/../debug.log');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respondJson(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $input = file_get_contents('php://input');
        error_log("AuthController: Login raw input: $input\n", 3, __DIR__ . '/../debug.log');
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->respondJson(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        if (empty($data['email']) || empty($data['password'])) {
            $this->respondJson(['success' => false, 'message' => 'Email and password required'], 400);
        }

        $email = $this->cleanInput($data['email']);
        $password = $this->cleanInput($data['password']);

        $user = \App\Models\User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->respondJson(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        session_start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];

        error_log("AuthController: Login successful for user ID: " . $user['id'] . "\n", 3, __DIR__ . '/../debug.log');

        $token = bin2hex(random_bytes(32));
        $this->respondJson([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ]
        ]);
    }

    // app-loove/backend/controllers/AuthController.php

    public function register() {
        error_log("AuthController: register() called\n", 3, __DIR__ . '/../debug.log');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respondJson(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $data = [];
        $file = null;
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            foreach ($_POST as $k => $v) {
                $data[$k] = $this->cleanInput($v);
            }
            if (isset($_FILES['profilePhoto'])) {
                $file = $_FILES['profilePhoto'];
            }
        } else {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->respondJson(['success' => false, 'message' => 'Invalid JSON'], 400);
            }
            foreach ($data as $k => $v) {
                $data[$k] = $this->cleanInput($v);
            }
        }

        $requiredFields = ['firstName', 'lastName', 'email', 'password', 'confirmPassword'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $this->respondJson(['success' => false, 'message' => 'All fields are required'], 400);
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->respondJson(['success' => false, 'message' => 'Invalid email'], 400);
        }

        if ($data['password'] !== $data['confirmPassword']) {
            $this->respondJson(['success' => false, 'message' => 'Passwords do not match'], 400);
        }

        if (strlen($data['password']) < 6) {
            $this->respondJson(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
        }

        if (\App\Models\User::findByEmail($data['email'])) {
            $this->respondJson(['success' => false, 'message' => 'Email already registered'], 409);
        }

        $pfpPathForDb = null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

            if ($file['size'] > $maxFileSize) {
                $this->respondJson(['success' => false, 'message' => 'Profile photo too large (max 2MB)'], 400);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mimeType, $allowedTypes)) {
                $this->respondJson(['success' => false, 'message' => 'Invalid image type'], 400);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('profile_', true) . '.' . $ext;
            $uploadDir = 'C:/Coding/Dating-app/app-loove/frontend/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $photoPath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $photoPath)) {
                $this->respondJson(['success' => false, 'message' => 'Failed to upload profile photo'], 500);
            }
            $pfpPathForDb = '/uploads/' . $filename;
        }

        $userId = \App\Models\User::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'date_birth' => $data['birthDate'] ?? null,
            'gender' => $data['gender'] ?? null,
            'gender_attraction' => $data['genderAttraction'] ?? null,
            'age_attraction_min' => $data['ageMin'] ?? 18,
            'age_attraction_max' => $data['ageMax'] ?? 99,
            'pfp_path' => $pfpPathForDb
        ]);

        if ($userId) {
            $this->respondJson(['success' => true, 'user_id' => $userId]);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Registration failed'], 500);
        }
    }

    public function me() {
        error_log("AuthController: me() called\n", 3, __DIR__ . '/../debug.log');
        session_start();
        if (empty($_SESSION['user_id'])) {
            $this->respondJson(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }
        $user = \App\Models\User::findByEmail($_SESSION['user_email']);
        if (!$user) {
            $this->respondJson(['success' => false, 'message' => 'User not found'], 404);
            return;
        }
        unset($user['password']);
        // Only return pfp_path, not photo_url
        if (!$user['pfp_path']) {
            $user['pfp_path'] = '/assets/img/sample-profile.jpg';
        }
        unset($user['photo_url']);
        $this->respondJson(['success' => true, 'user' => $user]);
    }

    public function updateProfile() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            $this->respondJson(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $data = [];
        $file = null;

        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            foreach ($_POST as $k => $v) {
                $data[$k] = htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
            }
            if (isset($_FILES['profilePhoto'])) {
                $file = $_FILES['profilePhoto'];
            }
        } else {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->respondJson(['success' => false, 'message' => 'Invalid JSON'], 400);
            }
            foreach ($data as $k => $v) {
                $data[$k] = htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
            }
        }

        $updateData = [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'date_birth' => $data['date_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'gender_attraction' => $data['gender_attraction'] ?? null,
            'age_attraction_min' => $data['age_attraction_min'] ?? null,
            'age_attraction_max' => $data['age_attraction_max'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        // Handle profile photo upload if present
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $maxFileSize = 2 * 1024 * 1024;
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if ($file['size'] > $maxFileSize) {
                $this->respondJson(['success' => false, 'message' => 'Profile photo too large (max 2MB)'], 400);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mimeType, $allowedTypes)) {
                $this->respondJson(['success' => false, 'message' => 'Invalid image type'], 400);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('profile_', true) . '.' . $ext;
            $uploadDir = 'C:/Coding/Dating-app/app-loove/frontend/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $photoPath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $photoPath)) {
                $this->respondJson(['success' => false, 'message' => 'Failed to upload profile photo'], 500);
            }
            $updateData['pfp_path'] = '/uploads/' . $filename;
        } else {
            // No new photo uploaded, preserve existing pfp_path
            $existingUser = \App\Models\User::findByEmail($_SESSION['user_email']);
            if ($existingUser && isset($existingUser['pfp_path'])) {
                $updateData['pfp_path'] = $existingUser['pfp_path'];
            }
        }

        $result = \App\Models\User::update($userId, $updateData);

        if ($result) {
            $this->respondJson(['success' => true]);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Update failed'], 500);
        }
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
    public function logout() {
        session_start();
        session_unset();
        session_destroy();
        $this->respondJson(['success' => true]);
    }
}