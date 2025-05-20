<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    // Query to count messages that have been delivered but not read
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM messages
        WHERE receiver_id = ?
        AND delivered_at IS NOT NULL
        AND read_at IS NULL
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = (int)$result['count'];
    
    echo json_encode([
        'success' => true,
        'has_unread_delivered' => $count > 0,
        'count' => $count
    ]);
    
} catch (PDOException $e) {
    error_log("Error in check_unread_delivered.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 