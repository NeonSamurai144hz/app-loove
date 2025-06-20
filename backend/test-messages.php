<?php
require_once __DIR__ . '/app-loove/vendor/autoload.php';

try {
    $db = \App\Database::getInstance();

    // First, let's see what matches exist
    $stmt = $db->prepare("SELECT * FROM matches LIMIT 5");
    $stmt->execute();
    $matches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    echo "Existing matches:\n";
    foreach ($matches as $match) {
        echo "Match ID: {$match['id']}, User1: {$match['user1_id']}, User2: {$match['user2_id']}\n";
    }

    // If we have matches, let's add some test messages
    if (count($matches) > 0) {
        $matchId = $matches[0]['id'];
        $user1 = $matches[0]['user1_id'];
        $user2 = $matches[0]['user2_id'];

        echo "\nAdding test messages to match $matchId...\n";

        // Add a few test messages
        $testMessages = [
            [$matchId, $user1, "Hey there! How are you doing?"],
            [$matchId, $user2, "Hi! I'm doing great, thanks for asking! How about you?"],
            [$matchId, $user1, "I'm doing well too! Would you like to grab coffee sometime?"],
            [$matchId, $user2, "That sounds wonderful! I'd love to."]
        ];

        foreach ($testMessages as $msg) {
            $stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, content, sent_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute($msg);
            echo "Added message: {$msg[2]}\n";
        }

        echo "\nTest messages added successfully!\n";

        // Verify messages were added
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages WHERE match_id = ?");
        $stmt->execute([$matchId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        echo "Total messages in match $matchId: {$result['count']}\n";
    } else {
        echo "\nNo matches found. Please create some matches first.\n";

        // Let's check if we have users
        $stmt = $db->prepare("SELECT id, first_name, last_name FROM users LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($users) >= 2) {
            echo "\nCreating a test match between users {$users[0]['id']} and {$users[1]['id']}...\n";
            $stmt = $db->prepare("INSERT INTO matches (user1_id, user2_id, matched_at) VALUES (?, ?, NOW())");
            $stmt->execute([$users[0]['id'], $users[1]['id']]);
            $matchId = $db->lastInsertId();
            echo "Created match with ID: $matchId\n";

            // Now add test messages
            $testMessages = [
                [$matchId, $users[0]['id'], "Hey there! How are you doing?"],
                [$matchId, $users[1]['id'], "Hi! I'm doing great, thanks for asking!"],
            ];

            foreach ($testMessages as $msg) {
                $stmt = $db->prepare("INSERT INTO messages (match_id, sender_id, content, sent_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute($msg);
                echo "Added message: {$msg[2]}\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>