<?php
namespace App\Models;

class Chat {
    public static function create($matchId) {
        $pdo = \App\Database::getInstance();

        // Check if chat already exists
        $stmt = $pdo->prepare('SELECT id FROM chats WHERE match_id = ?');
        $stmt->execute([$matchId]);
        if ($stmt->fetch()) {
            return false; // Chat already exists
        }

        $stmt = $pdo->prepare('INSERT INTO chats (match_id) VALUES (?)');
        $success = $stmt->execute([$matchId]);
        return $success ? $pdo->lastInsertId() : false;
    }

    public static function getForUser($userId) {
        $pdo = \App\Database::getInstance();
        $sql = 'SELECT c.*, m.user1_id, m.user2_id, 
                CASE 
                    WHEN m.user1_id = ? THEN u2.first_name 
                    ELSE u1.first_name 
                END as other_name,
                CASE 
                    WHEN m.user1_id = ? THEN COALESCE(u2.pfp_path, "/assets/img/default-profile.jpg")
                    ELSE COALESCE(u1.pfp_path, "/assets/img/default-profile.jpg")
                END as other_pfp,
                (SELECT COUNT(*) FROM messages msg WHERE msg.chat_id = c.id AND msg.sender_id != ? AND msg.is_read = 0) as unread_count,
                (SELECT content FROM messages msg WHERE msg.chat_id = c.id ORDER BY msg.created_at DESC LIMIT 1) as last_message
                FROM chats c
                JOIN matches m ON c.match_id = m.id
                JOIN users u1 ON m.user1_id = u1.id
                JOIN users u2 ON m.user2_id = u2.id
                WHERE m.user1_id = ? OR m.user2_id = ?
                ORDER BY c.updated_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getById($chatId, $userId) {
        $pdo = \App\Database::getInstance();
        $sql = 'SELECT c.*, m.user1_id, m.user2_id, 
                CASE 
                    WHEN m.user1_id = ? THEN u2.id
                    ELSE u1.id 
                END as other_id,
                CASE 
                    WHEN m.user1_id = ? THEN u2.first_name 
                    ELSE u1.first_name 
                END as other_name,
                CASE 
                    WHEN m.user1_id = ? THEN COALESCE(u2.pfp_path, "/assets/img/default-profile.jpg")
                    ELSE COALESCE(u1.pfp_path, "/assets/img/default-profile.jpg")
                END as other_pfp
                FROM chats c
                JOIN matches m ON c.match_id = m.id
                JOIN users u1 ON m.user1_id = u1.id
                JOIN users u2 ON m.user2_id = u2.id
                WHERE c.id = ? AND (m.user1_id = ? OR m.user2_id = ?)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $chatId, $userId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}