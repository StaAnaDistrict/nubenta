<?php
/**
 * debug_notification_links.php - Debug notification link generation
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    die('Please log in to view this page.');
}

$user = $_SESSION['user'];

// Get the latest media notification
$stmt = $pdo->prepare("
    SELECT n.*, 
           CONCAT_WS(' ', u.first_name, u.last_name) as actor_name
    FROM notifications n
    JOIN users u ON n.actor_id = u.id
    WHERE n.user_id = ? AND n.media_id IS NOT NULL
    ORDER BY n.created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Raw Notification Data:</h3>";
echo "<pre>";
print_r($notifications);
echo "</pre>";

// Test the API directly
echo "<h3>API Response:</h3>";
$apiUrl = "http://localhost/nubenta/api/get_notifications.php?limit=5";
$response = file_get_contents($apiUrl);
echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";

// Test link generation manually
if (!empty($notifications)) {
    $notification = $notifications[0];
    echo "<h3>Manual Link Generation Test:</h3>";
    
    if ($notification['media_id']) {
        $mediaStmt = $pdo->prepare("SELECT user_id FROM user_media WHERE id = ?");
        $mediaStmt->execute([$notification['media_id']]);
        $mediaOwner = $mediaStmt->fetch(PDO::FETCH_ASSOC);
        $ownerId = $mediaOwner ? $mediaOwner['user_id'] : $user['id'];
        $expectedLink = "view_album.php?id={$ownerId}&media_id={$notification['media_id']}&source=notification";
        
        echo "<p><strong>Media ID:</strong> {$notification['media_id']}</p>";
        echo "<p><strong>Media Owner ID:</strong> {$ownerId}</p>";
        echo "<p><strong>Expected Link:</strong> {$expectedLink}</p>";
        echo "<p><strong>Test Link:</strong> <a href='{$expectedLink}' target='_blank'>{$expectedLink}</a></p>";
    }
}
?>

<a href="notifications.php">Back to Notifications</a>
