<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['thread_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$thread_id = $input['thread_id'];
$action = $input['action'];

try {
    switch ($action) {
        case 'archive':
            $stmt = $pdo->prepare("UPDATE mailbox_flags SET is_archived = 1 WHERE thread_id = ? AND user_id = ?");
            break;
        case 'spam':
            $stmt = $pdo->prepare("UPDATE mailbox_flags SET is_spam = 1 WHERE thread_id = ? AND user_id = ?");
            break;
        case 'delete':
            $stmt = $pdo->prepare("UPDATE mailbox_flags SET is_deleted = 1 WHERE thread_id = ? AND user_id = ?");
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }

    $stmt->execute([$thread_id, $user_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in chat_flag.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
