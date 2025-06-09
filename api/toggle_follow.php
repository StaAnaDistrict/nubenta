<?php
session_start();
require_once '../bootstrap.php'; // For DB connection, session, and utilities
require_once '../includes/FollowManager.php'; // Include the FollowManager class

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in.'
    ]);
    exit;
}

if (!isset($_POST['followed_id']) || !isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Required parameters missing (followed_id, action).'
    ]);
    exit;
}

$followerId = $_SESSION['user']['id'];
$followedEntityId = $_POST['followed_id'];
$action = $_POST['action'];
$followedEntityType = 'user'; // Hardcoded for this task

if ($action !== 'toggle') {
    echo json_encode([
        'success' => false,
        'message' => "Invalid action. Expected 'toggle'."
    ]);
    exit;
}

// Basic validation for followedEntityId (should be an integer)
if (!filter_var($followedEntityId, FILTER_VALIDATE_INT)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid followed_id format.'
    ]);
    exit;
}
$followedEntityId = (int)$followedEntityId;

if ($followerId == $followedEntityId) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot follow yourself.'
    ]);
    exit;
}


try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $followManager = new FollowManager($db);

    // Check if the user being followed exists (optional, but good practice)
    // This might require a UserManager or similar, or a simple query.
    // For now, we assume FollowManager handles non-existent users gracefully or the profile page ensures existence.

    $toggleResult = $followManager->toggleFollow($followerId, $followedEntityId, $followedEntityType);

    if ($toggleResult['success']) {
        $isFollowing = $followManager->isFollowing($followerId, $followedEntityId, $followedEntityType);
        $newFollowersCount = $followManager->getFollowersCount($followedEntityId, $followedEntityType);

        echo json_encode([
            'success' => true,
            'isFollowing' => $isFollowing,
            'newFollowersCount' => $newFollowersCount,
            'message' => $toggleResult['message'] // Message from toggleFollow
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $toggleResult['message'] ?? 'Could not process follow/unfollow action.'
        ]);
    }

} catch (PDOException $e) {
    // Log error to server log instead of exposing to client
    error_log("Database error in toggle_follow.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    // Log error to server log
    error_log("General error in toggle_follow.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}

?>
