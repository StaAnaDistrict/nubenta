<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Revert to turn off display errors

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
            SELECT m.id, m.thread_id, m.sender_id, m.body as content, m.sent_at as created_at, m.delivered_at, m.read_at, u.full_name as sender_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.id 
            WHERE m.thread_id = ? 
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$thread_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit; // Ensure script exits after outputting JSON
    } catch (PDOException $e) {
        error_log("Error in chat_messages.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit; // Ensure script exits after outputting JSON
    }
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
        // First check if user is part of this thread and get receiver_id for direct messages
        $stmt = $pdo->prepare("
            SELECT tp.user_id, t.is_group
            FROM thread_participants tp
            JOIN threads t ON tp.thread_id = t.id
            WHERE tp.thread_id = ? AND tp.user_id != ?
        ");
        $stmt->execute([$thread_id, $user_id]);
        $participant_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$participant_info) {
             echo json_encode(['success' => false, 'error' => 'Not authorized to send messages in this thread or thread not found']);
             exit;
        }

        $receiver_id = !$participant_info['is_group'] ? $participant_info['user_id'] : NULL; // Get receiver_id only for direct messages

        $stmt = $pdo->prepare("
            INSERT INTO messages (thread_id, sender_id, receiver_id, body, sent_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$thread_id, $user_id, $receiver_id, $content]);
        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
        exit; // Ensure script exits after outputting JSON
    } catch (PDOException $e) {
        error_log("Error in chat_messages.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit; // Ensure script exits after outputting JSON
    }
}

/* anything else = 405 */
echo json_encode(['error' => 'Invalid request method']);
exit; 