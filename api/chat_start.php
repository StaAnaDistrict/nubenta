<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User ID not provided']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $otherUserId = $data['user_id'];
    
    // Check if a thread already exists between these users
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.thread_id
        FROM messages m
        WHERE ((m.sender_id = ? AND m.receiver_id = ?)
        OR (m.sender_id = ? AND m.receiver_id = ?))
        AND m.deleted_by_sender = 0 
        AND m.deleted_by_receiver = 0
        LIMIT 1
    ");
    $stmt->execute([$currentUserId, $otherUserId, $otherUserId, $currentUserId]);
    $existingThread = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingThread) {
        // Get other user's info
        $stmt = $pdo->prepare("
            SELECT first_name, last_name, profile_pic
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return existing thread
        header('Content-Type: application/json');
        echo json_encode([
            'thread' => [
                'id' => $existingThread['thread_id'],
                'title' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'participant_name' => trim($otherUser['first_name'] . ' ' . $otherUser['last_name']),
                'participant_id' => $otherUserId,
                'profile_pic' => $otherUser['profile_pic'] ? 'uploads/profile_pics/' . $otherUser['profile_pic'] : 'assets/images/default-avatar.png'
            ]
        ]);
        exit;
    }
    
    // Create new thread
    $pdo->beginTransaction();
    
    try {
        // Insert thread
        $stmt = $pdo->prepare("
            INSERT INTO chat_threads (type, created_at)
            VALUES ('one_on_one', NOW())
        ");
        $stmt->execute();
        $threadId = $pdo->lastInsertId();
        
        // Check if user_conversation_settings table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_conversation_settings'");
        $stmt->execute();
        $tableExists = $stmt->fetch();
        
        if ($tableExists) {
            // Create user conversation settings for both users
            $stmt = $pdo->prepare("
                INSERT INTO user_conversation_settings 
                (user_id, conversation_id, is_deleted_for_user) 
                VALUES (?, ?, FALSE), (?, ?, FALSE)
                ON DUPLICATE KEY UPDATE is_deleted_for_user = FALSE
            ");
            $stmt->execute([$currentUserId, $threadId, $otherUserId, $threadId]);
        }
        
        // Get other user's info
        $stmt = $pdo->prepare("
            SELECT first_name, last_name, profile_pic
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->commit();
        
        // Return the new thread
        header('Content-Type: application/json');
        echo json_encode([
            'thread' => [
                'id' => $threadId,
                'title' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'participant_name' => trim($otherUser['first_name'] . ' ' . $otherUser['last_name']),
                'participant_id' => $otherUserId,
                'profile_pic' => $otherUser['profile_pic'] ? 'uploads/profile_pics/' . $otherUser['profile_pic'] : 'assets/images/default-avatar.png'
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error in chat_start.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 