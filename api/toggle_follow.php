<?php
// Ensure this path is correct from the 'api' directory to the root 'bootstrap.php'
require_once __DIR__ . '/../bootstrap.php'; // Uses $pdo from bootstrap.php (via db.php)
require_once __DIR__ . '/../includes/FollowManager.php';

header('Content-Type: application/json');

// bootstrap.php should handle session_start(). If not, uncomment next line.
// if (session_status() === PHP_SESSION_NONE) { session_start(); }

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
$followedEntityIdParam = $_POST['followed_id']; // Keep as string for validation
$action = $_POST['action'];
$followedEntityType = 'user'; // Hardcoded for user-to-user follows

if ($action !== 'toggle') {
    echo json_encode([
        'success' => false,
        'message' => "Invalid action. Expected 'toggle'."
    ]);
    exit;
}

if (!filter_var($followedEntityIdParam, FILTER_VALIDATE_INT)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid followed_id format. It should be an integer.'
    ]);
    exit;
}
$followedEntityId = (int)$followedEntityIdParam; // Now cast to int

if ((int)$followerId === $followedEntityId) { // Ensure strict comparison after casting followerId if it's string from session
    echo json_encode([
        'success' => false,
        'message' => 'You cannot follow/unfollow yourself.'
    ]);
    exit;
}

try {
    // $pdo variable should be available here from bootstrap.php (which includes db.php)
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        error_log("API Error in toggle_follow.php: \$pdo is not available or not a PDO instance. Check bootstrap.php and db.php.");
        echo json_encode([
            'success' => false,
            'message' => 'Database connection is not available. Please contact support.'
        ]);
        exit;
    }
    
    $followManager = new FollowManager($pdo);

    // Perform the toggle action
    $actionSucceeded = $followManager->toggleFollow((int)$followerId, (string)$followedEntityId, $followedEntityType);

    // Regardless of toggle success/failure, get the current definitive state
    $isCurrentlyFollowing = $followManager->isFollowing((int)$followerId, (string)$followedEntityId, $followedEntityType);
    $currentFollowersCount = $followManager->getFollowersCount((string)$followedEntityId, $followedEntityType);

    if ($actionSucceeded) {
        echo json_encode([
            'success' => true,
            'isFollowing' => $isCurrentlyFollowing, // This reflects the state AFTER the toggle
            'newFollowersCount' => $currentFollowersCount,
            'message' => 'Follow status updated successfully.'
        ]);
    } else {
        // toggleFollow returned false, meaning the DB operation itself failed
        echo json_encode([
            'success' => false,
            'isFollowing' => $isCurrentlyFollowing, // Return the actual current state
            'newFollowersCount' => $currentFollowersCount, // Return the actual current count
            'message' => 'Could not update follow status in the database.'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in toggle_follow.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. (PDO)' // Avoid exposing $e->getMessage() to client
    ]);
} catch (Exception $e) {
    error_log("General error in toggle_follow.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.' // Avoid exposing $e->getMessage() to client
    ]);
}
?>