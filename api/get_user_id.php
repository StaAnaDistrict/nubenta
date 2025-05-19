<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if (!isset($_GET['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Username not provided']);
    exit;
}

try {
    $username = trim($_GET['username']);
    
    // Search for user by username (email) or full name
    $stmt = $pdo->prepare("
        SELECT id 
        FROM users 
        WHERE email = ? 
        OR CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?
        LIMIT 1
    ");
    
    $stmt->execute([$username, "%$username%"]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    if ($user) {
        echo json_encode(['user_id' => $user['id']]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 