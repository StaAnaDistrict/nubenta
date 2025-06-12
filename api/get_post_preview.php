<?php
require_once '../bootstrap.php'; // Or your DB connection file
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

$post_id = isset($_GET['id']) ? filter_var(trim($_GET['id']), FILTER_VALIDATE_INT) : null;

if (empty($post_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Post ID is required.']);
    exit;
}

try {
    // Fetch basic post details for preview
    // Ensure to respect post visibility and user's friendship status with author if not public
    // This is a simplified version; a full visibility check might be more complex
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.content,
            p.media,
            p.visibility,
            p.user_id as author_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
            u.profile_pic as author_profile_pic,
            u.gender as author_gender
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.post_type = 'original' -- Can only share original posts
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Original post not found or cannot be shared.']);
        exit;
    }

    // Simplified visibility check for preview (actual share permission is on share_post.php)
    $current_user_id = $_SESSION['user']['id'];
    if ($post['visibility'] === 'only_me' && $post['author_user_id'] != $current_user_id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to view this post.']);
        exit;
    }
    if ($post['visibility'] === 'friends' && $post['author_user_id'] != $current_user_id) {
        // Placeholder: Add actual friend check here if needed for preview
        $friend_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM friend_requests WHERE status = 'accepted' AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $friend_check_stmt->execute([$current_user_id, $post['author_user_id'], $post['author_user_id'], $current_user_id]);
        if ($friend_check_stmt->fetchColumn() == 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'This post is for friends only.']);
            exit;
        }
    }

    $defaultMalePic = '../assets/images/MaleDefaultProfilePicture.png'; // Adjusted path relative to api folder
    $defaultFemalePic = '../assets/images/FemaleDefaultProfilePicture.png'; // Adjusted path

    $author_profile_pic = !empty($post['author_profile_pic'])
        ? '../uploads/profile_pics/' . htmlspecialchars($post['author_profile_pic'])
        : ($post['author_gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);

    // Prepare media preview (e.g., first image or video thumbnail)
    $media_preview_html = '';
    if (!empty($post['media'])) {
        $media_items = json_decode($post['media'], true);
        $first_media_item_path = '';

        if (is_array($media_items) && count($media_items) > 0) {
            $first_media_item_path = $media_items[0];
        } elseif (!is_array($media_items) && !empty($post['media'])) { // Single media item
            $first_media_item_path = $post['media'];
        }

        if (!empty($first_media_item_path)) {
            if (strpos($first_media_item_path, 'uploads/') !== 0 && strpos($first_media_item_path, 'http') !== 0) {
                 $first_media_item_path = '../uploads/post_media/' . $first_media_item_path; // Adjusted path
            }

            if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $first_media_item_path)) {
                $media_preview_html = '<img src="' . htmlspecialchars($first_media_item_path) . '" style="max-width: 100%; max-height: 100px; border-radius: 4px;">';
            } elseif (preg_match('/\.mp4$/i', $first_media_item_path)) {
                $media_preview_html = '<video src="' . htmlspecialchars($first_media_item_path) . '" style="max-width: 100%; max-height: 100px; border-radius: 4px;" controls></video>';
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'post_preview' => [
            'author_name' => htmlspecialchars($post['author_name']),
            'author_profile_pic' => htmlspecialchars($author_profile_pic),
            'content_snippet' => htmlspecialchars(mb_substr(strip_tags($post['content'] ?? ''), 0, 100)) . (mb_strlen(strip_tags($post['content'] ?? '')) > 100 ? '...' : ''),
            'media_html' => $media_preview_html
        ]
    ]);

} catch (PDOException $e) {
    error_log("Get post preview PDOException: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
} catch (Exception $e) {
    error_log("Get post preview Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
}
?>
