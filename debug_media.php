<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

echo "<h1>Debug Media</h1>";

try {
    // Check if privacy column exists in user_media table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM user_media LIKE 'privacy'");
    $stmt->execute();
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Privacy column exists: " . ($column ? "Yes" : "No") . "</p>";
    
    if (!$column) {
        // Add privacy column if it doesn't exist
        echo "<p>Attempting to add privacy column...</p>";
        $pdo->exec("
            ALTER TABLE user_media 
            ADD COLUMN privacy ENUM('public', 'friends', 'private') NOT NULL DEFAULT 'public'
        ");
        echo "<p>Privacy column added successfully.</p>";
    }
    
    // Get user media
    echo "<p>Attempting to get user media...</p>";
    $stmt = $pdo->prepare("
        SELECT * FROM user_media 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($media) . " media items.</p>";
    
    // Display media details
    if (!empty($media)) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Type</th><th>URL</th><th>Privacy</th></tr>";
        
        foreach ($media as $item) {
            echo "<tr>";
            echo "<td>" . $item['id'] . "</td>";
            echo "<td>" . $item['media_type'] . "</td>";
            echo "<td>" . $item['media_url'] . "</td>";
            echo "<td>" . ($item['privacy'] ?? 'public') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // Check MediaUploader class
    echo "<p>Checking MediaUploader class...</p>";
    if (file_exists('includes/MediaUploader.php')) {
        require_once 'includes/MediaUploader.php';
        echo "<p>MediaUploader class file exists.</p>";
        
        try {
            $mediaUploader = new MediaUploader($pdo);
            echo "<p>MediaUploader instance created successfully.</p>";
            
            // Test getUserMediaByType method
            $testMedia = $mediaUploader->getUserMediaByType($user['id'], null, 5, 0);
            echo "<p>getUserMediaByType returned " . count($testMedia) . " items.</p>";
        } catch (Exception $e) {
            echo "<p>Error creating MediaUploader instance: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>MediaUploader class file not found!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p>General error: " . $e->getMessage() . "</p>";
}
?>