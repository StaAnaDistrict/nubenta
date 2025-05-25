<?php
// Enable error reporting during development
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
$success = false;
$error = null;
$details = [];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get all albums for this user
    $stmt = $pdo->prepare("
        SELECT * FROM user_media_albums 
        WHERE user_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$user['id']]);
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find duplicate Default Gallery albums
    $defaultAlbums = [];
    foreach ($albums as $album) {
        if ($album['album_name'] == 'Default Gallery') {
            $defaultAlbums[] = $album;
        }
    }
    
    if (count($defaultAlbums) > 1) {
        $details[] = "Found " . count($defaultAlbums) . " Default Gallery albums";
        
        // Keep the first one
        $keepAlbum = $defaultAlbums[0];
        $details[] = "Keeping album ID " . $keepAlbum['id'];
        
        // Delete the others
        for ($i = 1; $i < count($defaultAlbums); $i++) {
            $deleteAlbum = $defaultAlbums[$i];
            $details[] = "Deleting duplicate album ID " . $deleteAlbum['id'];
            
            // First, move any media to the album we're keeping
            $mediaStmt = $pdo->prepare("
                SELECT media_id FROM album_media
                WHERE album_id = ?
            ");
            $mediaStmt->execute([$deleteAlbum['id']]);
            $mediaIds = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($mediaIds)) {
                $details[] = "Found " . count($mediaIds) . " media items to move";
                
                foreach ($mediaIds as $mediaId) {
                    // Check if this media is already in the album we're keeping
                    $checkStmt = $pdo->prepare("
                        SELECT 1 FROM album_media 
                        WHERE album_id = ? AND media_id = ?
                    ");
                    $checkStmt->execute([$keepAlbum['id'], $mediaId]);
                    
                    if (!$checkStmt->fetch()) {
                        // Add to the album we're keeping
                        $insertStmt = $pdo->prepare("
                            INSERT INTO album_media (album_id, media_id, created_at)
                            VALUES (?, ?, NOW())
                        ");
                        $insertStmt->execute([$keepAlbum['id'], $mediaId]);
                        $details[] = "Moved media ID " . $mediaId . " to album ID " . $keepAlbum['id'];
                    }
                }
            }
            
            // Delete album_media entries
            $deleteMediaStmt = $pdo->prepare("
                DELETE FROM album_media 
                WHERE album_id = ?
            ");
            $deleteMediaStmt->execute([$deleteAlbum['id']]);
            
            // Delete the album
            $deleteAlbumStmt = $pdo->prepare("
                DELETE FROM user_media_albums 
                WHERE id = ?
            ");
            $deleteAlbumStmt->execute([$deleteAlbum['id']]);
        }
        
        $success = true;
    } else {
        $details[] = "No duplicate Default Gallery albums found";
        $success = true;
    }
    
    // Commit transaction
    $pdo->commit();
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $error = "Database error: " . $e->getMessage();
    $details[] = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Fix Albums - Nubenta</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Fix Albums</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> Album fix completed successfully!
                            </div>
                        <?php endif; ?>
                        
                        <p>This utility removes duplicate Default Gallery albums from your account.</p>
                        
                        <?php if (!empty($details)): ?>
                            <div class="mt-4">
                                <h5>Fix Details:</h5>
                                <div class="card">
                                    <div class="card-body bg-light">
                                        <pre class="mb-0"><?php echo implode("\n", $details); ?></pre>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="manage_albums.php" class="btn btn-dark">
                                <i class="fas fa-arrow-left me-2"></i> Return to Albums
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>