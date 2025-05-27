<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get media ID from query parameter
if (!isset($_GET['media_id']) || !is_numeric($_GET['media_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid media ID']);
    exit();
}

$media_id = intval($_GET['media_id']);
$user_id = $_SESSION['user']['id'];

try {
    // Get media details to find the post_id
    $stmt = $pdo->prepare("
        SELECT post_id, user_id as media_user_id
        FROM user_media 
        WHERE id = ?
    ");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Media not found']);
        exit();
    }

    // Get reaction count for this media
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_reactions,
               GROUP_CONCAT(DISTINCT rt.name) as reaction_types
        FROM media_reactions mr
        LEFT JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.media_id = ?
    ");
    $stmt->execute([$media_id]);
    $reactions = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get comment count for this media
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_comments
        FROM media_comments 
        WHERE media_id = ?
    ");
    $stmt->execute([$media_id]);
    $comments = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get post comment count if this media belongs to a post
    $post_comments = 0;
    if ($media['post_id']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_comments
            FROM comments 
            WHERE post_id = ?
        ");
        $stmt->execute([$media['post_id']]);
        $post_comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $post_comments = $post_comment_data['total_comments'];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'media_id' => $media_id,
        'post_id' => $media['post_id'],
        'stats' => [
            'reactions' => [
                'total' => intval($reactions['total_reactions']),
                'types' => $reactions['reaction_types'] ? explode(',', $reactions['reaction_types']) : []
            ],
            'media_comments' => intval($comments['total_comments']),
            'post_comments' => $post_comments,
            'total_comments' => intval($comments['total_comments']) + $post_comments
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error in sync_media_stats.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
