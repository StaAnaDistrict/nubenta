<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Revert to turn off display errors
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Handle GET request to load messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['thread_id'])) {
        echo json_encode(['success' => false, 'error' => 'Thread ID required']);
        exit;
    }

    $thread_id = $_GET['thread_id'];

    try {
        // First check if user is part of this thread
        $stmt = $pdo->prepare("
            SELECT 1 FROM thread_participants 
            WHERE thread_id = ? AND user_id = ?
        ");
        $stmt->execute([$thread_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Not authorized to view this thread']);
            exit;
        }

        // Then get messages
        $stmt = $pdo->prepare("
            SELECT m.id, m.thread_id, m.sender_id, m.body as content, m.file_path, m.sent_at as created_at, m.delivered_at, m.read_at, u.full_name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.thread_id = ? 
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$thread_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process messages to ensure file paths are correct
        foreach ($messages as &$message) {
            if (!empty($message['file_path'])) {
                // Ensure file paths start with a forward slash
                $filePaths = explode(',', $message['file_path']);
                $filePaths = array_map(function($path) {
                    return $path[0] === '/' ? $path : '/' . $path;
                }, $filePaths);
                $message['file_path'] = implode(',', $filePaths);
            }
        }
        
        // Log the messages for debugging
        error_log("Messages for thread $thread_id: " . print_r($messages, true));
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    } catch (PDOException $e) {
        error_log("Error in chat_messages.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request to send a message
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['thread_id']) || !isset($input['content'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }

    $thread_id = $input['thread_id'];
    $content = $input['content'];

    try {
        $pdo->beginTransaction();

        // Check if thread exists and get participant info
        $stmt = $pdo->prepare("
            SELECT tp.user_id, t.is_group
            FROM thread_participants tp
            JOIN threads t ON tp.thread_id = t.id
            WHERE tp.thread_id = ? AND tp.user_id != ?
        ");
        $stmt->execute([$thread_id, $user_id]);
        $participant_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$participant_info) {
            // Thread doesn't exist or user is not authorized
            echo json_encode(['success' => false, 'error' => 'Thread not found or not authorized']);
            exit;
        }

        // Check if thread is deleted for the recipient
        $stmt = $pdo->prepare("
            SELECT 1 FROM mailbox_flags 
            WHERE thread_id = ? AND user_id = ?
        ");
        $stmt->execute([$thread_id, $participant_info['user_id']]);
        $is_deleted = $stmt->fetch();

        error_log("chat_messages.php: Checking if thread " . $thread_id . " is deleted for recipient " . $participant_info['user_id'] . ": " . ($is_deleted ? 'Yes' : 'No'));

        if ($is_deleted) {
            // If thread is deleted for recipient, 'undelete' it by removing flags
            // This allows the message to be inserted into the original thread for both users.
            $stmt = $pdo->prepare("DELETE FROM mailbox_flags WHERE thread_id = ? AND user_id = ?");
            $stmt->execute([$thread_id, $participant_info['user_id']]);
            error_log("chat_messages.php: Deleted mailbox_flags for thread " . $thread_id . " and user " . $participant_info['user_id']);
        }

        // Insert the message into the original thread (if not deleted by recipient)
        $stmt = $pdo->prepare("
            INSERT INTO messages (thread_id, sender_id, receiver_id, body, sent_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $receiver_id = !$participant_info['is_group'] ? $participant_info['user_id'] : NULL;
        // Note: We use the OLD thread ID here for message insertion
        $stmt->execute([$thread_id, $user_id, $receiver_id, $content]);
        $message_id = $pdo->lastInsertId();

        $pdo->commit();
        // For messages in the original thread, also just return success and message_id
        echo json_encode(['success' => true, 'message_id' => $message_id]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in chat_messages.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/* anything else = 405 */
echo json_encode(['error' => 'Invalid request method']);
exit; 