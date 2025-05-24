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
  // Get comment count for the post
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM comments WHERE post_id = ?");
  $stmt->execute([$post_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  
  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'count' => intval($result['count'])
  ]);
  
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>