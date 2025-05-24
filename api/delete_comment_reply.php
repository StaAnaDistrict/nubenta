<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit();
}

// Check if reply ID is provided
if (!isset($_POST['reply_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Reply ID is required']);
  exit();
}

$reply_id = intval($_POST['reply_id']);
$user_id = $_SESSION['user']['id'];
$is_admin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

try {
  // Get the reply to check ownership
  $stmt = $pdo->prepare("SELECT * FROM comment_replies WHERE id = ?");
  $stmt->execute([$reply_id]);
  $reply = $stmt->fetch(PDO::FETCH_ASSOC);
  
  if (!$reply) {
    throw new Exception('Reply not found');
  }
  
  // Check if user owns the reply or is an admin
  if ($reply['user_id'] != $user_id && !$is_admin) {
    throw new Exception('You do not have permission to delete this reply');
  }
  
  // Delete the reply
  $stmt = $pdo->prepare("DELETE FROM comment_replies WHERE id = ?");
  $stmt->execute([$reply_id]);
  
  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>