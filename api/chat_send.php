<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off error display to prevent breaking JSON
ini_set('log_errors', 1); // Log errors instead

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user']['id'];
$threadId = intval($_POST['thread_id'] ?? 0);
$body = trim($_POST['body'] ?? '');

if ($threadId == 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid thread ID']);
    exit;
}

try {
    // Check if user is part of the thread and get the other participant
    $stmt = $pdo->prepare("
        SELECT tp.user_id 
        FROM thread_participants tp 
        WHERE tp.thread_id = ? AND tp.user_id != ?
        LIMIT 1
    ");
    $stmt->execute([$threadId, $userId]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receiver) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No receiver found for this thread']);
        exit;
    }

    // Handle file uploads if present
    $filePaths = [];
    if (!empty($_FILES['files'])) {
        $max = 10 * 1024 * 1024; // 10 MB
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Use absolute paths
        $baseDir = dirname(dirname(__FILE__));
        $sub = date('Y/m');
        $publicDir = $baseDir . "/uploads/msg_files/$sub";
        
        // Create directory with full permissions
        if (!is_dir($publicDir)) {
            if (!@mkdir($publicDir, 0777, true)) {
                error_log("Failed to create directory: $publicDir");
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Failed to create upload directory. Please check permissions.']);
                exit;
            }
            chmod($publicDir, 0777);
        }

        // Handle each file
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['files']['name'][$key];
                $fileSize = $_FILES['files']['size'][$key];
                
                if ($fileSize > $max) {
                    continue; // Skip this file if too large
                }

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    continue; // Skip this file if type not allowed
                }

                $uuid = bin2hex(random_bytes(8)) . '.' . $ext;
                $publicPath = "$publicDir/$uuid";
                
                if (move_uploaded_file($tmp_name, $publicPath)) {
                    chmod($publicPath, 0644);
                    // Store the URL path instead of the filesystem path
                    $filePaths[] = "/uploads/msg_files/$sub/$uuid";
                }
            }
        }
    }

    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (thread_id, sender_id, receiver_id, body, file_path)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Join multiple file paths with a comma if there are multiple files
    $filePath = !empty($filePaths) ? implode(',', $filePaths) : null;
    
    // Log the values we're trying to insert
    error_log("Inserting message with values: thread_id=$threadId, sender_id=$userId, receiver_id={$receiver['user_id']}, body='$body', file_path='$filePath'");
    
    $stmt->execute([$threadId, $userId, $receiver['user_id'], $body, $filePath]);
    $msgId = $pdo->lastInsertId();

    // Queue for scanning if needed
    $tmpDir = "../tmp";
    if (!is_dir($tmpDir)) {
        if (!@mkdir($tmpDir, 0775, true)) {
            error_log("Failed to create directory: $tmpDir");
        }
    }
    
    if (is_dir($tmpDir) && is_writable($tmpDir)) {
        @file_put_contents("$tmpDir/scan_queue.txt", "$msgId\n", FILE_APPEND);
    } else {
        error_log("Cannot write to scan queue: $tmpDir is not writable");
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'id' => $msgId]);
} catch (Exception $e) {
    error_log("Error in chat_send.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
