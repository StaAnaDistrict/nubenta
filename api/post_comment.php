<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Get data from request
$user = $_SESSION['user'];
$user_id = $user['id'];

// Check if it's a JSON request or form data
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (strpos($contentType, 'application/json') !== false) {
  // Handle JSON input
  $data = json_decode(file_get_contents('php://input'), true);
  $post_id = isset($data['post_id']) ? intval($data['post_id']) : 0;
  $content = isset($data['content']) ? trim($data['content']) : '';
} else {
  // Handle form data
  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $content = isset($_POST['content']) ? trim($_POST['content']) : '';
}

// Validate input
if ($post_id <= 0 || empty($content)) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit();
}

try {
  // First check if the user has permission to view the post
  $stmt = $pdo->prepare("
    SELECT p.*, 
           u.id as author_id,
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
           u.profile_pic,
           u.gender
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
  ");
  $stmt->execute([$post_id]);
  $post = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$post) {
    throw new Exception('Post not found');
  }
  
  // Check visibility permissions
  $can_view = false;
  
  if ($post['visibility'] === 'public') {
    // Anyone can view and comment on public posts
    $can_view = true;
  } else if ($post['visibility'] === 'friends') {
    // Check if user is friends with the post author
    if ($user_id == $post['author_id']) {
      // User is the author
      $can_view = true;
    } else {
      // Check friendship status
      $stmt = $pdo->prepare("
        SELECT COUNT(*) as is_friend
        FROM friend_requests
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'accepted'
      ");
      $stmt->execute([$user_id, $post['author_id'], $post['author_id'], $user_id]);
      $friendship = $stmt->fetch(PDO::FETCH_ASSOC);
      
      $can_view = ($friendship['is_friend'] > 0);
    }
  }
  
  if (!$can_view) {
    throw new Exception('You do not have permission to comment on this post');
  }
  
  // Insert the comment
  $stmt = $pdo->prepare("
    INSERT INTO comments (post_id, user_id, content, created_at)
    VALUES (?, ?, ?, NOW())
  ");
  $stmt->execute([$post_id, $user_id, $content]);
  $comment_id = $pdo->lastInsertId();
  
  // Get the newly created comment with author info
  $stmt = $pdo->prepare("
    SELECT c.*, 
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
           u.profile_pic,
           u.gender
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
  ");
  $stmt->execute([$comment_id]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // Determine profile picture
  $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
  $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
  $profilePic = !empty($comment['profile_pic']) 
      ? 'uploads/profile_pics/' . htmlspecialchars($comment['profile_pic']) 
      : ($comment['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
  
  // Format comment for response
  $formatted_comment = [
    'id' => $comment['id'],
    'content' => htmlspecialchars($comment['content']),
    'author' => htmlspecialchars($comment['author_name']),
    'profile_pic' => $profilePic,
    'created_at' => $comment['created_at'],
    'is_own_comment' => true
  ];
  
  // Get total comment count for this post
  $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
  $count_stmt->execute([$post_id]);
  $comment_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

  header('Content-Type: application/json');
  echo json_encode([
    'success' => true, 
    'comment' => $formatted_comment,
    'comment_count' => $comment_count
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
