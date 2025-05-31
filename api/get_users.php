<?php
/**
 * API: Get Users
 * Returns list of users for testimonial recipient selection
 */

session_start();
require_once '../bootstrap.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$currentUserId = $_SESSION['user']['id'];

try {
    // Get all users except current user
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, profile_pic
        FROM users 
        WHERE id != ? 
        ORDER BY first_name, last_name
        LIMIT 50
    ");
    
    $stmt->execute([$currentUserId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error retrieving users: ' . $e->getMessage()
    ]);
}
?>