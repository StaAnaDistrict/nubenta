<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Revert to turn off display errors to avoid corrupting JSON
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', __DIR__ . '/../php_error.log'); // Set error log file path

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];

/* ----------------------------------------------------------------------
 *  GET  – return list of threads for the sidebar
 * -------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.title,
                CASE 
                    WHEN t.is_group = 0 THEN (
                        SELECT u.full_name 
                        FROM thread_participants tp 
                        JOIN users u ON tp.user_id = u.id 
                        WHERE tp.thread_id = t.id AND tp.user_id != ?
                        LIMIT 1
                    )
                    ELSE t.title 
                END as participant_name,
                CASE 
                    WHEN t.is_group = 0 THEN (
                        SELECT tp.user_id 
                        FROM thread_participants tp 
                        WHERE tp.thread_id = t.id AND tp.user_id != ?
                        LIMIT 1
                    )
                    ELSE NULL 
                END as participant_id
            FROM threads t
            JOIN thread_participants tp ON t.id = tp.thread_id
            WHERE tp.user_id = ?
            AND NOT EXISTS (
                SELECT 1 FROM mailbox_flags mf 
                WHERE mf.thread_id = t.id 
                AND mf.user_id = ? 
                AND (mf.is_archived = 1 OR mf.is_spam = 1)
            )
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($threads);
    } catch (PDOException $e) {
        error_log("Error in chat_threads.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/* ----------------------------------------------------------------------
 *  POST – create new thread
 * -------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_group = isset($_POST['is_group']) ? (int)$_POST['is_group'] : 0;
    $members = isset($_POST['members']) ? json_decode($_POST['members'], true) : [];

    if (!$is_group && count($members) !== 1) {
        echo json_encode(['success' => false, 'error' => 'Direct messages must have exactly one recipient']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Create thread
        $stmt = $pdo->prepare("
            INSERT INTO threads (title, is_group, created_at, updated_at) 
            VALUES (?, ?, NOW(), NOW())
        ");
        $title = $is_group ? $_POST['title'] ?? 'New Group' : '';
        $stmt->execute([$title, $is_group]);
        $thread_id = $pdo->lastInsertId();

        // Add current user as participant
        $stmt = $pdo->prepare("
            INSERT INTO thread_participants (thread_id, user_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$thread_id, $user_id]);

        // Add other participants
        foreach ($members as $member_id) {
            $stmt->execute([$thread_id, $member_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'thread_id' => $thread_id]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error in chat_threads.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/* anything else = 405 */
echo json_encode(['error' => 'Invalid request method']);
exit;
