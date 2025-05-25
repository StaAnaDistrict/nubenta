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

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // 1. Find all default albums for this user
    $stmt = $pdo->prepare("
        SELECT id FROM user_media_albums 
        WHERE user_id = ? AND (id = 1 OR album_name = 'Default Gallery')
        ORDER BY id ASC
    ");
    $stmt->execute([$user['id']]);
    $defaultAlbums = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($defaultAlbums) > 1) {
        // Keep the first one (lowest ID)
        $keepId = $defaultAlbums[0];
        
        // Get all media from other default albums
        $mediaStmt = $pdo->prepare("
            SELECT DISTINCT media_id FROM album_media
            WHERE album_id IN (" . implode(',', array_slice($defaultAlbums, 1)) . ")
        ");
        $mediaStmt->execute();
        $mediaIds = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Move any unique media to the album we're keeping
        if (!empty($mediaIds)) {
            foreach ($mediaIds as $mediaId) {
                // Check if this media is already in the album we're keeping
                $checkStmt = $pdo->prepare("
                    SELECT 1 FROM album_media 
                    WHERE album_id = ? AND media_id = ?
                ");
                $checkStmt->execute([$keepId, $mediaId]);
                
                if (!$checkStmt->fetch()) {
                    // Add to the album we're keeping
                    $insertStmt = $pdo->prepare("
                        INSERT INTO album_media (album_id, media_id, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $insertStmt->execute([$keepId, $mediaId]);
                }
            }
        }
        
        // Delete the duplicate albums
        $deleteStmt = $pdo->prepare("
            DELETE FROM user_media_albums 
            WHERE user_id = ? AND id != ? AND (id = 1 OR album_name = 'Default Gallery')
        ");
        $deleteStmt->execute([$user['id'], $keepId]);
        
        // Make sure the album we're keeping has ID = 1
        if ($keepId != 1) {
            // This is complex - we need to update all references
            // For simplicity, let's just rename it to ensure it's recognized as the default
            $updateStmt = $pdo->prepare("
                UPDATE user_media_albums 
                SET album_name = 'Default Gallery',
                    description = 'Your default media gallery containing all your uploaded photos and videos',
                    privacy = 'private'
                WHERE id = ?
            ");
            $updateStmt->execute([$keepId]);
        }
        
        $success = true;
    } else {
        // No duplicates found
        $success = true;
    }
    
    // Commit transaction
    $pdo->commit();
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cleanup Albums - Nubenta</title>
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
                        <h4>Album Cleanup</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i> Album cleanup completed successfully!
                            </div>
                        <?php endif; ?>
                        
                        <p>This utility removes duplicate default albums from your account.</p>
                        
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