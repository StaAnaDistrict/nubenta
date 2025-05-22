<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $userId = $_SESSION['user']['id'];
    
    $sql = "SELECT 
                r.*,
                u.first_name as reported_user_first_name,
                u.last_name as reported_user_last_name,
                u.email as reported_user_email
            FROM user_reports r
            LEFT JOIN users u ON r.reported_user_id = u.id
            WHERE r.reporter_id = ?
            ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process reports to format dates and handle file paths
    foreach ($reports as &$report) {
        // Format report type for display
        $report['report_type'] = ucwords(str_replace('_', ' ', $report['report_type']));
        
        // Format status for display
        $report['status'] = ucfirst($report['status']);
        
        // Handle screenshot path
        if ($report['screenshot_path']) {
            $report['screenshot_path'] = $report['screenshot_path'];
        }
        
        // Format reported user name
        if ($report['reported_user_first_name'] || $report['reported_user_last_name']) {
            $report['reported_user_name'] = trim($report['reported_user_first_name'] . ' ' . $report['reported_user_last_name']);
        } else {
            $report['reported_user_name'] = $report['reported_user_email'];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'reports' => $reports]);
    
} catch (Exception $e) {
    error_log("Error in get_user_reports.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
} 