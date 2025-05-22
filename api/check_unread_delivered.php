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
    // Get messages that need delivery status update
    $stmt = $pdo->prepare("
        SELECT id, delivered_at, read_at
        FROM messages
        WHERE receiver_id = ?
        AND (delivered_at IS NOT NULL OR read_at IS NOT NULL)
        AND updated_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
} catch (PDOException $e) {
    error_log("Error in check_message_delivery.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} 