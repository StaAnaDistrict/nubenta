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

$user_id = $_SESSION['user']['id'];
$friend_id = intval($_POST['id']);

try {
    // Delete the friend relationship (in both directions)
    $stmt = $pdo->prepare(
        "DELETE FROM friend_requests 
         WHERE ((sender_id = ? AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = ?))
         AND status = 'accepted'"
    );
    $stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    error_log("Unfriend error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
