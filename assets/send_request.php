<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['id'])) {
    echo json_encode(['ok' => false, 'error' => 'No user ID provided']);
    exit;
}

$sender_id = $_SESSION['user']['id'];
$receiver_id = intval($_POST['id']);

// Don't allow sending request to yourself
if ($sender_id === $receiver_id) {
    echo json_encode(['ok' => false, 'error' => 'Cannot send friend request to yourself']);
    exit;
}

try {
    // Check if a request already exists
    $checkStmt = $pdo->prepare(
        "SELECT id, status 
         FROM friend_requests 
         WHERE (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)
         LIMIT 1"
    );
    $checkStmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ($existing['status'] === 'pending') {
            echo json_encode(['ok' => false, 'error' => 'Friend request already pending']);
        } elseif ($existing['status'] === 'accepted') {
            echo json_encode(['ok' => false, 'error' => 'Already friends']);
        }
        exit;
    }

    // Insert new friend request
    $stmt = $pdo->prepare(
        "INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) 
         VALUES (?, ?, 'pending', CURRENT_TIMESTAMP)"
    );
    $stmt->execute([$sender_id, $receiver_id]);

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    error_log("Friend request error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
