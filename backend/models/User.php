<?php
namespace App\Models;

use App\Database;
use PDO;
use PDOException;

class User {
    public static function getAllUsers() {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findByEmail($email) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($userData) {
        $db = Database::getInstance();

        $sql = "INSERT INTO users (
                    first_name, last_name, email, password,
                    date_birth, gender, gender_attraction,
                    age_attraction_min, age_attraction_max
                ) VALUES (
                    :first_name, :last_name, :email, :password,
                    :date_birth, :gender, :gender_attraction,
                    :age_attraction_min, :age_attraction_max
                )";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':first_name' => $userData['first_name'],
                ':last_name' => $userData['last_name'],
                ':email' => $userData['email'],
                ':password' => $userData['password'],
                ':date_birth' => $userData['date_birth'],
                ':gender' => $userData['gender'],
                ':gender_attraction' => $userData['gender_attraction'],
                ':age_attraction_min' => $userData['age_attraction_min'],
                ':age_attraction_max' => $userData['age_attraction_max']
            ]);

            return $db->lastInsertId();
        } catch (PDOException $e) {
            error_log("User creation failed: " . $e->getMessage());
            return false;
        }
    }

    public static function findById($id) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function update($userId, $userData) {
        $db = Database::getInstance();

        $sets = [];
        $params = [];

        foreach ($userData as $field => $value) {
            $sets[] = "$field = :$field";
            $params[":$field"] = $value;
        }

        $params[':id'] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = :id";

        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("User update failed: " . $e->getMessage());
            return false;
        }
    }
}