<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Get user info
$user = $_SESSION['user'];
$user_id = $user['id'];

// Validate input
if (!isset($data['post_id']) || !isset($data['reaction_type'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Missing required fields']);
  exit();
}

$post_id = intval($data['post_id']);
$reaction_type = $data['reaction_type'];
$toggle_off = isset($data['toggle_off']) ? $data['toggle_off'] : false;

// Validate reaction type
$valid_reactions = ['twothumbs', 'clap', 'pray', 'love', 'drool', 'laughloud', 'dislike', 'angry', 'annoyed', 'brokenheart', 'cry', 'loser'];
if (!in_array($reaction_type, $valid_reactions)) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Invalid reaction type']);
  exit();
}

try {
  // Start transaction
  $pdo->beginTransaction();
  
  // Check if user already has a reaction for this post
  $stmt = $pdo->prepare("SELECT * FROM post_reactions WHERE post_id = ? AND user_id = ?");
  $stmt->execute([$post_id, $user_id]);
  $existing_reaction = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if ($existing_reaction) {
    // If toggling off the same reaction
    if ($toggle_off && $existing_reaction['reaction_type'] === $reaction_type) {
      // Delete the reaction
      $stmt = $pdo->prepare("DELETE FROM post_reactions WHERE id = ?");
      $stmt->execute([$existing_reaction['id']]);
    } else {
      // Update to new reaction type
      $stmt = $pdo->prepare("UPDATE post_reactions SET reaction_type = ?, updated_at = NOW() WHERE id = ?");
      $stmt->execute([$reaction_type, $existing_reaction['id']]);
    }
  } else {
    // Insert new reaction
    $stmt = $pdo->prepare("INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([$post_id, $user_id, $reaction_type]);
  }
  
  // Commit transaction
  $pdo->commit();
  
  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  
} catch (Exception $e) {
  // Rollback transaction on error
  $pdo->rollBack();
  
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
