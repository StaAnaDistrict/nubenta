<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$reporter_id = $_SESSION['user']['id'];

// Get POST data
$reported_user_id = $_POST['reported_user_id'] ?? null;
$report_type = $_POST['report_type'] ?? null;
$details = $_POST['details'] ?? null;
$thread_id = $_POST['thread_id'] ?? null;

// Validate required fields
if (!$reported_user_id || !$report_type) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate report type
$valid_report_types = ['harassment', 'spam', 'hate_speech', 'inappropriate_content', 'other'];
if (!in_array($report_type, $valid_report_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid report type']);
    exit;
}

try {
    // Handle screenshot upload if present
    $screenshot_path = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/reports/' . date('Y/m');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
        }
        
        $filename = uniqid() . '.' . $file_extension;
        $filepath = $upload_dir . '/' . $filename;
        
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $filepath)) {
            $screenshot_path = 'uploads/reports/' . date('Y/m') . '/' . $filename;
        }
    }
    
    // Insert the report
    $stmt = $pdo->prepare("
        INSERT INTO user_reports 
        (reporter_id, reported_user_id, thread_id, report_type, details, screenshot_path) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $reporter_id,
        $reported_user_id,
        $thread_id,
        $report_type,
        $details,
        $screenshot_path
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully'
    ]);
} catch (PDOException $e) {
    error_log("Error in submit_report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in submit_report.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 