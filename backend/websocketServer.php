<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $userConnections = [];
    protected $matchRooms = [];
    protected $logger;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->logger = new \Monolog\Logger('websocket_server');
        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/../logs/websocket.log', \Monolog\Logger::DEBUG));
        $this->logger->info('WebSocket server initialized');
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->logger->info("New connection: {$conn->resourceId}");

        // Send a welcome message
        $conn->send(json_encode([
            'type' => 'connection-established',
            'message' => 'Connected to WebSocket server'
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $this->logger->debug("Received message: {$msg}");

        try {
            $data = json_decode($msg, true);

            if (!is_array($data) || !isset($data['type'])) {
                $this->logger->error("Invalid message format");
                return;
            }

            switch ($data['type']) {
                case 'register':
                    $this->handleRegistration($from, $data);
                    break;

                case 'offer':
                case 'answer':
                case 'ice-candidate':
                    $this->relaySignalingMessage($from, $data);
                    break;

                case 'disconnect':
                    $this->handleDisconnect($from, $data);
                    break;

                // New chat message handlers
                case 'chat-message':
                    $this->handleChatMessage($from, $data);
                    break;

                case 'typing':
                    $this->handleTypingIndicator($from, $data);
                    break;

                case 'read-receipt':
                    $this->handleReadReceipt($from, $data);
                    break;

                default:
                    $this->logger->warning("Unknown message type: {$data['type']}");
            }
        } catch (\Exception $e) {
            $this->logger->error("Error processing message: " . $e->getMessage());
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        // Notify others if this was a registered user
        $userId = $this->findUserIdByConnection($conn);
        if ($userId) {
            $this->handleUserDisconnect($userId);
            unset($this->userConnections[$userId]);
        }

        $this->logger->info("Connection {$conn->resourceId} closed");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error("Error: {$e->getMessage()}");
        $conn->close();
    }

    protected function handleRegistration($conn, $data) {
        if (!isset($data['data']['userId']) || !isset($data['data']['matchId'])) {
            $this->logger->error("Invalid registration data");
            return;
        }

        $userId = $data['data']['userId'];
        $matchId = $data['data']['matchId'];

        // Store the connection with user ID
        $this->userConnections[$userId] = $conn;

        // Add user to match room
        if (!isset($this->matchRooms[$matchId])) {
            $this->matchRooms[$matchId] = [];
        }
        $this->matchRooms[$matchId][$userId] = $conn;

        $this->logger->info("User {$userId} registered for match {$matchId}");

        // Notify other user in the match if present
        if (count($this->matchRooms[$matchId]) > 1) {
            foreach ($this->matchRooms[$matchId] as $memberId => $memberConn) {
                if ($memberId != $userId) {
                    $memberConn->send(json_encode([
                        'type' => 'user-connected',
                        'from' => $userId,
                        'to' => $memberId,
                        'matchId' => $matchId
                    ]));

                    $this->logger->info("Notified user {$memberId} about user {$userId} connecting");
                }
            }
        }

        // Confirm registration to the user
        $conn->send(json_encode([
            'type' => 'registration-complete',
            'userId' => $userId,
            'matchId' => $matchId
        ]));
    }

    protected function relaySignalingMessage($from, $data) {
        if (!isset($data['to']) || !isset($data['from']) || !isset($data['matchId'])) {
            $this->logger->error("Invalid signaling message: missing required fields");
            return;
        }

        $toUserId = $data['to'];
        $matchId = $data['matchId'];

        // Check if recipient is connected
        if (isset($this->userConnections[$toUserId])) {
            $this->userConnections[$toUserId]->send(json_encode($data));
            $this->logger->debug("Relayed {$data['type']} message from user {$data['from']} to user {$toUserId}");
        } else {
            $this->logger->warning("Cannot relay message: user {$toUserId} not connected");
        }
    }

    protected function handleDisconnect($conn, $data) {
        $userId = $data['from'] ?? $this->findUserIdByConnection($conn);
        $matchId = $data['matchId'] ?? null;

        if ($userId) {
            $this->handleUserDisconnect($userId, $matchId);
        }
    }

    protected function handleUserDisconnect($userId, $matchId = null) {
        // Notify all matches this user is part of
        if ($matchId) {
            // Notify specific match
            if (isset($this->matchRooms[$matchId])) {
                foreach ($this->matchRooms[$matchId] as $memberId => $conn) {
                    if ($memberId != $userId) {
                        $conn->send(json_encode([
                            'type' => 'user-disconnected',
                            'from' => $userId,
                            'to' => $memberId,
                            'matchId' => $matchId
                        ]));
                    }
                }

                // Remove user from this match room
                unset($this->matchRooms[$matchId][$userId]);

                // Clean up empty match rooms
                if (empty($this->matchRooms[$matchId])) {
                    unset($this->matchRooms[$matchId]);
                }
            }
        } else {
            // Notify all matches
            foreach ($this->matchRooms as $roomId => $members) {
                if (isset($members[$userId])) {
                    foreach ($members as $memberId => $conn) {
                        if ($memberId != $userId) {
                            $conn->send(json_encode([
                                'type' => 'user-disconnected',
                                'from' => $userId,
                                'to' => $memberId,
                                'matchId' => $roomId
                            ]));
                        }
                    }

                    // Remove user from this match room
                    unset($this->matchRooms[$roomId][$userId]);

                    // Clean up empty match rooms
                    if (empty($this->matchRooms[$roomId])) {
                        unset($this->matchRooms[$roomId]);
                    }
                }
            }
        }

        $this->logger->info("User {$userId} disconnected" . ($matchId ? " from match {$matchId}" : ""));
    }

    protected function findUserIdByConnection($conn) {
        foreach ($this->userConnections as $userId => $userConn) {
            if ($userConn === $conn) {
                return $userId;
            }
        }
        return null;
    }

    // New methods for chat functionality

    protected function handleChatMessage($from, $data) {
        if (!isset($data['to']) || !isset($data['from']) || !isset($data['matchId']) || !isset($data['data']['content'])) {
            $this->logger->error("Invalid chat message: missing required fields");
            return;
        }

        $toUserId = $data['to'];
        $fromUserId = $data['from'];
        $matchId = $data['matchId'];
        $messageContent = $data['data']['content'];
        $messageId = $data['data']['messageId'] ?? uniqid('msg_');

        // Store message in database - would typically call a service here
        $this->logger->info("Chat message from {$fromUserId} to {$toUserId} in match {$matchId}: {$messageContent}");

        // Add timestamp if not present
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = time();
        }

        // Add message ID if not present
        if (!isset($data['data']['messageId'])) {
            $data['data']['messageId'] = $messageId;
        }

        // Relay to recipient if online
        if (isset($this->userConnections[$toUserId])) {
            $this->userConnections[$toUserId]->send(json_encode($data));
            $this->logger->debug("Delivered chat message {$messageId} to user {$toUserId}");
        } else {
            $this->logger->warning("User {$toUserId} is offline, message queued");
            // In a real implementation, you might flag this message as "not delivered"
            // so the client can show appropriate status indicators
        }

        // Send delivery confirmation to sender
        $this->userConnections[$fromUserId]->send(json_encode([
            'type' => 'message-delivered',
            'from' => 'server',
            'to' => $fromUserId,
            'matchId' => $matchId,
            'data' => [
                'messageId' => $messageId,
                'status' => 'delivered'
            ]
        ]));
    }

    protected function handleTypingIndicator($from, $data) {
        if (!isset($data['to']) || !isset($data['from']) || !isset($data['matchId'])) {
            $this->logger->error("Invalid typing indicator: missing required fields");
            return;
        }

        $toUserId = $data['to'];
        $isTyping = $data['data']['isTyping'] ?? true;

        // Simply relay the typing indicator to the recipient if online
        if (isset($this->userConnections[$toUserId])) {
            $this->userConnections[$toUserId]->send(json_encode($data));
            $this->logger->debug("Relayed typing indicator from user {$data['from']} to user {$toUserId}");
        }
    }

    protected function handleReadReceipt($from, $data) {
        if (!isset($data['to']) || !isset($data['from']) || !isset($data['matchId']) || !isset($data['data']['messageIds'])) {
            $this->logger->error("Invalid read receipt: missing required fields");
            return;
        }

        $toUserId = $data['to'];
        $fromUserId = $data['from'];
        $messageIds = $data['data']['messageIds'];

        // Update messages as read in database - would typically call a service here
        $this->logger->info("User {$fromUserId} read messages: " . implode(', ', $messageIds));

        // Relay read receipt to sender if online
        if (isset($this->userConnections[$toUserId])) {
            $this->userConnections[$toUserId]->send(json_encode($data));
            $this->logger->debug("Relayed read receipt from user {$fromUserId} to user {$toUserId}");
        }
    }
}

// Create WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer()
        )
    ),
    8080
);

echo "WebSocket Server started on port 8080\n";
$server->run();