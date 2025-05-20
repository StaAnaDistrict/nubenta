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
$data = json_decode(file_get_contents('php://input'), true);
$message_id = $data['message_id'] ?? null;

if (!$message_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Message ID is required']);
    exit;
}

try {
    // First verify that the message belongs to the user
    $check_stmt = $pdo->prepare("
        SELECT id FROM messages 
        WHERE id = ? AND receiver_id = ?
    ");
    $check_stmt->execute([$message_id, $user_id]);
    
    if (!$check_stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Message not found or access denied']);
        exit;
    }
    
    // Delete the message
    $stmt = $pdo->prepare("
        DELETE FROM messages 
        WHERE id = ? AND receiver_id = ?
    ");
    
    $stmt->execute([$message_id, $user_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Error in delete_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 