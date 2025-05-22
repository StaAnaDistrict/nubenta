<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['report_id']) || !isset($data['appeal_reason'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $userId = $_SESSION['user']['id'];
    $reportId = $data['report_id'];
    $appealReason = $data['appeal_reason'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if report exists and belongs to user
    $stmt = $pdo->prepare("SELECT id, status FROM user_reports WHERE id = ? AND reporter_id = ?");
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        throw new Exception('Report not found or unauthorized');
    }
    
    if ($report['status'] === 'closed') {
        throw new Exception('Cannot appeal a closed report');
    }
    
    // Update report with appeal
    $stmt = $pdo->prepare("
        UPDATE user_reports 
        SET user_appeal = ?,
            appeal_date = CURRENT_TIMESTAMP,
            status = 'reviewed'
        WHERE id = ? AND reporter_id = ?
    ");
    
    $stmt->execute([$appealReason, $reportId, $userId]);
    
    // Create system message in the original thread
    $stmt = $pdo->prepare("
        INSERT INTO messages (
            thread_id,
            sender_id,
            content,
            is_system_message
        )
        SELECT 
            thread_id,
            ?,
            CONCAT('User has appealed the admin decision: ', ?),
            TRUE
        FROM user_reports
        WHERE id = ?
    ");
    
    $stmt->execute([$userId, $appealReason, $reportId]);
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in submit_appeal.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 