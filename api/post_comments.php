<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Validate input
if (!isset($_GET['post_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit();
}

$post_id = $_GET['post_id'];
$user = $_SESSION['user'];
$user_id = $user['id'];

try {
  // First check if the user has permission to view this post's comments
  $stmt = $pdo->prepare("
    SELECT p.*, u.id as author_id
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
    // Anyone can view public posts
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
    throw new Exception('You do not have permission to view comments on this post');
  }
  
  // Get comments
  $stmt = $pdo->prepare("
    SELECT c.*, 
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author,
           u.profile_pic,
           u.gender
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
  ");
  $stmt->execute([$post_id]);
  $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Define default profile pictures
  $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
  $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
  
  // Format comments for response
  $formatted_comments = [];
  foreach ($comments as $comment) {
    // Determine profile picture
    $profilePic = !empty($comment['profile_pic']) 
        ? 'uploads/profile_pics/' . htmlspecialchars($comment['profile_pic']) 
        : ($comment['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
    
    $formatted_comments[] = [
      'id' => $comment['id'],
      'post_id' => $comment['post_id'],
      'user_id' => $comment['user_id'],
      'author' => htmlspecialchars($comment['author']),
      'profile_pic' => $profilePic,
      'content' => htmlspecialchars($comment['content']),
      'created_at' => $comment['created_at']
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