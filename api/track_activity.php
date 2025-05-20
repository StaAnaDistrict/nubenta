<?php
require_once '../bootstrap.php';
header('Content-Type: application/json');

$userId = intval($_SESSION['user']['id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    error_log("track_activity.php: Starting activity tracking for user ID: " . $userId);

    // First update user's last_activity
    $stmt = $pdo->prepare("
        UPDATE users 
        SET last_activity = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    error_log("track_activity.php: Updated last_activity for user " . $userId . ", rows affected: " . $stmt->rowCount());

    // Debug: Check for undelivered messages
    $stmt = $pdo->prepare("
        SELECT id, thread_id, sender_id, receiver_id, delivered_at
        FROM messages
        WHERE receiver_id = ?
        AND delivered_at IS NULL
    ");
    $stmt->execute([$userId]);
    $undelivered = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("track_activity.php: Found " . count($undelivered) . " undelivered messages: " . print_r($undelivered, true));

    // Then mark any undelivered messages as delivered
    $stmt = $pdo->prepare("
        UPDATE messages
        SET delivered_at = NOW()
        WHERE receiver_id = ?
        AND delivered_at IS NULL
    ");
    $stmt->execute([$userId]);
    $rowsAffected = $stmt->rowCount();
    error_log("track_activity.php: Marked messages as delivered, rows affected: " . $rowsAffected);

    // Verify the update
    if ($rowsAffected > 0) {
        $stmt = $pdo->prepare("
            SELECT id, thread_id, sender_id, receiver_id, delivered_at
            FROM messages
            WHERE receiver_id = ?
            AND delivered_at IS NOT NULL
            ORDER BY delivered_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $recentlyDelivered = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("track_activity.php: Recently delivered messages: " . print_r($recentlyDelivered, true));
    }

    // Get count of unread messages
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages
        WHERE receiver_id = ?
        AND read_at IS NULL
    ");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    error_log("track_activity.php: Unread message count: " . $unreadCount);

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'debug' => [
            'undelivered_count' => count($undelivered),
            'rows_affected' => $rowsAffected
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error in track_activity.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} 