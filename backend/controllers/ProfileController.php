<?php
namespace App\Controllers;

use App\Models\User;

class ProfileController {
    private $db;

    public function __construct($database) {
        $this->db = $database;
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
            $existingUser = User::findByEmail($_SESSION['user_email']);
            if ($existingUser && isset($existingUser['pfp_path'])) {
                $updateData['pfp_path'] = $existingUser['pfp_path'];
            }
        }

        $result = User::update($userId, $updateData);

        if ($result) {
            $this->respondJson(['success' => true]);
        } else {
            $this->respondJson(['success' => false, 'message' => 'Update failed'], 500);
        }
    }

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}