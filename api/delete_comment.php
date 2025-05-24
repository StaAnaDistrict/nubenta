<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Check if comment ID is provided
if (!isset($_POST['comment_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Comment ID is required']);
  exit();
}

$comment_id = intval($_POST['comment_id']);
$user_id = $_SESSION['user']['id'];
$is_admin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

try {
  // Get the comment to check ownership
  $stmt = $pdo->prepare("SELECT c.*, p.id as post_id FROM comments c JOIN posts p ON c.post_id = p.id WHERE c.id = ?");
  $stmt->execute([$comment_id]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$comment) {
    throw new Exception('Comment not found');
  }
  
  // Check if user owns the comment or is an admin
  if ($comment['user_id'] != $user_id && !$is_admin) {
    throw new Exception('You do not have permission to delete this comment');
  }
  
  // Delete the comment (this will also delete replies due to foreign key constraint)
  $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
  $stmt->execute([$comment_id]);
  
  // Get updated comment count for the post
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
  $stmt->execute([$comment['post_id']]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'comment_count' => intval($result['count'])
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>