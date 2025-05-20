<?php
// api/chat_status.php
// DEBUG_TICK_MARK_ISSUE
require_once '../bootstrap.php';
header('Content-Type: application/json');

$userId = intval($_SESSION['user']['id'] ?? 0);
$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (!$userId || empty($ids)) { 
    echo json_encode(['success' => true, 'statuses' => []]);
    exit; 
}

try {
    error_log("chat_status.php: User ID: " . $userId . ", Input IDs: " . print_r($ids, true));

    // First, mark messages as delivered if they're not already
    if (!empty($ids)) {
        $ids_placeholders = implode(',', array_fill(0, count($ids), '?'));
        error_log("chat_status.php: Delivered update - IDs Placeholders: " . $ids_placeholders);
        error_log("chat_status.php: Delivered update - SQL: UPDATE messages m JOIN thread_participants tp ON m.thread_id = tp.thread_id SET delivered_at = CASE WHEN delivered_at IS NULL THEN NOW() ELSE delivered_at END WHERE m.id IN (" . $ids_placeholders . ") AND tp.user_id = ? AND m.sender_id != ?");
        
        // Log message thread IDs and user ID for debugging why updates are failing
        try {
            $stmt_debug = $pdo->prepare("SELECT id, thread_id, sender_id FROM messages WHERE id IN (" . $ids_placeholders . ")");
            $stmt_debug->execute($ids);
            $debug_messages = $stmt_debug->fetchAll(PDO::FETCH_ASSOC);
            error_log("chat_status.php: Debug Messages for Delivered Update (User ID: " . $userId . "): " . print_r($debug_messages, true));
        } catch (PDOException $e) {
            error_log("chat_status.php: Debug logging error: " . $e->getMessage());
        }

        $stmt = $pdo->prepare("
            UPDATE messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            SET delivered_at = CASE 
                WHEN delivered_at IS NULL THEN NOW() 
                ELSE delivered_at 
            END
            WHERE m.id IN (" . $ids_placeholders . ")
            AND tp.user_id = ?
            AND m.sender_id != ?
        ");
        $params = array_merge($ids, [$userId, $userId]);
        error_log("chat_status.php: Delivered update - Params: " . print_r($params, true));
        $stmt->execute($params);
        error_log("chat_status.php: Delivered update - Rows affected: " . $stmt->rowCount());
    }

    // Then get the current status of all messages
    $stmt = $pdo->prepare("
        SELECT id, delivered_at, read_at
        FROM messages
        WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
    ");
    $stmt->execute($ids);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("chat_status.php: Fetched statuses: " . print_r($statuses, true));

    // Check for 'read' action
    if (isset($data['action']) && $data['action'] === 'read' && isset($data['message_id'])) {
        $messageIdToMarkRead = $data['message_id'];
        error_log("chat_status.php: Read update - Message ID: " . $messageIdToMarkRead . ", User ID: " . $userId);
        error_log("chat_status.php: Read update - SQL: UPDATE messages m JOIN thread_participants tp ON m.thread_id = tp.thread_id SET m.read_at = NOW() WHERE m.id = ? AND tp.user_id = ? AND m.sender_id != ? AND m.read_at IS NULL");
        // Ensure the message belongs to a thread the user is in before marking as read
        $stmt = $pdo->prepare("
            UPDATE messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            SET m.read_at = NOW()
            WHERE m.id = ? AND tp.user_id = ? AND m.sender_id != ? AND m.read_at IS NULL
        ");
        $stmt->execute([$messageIdToMarkRead, $userId, $userId]);
        error_log("chat_status.php: Read update - Rows affected: " . $stmt->rowCount());
        echo json_encode(['success' => true]);
    }
    // Check for 'delivered' action
    else if (isset($data['action']) && $data['action'] === 'delivered' && isset($data['ids']) && is_array($data['ids']) && !empty($data['ids'])) {
        $messageIdsToMarkDelivered = $data['ids'];
        $ids_placeholders = implode(',', array_fill(0, count($messageIdsToMarkDelivered), '?'));
        error_log("chat_status.php: Delivered update (from recipient) - Message IDs: " . print_r($messageIdsToMarkDelivered, true) . ", User ID: " . $userId);
        error_log("chat_status.php: Delivered update (from recipient) - SQL: UPDATE messages m JOIN thread_participants tp ON m.thread_id = tp.thread_id SET m.delivered_at = IFNULL(m.delivered_at, NOW()) WHERE m.id IN (" . $ids_placeholders . ") AND tp.user_id = ? AND m.sender_id != ? AND m.delivered_at IS NULL");
        
        // Update delivered_at for messages received by the current user
        $stmt = $pdo->prepare("
            UPDATE messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            SET m.delivered_at = IFNULL(m.delivered_at, NOW())
            WHERE m.id IN (" . $ids_placeholders . ")
            AND tp.user_id = ? /* Ensure the current user is a participant in the thread */
            AND m.sender_id != ? /* Ensure the message is not sent by the current user */
            AND m.delivered_at IS NULL /* Only update if not already delivered */
        ");
        $params = array_merge($messageIdsToMarkDelivered, [$userId, $userId]);
        $stmt->execute($params);
        error_log("chat_status.php: Delivered update (from recipient) - Rows affected: " . $stmt->rowCount());
        echo json_encode(['success' => true]);
    }
    else {
        // Default behavior: return statuses (this is for the sender to check status)
        echo json_encode(['success' => true, 'statuses' => $statuses]);
    }

} catch (PDOException $e) {
    error_log("Error in chat_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage(), 'statuses' => []]);
}
