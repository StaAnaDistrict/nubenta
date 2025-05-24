<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$user = $_SESSION['user'];
$user_id = $user['id'];

// Validate input
if (!isset($data['post_id']) || !isset($data['visibility'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit();
}

$post_id = $data['post_id'];
$content = isset($data['content']) ? trim($data['content']) : '';
$visibility = $data['visibility'];

// Validate visibility
if (!in_array($visibility, ['public', 'friends'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid visibility option']);
  exit();
}

try {
  // First check if the user has permission to view the original post
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
  $original_post = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$original_post) {
    throw new Exception('Post not found');
  }
  
  // Check visibility permissions
  $can_view = false;
  
  if ($original_post['visibility'] === 'public') {
    // Anyone can view and share public posts
    $can_view = true;
  } else if ($original_post['visibility'] === 'friends') {
    // Check if user is friends with the post author
    if ($user_id == $original_post['author_id']) {
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
      $stmt->execute([$user_id, $original_post['author_id'], $original_post['author_id'], $user_id]);
      $friendship = $stmt->fetch(PDO::FETCH_ASSOC);
      
      $can_view = ($friendship['is_friend'] > 0);
    }
  }
  
  if (!$can_view) {
    throw new Exception('You do not have permission to share this post');
  }
  
  // Begin transaction
  $pdo->beginTransaction();
  
  // Create a new post as a share
  $shared_content = '';
  
  // If the original post is "friends only" and the share is "public"
  // We need to handle this special case
  $is_privacy_conflict = ($original_post['visibility'] === 'friends' && $visibility === 'public');
  
  // Prepare shared post data
  $shared_data = [
    'user_id' => $user_id,
    'content' => $content,
    'visibility' => $visibility,
    'is_share' => 1,
    'original_post_id' => $post_id
  ];
  
  // If there's a privacy conflict, we don't include the original post content
  // Instead, we'll show a message that the original content is private
  if ($is_privacy_conflict) {
    $shared_data['share_privacy_conflict'] = 1;
  }
  
  // Insert the shared post
  $columns = implode(', ', array_keys($shared_data));
  $placeholders = implode(', ', array_fill(0, count($shared_data), '?'));
  
  $stmt = $pdo->prepare("
    INSERT INTO posts ({$columns}, created_at)
    VALUES ({$placeholders}, NOW())
  ");
  
  $stmt->execute(array_values($shared_data));
  $shared_post_id = $pdo->lastInsertId();
  
  // Commit transaction
  $pdo->commit();
  
  // Get the newly created shared post with author info
  $stmt = $pdo->prepare("
    SELECT p.*, 
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
           u.profile_pic,
           u.gender
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
  ");
  $stmt->execute([$shared_post_id]);
  $shared_post = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // Determine profile picture
  $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
  $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
  $profilePic = !empty($shared_post['profile_pic']) 
      ? 'uploads/profile_pics/' . htmlspecialchars($shared_post['profile_pic']) 
      : ($shared_post['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
  
  // Format post for response
  $formatted_post = [
    'id' => $shared_post['id'],
    'content' => htmlspecialchars($shared_post['content']),
    'media' => $shared_post['media'] ? htmlspecialchars($shared_post['media']) : null,
    'author' => htmlspecialchars($shared_post['author_name']),
    'profile_pic' => $profilePic,
    'created_at' => $shared_post['created_at'],
    'visibility' => $shared_post['visibility'],
    'is_share' => (bool)$shared_post['is_share'],
    'original_post_id' => $shared_post['original_post_id'],
    'share_privacy_conflict' => (bool)($shared_post['share_privacy_conflict'] ?? false)
  ];
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true, 
    'post' => $formatted_post
  ]);
  
} catch (Exception $e) {
  // Rollback transaction on error
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
