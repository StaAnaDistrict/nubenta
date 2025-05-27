<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get post ID from query string
if (!isset($_GET['post_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Post ID is required']);
    exit();
}

$post_id = intval($_GET['post_id']);
$user_id = $_SESSION['user']['id'];

try {
    // Get media IDs associated with this post from user_media table
    $stmt = $pdo->prepare("
        SELECT id, media_url, media_type, thumbnail_url, created_at
        FROM user_media 
        WHERE post_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$post_id]);
    $media_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get the post to verify access permissions
    $stmt = $pdo->prepare("
        SELECT user_id, visibility, is_removed, is_flagged
        FROM posts 
        WHERE id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit();
    }
    
    // Check if user has permission to view this post
    $canView = false;
    if ($post['user_id'] == $user_id) {
        // Own post
        $canView = true;
    } elseif ($post['visibility'] === 'public') {
        // Public post
        $canView = true;
    } elseif ($post['visibility'] === 'friends') {
        // Check if users are friends
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status = 'accepted'
        ");
        $stmt->execute([$user_id, $post['user_id'], $post['user_id'], $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $canView = $result['is_friend'] > 0;
    }
    
    if (!$canView) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'media_items' => $media_items,
        'post_info' => [
            'id' => $post_id,
            'user_id' => $post['user_id'],
            'visibility' => $post['visibility'],
            'is_removed' => (bool)$post['is_removed'],
            'is_flagged' => (bool)$post['is_flagged']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_post_media_ids.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
