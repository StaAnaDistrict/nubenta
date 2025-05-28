<?php
/**
 * Get new chat messages since a specific time
 * Used for real-time updates in popup chat windows
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['thread_id']) || !isset($_GET['since'])) {
    echo json_encode(['success' => false, 'error' => 'Thread ID and since timestamp are required']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $threadId = intval($_GET['thread_id']);
    $since = $_GET['since'];

    // Verify user has access to this thread by checking if they have messages in it
    $accessCheck = $pdo->prepare("
        SELECT COUNT(*) as has_access
        FROM messages m
        WHERE m.thread_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
    ");
    $accessCheck->execute([$threadId, $currentUserId, $currentUserId]);
    $access = $accessCheck->fetch(PDO::FETCH_ASSOC);

    if (!$access || $access['has_access'] == 0) {
        echo json_encode(['success' => false, 'error' => 'Access denied to this thread']);
        exit;
    }

    // Get new messages since the specified time
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.body as content,
            m.sent_at as created_at,
            m.sender_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as sender_name,
            u.profile_pic as sender_profile_pic,
            CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_own
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.thread_id = ?
          AND m.sent_at > ?
          AND m.deleted_by_sender = 0
          AND m.deleted_by_receiver = 0
        ORDER BY m.sent_at ASC
    ");

    $stmt->execute([$currentUserId, $threadId, $since]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format messages for popup chat
    $formattedMessages = [];
    foreach ($messages as $message) {
        $profilePic = !empty($message['sender_profile_pic'])
            ? 'uploads/profile_pics/' . $message['sender_profile_pic']
            : 'assets/images/MaleDefaultProfilePicture.png';

        $formattedMessages[] = [
            'id' => $message['id'],
            'content' => $message['content'],
            'created_at' => $message['created_at'],
            'sender_id' => $message['sender_id'],
            'sender_name' => $message['sender_name'],
            'sender_profile_pic' => $profilePic,
            'is_own' => (bool)$message['is_own']
        ];
    }

    // Mark new messages as read if they're not from the current user
    if (!empty($formattedMessages)) {
        $newMessageIds = array_column($messages, 'id');
        $placeholders = str_repeat('?,', count($newMessageIds) - 1) . '?';

        $markReadStmt = $pdo->prepare("
            UPDATE messages
            SET read_at = NOW()
            WHERE id IN ($placeholders)
              AND receiver_id = ?
              AND read_at IS NULL
        ");
        $markReadStmt->execute(array_merge($newMessageIds, [$currentUserId]));
    }

    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'thread_id' => $threadId,
        'count' => count($formattedMessages),
        'since' => $since
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_new_chat_messages.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in get_new_chat_messages.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
