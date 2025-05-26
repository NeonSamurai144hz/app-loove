<?php
namespace App\Models;

use PDO;

class User {
    public static function findByEmail($email) {
        $pdo = \Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($data) {
        $pdo = \Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, date_birth, gender, gender_attraction, age_attraction_min, age_attraction_max, pfp_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $success = $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['password'],
            $data['date_birth'],
            $data['gender'],
            $data['gender_attraction'],
            $data['age_attraction_min'],
            $data['age_attraction_max'],
            $data['pfp_path'] ?? null
        ]);
        return $success ? $pdo->lastInsertId() : false;
    }
}