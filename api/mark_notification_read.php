<?php
/**
 * Mark notification(s) as read
 * Supports marking individual notifications or all notifications
 */

header('Content-Type: application/json');

session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user']['id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['notification_id'])) {
            // Mark specific notification as read
            $notificationId = intval($data['notification_id']);
            
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = TRUE, updated_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            
        } elseif (isset($data['mark_all'])) {
            // Mark all notifications as read
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = TRUE, updated_at = NOW() 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        // Mark specific notification as read via GET (for direct links)
        $notificationId = intval($_GET['id']);
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE, updated_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    error_log("Error in mark_notification_read.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
