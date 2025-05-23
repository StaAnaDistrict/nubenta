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

if (!isset($_GET['thread_id'])) {
    echo json_encode(['success' => false, 'error' => 'Thread ID not provided']);
    exit;
}

try {
    $currentUserId = $_SESSION['user']['id'];
    $threadId = intval($_GET['thread_id']);

    // Find the other participant's information in the thread
    // We join messages and users to get the participant's name
    $stmt = $pdo->prepare("
        SELECT
            u.id AS participant_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS participant_name
        FROM messages m
        JOIN users u ON (m.sender_id = u.id AND m.sender_id != ?) OR (m.receiver_id = u.id AND m.receiver_id != ?)
        WHERE m.thread_id = ?
        LIMIT 1
    ");

    $stmt->execute([$currentUserId, $currentUserId, $threadId]);
    $participantInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($participantInfo) {
        echo json_encode([
            'success' => true,
            'participant' => [
                'id' => $participantInfo['participant_id'],
                'name' => $participantInfo['participant_name']
            ]
        ]);
    } else {
        // This case might happen if the thread exists but has no messages (unlikely but possible)
        // Or if the thread_id is invalid
        echo json_encode([
            'success' => false,
            'error' => 'Participant not found for this thread or invalid thread ID.'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in get_thread_participant_info.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in get_thread_participant_info.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'General error: ' . $e->getMessage()
    ]);
} 