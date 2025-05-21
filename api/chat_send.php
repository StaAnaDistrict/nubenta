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
        error_log("Files received in chat_send.php: " . print_r($_FILES['files'], true));
        $max = 10 * 1024 * 1024; // 10 MB
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Use absolute paths
        $baseDir = dirname(dirname(__FILE__));
    $sub = date('Y/m');
        $publicDir = $baseDir . "/uploads/msg_files/$sub";
        
        error_log("Upload directory path: $publicDir");
        error_log("Directory exists: " . (is_dir($publicDir) ? 'yes' : 'no'));
        error_log("Directory writable: " . (is_writable($publicDir) ? 'yes' : 'no'));
        
        // Create directory with full permissions
        if (!is_dir($publicDir)) {
            error_log("Creating directory: $publicDir");
            if (!@mkdir($publicDir, 0777, true)) {
                error_log("Failed to create directory: $publicDir");
                error_log("Last error: " . error_get_last()['message']);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Failed to create upload directory. Please check permissions.']);
                exit;
            }
            chmod($publicDir, 0777);
        }

        // Ensure the directory is writable
        if (!is_writable($publicDir)) {
            error_log("Directory not writable: $publicDir");
            error_log("Directory permissions: " . substr(sprintf('%o', fileperms($publicDir)), -4));
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Upload directory not writable. Please check permissions.']);
            exit;
        }

        // Handle each file
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            error_log("Processing file $key: " . $_FILES['files']['name'][$key]);
            error_log("Temp file exists: " . (file_exists($tmp_name) ? 'yes' : 'no'));
            error_log("Temp file readable: " . (is_readable($tmp_name) ? 'yes' : 'no'));
            
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['files']['name'][$key];
                $fileSize = $_FILES['files']['size'][$key];
                
                if ($fileSize > $max) {
                    error_log("File too large: $fileName ($fileSize bytes)");
                    continue;
                }

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    error_log("File type not allowed: $fileName ($ext)");
                    continue;
                }

                $uuid = bin2hex(random_bytes(8)) . '.' . $ext;
    $publicPath = "$publicDir/$uuid";
                
                error_log("Attempting to move file to: $publicPath");
                if (move_uploaded_file($tmp_name, $publicPath)) {
                    chmod($publicPath, 0644);
                    // Store the URL path instead of the filesystem path
                    $filePaths[] = "/uploads/msg_files/$sub/$uuid";
                    error_log("Successfully uploaded file: $publicPath");
                    error_log("File exists after upload: " . (file_exists($publicPath) ? 'yes' : 'no'));
                    error_log("File permissions after upload: " . substr(sprintf('%o', fileperms($publicPath)), -4));
                } else {
                    error_log("Failed to move uploaded file to: $publicPath");
                    error_log("Upload error: " . error_get_last()['message']);
}
            } else {
                error_log("File upload error: " . $_FILES['files']['error'][$key]);
            }
        }
    }

    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (thread_id, sender_id, receiver_id, body, file_path, sent_at)
        VALUES (?, ?, ?, ?, ?, NOW())
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

    // Return success response with file paths
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true, 
        'id' => $msgId,
        'file_paths' => $filePaths // Include file paths in response
    ]);
} catch (Exception $e) {
    error_log("Error in chat_send.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
