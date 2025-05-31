<?php
/**
 * API: Manage Testimonial
 * Handles testimonial approval, rejection, and deletion
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
    
    $testimonialId = isset($input['testimonial_id']) ? (int)$input['testimonial_id'] : 0;
    $action = isset($input['action']) ? $input['action'] : '';
    
    if (!$testimonialId || !$action) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields: testimonial_id and action'
        ]);
        exit;
    }
    
    $testimonialManager = new TestimonialManager($pdo);
    
    switch ($action) {
        case 'approve':
            $result = $testimonialManager->approveTestimonial($testimonialId, $currentUserId);
            break;
            
        case 'reject':
            $result = $testimonialManager->rejectTestimonial($testimonialId, $currentUserId);
            break;
            
        case 'delete':
            $result = $testimonialManager->deleteTestimonial($testimonialId, $currentUserId);
            break;
            
        default:
            $result = ['success' => false, 'error' => 'Invalid action. Use: approve, reject, or delete'];
            break;
    }
    
    echo json_encode($result);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
}
?>