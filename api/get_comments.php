<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Get post ID from query string
if (!isset($_GET['post_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Post ID is required']);
  exit();
}

$post_id = intval($_GET['post_id']);
$user_id = $_SESSION['user']['id'];
$count_only = isset($_GET['count_only']) && $_GET['count_only'] === 'true';

try {
  // If we only need the count, use a simpler query
  if ($count_only) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'count' => intval($result['count']),
      'comments' => [] // Empty array since we only need the count
    ]);
    exit();
  }
  
  // Get full comments for post
  $stmt = $pdo->prepare("
    SELECT c.*, 
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
           u.profile_pic,
           u.gender
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
  ");
  $stmt->execute([$post_id]);
  $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Format comments for response
  $formatted_comments = [];
  foreach ($comments as $comment) {
    // Determine profile picture
    $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
    $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
    $profilePic = !empty($comment['profile_pic']) 
        ? 'uploads/profile_pics/' . htmlspecialchars($comment['profile_pic']) 
        : ($comment['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
    
    // Get replies for this comment
    $repliesStmt = $pdo->prepare("
      SELECT cr.*, 
             CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
             u.profile_pic,
             u.gender
      FROM comment_replies cr
      JOIN users u ON cr.user_id = u.id
      WHERE cr.comment_id = ?
      ORDER BY cr.created_at ASC
    ");
    $repliesStmt->execute([$comment['id']]);
    $replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format replies
    $formatted_replies = [];
    foreach ($replies as $reply) {
      $replyProfilePic = !empty($reply['profile_pic']) 
          ? 'uploads/profile_pics/' . htmlspecialchars($reply['profile_pic']) 
          : ($reply['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
      
      $formatted_replies[] = [
        'id' => $reply['id'],
        'content' => htmlspecialchars($reply['content']),
        'author' => htmlspecialchars($reply['author_name']),
        'profile_pic' => $replyProfilePic,
        'created_at' => $reply['created_at'],
        'is_own_reply' => ($reply['user_id'] == $user_id)
      ];
    }
    
    $formatted_comments[] = [
      'id' => $comment['id'],
      'content' => htmlspecialchars($comment['content']),
      'author' => htmlspecialchars($comment['author_name']),
      'profile_pic' => $profilePic,
      'created_at' => $comment['created_at'],
      'is_own_comment' => ($comment['user_id'] == $user_id),
      'replies' => $formatted_replies,
      'reply_count' => count($formatted_replies)
    ];
  }
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'comments' => $formatted_comments
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
