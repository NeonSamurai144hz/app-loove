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
        $userId = $_SESSION['user_id'];
        $stmt = $this->db->prepare("
            SELECT m.id as match_id,
                   u.id as user_id,
                   u.first_name,
                   u.last_name,
                   u.pfp_path,
                   u.gender,
                   u.date_birth,
                   u.description
            FROM matches m
            JOIN users u ON (u.id = IF(m.user1_id = ?, m.user2_id, m.user1_id))
            WHERE m.user1_id = ? OR m.user2_id = ?
            ORDER BY m.matched_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $matches = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respondJson(['success' => true, 'matches' => $matches]);
    }

    public function videoMatch() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            $this->respondJson(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];

        $stmt = $this->db->prepare("UPDATE users SET video_chat_status = 'available' WHERE id = ?");
        $stmt->execute([$userId]);

        $stmt = $this->db->prepare("
            SELECT gender, gender_attraction, age_attraction_min, age_attraction_max
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $userPrefs = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.pfp_path
            FROM users u
            WHERE u.id != ?
              AND u.video_chat_status = 'available'
              AND (u.gender = ? OR ? = 'both')
              AND (u.gender_attraction = ? OR u.gender_attraction = 'both')
              AND u.id NOT IN (
                  SELECT IF(m.user1_id = ?, m.user2_id, m.user1_id)
                  FROM matches m
                  WHERE (m.user1_id = ? OR m.user2_id = ?) AND m.match_type = 'video'
              )
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute([
            $userId,
            $userPrefs['gender_attraction'],
            $userPrefs['gender_attraction'],
            $userPrefs['gender'],
            $userId,
            $userId,
            $userId
        ]);

        $matchedUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$matchedUser) {
            $stmt = $this->db->prepare("
                SELECT u.id, u.first_name, u.last_name, u.pfp_path
                FROM users u
                WHERE u.id != ?
                  AND u.video_chat_status = 'available'
                ORDER BY RAND()
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $matchedUser = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$matchedUser) {
                $this->respondJson(['success' => false, 'message' => 'No users available for video chat']);
                return;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO matches (user1_id, user2_id, matched_at, match_type)
            VALUES (?, ?, NOW(), 'video')
        ");
        $stmt->execute([$userId, $matchedUser['id']]);
        $matchId = $this->db->lastInsertId();

        $stmt = $this->db->prepare("
            UPDATE users
            SET video_chat_status = 'busy'
            WHERE id IN (?, ?)
        ");
        $stmt->execute([$userId, $matchedUser['id']]);

        $this->respondJson([
            'success' => true,
            'match_id' => $matchId,
            'user_id' => $matchedUser['id'],
            'first_name' => $matchedUser['first_name'],
            'last_name' => $matchedUser['last_name'],
            'pfp_path' => $matchedUser['pfp_path'] ?: '/assets/img/sample-profile.jpg'
        ]);
    }

    public function getRecommendedMatches() {
        session_start();
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Retrieve user info (like age range, gender preferences)
        // For simplicity, this example uses only a basic query
        $db = \App\Database::getInstance();
        $stmt = $db->prepare("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.pfp_path,
            TIMESTAMPDIFF(YEAR, u.date_birth, CURDATE()) AS age,
            0 AS distance
        FROM
            users u
        WHERE
            u.is_active = 1
            AND u.id != :userId
        ORDER BY u.id ASC
        LIMIT :lim OFFSET :offs
    ");
        $stmt->bindValue(':userId', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offs', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $matches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Determine if more rows exist
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND id != :userId");
        $stmtCount->execute([':userId' => $userId]);
        $total = $stmtCount->fetchColumn();
        $hasMore = ($page * $limit) < $total;

        echo json_encode([
            'success' => true,
            'matches' => $matches,
            'hasMore' => $hasMore
        ]);
    }

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}