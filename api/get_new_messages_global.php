<?php
/**
 * Get new messages globally for auto-popup functionality
 * Returns all new messages for the current user since a specific timestamp
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

if (!isset($_GET['since'])) {
    echo json_encode(['success' => false, 'error' => 'Since timestamp is required']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $since = $_GET['since'];

    // Get all new messages for this user since the specified time
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.body as content,
            m.sent_at as created_at,
            m.sender_id,
            m.thread_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as sender_name,
            u.profile_pic as sender_profile_pic,
            CASE WHEN m.sender_id = ? THEN 1 ELSE 0 END as is_own
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = ?
          AND m.sent_at > ?
          AND m.deleted_by_receiver = 0
        ORDER BY m.sent_at ASC
    ");

    $stmt->execute([$currentUserId, $currentUserId, $since]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format messages for popup chat
    $formattedMessages = [];
    $latestTimestamp = $since;
    
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
            'thread_id' => $message['thread_id'],
            'is_own' => (bool)$message['is_own']
        ];
        
        // Track latest timestamp
        if ($message['created_at'] > $latestTimestamp) {
            $latestTimestamp = $message['created_at'];
        }
    }

    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'count' => count($formattedMessages),
        'since' => $since,
        'latest_timestamp' => $latestTimestamp
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_new_messages_global.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in get_new_messages_global.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>