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
$threadId = intval($data['thread_id'] ?? 0);
$action = $data['action'] ?? '';

if (!$threadId || !$action) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing thread_id or action']);
    exit;
}

try {
    $userId = $_SESSION['user']['id'];
    
    // Verify user is part of the thread
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM thread_participants 
        WHERE thread_id = ? AND user_id = ?
    ");
    $stmt->execute([$threadId, $userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] === 0) {
        throw new Exception('Not authorized to modify this thread');
    }
    
    switch ($action) {
        case 'archive':
            // Check if already archived
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM archived_threads 
                WHERE thread_id = ? AND user_id = ?
            ");
            $stmt->execute([$threadId, $userId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] === 0) {
                // Archive the thread
                $stmt = $pdo->prepare("
                    INSERT INTO archived_threads (thread_id, user_id, archived_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$threadId, $userId]);
            }
            break;
            
        case 'unarchive':
            // Remove from archives
            $stmt = $pdo->prepare("
                DELETE FROM archived_threads 
                WHERE thread_id = ? AND user_id = ?
            ");
            $stmt->execute([$threadId, $userId]);
            break;
            
        case 'spam':
            // Mark as spam
            $stmt = $pdo->prepare("
                INSERT INTO spam_threads (thread_id, user_id, marked_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE marked_at = NOW()
            ");
            $stmt->execute([$threadId, $userId]);
            break;
            
        case 'delete':
            // Soft delete the thread for this user
            $stmt = $pdo->prepare("
                UPDATE thread_participants 
                SET deleted_at = NOW() 
                WHERE thread_id = ? AND user_id = ?
            ");
            $stmt->execute([$threadId, $userId]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error in chat_flag.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
