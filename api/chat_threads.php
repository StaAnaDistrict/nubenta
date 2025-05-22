<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', __DIR__ . '/../php_error.log'); // Set error log file path

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    $userId = $_SESSION['user']['id'];
    $showArchived = isset($_GET['archived']) && $_GET['archived'] === '1';
    
    // Base query for threads
    $sql = "
        SELECT DISTINCT 
            m.thread_id as id,
            u.id as participant_id,
            CONCAT_WS(' ', u.first_name, u.last_name) as participant_name,
            u.profile_pic,
            (SELECT COUNT(*) FROM messages m2 
             WHERE m2.thread_id = m.thread_id 
             AND m2.read_at IS NULL 
             AND m2.sender_id != ?) as unread_count,
            (SELECT MAX(sent_at) FROM messages 
             WHERE thread_id = m.thread_id) as last_message_time
        FROM messages m
        JOIN users u ON (
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id = u.id
                ELSE m.sender_id = u.id
            END
        )
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
        AND m.deleted_by_sender = 0 
        AND m.deleted_by_receiver = 0
        AND NOT EXISTS (
            SELECT 1 FROM archived_threads at 
            WHERE at.thread_id = m.thread_id 
            AND at.user_id = ?
        )
        ORDER BY last_message_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process threads to handle profile pictures
    foreach ($threads as &$thread) {
        if ($thread['profile_pic']) {
            $thread['profile_pic'] = 'uploads/profile_pics/' . $thread['profile_pic'];
        } else {
            $thread['profile_pic'] = 'assets/images/default-avatar.png';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($threads);

} catch (Exception $e) {
    error_log("Error in chat_threads.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
