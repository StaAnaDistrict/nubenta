<?php
/**
 * API: Submit Testimonial
 * Handles testimonial submission
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
        $input = $_POST; // Fallback to form data
    }
    
    $recipientUserId = isset($input['recipient_user_id']) ? (int)$input['recipient_user_id'] : 0;
    $content = isset($input['content']) ? trim($input['content']) : '';
    $rating = isset($input['rating']) ? (int)$input['rating'] : 5; // Default to 5 if not provided
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $rating = 5; // Default to 5 if invalid
    }
    
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
    
    // Sanitize content
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    try {
        $testimonialManager = new TestimonialManager($pdo);
        
        // Create testimonial
        $testimonialData = [
            'writer_user_id' => $currentUserId,
            'recipient_user_id' => $recipientUserId,
            'content' => $content,
            'media_url' => null,
            'media_type' => null,
            'external_media_url' => null,
            'rating' => $rating
        ];
        
        $result = $testimonialManager->createTestimonial($testimonialData);
        
        if ($result['success']) {
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
?>