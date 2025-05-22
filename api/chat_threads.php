<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

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
    $showSpam = isset($_GET['spam']) && $_GET['spam'] === '1';
    
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
            (
                SELECT MAX(sent_at) 
                FROM messages m3
                WHERE m3.thread_id = m.thread_id
                AND m3.deleted_by_sender = 0 
                AND m3.deleted_by_receiver = 0
            ) as last_message_time,
            (
                SELECT COUNT(*) > 0
                FROM user_reports r
                WHERE r.thread_id = m.thread_id
                AND r.admin_response IS NOT NULL
                AND r.notification_sent = TRUE
            ) as has_admin_response
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
        " . ($showArchived ? "
        AND EXISTS (
            SELECT 1 FROM archived_threads at 
            WHERE at.thread_id = m.thread_id 
            AND at.user_id = ?
        )" : ($showSpam ? "
        AND EXISTS (
            SELECT 1 FROM spam_threads st 
            WHERE st.thread_id = m.thread_id 
            AND st.user_id = ?
        )" : "
        AND NOT EXISTS (
            SELECT 1 FROM archived_threads at 
            WHERE at.thread_id = m.thread_id 
            AND at.user_id = ?
        )
        AND NOT EXISTS (
            SELECT 1 FROM spam_threads st 
            WHERE st.thread_id = m.thread_id 
            AND st.user_id = ?
        )")) . "
        ORDER BY last_message_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    if ($showArchived) {
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    } else if ($showSpam) {
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    } else {
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
    }
    
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
