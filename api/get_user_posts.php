<?php
/**
 * Get posts for a specific user (for profile Contents section)
 */

session_start();
require_once '../db.php';
require_once '../includes/MediaParser.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get user ID from query parameter
if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit();
}

$profileUserId = intval($_GET['user_id']);
$currentUserId = $_SESSION['user']['id'];

try {
    // Check if the profile user exists
    $userStmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    $userStmt->execute([$profileUserId]);
    $profileUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profileUser) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    // Check friendship status for privacy
    $canViewPosts = true;
    if ($profileUserId != $currentUserId) {
        // Check if they are friends or if posts are public
        $friendStmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status = 'accepted'
        ");
        $friendStmt->execute([$currentUserId, $profileUserId, $profileUserId, $currentUserId]);
        $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
        $areFriends = $friendship['is_friend'] > 0;

        // For now, allow viewing if they are friends or posts are public
        // This can be enhanced with more privacy controls
    }

    if (!$canViewPosts) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }

    // Get user's posts
    $postsQuery = "
        SELECT p.*,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author,
               u.profile_pic,
               u.gender,
               p.user_id
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
        AND (p.visibility = 'public' OR p.visibility = 'friends' OR p.user_id = ?)
        ORDER BY p.created_at DESC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($postsQuery);
    $stmt->execute([$profileUserId, $currentUserId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format posts for display
    $formattedPosts = [];
    foreach ($posts as $post) {
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

        $formattedPosts[] = [
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
    }

    echo json_encode([
        'success' => true,
        'posts' => $formattedPosts,
        'total_count' => count($formattedPosts),
        'profile_user' => [
            'id' => $profileUser['id'],
            'name' => trim($profileUser['first_name'] . ' ' . $profileUser['last_name'])
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_user_posts.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in get_user_posts.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>
