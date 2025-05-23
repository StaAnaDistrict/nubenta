<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    $userId = $_SESSION['user']['id'];
    $threadId = $_POST['thread_id'] ?? null;
    $content = $_POST['content'] ?? '';

    if (!$threadId) {
        throw new Exception('Thread ID is required');
    }
    
    // Start transaction
    $pdo->beginTransaction();

    // First verify that the user is a participant in this thread
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM thread_participants 
        WHERE thread_id = ? AND user_id = ?
    ");
    $stmt->execute([$threadId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('You are not a participant in this thread');
    }

    // Get the receiver_id from thread participants
    $stmt = $pdo->prepare("
        SELECT tp.user_id 
        FROM thread_participants tp 
        WHERE tp.thread_id = ? AND tp.user_id != ?
        LIMIT 1
    ");
    $stmt->execute([$threadId, $userId]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receiver) {
        throw new Exception('No receiver found for this thread');
    }
    
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO messages (thread_id, sender_id, receiver_id, body, sent_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([$threadId, $userId, $receiver['user_id'], $content]);
    $messageId = $pdo->lastInsertId();
    
    // Handle file attachments if any
    if (!empty($_FILES['attachments'])) {
        $max = 10 * 1024 * 1024; // 10 MB
        $allowed = [
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
            // Documents
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            // Text files
            'txt', 'csv',
            // Archives
            'zip', 'rar', '7z'
        ];
        
        // Use absolute paths
        $baseDir = dirname(dirname(__FILE__));
        $sub = date('Y/m');
        $uploadDir = $baseDir . "/uploads/msg_files/$sub";
        
        // Create directory with full permissions
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0777, true)) {
                error_log("Failed to create directory: $uploadDir. Error: " . error_get_last()['message']);
                throw new Exception('Failed to create upload directory');
            }
            chmod($uploadDir, 0777);
        }
        
        // Ensure directory is writable
        if (!is_writable($uploadDir)) {
            error_log("Directory not writable: $uploadDir");
            chmod($uploadDir, 0777);
        }
        
        $filePaths = [];
        $fileInfo = [];
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['attachments']['name'][$key];
                $fileSize = $_FILES['attachments']['size'][$key];
                
                if ($fileSize > $max) {
                    continue;
                }
                
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    continue;
                }
                
                $uuid = bin2hex(random_bytes(8)) . '.' . $ext;
                $filePath = "$uploadDir/$uuid";
                
                // Log file upload attempt
                error_log("Attempting to upload file: $fileName to $filePath");
                
                if (move_uploaded_file($tmp_name, $filePath)) {
                    chmod($filePath, 0644);
                    // Store the relative path without uploads prefix
                    $relativePath = "msg_files/$sub/$uuid";
                    $filePaths[] = $relativePath;
                    
                    // Log successful upload
                    error_log("Successfully uploaded file: $fileName to $filePath");
                    
                    // Store file information
                    $fileInfo[] = [
                        'path' => $relativePath,
                        'name' => $fileName,
                        'size' => $fileSize,
                        'type' => $ext,
                        'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])
                    ];
                } else {
                    // Log upload failure
                    error_log("Failed to upload file: $fileName. Error: " . error_get_last()['message']);
                }
            }
        }
        
        if (!empty($filePaths)) {
            // Update message with file paths and file info
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET file_path = ?,
                    file_info = ?
                WHERE id = ?
            ");
            $stmt->execute([
                implode(',', $filePaths),
                json_encode($fileInfo),
                $messageId
            ]);
        }
    }

    // Get the complete message data
    $stmt = $pdo->prepare("
        SELECT m.*, 
               CONCAT_WS(' ', u.first_name, u.last_name) as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in chat_send.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
