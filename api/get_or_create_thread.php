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

if (!isset($_GET['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User ID not provided']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $otherUserId = intval($_GET['user_id']);
    
    // Check if a thread already exists between these users
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.created_at,
               u.first_name, u.last_name, u.profile_pic
        FROM threads t
        JOIN thread_participants tp1 ON t.id = tp1.thread_id
        JOIN thread_participants tp2 ON t.id = tp2.thread_id
        JOIN users u ON u.id = ?
        WHERE tp1.user_id = ? AND tp2.user_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$otherUserId, $currentUserId, $otherUserId]);
    $existingThread = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingThread) {
        // Return existing thread
        header('Content-Type: application/json');
        echo json_encode([
            'thread' => [
                'id' => $existingThread['id'],
                'title' => $existingThread['title'],
                'created_at' => $existingThread['created_at'],
                'participant_name' => trim($existingThread['first_name'] . ' ' . $existingThread['last_name']),
                'participant_id' => $otherUserId,
                'profile_pic' => $existingThread['profile_pic'] ?: 'assets/images/default-avatar.png'
            ]
        ]);
        exit;
    }
    
    // Create new thread
    $pdo->beginTransaction();
    
    try {
        // Insert thread with created_by field
        $stmt = $pdo->prepare("
            INSERT INTO threads (title, created_at, created_by)
            VALUES ('', NOW(), ?)
        ");
        $stmt->execute([$currentUserId]);
        $threadId = $pdo->lastInsertId();
        
        // Add participants
        $stmt = $pdo->prepare("
            INSERT INTO thread_participants (thread_id, user_id)
            VALUES (?, ?), (?, ?)
        ");
        $stmt->execute([$threadId, $currentUserId, $threadId, $otherUserId]);
        
        // Get other user's info
        $stmt = $pdo->prepare("
            SELECT first_name, last_name, profile_pic
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify thread was created
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM thread_participants
            WHERE thread_id = ?
        ");
        $stmt->execute([$threadId]);
        $participantCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($participantCount !== 2) {
            throw new Exception("Thread creation verification failed");
        }
        
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
                'profile_pic' => $otherUser['profile_pic'] ?: 'assets/images/default-avatar.png'
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error in get_or_create_thread.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 