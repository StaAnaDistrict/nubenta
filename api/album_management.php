<?php
/**
 * API endpoint for album management operations
 */

// Enable error reporting during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../db.php';
require_once '../includes/MediaUploader.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

$user = $_SESSION['user'];
$mediaUploader = new MediaUploader($pdo);

// Get request data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'create':
        if ($requestMethod !== 'POST') {
            respondWithError('Invalid request method');
        }
        
        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        // Validate required fields
        if (empty($data['album_name'])) {
            respondWithError('Album name is required');
        }
        
        // Create album
        $result = $mediaUploader->createAlbum(
            $user['id'],
            $data['album_name'],
            $data['description'] ?? '',
            $data['privacy'] ?? 'public',
            $data['media_ids'] ?? []
        );
        
        echo json_encode($result);
        break;
        
    case 'delete':
        if ($requestMethod !== 'POST' && $requestMethod !== 'DELETE') {
            respondWithError('Invalid request method');
        }
        
        // Get album ID
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $albumId = $data['album_id'] ?? null;
        if (!$albumId) {
            respondWithError('Album ID is required');
        }
        
        // Delete album
        $result = $mediaUploader->deleteAlbum($albumId, $user['id']);
        
        echo json_encode($result);
        break;
        
    case 'get_albums':
        if ($requestMethod !== 'GET') {
            respondWithError('Invalid request method');
        }
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 12;
        
        // Get albums
        $result = $mediaUploader->getUserAlbums($user['id'], $page, $perPage);
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
        break;
        
    case 'add_media':
        if ($requestMethod !== 'POST') {
            respondWithError('Invalid request method');
        }
        
        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        // Validate required fields
        if (empty($data['album_id']) || empty($data['media_ids'])) {
            respondWithError('Album ID and media IDs are required');
        }
        
        // Add media to album
        $result = $mediaUploader->addMediaToAlbum(
            $data['album_id'],
            $data['media_ids'],
            $user['id']
        );
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Media added to album successfully' : 'Failed to add media to album'
        ]);
        break;
        
    case 'update_privacy':
        if ($requestMethod !== 'POST') {
            respondWithError('Invalid request method');
        }
        
        // Get POST data
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        // Validate required fields
        if (empty($data['album_id']) || empty($data['privacy'])) {
            respondWithError('Album ID and privacy setting are required');
        }
        
        // Update album privacy
        $stmt = $pdo->prepare("
            UPDATE user_media_albums
            SET privacy = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $result = $stmt->execute([
            $data['privacy'],
            $data['album_id'],
            $user['id']
        ]);
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Privacy updated successfully' : 'Failed to update privacy'
        ]);
        break;
        
    case 'set_default_always_public':
        if ($requestMethod !== 'POST') {
            respondWithError('Invalid request method');
        }
        
        // Set user preference for default gallery always public
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, preference_key, preference_value)
            VALUES (?, 'default_gallery_always_public', 'true')
            ON DUPLICATE KEY UPDATE preference_value = 'true'
        ");
        
        $result = $stmt->execute([$user['id']]);
        
        // Also update current default gallery to public
        if ($result) {
            $updateStmt = $pdo->prepare("
                UPDATE user_media_albums
                SET privacy = 'public'
                WHERE (id = 1 OR album_name = 'Default Gallery') AND user_id = ?
            ");
            
            $updateStmt->execute([$user['id']]);
        }
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Default gallery will now always be public' : 'Failed to update preference'
        ]);
        break;
        
    default:
        respondWithError('Invalid action');
}

/**
 * Respond with error message
 * @param string $message Error message
 */
function respondWithError($message) {
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit();
}
