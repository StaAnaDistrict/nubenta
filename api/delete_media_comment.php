<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user']['id'];

// Get POST data
$comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;

// Validate input
if ($comment_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid comment ID']);
    exit();
}

try {
    // Check if comment exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT mc.*, um.user_id as media_owner_id
        FROM media_comments mc
        JOIN user_media um ON mc.media_id = um.id
        WHERE mc.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit();
    }
    
    // Check if user can delete this comment (own comment or media owner or admin)
    $canDelete = false;
    if ($comment['user_id'] == $user_id) {
        // Own comment
        $canDelete = true;
    } elseif ($comment['media_owner_id'] == $user_id) {
        // Media owner can delete comments on their media
        $canDelete = true;
    } elseif (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
        // Admin can delete any comment
        $canDelete = true;
    }
    
    if (!$canDelete) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit();
    }
    
    // Delete the comment
    $stmt = $pdo->prepare("DELETE FROM media_comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to delete comment'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error in delete_media_comment.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
