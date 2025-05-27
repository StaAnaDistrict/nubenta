<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get media ID from query string
if (!isset($_GET['media_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Media ID is required']);
    exit();
}

$media_id = intval($_GET['media_id']);
$user_id = $_SESSION['user']['id'];
$count_only = isset($_GET['count_only']) && $_GET['count_only'] === '1';

try {
    // Check if media exists and user has permission to view it
    $stmt = $pdo->prepare("
        SELECT um.*, p.user_id as post_user_id, p.visibility
        FROM user_media um
        LEFT JOIN posts p ON um.post_id = p.id
        WHERE um.id = ?
    ");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Media not found']);
        exit();
    }

    // Check permission (simplified - can be expanded)
    $canView = true;
    if ($media['post_user_id'] && $media['visibility'] === 'friends') {
        // Check if users are friends
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status = 'accepted'
        ");
        $stmt->execute([$user_id, $media['post_user_id'], $media['post_user_id'], $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $canView = $result['is_friend'] > 0 || $media['post_user_id'] == $user_id;
    }

    if (!$canView) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }

    // If we only need the count, use a simpler query
    if ($count_only) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM media_comments WHERE media_id = ?");
        $stmt->execute([$media_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'count' => intval($result['count']),
            'comments' => [] // Empty array since we only need the count
        ]);
        exit();
    }

    // Get full comments for media
    $stmt = $pdo->prepare("
        SELECT mc.*,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
               u.profile_pic,
               u.gender
        FROM media_comments mc
        JOIN users u ON mc.user_id = u.id
        WHERE mc.media_id = ?
        ORDER BY mc.created_at ASC
    ");
    $stmt->execute([$media_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format comments for response
    $formatted_comments = [];
    foreach ($comments as $comment) {
        // Determine profile picture with proper path
        $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
        $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

        if (!empty($comment['profile_pic'])) {
            // User has uploaded profile picture
            $profilePic = 'uploads/profile_pics/' . htmlspecialchars($comment['profile_pic']);
        } else {
            // Use default based on gender
            $profilePic = ($comment['gender'] === 'Female') ? $defaultFemalePic : $defaultMalePic;
        }

        $formatted_comments[] = [
            'id' => $comment['id'],
            'content' => htmlspecialchars($comment['content']),
            'author' => htmlspecialchars($comment['author_name']),
            'author_id' => $comment['user_id'], // Add user ID for profile links
            'profile_pic' => $profilePic,
            'created_at' => $comment['created_at'],
            'is_own_comment' => ($comment['user_id'] == $user_id),
            'replies' => [] // TODO: Implement replies for media comments if needed
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'comments' => $formatted_comments,
        'count' => count($formatted_comments)
    ]);

} catch (PDOException $e) {
    error_log("Error in get_media_comments.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
