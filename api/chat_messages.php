<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Revert to turn off display errors
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

error_log("=== New Request to chat_messages.php ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    error_log("No user session found");
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];
error_log("User ID: " . $user_id);

// Function to ensure thread exists
function ensureThreadExists($pdo, $thread_id) {
    try {
        error_log("=== Thread Existence Check Start ===");
        error_log("Checking thread ID: " . $thread_id);
        
        // First check if thread exists in chat_threads
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        $threadExists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        error_log("Thread exists in chat_threads: " . ($threadExists ? 'Yes' : 'No'));
        
        // Check messages table
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, 
                   MIN(sender_id) as sender_id,
                   MIN(receiver_id) as receiver_id
            FROM messages 
            WHERE thread_id = ?
        ");
        $stmt->execute([$thread_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $messageCount = $result['count'];
        error_log("Found " . $messageCount . " messages for thread " . $thread_id);
        
        if ($messageCount > 0) {
            $sender_id = $result['sender_id'];
            $receiver_id = $result['receiver_id'];
            error_log("Thread participants - Sender: " . $sender_id . ", Receiver: " . $receiver_id);
            
            // Create thread if it doesn't exist
            if (!$threadExists) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO chat_threads (id, type, created_at) 
                        VALUES (?, 'one_on_one', NOW())
                    ");
                    $stmt->execute([$thread_id]);
                    error_log("Created thread record in chat_threads");
                } catch (PDOException $e) {
                    error_log("Error creating thread: " . $e->getMessage());
                    // Continue anyway as the thread might have been created by another request
                }
            }
            
            // Create settings if they don't exist
            try {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO user_conversation_settings 
                    (user_id, conversation_id, is_deleted_for_user) 
                    VALUES (?, ?, FALSE), (?, ?, FALSE)
                ");
                $stmt->execute([
                    $sender_id, $thread_id,
                    $receiver_id, $thread_id
                ]);
                error_log("Created/verified user conversation settings");
            } catch (PDOException $e) {
                error_log("Error creating user settings: " . $e->getMessage());
                // Continue anyway as the settings might have been created by another request
            }
            
            error_log("=== Thread Existence Check End - Success ===");
            return true;
        }
        
        error_log("=== Thread Existence Check End - Failed ===");
        return false;
    } catch (PDOException $e) {
        error_log("Error in ensureThreadExists: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Handle GET request to load messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['thread_id'])) {
        error_log("No thread_id provided in GET request");
        echo json_encode(['success' => false, 'error' => 'Thread ID required']);
        exit;
    }

    $thread_id = $_GET['thread_id'];
    error_log("=== Message Loading Start ===");
    error_log("Loading messages for thread ID: " . $thread_id . " and user ID: " . $user_id);

    try {
        // First check if thread exists in messages table
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, 
                   MIN(sender_id) as sender_id,
                   MIN(receiver_id) as receiver_id
            FROM messages 
            WHERE thread_id = ?
        ");
        $stmt->execute([$thread_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $messageCount = $result['count'];
        error_log("Found " . $messageCount . " messages for thread " . $thread_id);
        
        if ($messageCount == 0) {
            error_log("No messages found for thread " . $thread_id);
            echo json_encode(['success' => false, 'error' => 'Thread does not exist']);
            exit;
        }

        // Ensure thread exists in chat_threads and create settings
        if (!ensureThreadExists($pdo, $thread_id)) {
            error_log("Failed to ensure thread " . $thread_id . " exists");
            echo json_encode(['success' => false, 'error' => 'Thread does not exist']);
            exit;
        }

        // Check user authorization - Modified to be more permissive
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM messages m
            WHERE m.thread_id = ? 
            AND (m.sender_id = ? OR m.receiver_id = ?)
        ");
        $stmt->execute([$thread_id, $user_id, $user_id]);
        $authCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        error_log("User authorization check count: " . $authCount);
        
        if ($authCount == 0) {
            error_log("User " . $user_id . " is not authorized to view thread " . $thread_id);
            echo json_encode(['success' => false, 'error' => 'Not authorized to view this thread']);
            exit;
        }

        // Get messages - Modified to match actual database structure
        $stmt = $pdo->prepare("
            SELECT 
                m.id, 
                m.thread_id, 
                m.sender_id, 
                m.body as content, 
                m.file_path, 
                m.file_info, 
                m.file_mime, 
                m.file_size, 
                m.sent_at as created_at, 
                m.delivered_at, 
                m.read_at, 
                u.full_name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.thread_id = ? 
            AND (
                (m.sender_id = ? AND m.deleted_by_sender = 0) OR
                (m.receiver_id = ? AND m.deleted_by_receiver = 0)
            )
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$thread_id, $user_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Retrieved " . count($messages) . " messages for thread " . $thread_id);
        
        // Process messages
        foreach ($messages as &$message) {
            if ($message['file_path']) {
                $message['file_path'] = 'uploads/' . $message['file_path'];
            }
        }
        
        error_log("=== Message Loading End - Success ===");
        echo json_encode(['success' => true, 'messages' => $messages]);
        
    } catch (PDOException $e) {
        error_log("Error in chat_messages.php: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request to send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['thread_id']) || !isset($input['content'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }

    $thread_id = $input['thread_id'];
    $content = $input['content'];
    $message_type = $input['message_type'] ?? 'text';

    try {
        $pdo->beginTransaction();

        // Ensure thread exists
        if (!ensureThreadExists($pdo, $thread_id)) {
            echo json_encode(['success' => false, 'error' => 'Thread not found or not authorized']);
            exit;
        }

        // Check if thread exists and get participant info
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as other_user_id
            FROM messages 
            WHERE thread_id = ? 
            AND (sender_id = ? OR receiver_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$user_id, $thread_id, $user_id, $user_id]);
        $participant_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$participant_info) {
            echo json_encode(['success' => false, 'error' => 'Thread not found or not authorized']);
            exit;
        }

        // Check if thread is deleted for current user
        $stmt = $pdo->prepare("
            SELECT is_deleted_for_user 
            FROM user_conversation_settings 
            WHERE conversation_id = ? AND user_id = ?
        ");
        $stmt->execute([$thread_id, $user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        // If thread is deleted for current user, create a new thread
        if ($settings && $settings['is_deleted_for_user']) {
            // Create new thread
            $stmt = $pdo->prepare("
                INSERT INTO chat_threads (type, created_at) 
                VALUES ('one_on_one', NOW())
            ");
            $stmt->execute();
            $new_thread_id = $pdo->lastInsertId();

            // Create settings for both users
            $stmt = $pdo->prepare("
                INSERT INTO user_conversation_settings 
                (user_id, conversation_id, is_deleted_for_user) 
                VALUES (?, ?, FALSE), (?, ?, FALSE)
            ");
            $stmt->execute([
                $user_id, $new_thread_id,
                $participant_info['other_user_id'], $new_thread_id
            ]);

            // Use new thread ID
            $thread_id = $new_thread_id;
        }

        // Insert the message
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                thread_id, 
                sender_id, 
                receiver_id, 
                body,
                message_type,
                sent_at,
                delivered_at,
                deleted_by_sender,
                deleted_by_receiver,
                is_unsent_for_everyone
            ) VALUES (
                ?, ?, ?, ?, ?, 
                NOW(), NOW(), 0, 0, 0
            )
        ");
        $stmt->execute([
            $thread_id,
            $user_id,
            $participant_info['other_user_id'],
            $content,
            $message_type
        ]);
        $message_id = $pdo->lastInsertId();

        // Update last read message for sender
        $stmt = $pdo->prepare("
            INSERT INTO user_conversation_settings 
            (user_id, conversation_id, last_read_message_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE last_read_message_id = ?
        ");
        $stmt->execute([$user_id, $thread_id, $message_id, $message_id]);

        // Create or update settings for receiver
        $stmt = $pdo->prepare("
            INSERT INTO user_conversation_settings 
            (user_id, conversation_id, is_deleted_for_user)
            VALUES (?, ?, FALSE)
            ON DUPLICATE KEY UPDATE is_deleted_for_user = FALSE
        ");
        $stmt->execute([$participant_info['other_user_id'], $thread_id]);

        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message_id' => $message_id,
            'thread_id' => $thread_id,
            'is_new_thread' => isset($new_thread_id)
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in chat_messages.php: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request to unsend message
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message_id'])) {
        echo json_encode(['success' => false, 'error' => 'Message ID required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_unsent_for_everyone = TRUE,
                body = 'This message was unsent'
            WHERE id = ? AND sender_id = ?
        ");
        $stmt->execute([$input['message_id'], $user_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Message not found or not authorized']);
        }
    } catch (PDOException $e) {
        error_log("Error in chat_messages.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

/* anything else = 405 */
echo json_encode(['error' => 'Invalid request method']);
exit; 