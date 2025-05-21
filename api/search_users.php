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

if (!isset($_GET['query'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Search query not provided']);
    exit;
}

try {
    $query = trim($_GET['query']);
    $currentUserId = $_SESSION['user']['id'];
    
    // Search for users by first name, last name, or full name
    // Exclude the current user and show more details
    $stmt = $pdo->prepare("
        SELECT 
            id,
            first_name,
            last_name,
            email,
            profile_pic
        FROM users 
        WHERE id != ? 
        AND (
            first_name LIKE ? 
            OR last_name LIKE ? 
            OR CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?
        )
        LIMIT 10
    ");
    
    $searchTerm = "%$query%";
    $stmt->execute([$currentUserId, $searchTerm, $searchTerm, $searchTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results
    $results = array_map(function($user) {
        $profilePic = $user['profile_pic'];
        if (empty($profilePic)) {
            $profilePic = 'assets/images/default-avatar.png';
        } else {
            // Check if the file exists in the profile_pics directory
            $uploadPath = '../uploads/profile_pics/' . $profilePic;
            if (file_exists($uploadPath)) {
                $profilePic = 'uploads/profile_pics/' . $profilePic;
            } else {
                $profilePic = 'assets/images/default-avatar.png';
            }
        }
        
        return [
            'id' => $user['id'],
            'name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'profile_picture' => $profilePic
        ];
    }, $users);
    
    header('Content-Type: application/json');
    echo json_encode(['users' => $results]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 