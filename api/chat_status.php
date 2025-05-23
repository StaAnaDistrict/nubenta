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

    // Update user's last_activity if provided
    if (isset($data['last_activity'])) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_activity = FROM_UNIXTIME(?)
            WHERE id = ?
        ");
        $stmt->execute([$data['last_activity'] / 1000, $userId]);
    }

    // If checking for unread messages (from navigation)
    if (isset($data['check_unread']) && $data['check_unread']) {
        // Get unread message count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            WHERE tp.user_id = ?
            AND m.sender_id != ?
            AND m.read_at IS NULL
        ");
        $stmt->execute([$userId, $userId]);
        $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];

        // Mark messages as delivered for this user
        $stmt = $pdo->prepare("
            UPDATE messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            SET m.delivered_at = IFNULL(m.delivered_at, NOW())
            WHERE tp.user_id = ?
            AND m.sender_id != ?
            AND m.delivered_at IS NULL
        ");
        $stmt->execute([$userId, $userId]);

        echo json_encode([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
        exit;
    }

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
    if (isset($data['action']) && $data['action'] === 'read' && isset($data['ids']) && is_array($data['ids'])) {
        $messageIdsToMarkRead = $data['ids'];
        $ids_placeholders = implode(',', array_fill(0, count($messageIdsToMarkRead), '?'));
        error_log("chat_status.php: Read update - Message IDs: " . print_r($messageIdsToMarkRead, true) . ", User ID: " . $userId);
        
        // Update both delivered_at and read_at for messages
        $stmt = $pdo->prepare("
            UPDATE messages m
            JOIN thread_participants tp ON m.thread_id = tp.thread_id
            SET m.delivered_at = IFNULL(m.delivered_at, NOW()),
                m.read_at = NOW()
            WHERE m.id IN (" . $ids_placeholders . ")
            AND tp.user_id = ?
            AND m.sender_id != ?
            AND m.read_at IS NULL
        ");
        $params = array_merge($messageIdsToMarkRead, [$userId, $userId]);
        $stmt->execute($params);
        error_log("chat_status.php: Read update - Rows affected: " . $stmt->rowCount());
        
        // Return updated statuses
        $stmt = $pdo->prepare("
            SELECT id, delivered_at, read_at
            FROM messages
            WHERE id IN (" . $ids_placeholders . ")
        ");
        $stmt->execute($messageIdsToMarkRead);
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'statuses' => $statuses]);
        exit;
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
        
        // Return updated statuses after marking as delivered
        $stmt = $pdo->prepare("
            SELECT id, delivered_at, read_at
            FROM messages
            WHERE id IN (" . $ids_placeholders . ")
        ");
        $stmt->execute($messageIdsToMarkDelivered);
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'statuses' => $statuses]);
    }
    else {
        // Default behavior: return statuses (this is for the sender to check status)
        echo json_encode(['success' => true, 'statuses' => $statuses]);
    }

} catch (PDOException $e) {
    error_log("Error in chat_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage(), 'statuses' => []]);
}
