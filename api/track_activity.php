<?php
/**
 * Track user activity for online status and message delivery
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user']['id'];
$currentPage = $_SERVER['HTTP_REFERER'] ?? '';

try {
    // Update or insert user activity
    $stmt = $pdo->prepare("
        INSERT INTO user_activity (user_id, last_activity, current_page, is_online)
        VALUES (?, NOW(), ?, 1)
        ON DUPLICATE KEY UPDATE
        last_activity = NOW(),
        current_page = VALUES(current_page),
        is_online = 1
    ");
    $stmt->execute([$userId, $currentPage]);
    
    // Get unread message count for navigation badge
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages
        WHERE receiver_id = ? 
        AND read_at IS NULL 
        AND deleted_by_receiver = 0
    ");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetchColumn() ?: 0;
    
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unreadCount,
        'debug' => [
            'user_id' => $userId,
            'current_page' => $currentPage,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error tracking activity: " . $e->getMessage());
    
    // If user_activity table doesn't exist, still return unread count
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count
            FROM messages
            WHERE receiver_id = ? 
            AND read_at IS NULL 
            AND deleted_by_receiver = 0
        ");
        $stmt->execute([$userId]);
        $unreadCount = $stmt->fetchColumn() ?: 0;
        
        echo json_encode([
            'success' => true,
            'unread_count' => (int)$unreadCount,
            'warning' => 'Activity tracking unavailable - run setup_user_activity_table.php'
        ]);
    } catch (PDOException $e2) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e2->getMessage()
        ]);
    }
}
?>