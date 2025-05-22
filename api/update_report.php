<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

// Get POST data
$report_id = $_POST['report_id'] ?? null;
$status = $_POST['status'] ?? null;
$admin_response = $_POST['admin_response'] ?? null;

if (!$report_id) {
    echo json_encode(['success' => false, 'error' => 'Missing report ID']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get report details first
    $stmt = $pdo->prepare("
        SELECT r.*, t.id as thread_id 
        FROM user_reports r 
        LEFT JOIN chat_threads t ON r.thread_id = t.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        throw new Exception('Report not found');
    }
    
    // Update report status if provided
    if ($status) {
        $valid_statuses = ['pending', 'reviewed', 'resolved', 'dismissed'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception('Invalid status');
        }
        
        $stmt = $pdo->prepare("
            UPDATE user_reports 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$status, $report_id]);
    }
    
    // Update admin response if provided
    if ($admin_response) {
        $stmt = $pdo->prepare("
            UPDATE user_reports 
            SET admin_response = ?, updated_at = CURRENT_TIMESTAMP, notification_sent = TRUE 
            WHERE id = ?
        ");
        $stmt->execute([$admin_response, $report_id]);
        
        // Create a system message about the report response
        if ($report['thread_id']) {
            $systemMessage = "Your report has received an admin response:\n\n" . $admin_response;
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (thread_id, sender_id, content, is_system_message)
                VALUES (?, 0, ?, TRUE)
            ");
            $stmt->execute([$report['thread_id'], $systemMessage]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error in update_report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 