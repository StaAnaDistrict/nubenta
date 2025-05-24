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
  // Get all reactions for the post
  $stmt = $pdo->prepare("
    SELECT pr.*, u.name, u.profile_pic 
    FROM post_reactions pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.post_id = ?
  ");
  $stmt->execute([$post_id]);
  $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Get user's reaction if any
  $stmt = $pdo->prepare("SELECT reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?");
  $stmt->execute([$post_id, $user_id]);
  $user_reaction = $stmt->fetchColumn();
  
  // Count reactions by type
  $reaction_count = [
    'total' => count($reactions),
    'by_type' => []
  ];
  
  // Group reactions by type
  $reactions_by_type = [];
  
  foreach ($reactions as $reaction) {
    $type = $reaction['reaction_type'];
    
    // Count by type
    if (!isset($reaction_count['by_type'][$type])) {
      $reaction_count['by_type'][$type] = 0;
    }
    $reaction_count['by_type'][$type]++;
    
    // Group users by reaction type
    if (!isset($reactions_by_type[$type])) {
      $reactions_by_type[$type] = [];
    }
    
    $reactions_by_type[$type][] = [
      'id' => $reaction['user_id'],
      'name' => $reaction['name'],
      'profile_pic' => $reaction['profile_pic'] ?: 'assets/images/default-avatar.png'
    ];
  }
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'reaction_count' => $reaction_count,
    'user_reaction' => $user_reaction,
    'reactions_by_type' => $reactions_by_type
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
