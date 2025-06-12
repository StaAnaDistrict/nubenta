<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Use bootstrap.php for DB connection and other initial setup
require_once '../bootstrap.php'; 

// Check if user is logged in using session
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Only POST is accepted.']);
    exit;
}

$sharer_user_id = $_SESSION['user']['id'];
$original_post_id = isset($_POST['original_post_id']) ? filter_var(trim($_POST['original_post_id']), FILTER_VALIDATE_INT) : null;
$sharer_comment = isset($_POST['sharer_comment']) ? trim($_POST['sharer_comment']) : ''; // Default to empty string
$visibility = isset($_POST['visibility']) ? trim($_POST['visibility']) : 'friends'; // Default visibility

// Validate visibility input
$allowed_visibilities = ['public', 'friends', 'only_me']; // Customize as per your system
if (!in_array($visibility, $allowed_visibilities)) {
    $visibility = 'friends'; // Default to 'friends' or another appropriate default
}

if (empty($original_post_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Original post ID is required and must be a valid integer.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verify Original Post
    // Select is_share instead of post_type. Original posts should have is_share = 0 or is_share IS NULL.
    $stmt_check = $pdo->prepare("SELECT id, user_id, visibility, is_share FROM posts WHERE id = ? AND (is_share = 0 OR is_share IS NULL)");
    $stmt_check->execute([$original_post_id]);
    $original_post = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$original_post) {
        // This also handles the case where the post ID exists but it's already a share (is_share = 1 was filtered by query)
        // or if the post simply doesn't exist.
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'Original post not found or is not shareable (e.g., it is already a share).']);
        $pdo->rollBack();
        exit;
    }

    // The SQL query now ensures that $original_post['is_share'] is 0 or NULL.
    // Thus, an explicit check like `if ($original_post['is_share'] == 1)` is redundant here
    // as such posts would not be fetched by the modified $stmt_check query.
    
    // Basic visibility check for sharing (can be expanded)
    // If original post is 'only_me', only the author can "share" it (effectively a repost with new comment)
    if ($original_post['visibility'] === 'only_me' && $original_post['user_id'] != $sharer_user_id) {
         http_response_code(403); // Forbidden
         echo json_encode(['status' => 'error', 'message' => 'You do not have permission to share this private post.']);
         $pdo->rollBack();
         exit;
    }
    // If original post is 'friends' only, sharer must be friends with original author
    // This logic needs your specific friend checking mechanism. Placeholder:
    if ($original_post['visibility'] === 'friends' && $original_post['user_id'] != $sharer_user_id) {
        // Example check (replace with your actual friend checking logic):
        $friend_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM friend_requests WHERE status = 'accepted' AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $friend_check_stmt->execute([$sharer_user_id, $original_post['user_id'], $original_post['user_id'], $sharer_user_id]);
        if ($friend_check_stmt->fetchColumn() == 0) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'You can only share a "friends-only" post if you are friends with the original author.']);
            $pdo->rollBack();
            exit;
        }
    }
    
    // Prevent sharing own post without a comment (which is essentially just a duplicate)
     if ($original_post['user_id'] == $sharer_user_id && empty($sharer_comment)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Sharing your own post requires adding a comment or thought.']);
        $pdo->rollBack();
        exit;
    }

    // 2. Database Insertion for the new "shared" post
    // Use is_share = 1 for shared posts
    $stmt_insert = $pdo->prepare(
        "INSERT INTO posts (user_id, content, original_post_id, is_share, visibility, created_at, updated_at) 
         VALUES (?, ?, ?, 1, ?, NOW(), NOW())"
    );
    
    $success = $stmt_insert->execute([
        $sharer_user_id,
        empty($sharer_comment) ? NULL : htmlspecialchars($sharer_comment, ENT_QUOTES, 'UTF-8'),
        $original_post_id,
        $visibility
    ]);

    if ($success) {
        $shared_post_id = $pdo->lastInsertId();
        
        // Insert notification for the original post author
        $original_author_id = $original_post['user_id'];
        if ($original_author_id && $original_author_id != $sharer_user_id) {
            try {
                // Construct a link to the new shared post. 
                // Adjust the base URL as necessary if this script is deep in /api/
                // For example, if newsfeed items have anchors like #post-123 or posts.php?id=123
                $notification_link = "../posts.php?id=" . $shared_post_id; 
                // Or, link to the original post that was shared, and context shows it as a share:
                // $notification_link = "../posts.php?id=" . $original_post_id . "&focus_share=" . $shared_post_id;

                $stmt_notify = $pdo->prepare(
                    "INSERT INTO notifications (user_id, actor_id, type, target_id, link, created_at) 
                     VALUES (?, ?, 'post_share', ?, ?, NOW())"
                );
                $stmt_notify->execute([$original_author_id, $sharer_user_id, $shared_post_id, $notification_link]);
            } catch (PDOException $e) {
                // Log notification error, but don't fail the whole share transaction
                error_log("Failed to create notification for post share: " . $e->getMessage());
            }
        }
        
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Post shared successfully.', 'shared_post_id' => $shared_post_id]);
    } else {
        $pdo->rollBack();
        http_response_code(500); // Internal Server Error
        $errorInfo = $stmt_insert->errorInfo();
        error_log("Failed to share post. DB Error: " . ($errorInfo[2] ?? 'Unknown error'));
        echo json_encode(['status' => 'error', 'message' => 'Failed to share post due to a server error.']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Share post PDOException: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error while sharing post. Details logged.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Share post General Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
}
?>
