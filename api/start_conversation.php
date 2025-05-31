<?php
/**
 * Simplified API to start a conversation with a user
 * Handles all the complex logic of checking existing threads, creating new ones, etc.
 * Returns a thread_id that can be used to redirect to messages.php
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User ID not provided']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $otherUserId = intval($input['user_id']);
    
    error_log("Starting conversation - Current User ID: $currentUserId, Other User ID: $otherUserId");
    
    // Check if the other user exists
    $userCheck = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    $userCheck->execute([$otherUserId]);
    $otherUser = $userCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$otherUser) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Look for existing thread between these two users using the messages table
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.thread_id
        FROM messages m
        WHERE ((m.sender_id = ? AND m.receiver_id = ?)
        OR (m.sender_id = ? AND m.receiver_id = ?))
        AND m.deleted_by_sender = 0 
        AND m.deleted_by_receiver = 0
        ORDER BY m.sent_at DESC
        LIMIT 1
    ");
    
    $stmt->execute([$currentUserId, $otherUserId, $otherUserId, $currentUserId]);
    $existingThread = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingThread) {
        // Check if user_conversation_settings table exists
        $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'user_conversation_settings'");
        $tableCheck->execute();
        $tableExists = $tableCheck->fetch();
        
        if ($tableExists) {
            // Thread exists, check if it was deleted by current user
            $settingsCheck = $pdo->prepare("
                SELECT is_deleted_for_user 
                FROM user_conversation_settings 
                WHERE conversation_id = ? AND user_id = ?
            ");
            $settingsCheck->execute([$existingThread['thread_id'], $currentUserId]);
            $settings = $settingsCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($settings && $settings['is_deleted_for_user']) {
                // Thread was deleted by current user, restore it
                $restoreStmt = $pdo->prepare("
                    UPDATE user_conversation_settings 
                    SET is_deleted_for_user = 0 
                    WHERE conversation_id = ? AND user_id = ?
                ");
                $restoreStmt->execute([$existingThread['thread_id'], $currentUserId]);
                error_log("Restored deleted thread for user: " . $existingThread['thread_id']);
            }
        }
        
        error_log("Using existing thread: " . $existingThread['thread_id']);
        echo json_encode([
            'success' => true,
            'thread_id' => $existingThread['thread_id'],
            'action' => 'existing_thread'
        ]);
        exit;
    }
    
    // No existing thread, create a new one
    $pdo->beginTransaction();
    
    try {
        // Create new thread in chat_threads table
        $createThread = $pdo->prepare("
            INSERT INTO chat_threads (type, created_at)
            VALUES ('one_on_one', NOW())
        ");
        $createThread->execute();
        $threadId = $pdo->lastInsertId();
        
        // Check if user_conversation_settings table exists
        $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'user_conversation_settings'");
        $tableCheck->execute();
        $tableExists = $tableCheck->fetch();
        
        if ($tableExists) {
            // Create conversation settings for both users
            $createSettings = $pdo->prepare("
                INSERT INTO user_conversation_settings (conversation_id, user_id, is_deleted_for_user)
                VALUES (?, ?, 0), (?, ?, 0)
                ON DUPLICATE KEY UPDATE is_deleted_for_user = 0
            ");
            $createSettings->execute([$threadId, $currentUserId, $threadId, $otherUserId]);
        }
        
        $pdo->commit();
        
        error_log("Created new thread: $threadId");
        echo json_encode([
            'success' => true,
            'thread_id' => $threadId,
            'action' => 'new_thread'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in start_conversation.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in start_conversation.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
