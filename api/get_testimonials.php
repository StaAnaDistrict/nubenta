<?php
/**
 * API: Get Testimonials
 * Retrieves testimonials based on type and user
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

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $currentUserId;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $testimonialManager = new TestimonialManager($pdo);
    
    switch ($type) {
        case 'received':
            // Handle different filters for received testimonials
            switch ($filter) {
                case 'pending':
                    // Only allow users to see their own pending testimonials
                    if ($userId !== $currentUserId) {
                        echo json_encode(['success' => false, 'error' => 'Access denied']);
                        exit;
                    }
                    $result = $testimonialManager->getPendingTestimonials($userId);
                    break;
                    
                case 'approved':
                    $result = $testimonialManager->getApprovedTestimonialsForProfile($userId, $limit);
                    break;
                    
                case 'all':
                default:
                    // Only allow users to see their own testimonials (all statuses)
                    if ($userId !== $currentUserId) {
                        // For other users, only show approved testimonials
                        $result = $testimonialManager->getApprovedTestimonialsForProfile($userId, $limit);
                    } else {
                        $result = $testimonialManager->getAllTestimonialsForUser($userId, $limit);
                    }
                    break;
            }
            break;
            
        case 'pending':
            // Only allow users to see their own pending testimonials
            if ($userId !== $currentUserId) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            $result = $testimonialManager->getPendingTestimonials($userId);
            break;
            
        case 'approved':
            $result = $testimonialManager->getApprovedTestimonialsForProfile($userId, $limit);
            break;
            
        case 'written':
            // Only allow users to see their own written testimonials
            if ($userId !== $currentUserId) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit;
            }
            $result = $testimonialManager->getTestimonialsWrittenByUser($userId);
            break;
            
        case 'stats':
            $result = $testimonialManager->getTestimonialStats($userId);
            break;
            
        default:
            $result = ['success' => false, 'error' => 'Invalid type. Use: received, pending, approved, written, or stats'];
            break;
    }
    
    echo json_encode($result);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only GET method allowed']);
}
?>