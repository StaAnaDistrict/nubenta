<?php
/**
 * test_notification_links_fixed.php - Test the fixed notification link generation
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    die('Please log in to view this page.');
}

$user = $_SESSION['user'];

try {
    // Test media_id=24 specifically
    $mediaId = 24;
    
    echo "<h3>Testing Notification Link Generation for Media ID: {$mediaId}</h3>";
    
    // Check if media exists
    $mediaStmt = $pdo->prepare("SELECT * FROM user_media WHERE id = ?");
    $mediaStmt->execute([$mediaId]);
    $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$media) {
        echo "<p style='color: red;'>Media ID {$mediaId} not found!</p>";
        exit;
    }
    
    echo "<h4>Media Details:</h4>";
    echo "<ul>";
    echo "<li><strong>Media ID:</strong> {$media['id']}</li>";
    echo "<li><strong>User ID:</strong> {$media['user_id']}</li>";
    echo "<li><strong>Media URL:</strong> {$media['media_url']}</li>";
    echo "<li><strong>Created:</strong> {$media['created_at']}</li>";
    echo "</ul>";
    
    // Check which album this media belongs to
    $albumStmt = $pdo->prepare("
        SELECT am.album_id, uma.album_name 
        FROM album_media am 
        JOIN user_media_albums uma ON am.album_id = uma.id
        WHERE am.media_id = ?
    ");
    $albumStmt->execute([$mediaId]);
    $albumData = $albumStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h4>Album Information:</h4>";
    if ($albumData) {
        echo "<ul>";
        echo "<li><strong>Album ID:</strong> {$albumData['album_id']}</li>";
        echo "<li><strong>Album Name:</strong> {$albumData['album_name']}</li>";
        echo "</ul>";
        $correctAlbumId = $albumData['album_id'];
    } else {
        echo "<p><strong>Media is NOT in any specific album - should use Default Album (ID: 1)</strong></p>";
        $correctAlbumId = 1;
    }
    
    // Test the new link generation logic
    echo "<h4>Link Generation Test:</h4>";
    
    // Simulate the notification link generation
    $albumStmt = $pdo->prepare("
        SELECT am.album_id 
        FROM album_media am 
        WHERE am.media_id = ? 
        LIMIT 1
    ");
    $albumStmt->execute([$mediaId]);
    $albumResult = $albumStmt->fetch(PDO::FETCH_ASSOC);
    
    $generatedAlbumId = $albumResult ? $albumResult['album_id'] : 1;
    $generatedLink = "view_album.php?id={$generatedAlbumId}&media_id={$mediaId}&source=notification";
    
    echo "<ul>";
    echo "<li><strong>Generated Album ID:</strong> {$generatedAlbumId}</li>";
    echo "<li><strong>Generated Link:</strong> <a href='{$generatedLink}' target='_blank'>{$generatedLink}</a></li>";
    echo "<li><strong>Expected Album ID:</strong> {$correctAlbumId}</li>";
    echo "</ul>";
    
    if ($generatedAlbumId == $correctAlbumId) {
        echo "<div style='color: green; font-weight: bold; padding: 10px; border: 2px solid green; margin: 10px 0;'>";
        echo "✅ SUCCESS: Link generation is working correctly!";
        echo "</div>";
    } else {
        echo "<div style='color: red; font-weight: bold; padding: 10px; border: 2px solid red; margin: 10px 0;'>";
        echo "❌ ERROR: Link generation is incorrect!";
        echo "</div>";
    }
    
    // Test the actual API
    echo "<h4>API Test:</h4>";
    echo "<p><a href='api/get_notifications.php?limit=10' target='_blank'>Test API Response</a></p>";
    
    // Check recent notifications for this media
    $notificationStmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE media_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $notificationStmt->execute([$mediaId]);
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Recent Notifications for Media ID {$mediaId}:</h4>";
    if (empty($notifications)) {
        echo "<p>No notifications found for this media.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Type</th><th>Actor ID</th><th>User ID</th><th>Created</th></tr>";
        foreach ($notifications as $notif) {
            echo "<tr>";
            echo "<td>{$notif['id']}</td>";
            echo "<td>{$notif['type']}</td>";
            echo "<td>{$notif['actor_id']}</td>";
            echo "<td>{$notif['user_id']}</td>";
            echo "<td>{$notif['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check all albums for the media owner
    echo "<h4>All Albums for User {$media['user_id']}:</h4>";
    $userAlbumsStmt = $pdo->prepare("
        SELECT id, album_name, user_id 
        FROM user_media_albums 
        WHERE user_id = ? 
        ORDER BY id
    ");
    $userAlbumsStmt->execute([$media['user_id']]);
    $userAlbums = $userAlbumsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Album ID</th><th>Album Name</th><th>User ID</th><th>Test Link</th></tr>";
    foreach ($userAlbums as $album) {
        $testLink = "view_album.php?id={$album['id']}&media_id={$mediaId}";
        echo "<tr>";
        echo "<td>{$album['id']}</td>";
        echo "<td>{$album['album_name']}</td>";
        echo "<td>{$album['user_id']}</td>";
        echo "<td><a href='{$testLink}' target='_blank'>Test</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div style="margin-top: 20px;">
    <a href="notifications.php">Back to Notifications</a> | 
    <a href="view_album.php?id=1&media_id=24">Test Direct Link</a> |
    <a href="debug_notifications.php">Debug Notifications</a>
</div>
