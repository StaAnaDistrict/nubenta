<?php
/**
 * Get a single post for notification modal display
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get parameters
$postId = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$createdAt = isset($_GET['created_at']) ? $_GET['created_at'] : '';

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Post ID is required']);
    exit();
}

try {
    // Get the specific post with enhanced matching
    $query = "
        SELECT p.*, 
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author,
               u.profile_pic,
               u.gender,
               p.user_id
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ";
    
    $params = [$postId];
    
    // Add additional filters if provided for better matching
    if ($userId) {
        $query .= " AND p.user_id = ?";
        $params[] = $userId;
    }
    
    if ($createdAt) {
        $query .= " AND p.created_at = ?";
        $params[] = $createdAt;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit();
    }
    
    // Check if user can view this post (privacy check)
    $currentUserId = $_SESSION['user']['id'];
    $canView = true;
    
    if ($post['user_id'] != $currentUserId) {
        // Check friendship status for privacy
        if ($post['visibility'] === 'friends') {
            $friendStmt = $pdo->prepare("
                SELECT COUNT(*) as is_friend
                FROM friend_requests
                WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                AND status = 'accepted'
            ");
            $friendStmt->execute([$currentUserId, $post['user_id'], $post['user_id'], $currentUserId]);
            $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
            $canView = $friendship['is_friend'] > 0;
        }
    }
    
    if (!$canView) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }
    
    // Determine profile picture
    $profilePic = 'assets/images/MaleDefaultProfilePicture.png'; // Default
    if (!empty($post['profile_pic'])) {
        $profilePic = 'uploads/profile_pics/' . htmlspecialchars($post['profile_pic']);
    } elseif ($post['gender'] === 'Female') {
        $profilePic = 'assets/images/FemaleDefaultProfilePicture.png';
    }
    
    // Handle media
    $media = null;
    if (!empty($post['media'])) {
        // Check if it's JSON array or single media
        $mediaData = json_decode($post['media'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($mediaData)) {
            // It's a JSON array of media files
            $media = $mediaData;
        } else {
            // It's a single media file
            $media = [$post['media']];
        }
        
        // Ensure proper paths
        $media = array_map(function($item) {
            if (is_string($item) && !str_starts_with($item, 'uploads/')) {
                return 'uploads/' . $item;
            }
            return $item;
        }, $media);
    }
    
    // Format the post
    $formattedPost = [
        'id' => $post['id'],
        'user_id' => $post['user_id'],
        'content' => htmlspecialchars($post['content']),
        'media' => $media ? json_encode($media) : null,
        'author' => htmlspecialchars($post['author']),
        'profile_pic' => $profilePic,
        'created_at' => $post['created_at'],
        'visibility' => $post['visibility'] ?? 'public',
        'is_own_post' => ($post['user_id'] == $currentUserId),
        'is_removed' => (bool)($post['is_removed'] ?? false),
        'removed_reason' => $post['removed_reason'] ?? '',
        'is_flagged' => (bool)($post['is_flagged'] ?? false),
        'flag_reason' => $post['flag_reason'] ?? ''
    ];
    
    echo json_encode([
        'success' => true,
        'post' => $formattedPost
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_single_post.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in get_single_post.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>
