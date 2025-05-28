<?php
/**
 * debug_notifications.php - Debug notification creation for media
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    die('Please log in to view this page.');
}

$user = $_SESSION['user'];

try {
    // Check recent media comments
    echo "<h3>Recent Media Comments (media_id=24):</h3>";
    $stmt = $pdo->prepare("
        SELECT mc.*, um.user_id as media_owner_id,
               CONCAT_WS(' ', u.first_name, u.last_name) as commenter_name
        FROM media_comments mc
        JOIN user_media um ON mc.media_id = um.id
        JOIN users u ON mc.user_id = u.id
        WHERE mc.media_id = 24
        ORDER BY mc.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($comments);
    echo "</pre>";

    // Check if notifications exist for media_id=24
    echo "<h3>Notifications for media_id=24:</h3>";
    $stmt = $pdo->prepare("
        SELECT n.*, 
               CONCAT_WS(' ', u.first_name, u.last_name) as actor_name
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.media_id = 24
        ORDER BY n.created_at DESC
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($notifications);
    echo "</pre>";

    // Check all recent notifications for current user
    echo "<h3>All Recent Notifications for User {$user['id']}:</h3>";
    $stmt = $pdo->prepare("
        SELECT n.*, 
               CONCAT_WS(' ', u.first_name, u.last_name) as actor_name
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $allNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($allNotifications);
    echo "</pre>";

    // Check the structure of notifications table
    echo "<h3>Notifications Table Structure:</h3>";
    $stmt = $pdo->prepare("DESCRIBE notifications");
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($structure);
    echo "</pre>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<a href="dashboard.php">Back to Dashboard</a>
