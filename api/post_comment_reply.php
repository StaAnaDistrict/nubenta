<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Get user data
$user = $_SESSION['user'];
$user_id = $user['id'];

// Check if it's a JSON request or form data
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (strpos($contentType, 'application/json') !== false) {
  // Handle JSON input
  $data = json_decode(file_get_contents('php://input'), true);
  $comment_id = isset($data['comment_id']) ? intval($data['comment_id']) : 0;
  $content = isset($data['content']) ? trim($data['content']) : '';
} else {
  // Handle form data
  $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
  $content = isset($_POST['content']) ? trim($_POST['content']) : '';
}

// Check if required parameters are provided
if ($comment_id <= 0 || empty($content)) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Comment ID and content are required']);
  exit();
}

try {
  // First check if the comment exists
  $stmt = $pdo->prepare("SELECT c.*, p.visibility, p.user_id as post_author_id FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.id = ?");
  $stmt->execute([$comment_id]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$comment) {
    throw new Exception('Comment not found');
  }
  
  // Check visibility permissions (similar to post_comment.php)
  $can_reply = false;
  
  if ($comment['visibility'] === 'public') {
    // Anyone can reply to comments on public posts
    $can_reply = true;
  } else if ($comment['visibility'] === 'friends') {
    // Check if user is friends with the post author
    if ($user_id == $comment['post_author_id']) {
      // User is the post author
      $can_reply = true;
    } else {
      // Check friendship status
      $stmt = $pdo->prepare("
        SELECT COUNT(*) as is_friend
        FROM friend_requests
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'accepted'
      ");
      $stmt->execute([$user_id, $comment['post_author_id'], $comment['post_author_id'], $user_id]);
      $friendship = $stmt->fetch(PDO::FETCH_ASSOC);
      
      $can_reply = ($friendship['is_friend'] > 0);
    }
  }
  
  if (!$can_reply) {
    throw new Exception('You do not have permission to reply to this comment');
  }
  
  // Insert the reply
  $stmt = $pdo->prepare("
    INSERT INTO comment_replies (comment_id, user_id, content, created_at)
    VALUES (?, ?, ?, NOW())
  ");
  $stmt->execute([$comment_id, $user_id, $content]);
  $reply_id = $pdo->lastInsertId();
  
  // Get the newly created reply with author info
  $stmt = $pdo->prepare("
    SELECT cr.*, 
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
           u.profile_pic,
           u.gender
    FROM comment_replies cr
    JOIN users u ON cr.user_id = u.id
    WHERE cr.id = ?
  ");
  $stmt->execute([$reply_id]);
  $reply = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // Determine profile picture
  $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
  $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
  $profilePic = !empty($reply['profile_pic']) 
      ? 'uploads/profile_pics/' . htmlspecialchars($reply['profile_pic']) 
      : ($reply['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
  
  // Format reply for response
  $formatted_reply = [
    'id' => $reply['id'],
    'content' => htmlspecialchars($reply['content']),
    'author' => htmlspecialchars($reply['author_name']),
    'profile_pic' => $profilePic,
    'created_at' => $reply['created_at'],
    'is_own_reply' => true
  ];

  header('Content-Type: application/json');
  echo json_encode([
    'success' => true, 
    'reply' => $formatted_reply
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
