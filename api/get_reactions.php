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
  // First check if the user has permission to view the post
  $stmt = $pdo->prepare("
    SELECT p.*, 
           u.id as author_id
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
    // Anyone can view reactions on public posts
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
    throw new Exception('You do not have permission to view reactions on this post');
  }
  
  // Get user's reaction to this post
  $stmt = $pdo->prepare("
    SELECT reaction_type 
    FROM post_reactions 
    WHERE post_id = ? AND user_id = ?
  ");
  $stmt->execute([$post_id, $user_id]);
  $user_reaction = $stmt->fetch(PDO::FETCH_ASSOC);

  // Get reaction counts
  $stmt = $pdo->prepare("
    SELECT reaction_type, COUNT(*) as count
    FROM post_reactions
    WHERE post_id = ?
    GROUP BY reaction_type
  ");
  $stmt->execute([$post_id]);
  $reaction_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Format reaction counts
  $formatted_counts = [
    'total' => 0,
    'by_type' => []
  ];

  foreach ($reaction_counts as $reaction) {
    $formatted_counts['total'] += (int)$reaction['count'];
    $formatted_counts['by_type'][$reaction['reaction_type']] = (int)$reaction['count'];
  }

  // Get detailed reaction data with user info (for displaying in modal)
  $stmt = $pdo->prepare("
    SELECT r.reaction_type,
           u.id as user_id,
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as user_name,
           u.profile_pic,
           u.gender
    FROM post_reactions r
    JOIN users u ON r.user_id = u.id
    WHERE r.post_id = ?
    ORDER BY r.created_at DESC
  ");
  $stmt->execute([$post_id]);
  $detailed_reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Group reactions by type with user info
  $reactions_by_type = [];
  foreach ($detailed_reactions as $reaction) {
    $type = $reaction['reaction_type'];
    
    // Determine profile picture
    $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
    $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
    $profilePic = !empty($reaction['profile_pic']) 
        ? 'uploads/profile_pics/' . htmlspecialchars($reaction['profile_pic']) 
        : ($reaction['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
    
    if (!isset($reactions_by_type[$type])) {
      $reactions_by_type[$type] = [];
    }
    
    $reactions_by_type[$type][] = [
      'id' => $reaction['user_id'],
      'name' => htmlspecialchars($reaction['user_name']),
      'profile_pic' => $profilePic
    ];
  }
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'reaction_count' => $formatted_counts,
    'user_reaction' => $user_reaction ? $user_reaction['reaction_type'] : null,
    'reactions_by_type' => $reactions_by_type
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
