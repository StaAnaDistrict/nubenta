<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

session_start();
require_once '../db.php';

// Debug database structure
try {
    $tables = ['chat_threads', 'messages', 'deleted_threads'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Table structure for $table: " . print_r($result, true));
    }
} catch (PDOException $e) {
    error_log("Error checking database structure: " . $e->getMessage());
}

// Function to verify user exists
function verifyUserExists($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error verifying user: " . $e->getMessage());
        return false;
    }
}

// Function to ensure thread exists in chat_threads
function ensureThreadExists($pdo, $thread_id, $user1_id, $user2_id) {
    try {
        // Check if thread exists
        $stmt = $pdo->prepare("SELECT 1 FROM chat_threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        
        if (!$stmt->fetch()) {
            // Create thread if it doesn't exist
            $stmt = $pdo->prepare("
                INSERT INTO chat_threads (id, user1_id, user2_id, created_at) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            return $stmt->execute([$thread_id, $user1_id, $user2_id]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error ensuring thread exists: " . $e->getMessage());
        return false;
    }
}

// Function to mark messages as deleted
function markMessagesAsDeleted($pdo, $thread_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET deleted_by_sender = CASE WHEN sender_id = ? THEN 1 ELSE deleted_by_sender END,
                deleted_by_receiver = CASE WHEN receiver_id = ? THEN 1 ELSE deleted_by_receiver END
            WHERE thread_id = ?
        ");
        return $stmt->execute([$user_id, $user_id, $thread_id]);
    } catch (PDOException $e) {
        error_log("Error marking messages as deleted: " . $e->getMessage());
        return false;
    }
}

// Function to handle deleted_threads table
function handleDeletedThread($pdo, $thread_id, $user_id) {
    try {
        // First verify both user and thread exist
        if (!verifyUserExists($pdo, $user_id)) {
            error_log("User {$user_id} does not exist");
            return false;
        }

        // Check if thread exists in chat_threads
        $stmt = $pdo->prepare("SELECT 1 FROM chat_threads WHERE id = ?");
        $stmt->execute([$thread_id]);
        if (!$stmt->fetch()) {
            error_log("Thread {$thread_id} does not exist in chat_threads");
            return false;
        }

        // Try to insert, if fails due to unique constraint, update instead
        try {
            $stmt = $pdo->prepare("
                INSERT INTO deleted_threads (thread_id, user_id, deleted_at) 
                VALUES (?, ?, CURRENT_TIMESTAMP)
            ");
            return $stmt->execute([$thread_id, $user_id]);
        } catch (PDOException $e) {
            // If duplicate entry, update instead
            if ($e->getCode() == 23000) {
                $stmt = $pdo->prepare("
                    UPDATE deleted_threads 
                    SET deleted_at = CURRENT_TIMESTAMP 
                    WHERE thread_id = ? AND user_id = ?
                ");
                return $stmt->execute([$thread_id, $user_id]);
            }
            throw $e;
        }
    } catch (PDOException $e) {
        error_log("Error handling deleted thread: " . $e->getMessage());
        return false;
    }
}

// Main deletion logic
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];
error_log("User ID from session: " . $user_id);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

$raw_input = file_get_contents('php://input');
error_log("Raw input received: " . $raw_input);

$data = json_decode($raw_input, true);
error_log("Decoded data: " . print_r($data, true));

$thread_id = $data['thread_id'] ?? null;
error_log("Thread ID from request: " . ($thread_id ?? 'null'));

if (!$thread_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Thread ID required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Check if thread exists and user is a participant
    $stmt = $pdo->prepare("
        SELECT DISTINCT sender_id, receiver_id 
        FROM messages 
        WHERE thread_id = ? 
        AND (sender_id = ? OR receiver_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$thread_id, $user_id, $user_id]);
    $participants = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$participants) {
        error_log("Thread existence check failed - Thread ID: $thread_id, User ID: $user_id");
        echo json_encode(['success' => false, 'error' => 'Thread not found or not authorized']);
        exit;
    }

    // Update user_conversation_settings to mark as deleted
    $stmt = $pdo->prepare("
        INSERT INTO user_conversation_settings (user_id, conversation_id, is_deleted_for_user)
        VALUES (?, ?, TRUE)
        ON DUPLICATE KEY UPDATE is_deleted_for_user = TRUE
    ");
    $stmt->execute([$user_id, $thread_id]);

    // Remove from archived and spam settings if present
    $stmt = $pdo->prepare("
        UPDATE user_conversation_settings 
        SET is_archived_for_user = FALSE,
            is_muted = FALSE
        WHERE user_id = ? AND conversation_id = ?
    ");
    $stmt->execute([$user_id, $thread_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error in chat_delete.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage(),
        'details' => null
    ]);
} 