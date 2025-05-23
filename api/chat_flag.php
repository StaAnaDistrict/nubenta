<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['thread_id']) || !isset($data['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$userId = $_SESSION['user']['id'];
$threadId = $data['thread_id'];
$action = $data['action'];
$value = isset($data['value']) ? (int)$data['value'] : 1;  // Convert to integer

try {
    $pdo->beginTransaction();

    // First check if user is part of this thread and get participant info
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
    $stmt->execute([$userId, $threadId, $userId, $userId]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participant) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authorized to modify this thread']);
        exit;
    }

    // Ensure thread exists in chat_threads
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO chat_threads (
            id, user1_id, user2_id, user_id, participant_id,
            user1_deleted, user2_deleted, is_spam, is_archived, is_deleted
        ) VALUES (
            ?, ?, ?, ?, ?,
            0, 0, 0, 0, 0
        )
    ");
    $stmt->execute([
        $threadId,
        $userId,
        $participant['other_user_id'],
        $userId,
        $participant['other_user_id']
    ]);

    // Update thread based on action
    switch ($action) {
        case 'archive':
            $sql = "UPDATE chat_threads SET is_archived = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = [$value, $threadId];
            break;
        case 'mute':
            $sql = "UPDATE chat_threads SET is_spam = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = [$value, $threadId];
            break;
        case 'delete':
            // First check if this is a message deletion or thread deletion
            if (isset($data['message_id'])) {
                // Message deletion
                $sql = "UPDATE messages SET 
                        deleted_by_sender = CASE WHEN sender_id = ? THEN ? ELSE deleted_by_sender END,
                        deleted_by_receiver = CASE WHEN receiver_id = ? THEN ? ELSE deleted_by_receiver END
                        WHERE id = ?";
                $params = [$userId, $value, $userId, $value, $data['message_id']];
            } else {
                // Thread deletion - update both thread and messages
                $sql = "UPDATE chat_threads SET 
                        user1_deleted = CASE WHEN user1_id = ? THEN ? ELSE user1_deleted END,
                        user2_deleted = CASE WHEN user2_id = ? THEN ? ELSE user2_deleted END,
                        is_deleted = CASE WHEN user1_id = ? OR user2_id = ? THEN ? ELSE is_deleted END,
                        updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ?";
                $params = [$userId, $value, $userId, $value, $userId, $userId, $value, $threadId];
                
                // Also mark all messages in the thread as deleted for this user
                $stmt = $pdo->prepare("
                    UPDATE messages SET 
                    deleted_by_sender = CASE WHEN sender_id = ? THEN ? ELSE deleted_by_sender END,
                    deleted_by_receiver = CASE WHEN receiver_id = ? THEN ? ELSE deleted_by_receiver END
                    WHERE thread_id = ?
                ");
                $stmt->execute([$userId, $value, $userId, $value, $threadId]);
            }
            break;
        default:
            $pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $pdo->commit();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in chat_flag.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
