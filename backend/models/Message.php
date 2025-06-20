<?php
namespace App\Models;

class Message {
    public static function create($chatId, $senderId, $content) {
        try {
            $pdo = \App\Database::getInstance();

            // First, let's check what columns exist in the messages table
            $columnsStmt = $pdo->prepare("SHOW COLUMNS FROM messages");
            $columnsStmt->execute();
            $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);

            // Check if we have match_id or chat_id
            $matchIdExists = in_array('match_id', $columns);
            $chatIdExists = in_array('chat_id', $columns);
            $timestampColumn = in_array('sent_at', $columns) ? 'sent_at' : 'created_at';

            if (!$matchIdExists && !$chatIdExists) {
                throw new \Exception("Error: neither match_id nor chat_id exists in messages table");
            }

            $idColumn = $chatIdExists ? 'chat_id' : 'match_id';

            $query = "INSERT INTO messages ($idColumn, sender_id, content, $timestampColumn) VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($query);
            $success = $stmt->execute([$chatId, $senderId, $content]);
            return $success ? $pdo->lastInsertId() : false;
        } catch (\Exception $e) {
            error_log("Error in Message::create: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            return false;
        }
    }

    public static function getForChat($chatId, $userId, $otherUserId, $limit = 50, $before = null) {
        try {
            $pdo = \App\Database::getInstance();
            $timestampColumn = 'created_at';

            $sql = "SELECT m.*, u.first_name, u.pfp_path FROM messages m 
                JOIN users u ON m.sender_id = u.id
                WHERE m.chat_id = ?
                  AND m.sender_id IN (?, ?)
                  AND m.receiver_id IN (?, ?)
                  AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))";
            $params = [
                $chatId,
                $userId, $otherUserId,
                $userId, $otherUserId,
                $userId, $otherUserId,
                $otherUserId, $userId
            ];

            if ($before) {
                $sql .= " AND m.id < ?";
                $params[] = $before;
            }

            $sql .= " ORDER BY m.$timestampColumn DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error in Message::getForChat: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            return [];
        }
    }

    public static function markAsRead($chatId, $userId) {
        try {
            $pdo = \App\Database::getInstance();

            // First, let's check what columns exist in the messages table
            $columnsStmt = $pdo->prepare("SHOW COLUMNS FROM messages");
            $columnsStmt->execute();
            $columns = $columnsStmt->fetchAll(\PDO::FETCH_COLUMN);

            // Check if we have match_id or chat_id and is_read
            $matchIdExists = in_array('match_id', $columns);
            $chatIdExists = in_array('chat_id', $columns);
            $isReadExists = in_array('is_read', $columns);

            if (!$isReadExists) {
                // is_read column doesn't exist, so nothing to mark
                return true;
            }

            if (!$matchIdExists && !$chatIdExists) {
                throw new \Exception("Error: neither match_id nor chat_id exists in messages table");
            }

            $idColumn = $chatIdExists ? 'chat_id' : 'match_id';

            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 
                                  WHERE $idColumn = ? AND sender_id != ?");
            return $stmt->execute([$chatId, $userId]);
        } catch (\Exception $e) {
            error_log("Error in Message::markAsRead: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            return false;
        }
    }
}