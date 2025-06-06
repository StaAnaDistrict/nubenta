<?php
session_start();
require_once '../db.php';
require_once '../includes/MediaUploader.php'; // Include MediaUploader

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$loggedInUser = $_SESSION['user'];
$loggedInUserId = $loggedInUser['id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['post_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing post_id']);
    exit();
}

$postId = filter_var($data['post_id'], FILTER_VALIDATE_INT);

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Invalid post_id']);
    exit();
}

$mediaUploader = new MediaUploader($pdo);

try {
    $pdo->beginTransaction();

    // Fetch post details, especially user_id and media paths
    $stmt = $pdo->prepare("SELECT user_id, media FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        throw new Exception('Post not found.');
    }

    // Ownership check: only post owner or admin can delete
    if ($post['user_id'] != $loggedInUserId && $loggedInUser['role'] !== 'admin') {
        throw new Exception('You do not have permission to delete this post.');
    }

    // 1. Delete associated media from user_media and files
    if (!empty($post['media'])) {
        $mediaPaths = json_decode($post['media'], true);
        if (is_array($mediaPaths)) {
            foreach ($mediaPaths as $path) {
                if (empty($path)) continue;
                // Find media_id in user_media based on media_url and post_id (and user_id for security)
                $mediaQuery = $pdo->prepare("SELECT id FROM user_media WHERE media_url = ? AND user_id = ? AND post_id = ?");
                $mediaQuery->execute([$path, $post['user_id'], $postId]);
                $mediaToDelete = $mediaQuery->fetch(PDO::FETCH_ASSOC);
                
                if ($mediaToDelete) {
                    $deleteSuccess = $mediaUploader->deleteMedia($mediaToDelete['id'], $post['user_id']);
                    if ($deleteSuccess) {
                        error_log("Successfully deleted media ID: " . $mediaToDelete['id'] . " associated with post ID: " . $postId);
                    } else {
                        error_log("Failed to delete media ID: " . $mediaToDelete['id'] . " associated with post ID: " . $postId);
                        // Decide if this should throw an exception and rollback or just log
                    }
                } else {
                    error_log("Could not find media in user_media for path: " . $path . " and post ID: " . $postId);
                }
            }
        }
    }

    // 2. Delete comments associated with the post (if comments table has post_id)
    // Assuming a 'comments' table with a 'post_id' column
    // $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = ?");
    // $stmt->execute([$postId]);
    // Add similar for comment_replies if they exist and are linked to post_id or comment_id

    // 3. Delete reactions associated with the post (if post_reactions table has post_id)
    // Assuming a 'post_reactions' table with a 'post_id' column
    // $stmt = $pdo->prepare("DELETE FROM post_reactions WHERE post_id = ?");
    // $stmt->execute([$postId]);

    // 4. Finally, delete the post itself
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$postId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error deleting post ID " . $postId . ": " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>