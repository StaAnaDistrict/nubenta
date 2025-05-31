<?php
/**
 * API: Write Testimonial
 * Handles testimonial submission with media support
 */

session_start();
require_once '../bootstrap.php';
require_once '../includes/TestimonialManager.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$currentUserId = $_SESSION['user']['id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST; // Fallback to form data for file uploads
    }
    
    $recipientUserId = isset($input['recipient_user_id']) ? (int)$input['recipient_user_id'] : 0;
    $content = isset($input['content']) ? trim($input['content']) : '';
    $externalMediaUrl = isset($input['external_media_url']) ? trim($input['external_media_url']) : '';
    
    // Validation
    if (!$recipientUserId || !$content) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields: recipient_user_id and content'
        ]);
        exit;
    }
    
    // Prevent self-testimonials
    if ($recipientUserId === $currentUserId) {
        echo json_encode([
            'success' => false, 
            'error' => 'You cannot write a testimonial for yourself'
        ]);
        exit;
    }
    
    // Sanitize content (allow basic HTML)
    $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><blockquote>';
    $content = strip_tags($content, $allowedTags);
    
    // Validate external media URL if provided
    if ($externalMediaUrl && !filter_var($externalMediaUrl, FILTER_VALIDATE_URL)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid external media URL'
        ]);
        exit;
    }
    
    try {
        $testimonialManager = new TestimonialManager($pdo);
        
        // Handle file upload if present
        $mediaUrl = null;
        $mediaType = null;
        
        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleMediaUpload($_FILES['media']);
            if ($uploadResult['success']) {
                $mediaUrl = $uploadResult['url'];
                $mediaType = $uploadResult['type'];
            } else {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Media upload failed: ' . $uploadResult['error']
                ]);
                exit;
            }
        }
        
        // Create testimonial
        $testimonialData = [
            'writer_user_id' => $currentUserId,
            'recipient_user_id' => $recipientUserId,
            'content' => $content,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'external_media_url' => $externalMediaUrl ?: null
        ];
        
        $result = $testimonialManager->createTestimonial($testimonialData);
        
        if ($result['success']) {
            // Log activity
            logActivity($pdo, $currentUserId, 'testimonial_written', [
                'recipient_user_id' => $recipientUserId,
                'testimonial_id' => $result['testimonial_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Testimonial submitted successfully! It will be visible once approved.',
                'testimonial_id' => $result['testimonial_id']
            ]);
        } else {
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred while submitting the testimonial: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
}

/**
 * Handle media file upload
 */
function handleMediaUpload($file) {
    $uploadDir = '../uploads/testimonial_media_types/';
    $allowedTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'video/mp4' => ['mp4'],
        'video/webm' => ['webm'],
        'video/quicktime' => ['mov']
    ];
    
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    $maxVideoSize = 50 * 1024 * 1024; // 50MB for videos
    
    // Check file size
    if ($file['size'] > $maxVideoSize) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 50MB for videos, 10MB for images.'];
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!array_key_exists($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, MP4, WebM, MOV'];
    }
    
    // Additional size check for images
    if (strpos($mimeType, 'image/') === 0 && $file['size'] > $maxFileSize) {
        return ['success' => false, 'error' => 'Image too large. Maximum size is 10MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('testimonial_') . '_' . time() . '.' . $extension;
    
    // Determine subdirectory based on type
    if (strpos($mimeType, 'image/') === 0) {
        $subDir = 'images/';
        $mediaType = 'image';
    } else {
        $subDir = 'videos/';
        $mediaType = 'video';
    }
    
    $targetDir = $uploadDir . $subDir;
    $targetPath = $targetDir . $filename;
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $relativeUrl = 'uploads/testimonial_media_types/' . $subDir . $filename;
        return [
            'success' => true,
            'url' => $relativeUrl,
            'type' => $mediaType,
            'filename' => $filename
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to save uploaded file'];
    }
}

/**
 * Log user activity
 */
function logActivity($pdo, $userId, $action, $data = []) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_activity (user_id, action, data, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, json_encode($data)]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>