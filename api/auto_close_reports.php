<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Find reports that are older than 7 days and not closed
    $stmt = $pdo->prepare("
        SELECT id, thread_id
        FROM user_reports
        WHERE status != 'closed'
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $closedCount = 0;
    
    foreach ($reports as $report) {
        // Update report status to closed
        $stmt = $pdo->prepare("
            UPDATE user_reports 
            SET status = 'closed',
                closed_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->execute([$report['id']]);
        
        // Create system message in the original thread
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                thread_id,
                sender_id,
                content,
                is_system_message
            )
            VALUES (?, 1, 'Report has been automatically closed after 7 days.', TRUE)
        ");
        
        $stmt->execute([$report['thread_id']]);
        
        $closedCount++;
    }
    
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'closed_count' => $closedCount
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in auto_close_reports.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
} 