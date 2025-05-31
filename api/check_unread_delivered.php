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

try {
    // Get count of unread messages for this user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages
        WHERE receiver_id = ?
        AND read_at IS NULL
        AND deleted_by_receiver = 0
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unread_count = $result['unread_count'] ?? 0;
    $has_unread = $unread_count > 0;

    echo json_encode([
        'success' => true,
        'has_unread_delivered' => $has_unread,
        'count' => $unread_count
    ]);
} catch (PDOException $e) {
    error_log("Error in check_unread_delivered.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} 