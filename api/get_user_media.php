<?php
session_start();
require_once '../db.php';
require_once '../includes/MediaUploader.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user']['id'];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$mediaType = isset($_GET['media_type']) ? $_GET['media_type'] : null;

// Initialize media uploader
$mediaUploader = new MediaUploader($pdo);

// Get user media
$media = $mediaUploader->getUserMediaByType($userId, $mediaType, $limit, $offset);

// Return JSON response
echo json_encode([
    'success' => true,
    'media' => $media,
    'count' => count($media),
    'has_more' => count($media) === $limit
]);
?>
