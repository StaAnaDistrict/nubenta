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

$user_id = $_SESSION['user']['id'];
$target_user_id = $_GET['user_id'] ?? null;

if (!$target_user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing user ID']);
    exit;
}

try {
    // Check for existing thread between users
    $stmt = $pdo->prepare("
        SELECT DISTINCT thread_id
        FROM messages
        WHERE ((sender_id = ? AND receiver_id = ?)
        OR (sender_id = ? AND receiver_id = ?))
        AND deleted_by_sender = 0 
        AND deleted_by_receiver = 0
        LIMIT 1
    ");
    $stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'exists' => !empty($thread),
        'thread_id' => $thread['thread_id'] ?? null
    ]);
} catch (PDOException $e) {
    error_log("Error in check_existing_thread.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} 