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

$data = json_decode(file_get_contents('php://input'), true);
$thread_id = $data['thread_id'] ?? null;
$action = $data['action'] ?? null;
$user_id = $_SESSION['user']['id'];

if (!$thread_id || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

try {
    // First, ensure user is part of the thread
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE thread_id = ? AND (sender_id = ? OR receiver_id = ?)
    ");
    $stmt->execute([$thread_id, $user_id, $user_id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] === 0) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to modify this thread']);
        exit;
    }

    switch ($action) {
        case 'archive':
            // Insert into archived_threads if not already archived
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO archived_threads (thread_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$thread_id, $user_id]);
            break;
            
        case 'unarchive':
            // Remove from archived_threads
            $stmt = $pdo->prepare("
                DELETE FROM archived_threads 
                WHERE thread_id = ? AND user_id = ?
            ");
            $stmt->execute([$thread_id, $user_id]);
            break;
            
        case 'spam':
            // Insert into spam_threads if not already marked as spam
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO spam_threads (thread_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$thread_id, $user_id]);
            break;
            
        case 'unspam':
            // Remove from spam_threads
            $stmt = $pdo->prepare("
                DELETE FROM spam_threads 
                WHERE thread_id = ? AND user_id = ?
            ");
            $stmt->execute([$thread_id, $user_id]);
            break;
            
        case 'delete':
            // Mark messages as deleted for this user
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET deleted_by_receiver = 1 
                WHERE thread_id = ? AND receiver_id = ?
            ");
            $stmt->execute([$thread_id, $user_id]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in chat_flag.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
