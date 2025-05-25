<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

echo "<h1>Available Albums</h1>";

try {
    $stmt = $pdo->query("SELECT * FROM user_media_albums");
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($albums)) {
        echo "<p>No albums found in the database.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Album Name</th><th>Description</th><th>Privacy</th><th>Created At</th></tr>";
        
        foreach ($albums as $album) {
            echo "<tr>";
            echo "<td>" . $album['id'] . "</td>";
            echo "<td>" . $album['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($album['album_name']) . "</td>";
            echo "<td>" . htmlspecialchars($album['description'] ?? '') . "</td>";
            echo "<td>" . $album['privacy'] . "</td>";
            echo "<td>" . $album['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}