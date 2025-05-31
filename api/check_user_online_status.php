<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Check if user is online (active within last 5 minutes)
    $stmt = $pdo->prepare("
        SELECT 
            user_id,
            last_activity,
            current_page,
            is_online,
            CASE 
                WHEN last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 
                ELSE 0 
            END as is_currently_online,
            CASE 
                WHEN current_page LIKE '%messages%' AND last_activity > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 
                ELSE 0 
            END as is_on_messages_page
        FROM user_activity 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$activity) {
        echo json_encode([
            'success' => true,
            'is_online' => false,
            'is_on_messages_page' => false,
            'last_activity' => null
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'is_online' => (bool)$activity['is_currently_online'],
            'is_on_messages_page' => (bool)$activity['is_on_messages_page'],
            'last_activity' => $activity['last_activity'],
            'current_page' => $activity['current_page']
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>