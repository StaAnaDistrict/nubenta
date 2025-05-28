<?php
/**
 * test_api_session.php - Test session and API consistency
 */

session_start();
require_once 'db.php';

echo "<h3>Session Test</h3>";
echo "<p><strong>Session User ID:</strong> " . ($_SESSION['user']['id'] ?? 'Not set') . "</p>";
echo "<p><strong>Session User Name:</strong> " . ($_SESSION['user']['first_name'] ?? 'Not set') . " " . ($_SESSION['user']['last_name'] ?? 'Not set') . "</p>";

if (isset($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];
    
    echo "<h3>Direct Database Query (Same as API)</h3>";
    
    // Same query as the API
    $stmt = $pdo->prepare("
        SELECT n.*,
               u.first_name,
               u.last_name,
               u.profile_pic,
               u.gender
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Found:</strong> " . count($notifications) . " notifications</p>";
    
    echo "<h4>Notifications:</h4>";
    foreach ($notifications as $notif) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
        echo "<strong>ID:</strong> {$notif['id']}<br>";
        echo "<strong>Type:</strong> {$notif['type']}<br>";
        echo "<strong>Post ID:</strong> " . ($notif['post_id'] ?: 'NULL') . "<br>";
        echo "<strong>Media ID:</strong> " . ($notif['media_id'] ?: 'NULL') . "<br>";
        echo "<strong>Actor:</strong> {$notif['first_name']} {$notif['last_name']}<br>";
        echo "<strong>Created:</strong> {$notif['created_at']}<br>";
        echo "</div>";
    }
    
    echo "<h3>API Test</h3>";
    echo "<p><a href='api/get_notifications.php?limit=10' target='_blank'>Test API directly</a></p>";
    
} else {
    echo "<p style='color: red;'>No user session found!</p>";
}
?>

<a href="notifications.php">Back to Notifications</a>
