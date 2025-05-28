<?php
/**
 * Migration Script: Create Profile Pictures Albums
 * 
 * This script creates Profile Pictures albums for all existing users
 * and syncs their current profile pictures to the albums.
 */

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'includes/MediaUploader.php';

echo "<h1>Profile Pictures Album Migration</h1>\n";
echo "<p>Creating Profile Pictures albums for all users...</p>\n";

try {
    // Initialize MediaUploader
    $mediaUploader = new MediaUploader($pdo);
    
    // Get all users
    $stmt = $pdo->prepare("SELECT id, username, profile_pic FROM users ORDER BY id ASC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalUsers = count($users);
    $processedUsers = 0;
    $successCount = 0;
    $errorCount = 0;
    
    echo "<p>Found $totalUsers users to process.</p>\n";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
    
    foreach ($users as $user) {
        $userId = $user['id'];
        $username = $user['username'];
        $profilePic = $user['profile_pic'];
        
        echo "Processing User ID $userId ($username)...\n";
        
        try {
            // Ensure Profile Pictures album exists
            $albumResult = $mediaUploader->ensureProfilePicturesAlbum($userId);
            
            if ($albumResult['success']) {
                $albumId = $albumResult['album_id'];
                echo "  ✅ Profile Pictures album: " . $albumResult['message'] . " (ID: $albumId)\n";
                
                // If user has a profile picture, sync it
                if (!empty($profilePic)) {
                    $profilePicPath = 'uploads/profile_pics/' . $profilePic;
                    
                    if (file_exists($profilePicPath)) {
                        // Check if already synced
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count FROM user_media 
                            WHERE user_id = ? AND media_url = ?
                        ");
                        $stmt->execute([$userId, $profilePicPath]);
                        $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                        
                        if (!$exists) {
                            // Create media entry and add to album
                            $addResult = $mediaUploader->addProfilePictureToAlbum($userId, $profilePic);
                            if ($addResult) {
                                echo "  ✅ Profile picture synced: $profilePic\n";
                            } else {
                                echo "  ⚠️  Failed to sync profile picture: $profilePic\n";
                            }
                        } else {
                            echo "  ℹ️  Profile picture already synced: $profilePic\n";
                        }
                    } else {
                        echo "  ⚠️  Profile picture file not found: $profilePicPath\n";
                    }
                } else {
                    echo "  ℹ️  No profile picture to sync\n";
                }
                
                $successCount++;
            } else {
                echo "  ❌ Failed to create album: " . $albumResult['message'] . "\n";
                $errorCount++;
            }
        } catch (Exception $e) {
            echo "  ❌ Error processing user: " . $e->getMessage() . "\n";
            $errorCount++;
        }
        
        $processedUsers++;
        echo "\n";
        
        // Flush output for real-time display
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    echo "</div>\n";
    echo "<h2>Migration Summary</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Total Users:</strong> $totalUsers</li>\n";
    echo "<li><strong>Processed:</strong> $processedUsers</li>\n";
    echo "<li><strong>Successful:</strong> $successCount</li>\n";
    echo "<li><strong>Errors:</strong> $errorCount</li>\n";
    echo "</ul>\n";
    
    if ($errorCount === 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ Migration completed successfully!</p>\n";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ Migration completed with $errorCount errors. Check the log above for details.</p>\n";
    }
    
    // Show some statistics
    echo "<h2>Album Statistics</h2>\n";
    
    // Count Profile Pictures albums
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM user_media_albums 
        WHERE album_name = 'Profile Pictures'
    ");
    $stmt->execute();
    $profileAlbumsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count synced profile pictures
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM user_media 
        WHERE media_url LIKE 'uploads/profile_pics/%'
    ");
    $stmt->execute();
    $syncedPicsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<ul>\n";
    echo "<li><strong>Profile Pictures albums created:</strong> $profileAlbumsCount</li>\n";
    echo "<li><strong>Profile pictures synced:</strong> $syncedPicsCount</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Migration failed: " . $e->getMessage() . "</p>\n";
    error_log("Profile Pictures migration error: " . $e->getMessage());
}

echo "<p><a href='manage_albums.php'>← Back to Manage Albums</a></p>\n";
?>
