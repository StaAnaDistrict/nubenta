<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User ID not provided']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $otherUserId = intval($_GET['user_id']);
    
    error_log("Checking thread status - Current User ID: $currentUserId, Other User ID: $otherUserId");
    
    // Check if thread exists and get its status for both users
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            m.thread_id as id,
            ucs.is_deleted_for_user
        FROM messages m
        JOIN user_conversation_settings ucs ON m.thread_id = ucs.conversation_id AND ucs.user_id = ?
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        AND (
            (m.sender_id = ? AND m.deleted_by_sender = 0) OR
            (m.receiver_id = ? AND m.deleted_by_receiver = 0)
        )
        LIMIT 1
    ");
    
    error_log("Executing query with parameters: [$currentUserId, $currentUserId, $otherUserId, $otherUserId, $currentUserId, $currentUserId, $currentUserId]");
    $stmt->execute([
        $currentUserId,
        $currentUserId, $otherUserId,
        $otherUserId, $currentUserId,
        $currentUserId,
        $currentUserId
    ]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Query result: " . print_r($thread, true));
    
    if ($thread) {
        // Thread exists, check if it was deleted by current user
        $isDeletedByCurrentUser = $thread['is_deleted_for_user'] ?? false;
        
        error_log("Thread exists. Deleted by current user: " . ($isDeletedByCurrentUser ? 'yes' : 'no'));
        
        echo json_encode([
            'success' => true,
            'thread_id' => $thread['id'],
            'exists' => true,
            'deleted_by_current_user' => $isDeletedByCurrentUser
        ]);
    } else {
        error_log("No thread found between users");
        echo json_encode([
            'success' => true,
            'exists' => false
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in check_thread_status.php: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Error Info: " . print_r($e->errorInfo, true));
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in check_thread_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'General error: ' . $e->getMessage()
    ]);
} 