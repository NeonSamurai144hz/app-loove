<?php

namespace App\Controllers;

class ChatController {

    public function getUserChats() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $chats = $this->getChatsForUser($userId);
        $this->respondJson(['success' => true, 'chats' => $chats]);
    }

    public function createOrGetMatch() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $currentUserId = $_SESSION['user_id'] ?? null;
        if (!$currentUserId) {
            $this->respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $otherUserId = $input['userId'] ?? null;

        if (!$otherUserId) {
            $this->respondJson(['success' => false, 'message' => 'User ID is required'], 400);
            return;
        }

        try {
            $db = \App\Database::getInstance();

            // Check if a match already exists between these users
            $stmt = $db->prepare("
                SELECT id 
                FROM matches 
                WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
            ");
            $stmt->execute([$currentUserId, $otherUserId, $otherUserId, $currentUserId]);
            $existingMatch = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingMatch) {
                // Match already exists
                $this->respondJson(['success' => true, 'matchId' => $existingMatch['id'], 'isNew' => false]);
            } else {
                // Create new match
                $stmt = $db->prepare("INSERT INTO matches (user1_id, user2_id) VALUES (?, ?)");
                $stmt->execute([$currentUserId, $otherUserId]);
                $matchId = $db->lastInsertId();

                $this->respondJson(['success' => true, 'matchId' => $matchId, 'isNew' => true]);
            }
        } catch (\Exception $e) {
            error_log("Error in createOrGetMatch: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            $this->respondJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function getMessages($matchId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        error_log("SECURITY: Getting messages for match $matchId, user $userId\n", 3, __DIR__ . '/../debug.log');

        // CRITICAL SECURITY: Verify user is part of this match
        if (!$this->userHasAccessToMatch($userId, $matchId)) {
            error_log("SECURITY VIOLATION: User $userId attempted to access match $matchId without permission\n", 3, __DIR__ . '/../debug.log');
            $this->respondJson(['success' => false, 'message' => 'Unauthorized access to this chat'], 403);
            return;
        }

        try {
            $messages = $this->getMessagesForMatchAndUser($matchId, $userId);
            error_log("SECURITY: Found " . count($messages) . " messages for match $matchId and user $userId\n", 3, __DIR__ . '/../debug.log');
            $this->respondJson(['success' => true, 'messages' => $messages]);
        } catch (\Exception $e) {
            error_log("Error getting messages: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function sendMessage() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $matchId = $input['matchId'] ?? null;
        $content = $input['content'] ?? null;

        error_log("SECURITY: Sending message: matchId=$matchId, userId=$userId\n", 3, __DIR__ . '/../debug.log');

        if (!$matchId || !$content) {
            $this->respondJson(['success' => false, 'message' => 'Match ID and content are required'], 400);
            return;
        }

        // CRITICAL SECURITY: Verify user has access to this match
        if (!$this->userHasAccessToMatch($userId, $matchId)) {
            error_log("SECURITY VIOLATION: User $userId attempted to send message to match $matchId without permission\n", 3, __DIR__ . '/../debug.log');
            $this->respondJson(['success' => false, 'message' => 'Unauthorized access to this chat'], 403);
            return;
        }

        try {
            $messageId = $this->saveMessage($matchId, $userId, $content);
            if ($messageId) {
                error_log("SECURITY: Message saved with ID: $messageId for user $userId\n", 3, __DIR__ . '/../debug.log');
                $this->respondJson(['success' => true, 'messageId' => $messageId]);
            } else {
                error_log("Failed to save message for user $userId\n", 3, __DIR__ . '/../debug.log');
                $this->respondJson(['success' => false, 'message' => 'Failed to send message'], 500);
            }
        } catch (\Exception $e) {
            error_log("Error sending message: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            $this->respondJson(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function getChatsForUser($userId) {
        try {
            $db = \App\Database::getInstance();

            // SECURITY: Only get chats where the user is actually a participant
            $query = "
                SELECT 
                    m.id as match_id,
                    m.user1_id,
                    m.user2_id,
                    CASE 
                        WHEN m.user1_id = ? THEN u2.first_name 
                        ELSE u1.first_name 
                    END as other_name,
                    CASE 
                        WHEN m.user1_id = ? THEN u2.last_name 
                        ELSE u1.last_name 
                    END as other_last_name,
                    CASE 
                        WHEN m.user1_id = ? THEN u2.pfp_path 
                        ELSE u1.pfp_path 
                    END as other_pfp,
                    CASE 
                        WHEN m.user1_id = ? THEN u2.id 
                        ELSE u1.id 
                    END as other_user_id,
                    (SELECT content FROM messages msg 
                     JOIN chats c ON msg.chat_id = c.id 
                     WHERE c.match_id = m.id 
                     AND (msg.sender_id = ? OR msg.receiver_id = ?)
                     ORDER BY msg.created_at DESC LIMIT 1) as last_message,
                    (SELECT msg.created_at FROM messages msg 
                     JOIN chats c ON msg.chat_id = c.id 
                     WHERE c.match_id = m.id 
                     AND (msg.sender_id = ? OR msg.receiver_id = ?)
                     ORDER BY msg.created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) FROM messages msg 
                     JOIN chats c ON msg.chat_id = c.id 
                     WHERE c.match_id = m.id 
                     AND (msg.sender_id = ? OR msg.receiver_id = ?)) as message_count
                FROM matches m
                JOIN users u1 ON m.user1_id = u1.id
                JOIN users u2 ON m.user2_id = u2.id
                WHERE (m.user1_id = ? OR m.user2_id = ?)
                ORDER BY last_message_time DESC NULLS LAST
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                $userId, $userId, $userId, $userId, // For CASE statements
                $userId, $userId, // For last_message subquery
                $userId, $userId, // For last_message_time subquery
                $userId, $userId, // For message_count subquery
                $userId, $userId  // For WHERE clause
            ]);

            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            error_log("SECURITY: getChatsForUser found " . count($result) . " chats for user $userId\n", 3, __DIR__ . '/../debug.log');
            return $result;
        } catch (\Exception $e) {
            error_log("Error in getChatsForUser: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            return [];
        }
    }

    private function userHasAccessToMatch($userId, $matchId) {
        try {
            $db = \App\Database::getInstance();
            $stmt = $db->prepare("SELECT id, user1_id, user2_id FROM matches WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
            $stmt->execute([$matchId, $userId, $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                error_log("SECURITY: User access check PASSED - matchId=$matchId, userId=$userId, user1_id={$result['user1_id']}, user2_id={$result['user2_id']}\n", 3, __DIR__ . '/../debug.log');
                return true;
            } else {
                error_log("SECURITY: User access check FAILED - matchId=$matchId, userId=$userId\n", 3, __DIR__ . '/../debug.log');
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in userHasAccessToMatch: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            return false;
        }
    }

    // CRITICAL SECURITY METHOD: Only get messages where user is sender OR receiver
    private function getMessagesForMatchAndUser($matchId, $userId) {
        try {
            $db = \App\Database::getInstance();

            // First, get the other participant in this match
            $matchStmt = $db->prepare("SELECT user1_id, user2_id FROM matches WHERE id = ?");
            $matchStmt->execute([$matchId]);
            $match = $matchStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$match) {
                error_log("SECURITY: No match found for ID $matchId\n", 3, __DIR__ . '/../debug.log');
                return [];
            }

            // Determine the other user in this conversation
            $otherUserId = null;
            if ($match['user1_id'] == $userId) {
                $otherUserId = $match['user2_id'];
            } elseif ($match['user2_id'] == $userId) {
                $otherUserId = $match['user1_id'];
            } else {
                error_log("SECURITY: User $userId is not part of match $matchId\n", 3, __DIR__ . '/../debug.log');
                return [];
            }

            // Get the chat ID for this match
            $chatStmt = $db->prepare("SELECT id FROM chats WHERE match_id = ?");
            $chatStmt->execute([$matchId]);
            $chat = $chatStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$chat) {
                error_log("SECURITY: No chat found for match $matchId\n", 3, __DIR__ . '/../debug.log');
                return [];
            }

            $chatId = $chat['id'];

            // ULTRA SECURE: Only get messages between these exact 2 users
            $query = "
                SELECT 
                    m.id, 
                    m.content, 
                    m.sender_id,
                    m.receiver_id,
                    m.created_at as sent_at,
                    'text' as message_type,
                    NULL as media_url,
                    u.first_name, 
                    u.pfp_path
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.chat_id = ?
                AND m.sender_id IN (?, ?)
                AND m.receiver_id IN (?, ?)
                AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                $chatId,
                $userId, $otherUserId,  // sender_id IN (current_user, other_user)
                $userId, $otherUserId,  // receiver_id IN (current_user, other_user)
                $userId, $otherUserId,  // (sender=current_user AND receiver=other_user)
                $otherUserId, $userId   // OR (sender=other_user AND receiver=current_user)
            ]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            error_log("SECURITY: ULTRA SECURE getMessagesForMatchAndUser found " . count($messages) . " messages between users $userId and $otherUserId in chat $chatId (match $matchId)\n", 3, __DIR__ . '/../debug.log');

            // Log each message for debugging (only in development)
            foreach ($messages as $msg) {
                error_log("SECURITY: Message ID {$msg['id']}: sender={$msg['sender_id']}, receiver={$msg['receiver_id']}, content={$msg['content']}\n", 3, __DIR__ . '/../debug.log');
            }

            return $messages;
        } catch (\Exception $e) {
            error_log("Error in getMessagesForMatchAndUser: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            throw $e;
        }
    }

    private function getMessagesForMatch($matchId) {
        // Redirect to secure version
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception("No user session found");
        }

        return $this->getMessagesForMatchAndUser($matchId, $userId);
    }

    private function saveMessage($matchId, $senderId, $content) {
        try {
            $db = \App\Database::getInstance();

            // Get the match participants
            $matchStmt = $db->prepare("SELECT user1_id, user2_id FROM matches WHERE id = ?");
            $matchStmt->execute([$matchId]);
            $match = $matchStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$match) {
                throw new \Exception("Match not found");
            }

            $receiverId = ($match['user1_id'] == $senderId) ? $match['user2_id'] : $match['user1_id'];

            // Get or create chat
            $chatStmt = $db->prepare("SELECT id FROM chats WHERE match_id = ?");
            $chatStmt->execute([$matchId]);
            $chat = $chatStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$chat) {
                // Create chat if it doesn't exist
                $createChatStmt = $db->prepare("INSERT INTO chats (match_id) VALUES (?)");
                $createChatStmt->execute([$matchId]);
                $chatId = $db->lastInsertId();
            } else {
                $chatId = $chat['id'];
            }

            // Insert message with proper receiver_id
            $query = "INSERT INTO messages (chat_id, sender_id, receiver_id, content, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);

            if ($stmt->execute([$chatId, $senderId, $receiverId, $content])) {
                $messageId = $db->lastInsertId();
                error_log("SECURITY: Message saved - ID: $messageId, chat: $chatId, sender: $senderId, receiver: $receiverId\n", 3, __DIR__ . '/../debug.log');
                return $messageId;
            }
            return false;
        } catch (\Exception $e) {
            error_log("Error in saveMessage: " . $e->getMessage() . "\n", 3, __DIR__ . '/../debug.log');
            throw $e;
        }
    }

    private function respondJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}