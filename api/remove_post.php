<?php
session_start();
require_once '../db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['post_id']) || !isset($data['reason']) || !isset($data['violation_type']) || !isset($data['replacement_text'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$post_id = $data['post_id'];
$reason = $data['reason'];
$violation_type = $data['violation_type'];
$replacement_text = $data['replacement_text'];
$admin_id = $_SESSION['user']['id'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get post information before updating
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        throw new Exception('Post not found');
    }
    
    // Check if admin_actions table exists, if not, skip logging
    $tableExists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'admin_actions'");
        $tableExists = ($check->rowCount() > 0);
    } catch (Exception $e) {
        // Table doesn't exist, continue without logging
    }
    
    // Log the removal action if table exists
    if ($tableExists) {
        $stmt = $pdo->prepare("
            INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, reason)
            VALUES (?, 'remove', 'post', ?, ?, ?)
        ");
        $details = json_encode([
            'original_content' => $post['content'],
            'original_media' => $post['media'],
            'violation_type' => $violation_type,
            'replacement_text' => $replacement_text
        ]);
        $stmt->execute([$admin_id, $post_id, $details, $reason]);
    }
    
    // Update the post content
    $stmt = $pdo->prepare("
        UPDATE posts 
        SET content = ?, media = NULL, is_removed = 1, removed_reason = ?, removed_by = ?, removed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$replacement_text, $reason, $admin_id, $post_id]);
    
    // Commit transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
