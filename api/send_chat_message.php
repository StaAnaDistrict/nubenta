<?php
/**
 * Send a message in popup chat
 * Handles message sending for the popup chat system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['thread_id']) || !isset($input['content'])) {
    echo json_encode(['success' => false, 'error' => 'Thread ID and content are required']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $threadId = intval($input['thread_id']);
    $content = trim($input['content']);

    if (empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Message content cannot be empty']);
        exit;
    }

    // Get receiver info from existing messages in this thread
    $accessCheck = $pdo->prepare("
        SELECT
            CASE
                WHEN m.sender_id = ? THEN m.receiver_id
                ELSE m.sender_id
            END as participant_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as participant_name
        FROM messages m
        JOIN users u ON (
            CASE
                WHEN m.sender_id = ? THEN u.id = m.receiver_id
                ELSE u.id = m.sender_id
            END
        )
        WHERE m.thread_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
        LIMIT 1
    ");
    $accessCheck->execute([$currentUserId, $currentUserId, $threadId, $currentUserId, $currentUserId]);
    $receiver = $accessCheck->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        echo json_encode(['success' => false, 'error' => 'Access denied or invalid thread']);
        exit;
    }

    $receiverId = $receiver['participant_id'];

    // Check receiver's online status to determine delivery status
    $deliveredAt = null;
    try {
        // First check if user_activity table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_activity'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    CASE 
                        WHEN last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 
                        ELSE 0 
                    END as is_online,
                    CASE 
                        WHEN current_page LIKE '%messages%' AND last_activity > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 
                        ELSE 0 
                    END as is_on_messages_page
                FROM user_activity 
                WHERE user_id = ?
            ");
            $stmt->execute([$receiverId]);
            $receiverStatus = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Set delivered_at based on receiver's status
            if ($receiverStatus && $receiverStatus['is_online']) {
                $deliveredAt = 'NOW()';
            }
        } else {
            // user_activity table doesn't exist, assume immediate delivery for now
            $deliveredAt = 'NOW()';
            error_log("Info: user_activity table not found, assuming immediate delivery");
        }
    } catch (PDOException $e) {
        // If there are any issues, just continue without delivery status
        error_log("Warning: Could not check user activity status: " . $e->getMessage());
        // Message will be sent without immediate delivery status
    }

    // Insert the message using the correct 'body' column
    $stmt = $pdo->prepare("
        INSERT INTO messages (
            thread_id,
            sender_id,
            receiver_id,
            body,
            sent_at" . ($deliveredAt ? ", delivered_at" : "") . "
        ) VALUES (?, ?, ?, ?, NOW()" . ($deliveredAt ? ", $deliveredAt" : "") . ")
    ");

    $stmt->execute([$threadId, $currentUserId, $receiverId, $content]);
    $messageId = $pdo->lastInsertId();

    // Update thread's last activity
    $updateThread = $pdo->prepare("
        UPDATE chat_threads
        SET updated_at = NOW()
        WHERE id = ?
    ");
    $updateThread->execute([$threadId]);

    // Get the complete message data for response
    $messageStmt = $pdo->prepare("
        SELECT
            m.id,
            m.body as content,
            m.sent_at as created_at,
            m.delivered_at,
            m.read_at,
            m.sender_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as sender_name,
            u.profile_pic as sender_profile_pic
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");

    $messageStmt->execute([$messageId]);
    $messageData = $messageStmt->fetch(PDO::FETCH_ASSOC);

    if ($messageData) {
        $profilePic = !empty($messageData['sender_profile_pic'])
            ? 'uploads/profile_pics/' . $messageData['sender_profile_pic']
            : 'assets/images/MaleDefaultProfilePicture.png';

        $formattedMessage = [
            'id' => $messageData['id'],
            'content' => $messageData['content'],
            'created_at' => $messageData['created_at'],
            'delivered_at' => $messageData['delivered_at'],
            'read_at' => $messageData['read_at'],
            'sender_id' => $messageData['sender_id'],
            'sender_name' => $messageData['sender_name'],
            'sender_profile_pic' => $profilePic,
            'is_own' => true
        ];

        echo json_encode([
            'success' => true,
            'message' => $formattedMessage,
            'thread_id' => $threadId
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'thread_id' => $threadId
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in send_chat_message.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in send_chat_message.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
