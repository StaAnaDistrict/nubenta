<?php
/**
 * get_unread_notification_count.php - Get only the unread notification count
 * This is a lightweight endpoint for navigation badge checking
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user']['id'];

try {
    // Get unread notification count only
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications n
        WHERE n.user_id = ? AND n.is_read = 0
    ");
    
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unreadCount = (int)$result['unread_count'];
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount
    ]);

} catch (PDOException $e) {
    error_log("Error getting unread notification count: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
