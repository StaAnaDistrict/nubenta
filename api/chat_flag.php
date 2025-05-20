<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Revert to turn off display errors

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || !isset($input['thread_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing action or thread_id']);
    exit;
}

$action = $input['action'];
$thread_id = $input['thread_id'];

// Define allowed actions that affect mailbox_flags table
$allowed_thread_actions = ['archive', 'spam', 'delete']; // Treat 'delete' as archive for mailbox_flags

if (!in_array($action, $allowed_thread_actions)) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

try {
    // Determine if it's a direct message and get the peer ID
    $stmt_thread_info = $pdo->prepare("
        SELECT is_group
        FROM threads
        WHERE id = ?
    ");
    $stmt_thread_info->execute([$thread_id]);
    $thread_info = $stmt_thread_info->fetch(PDO::FETCH_ASSOC);

    $peer_id = NULL; // Default to NULL for group chats or if thread info not found
    if ($thread_info && !$thread_info['is_group']) {
        // Get the other participant's ID for direct messages
        $stmt_peer = $pdo->prepare("
            SELECT user_id
            FROM thread_participants
            WHERE thread_id = ? AND user_id != ?
            LIMIT 1
        ");
        $stmt_peer->execute([$thread_id, $user_id]);
        $peer_info = $stmt_peer->fetch(PDO::FETCH_ASSOC);
        if ($peer_info) {
            $peer_id = $peer_info['user_id'];
        }
    }

    // For 'delete' action, we will mark the thread as archived for this user
    $is_archived = ($action === 'archive' || $action === 'delete') ? 1 : 0;
    $is_spam = ($action === 'spam') ? 1 : 0;

    // --- Start: REPLACE INTO Logic ---
    // Use REPLACE INTO to either insert a new row or replace an existing one based on the primary key (user_id, peer_id)
    // This might resolve issues with inserting due to existing PKs, but be aware it replaces the whole row.
    // We include thread_id here, but its role with the composite PK is still a bit ambiguous in the table design.
    // Using 0 as a placeholder for peer_id if NULL, assuming peer_id cannot be NULL per PK.
     $peer_id_insert = ($peer_id !== NULL) ? $peer_id : 0; 

    $stmt_replace = $pdo->prepare("
        REPLACE INTO mailbox_flags (user_id, thread_id, peer_id, is_archived, is_spam)
        VALUES (?, ?, ?, ?, ?)
    ");

    if ($stmt_replace->execute([$user_id, $thread_id, $peer_id_insert, $is_archived, $is_spam])) {
        echo json_encode(['success' => true]);
    } else {
        // If execute fails, get error info and return failure response
        $error_info = $stmt_replace->errorInfo();
        error_log("Error executing chat_flag.php REPLACE INTO statement: " . $error_info[2]);
        echo json_encode(['success' => false, 'error' => 'Statement REPLACE INTO failed: ' . $error_info[2]]);
    }
    // --- End: REPLACE INTO Logic ---

} catch (PDOException $e) {
    error_log("Error in chat_flag.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

exit; // Ensure script exits after outputting JSON
