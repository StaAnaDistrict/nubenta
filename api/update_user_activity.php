<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$page = $_POST['page'] ?? '';
$action = $_POST['action'] ?? 'heartbeat'; // heartbeat, page_change, focus, blur

try {
    // Update user's last activity and current page
    $stmt = $pdo->prepare("
        INSERT INTO user_activity (user_id, last_activity, current_page, is_online) 
        VALUES (?, NOW(), ?, 1)
        ON DUPLICATE KEY UPDATE 
        last_activity = NOW(), 
        current_page = VALUES(current_page),
        is_online = 1
    ");
    $stmt->execute([$user_id, $page]);
    
    // If user is on messages.php, mark messages as delivered
    if ($page === 'messages.php' || strpos($page, 'messages') !== false) {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET delivered_at = COALESCE(delivered_at, NOW())
            WHERE receiver_id = ? 
            AND delivered_at IS NULL
        ");
        $stmt->execute([$user_id]);
    }
    
    // If action is focus on messages page, mark messages as read
    if ($action === 'focus' && ($page === 'messages.php' || strpos($page, 'messages') !== false)) {
        $thread_id = $_POST['thread_id'] ?? null;
        if ($thread_id) {
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET read_at = COALESCE(read_at, NOW())
                WHERE receiver_id = ? 
                AND thread_id = ?
                AND read_at IS NULL
            ");
            $stmt->execute([$user_id, $thread_id]);
        }
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>