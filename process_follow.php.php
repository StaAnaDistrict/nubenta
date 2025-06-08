<?php
session_start();
require_once 'db.php'; // Provides $pdo
require_once 'includes/FollowManager.php'; // Path to the new class

// Function to check if it's an AJAX request
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Ensure user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not logged in.', 'isFollowing' => false, 'followerCount' => 0]);
        exit;
    }
    header('Location: login.php');
    exit;
}

$current_user_id = (int)$_SESSION['user']['id'];

// Check if followed_id is provided via POST
if (!isset($_POST['followed_id'])) {
    error_log("process_follow.php.php: followed_id not set in POST data. User ID: " . $current_user_id);
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'followed_id not provided.', 'isFollowing' => false, 'followerCount' => 0]);
        exit;
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}

$followed_entity_id = (int)$_POST['followed_id'];

// Basic validation for followed_entity_id
if ($followed_entity_id <= 0) {
    error_log("process_follow.php.php: Invalid followed_id provided: " . $_POST['followed_id'] . ". User ID: " . $current_user_id);
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid followed_id.', 'isFollowing' => false, 'followerCount' => 0]);
        exit;
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}

// Instantiate FollowManager
if (!isset($pdo)) {
    error_log("process_follow.php.php: PDO object not available from db.php. User ID: " . $current_user_id);
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection error.', 'isFollowing' => false, 'followerCount' => 0]);
        exit;
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}
$followManager = new FollowManager($pdo);

// Perform the toggle follow action
$actionSuccess = $followManager->toggleFollow($current_user_id, (string)$followed_entity_id, 'user');
$message = '';

if (!$actionSuccess) {
    $errorMessage = 'Could not complete follow action.';
    // Check if the failure was due to attempting to follow oneself,
    // FollowManager->toggleFollow already has a check, but we can be more specific in message here
    // This check is simplified; a more robust way would be for toggleFollow to return a specific error code/type.
    if ($followed_entity_id === $current_user_id && (string)$_POST['followed_id'] === (string)$current_user_id) {
        $errorMessage = 'You cannot follow yourself.';
    }
    error_log("process_follow.php.php: toggleFollow returned false. User ID: {$current_user_id}, Entity ID: {$followed_entity_id}. Error: " . $errorMessage);
    $message = $errorMessage;
} else {
    $message = 'Follow status updated successfully.';
}

// For AJAX requests, return JSON
if (is_ajax_request()) {
    $newIsFollowing = $followManager->isFollowing($current_user_id, (string)$followed_entity_id, 'user');
    $newFollowerCount = $followManager->getFollowersCount((string)$followed_entity_id, 'user');

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $actionSuccess,
        'isFollowing' => $newIsFollowing,
        'followerCount' => $newFollowerCount,
        'message' => $message
    ]);
    exit;
}

// For non-AJAX requests, redirect back (existing behavior)
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit;
?>
