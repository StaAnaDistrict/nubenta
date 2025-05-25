<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'includes/MediaUploader.php';

echo "<h1>Syncing Media Tables</h1>";

$mediaUploader = new MediaUploader($pdo);

// Ensure tables exist
$mediaUploader->ensureTablesExist();

// Get all posts with media
$stmt = $pdo->query("
    SELECT id, user_id, media, created_at 
    FROM posts 
    WHERE media IS NOT NULL AND media != '[]' AND media != ''
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Found " . count($posts) . " posts with media</p>";

$totalMediaItems = 0;
$successfullyImported = 0;

foreach ($posts as $post) {
    // Try to decode as JSON first
    $mediaPaths = json_decode($post['media'], true);
    
    // If not valid JSON or not an array, treat as a single path
    if (empty($mediaPaths) || !is_array($mediaPaths)) {
        $mediaPaths = [$post['media']];
    }
    
    echo "<p>Processing post ID " . $post['id'] . " with " . count($mediaPaths) . " media items</p>";
    $totalMediaItems += count($mediaPaths);
    
    // Insert each media item
    $mediaIds = [];
    foreach ($mediaPaths as $path) {
        // Determine media type from file extension
        $mediaType = 'image'; // Default
        if (preg_match('/\.(mp4|mov|avi|wmv)$/i', $path)) {
            $mediaType = 'video';
        } elseif (preg_match('/\.(mp3|wav|ogg)$/i', $path)) {
            $mediaType = 'audio';
        }
        
        // Insert into user_media
        $stmt = $pdo->prepare("
            INSERT INTO user_media 
            (user_id, media_url, media_type, post_id, created_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([
                $post['user_id'],
                $path,
                $mediaType,
                $post['id'],
                $post['created_at']
            ]);
            
            $mediaIds[] = $pdo->lastInsertId();
            $successfullyImported++;
            
        } catch (PDOException $e) {
            echo "<p>Error importing media: " . $e->getMessage() . "</p>";
        }
    }
    
    // Create or update "Posts" album for this user
    if (!empty($mediaIds)) {
        // Check if "Posts" album exists for this user
        $stmt = $pdo->prepare("
            SELECT id FROM user_media_albums 
            WHERE user_id = ? AND album_name = 'Posts'
        ");
        $stmt->execute([$post['user_id']]);
        $postsAlbum = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($postsAlbum) {
            // Add media to existing album
            foreach ($mediaIds as $mediaId) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO album_media 
                        (album_id, media_id, created_at) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $postsAlbum['id'],
                        $mediaId,
                        $post['created_at']
                    ]);
                } catch (PDOException $e) {
                    echo "<p>Error adding media to album: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            // Create a new "Posts" album
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO user_media_albums 
                    (user_id, album_name, description, privacy, created_at) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $post['user_id'],
                    'Posts',
                    'Media shared in posts',
                    'public',
                    $post['created_at']
                ]);
                
                $albumId = $pdo->lastInsertId();
                
                // Add media to the new album
                foreach ($mediaIds as $mediaId) {
                    $stmt = $pdo->prepare("
                        INSERT INTO album_media 
                        (album_id, media_id, created_at) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $albumId,
                        $mediaId,
                        $post['created_at']
                    ]);
                }
            } catch (PDOException $e) {
                echo "<p>Error creating album: " . $e->getMessage() . "</p>";
            }
        }
    }
}

echo "<h2>Summary</h2>";
echo "<p>Total media items found: $totalMediaItems</p>";
echo "<p>Successfully imported: $successfullyImported</p>";
echo "<p>Done!</p>";
?>
