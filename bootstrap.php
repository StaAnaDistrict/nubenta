<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Update user's last_seen timestamp for online status tracking
try {
    $userId = intval($_SESSION['user']['id']);
    $stmt = $pdo->prepare("
        UPDATE users
        SET last_seen = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$userId]);

    // Also mark any undelivered messages as delivered
    $stmt = $pdo->prepare("
        UPDATE messages m
        JOIN thread_participants tp ON m.thread_id = tp.thread_id
        SET m.delivered_at = IFNULL(m.delivered_at, NOW())
        WHERE tp.user_id = ?
        AND m.sender_id != ?
        AND m.delivered_at IS NULL
    ");
    $stmt->execute([$userId, $userId]);
} catch (PDOException $e) {
    error_log("Error updating user activity: " . $e->getMessage());
}
?>