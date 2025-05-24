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

try {
  // Get post data
  $stmt = $pdo->prepare("
    SELECT p.*, 
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit();
  }
  
  // Determine profile picture
  $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
  $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
  $profilePic = !empty($post['profile_pic']) 
      ? 'uploads/profile_pics/' . htmlspecialchars($post['profile_pic']) 
      : ($post['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
  
  // Format post for response
  $formatted_post = [
    'id' => $post['id'],
    'content' => htmlspecialchars($post['content']),
    'media' => $post['media'] ? 'uploads/post_media/' . htmlspecialchars($post['media']) : null,
    'author' => htmlspecialchars($post['author_name']),
    'profile_pic' => $profilePic,
    'created_at' => $post['created_at'],
    'visibility' => $post['visibility'] ?? 'public',
    'is_own_post' => ($post['user_id'] == $user_id),
    'is_removed' => (bool)($post['is_removed'] ?? false),
    'removed_reason' => $post['removed_reason'] ?? '',
    'is_flagged' => (bool)($post['is_flagged'] ?? false),
    'flag_reason' => $post['flag_reason'] ?? ''
  ];
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true, 
    'post' => $formatted_post
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
