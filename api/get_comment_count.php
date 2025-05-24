<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if ($post_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
    exit;
}

try {
    // Get comment count for the post
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM comments
        WHERE post_id = ?
    ");
    $stmt->execute([$post_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => intval($result['count'])
    ]);
} catch (PDOException $e) {
    error_log("Error in get_comment_count.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
