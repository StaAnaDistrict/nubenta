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

// Get POST data - handle both form data and JSON
$media_id = 0;
$content = '';

if ($_SERVER['CONTENT_TYPE'] === 'application/json' || strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // Handle JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $media_id = isset($input['media_id']) ? intval($input['media_id']) : 0;
        $content = isset($input['content']) ? trim($input['content']) : '';
    }
} else {
    // Handle form data
    $media_id = isset($_POST['media_id']) ? intval($_POST['media_id']) : 0;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
}

// Validate input
if ($media_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid media ID']);
    exit();
}

if (empty($content)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Comment content is required']);
    exit();
}

// Additional validation
if (strlen($content) > 1000) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Comment is too long (max 1000 characters)']);
    exit();
}

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

    // Create media_comments table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS media_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            media_id INT NOT NULL,
            user_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_media_id (media_id),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert the comment
    $stmt = $pdo->prepare("
        INSERT INTO media_comments (media_id, user_id, content, created_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$media_id, $user_id, $content]);

    $comment_id = $pdo->lastInsertId();

    // Get the newly created comment with user info
    $stmt = $pdo->prepare("
        SELECT mc.*,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
               u.profile_pic,
               u.gender
        FROM media_comments mc
        JOIN users u ON mc.user_id = u.id
        WHERE mc.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Determine profile picture
    $profilePic = 'assets/images/MaleDefaultProfilePicture.png'; // Default
    if (!empty($comment['profile_pic'])) {
        $profilePic = $comment['profile_pic'];
    } elseif ($comment['gender'] === 'female') {
        $profilePic = 'assets/images/FemaleDefaultProfilePicture.png';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully',
        'comment' => [
            'id' => $comment['id'],
            'content' => htmlspecialchars($comment['content']),
            'author' => htmlspecialchars($comment['author_name']),
            'profile_pic' => $profilePic,
            'created_at' => $comment['created_at'],
            'is_own_comment' => true
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error in post_media_comment.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
