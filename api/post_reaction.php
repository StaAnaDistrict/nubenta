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
if (!isset($data['post_id']) || !isset($data['reaction_type'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit();
}

$post_id = $data['post_id'];
$reaction_type = $data['reaction_type'];
$toggle_off = isset($data['toggle_off']) ? $data['toggle_off'] : false;

// Validate reaction type
$valid_reactions = ['twothumbs', 'clap', 'bigsmile', 'love', 'dislike', 'angry', 'annoyed', 'shame'];
if (!in_array($reaction_type, $valid_reactions)) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid reaction type: ' . $reaction_type]);
  exit();
}

try {
  // Check if user has already reacted to this post
  $check_stmt = $pdo->prepare("SELECT id, reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?");
  $check_stmt->execute([$post_id, $user_id]);
  $existing_reaction = $check_stmt->fetch();
  
  if ($existing_reaction) {
    // If toggling off the same reaction, delete it
    if ($toggle_off && $existing_reaction['reaction_type'] === $reaction_type) {
      $delete_stmt = $pdo->prepare("DELETE FROM post_reactions WHERE id = ?");
      $delete_stmt->execute([$existing_reaction['id']]);
      
      header('Content-Type: application/json');
      echo json_encode(['success' => true, 'message' => 'Reaction removed']);
      exit();
    } else {
      // Update existing reaction
      $update_stmt = $pdo->prepare("UPDATE post_reactions SET reaction_type = ?, updated_at = NOW() WHERE id = ?");
      $update_stmt->execute([$reaction_type, $existing_reaction['id']]);
    }
  } else {
    // Insert new reaction
    $insert_stmt = $pdo->prepare("INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at) VALUES (?, ?, ?, NOW())");
    $insert_stmt->execute([$post_id, $user_id, $reaction_type]);
  }
  
  header('Content-Type: application/json');
  echo json_encode(['success' => true, 'message' => 'Reaction saved']);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
