<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Get user ID
$user_id = $_SESSION['user']['id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check required fields
if (!isset($data['post_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Missing required fields']);
  exit();
}

$post_id = $data['post_id'];

try {
  // First check if the post exists and belongs to the user
  $stmt = $pdo->prepare("
    SELECT * FROM posts 
    WHERE id = ? AND user_id = ?
  ");
  $stmt->execute([$post_id, $user_id]);
  $post = $stmt->fetch();
  
  if (!$post) {
    throw new Exception('Post not found or you do not have permission to delete it');
  }
  
  // Begin transaction
  $pdo->beginTransaction();
  
  // Delete post
  $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
  $stmt->execute([$post_id]);
  
  // Commit transaction
  $pdo->commit();
  
  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  
} catch (Exception $e) {
  // Rollback transaction on error
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>