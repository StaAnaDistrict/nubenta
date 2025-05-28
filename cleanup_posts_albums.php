<?php
/**
 * cleanup_posts_albums.php - Remove redundant "Posts" albums
 * This script removes automatically created "Posts" albums since they're redundant with default gallery
 */

session_start();
require_once 'db.php';

// Check if user is logged in and is admin (for safety)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Access denied. Admin access required.');
}

$results = [
    'albums_found' => 0,
    'albums_removed' => 0,
    'media_moved' => 0,
    'errors' => []
];

try {
    // Find all "Posts" albums
    $stmt = $pdo->prepare("
        SELECT id, user_id, album_name 
        FROM user_media_albums 
        WHERE album_name = 'Posts' 
        ORDER BY user_id, id
    ");
    $stmt->execute();
    $postsAlbums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results['albums_found'] = count($postsAlbums);
    
    foreach ($postsAlbums as $album) {
        try {
            $pdo->beginTransaction();
            
            // Get all media in this "Posts" album
            $mediaStmt = $pdo->prepare("
                SELECT media_id 
                FROM album_media 
                WHERE album_id = ?
            ");
            $mediaStmt->execute([$album['id']]);
            $mediaIds = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Remove all media from this album (media stays in user_media table)
            if (!empty($mediaIds)) {
                $deleteMediaStmt = $pdo->prepare("
                    DELETE FROM album_media 
                    WHERE album_id = ?
                ");
                $deleteMediaStmt->execute([$album['id']]);
                
                $results['media_moved'] += count($mediaIds);
            }
            
            // Delete the "Posts" album
            $deleteAlbumStmt = $pdo->prepare("
                DELETE FROM user_media_albums 
                WHERE id = ?
            ");
            $deleteAlbumStmt->execute([$album['id']]);
            
            $pdo->commit();
            $results['albums_removed']++;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $results['errors'][] = "Error processing album {$album['id']} for user {$album['user_id']}: " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    $results['errors'][] = "Database error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Posts Albums - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h2 class="h4 mb-0">
                            <i class="fas fa-broom me-2"></i>
                            Posts Albums Cleanup Results
                        </h2>
                    </div>
                    <div class="card-body">
                        
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['albums_found'] ?></h5>
                                        <small>"Posts" Albums Found</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['albums_removed'] ?></h5>
                                        <small>Albums Removed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['media_moved'] ?></h5>
                                        <small>Media Items Freed</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <?php if ($results['albums_removed'] > 0): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Cleanup Complete!</h5>
                                <p>Successfully removed <?= $results['albums_removed'] ?> redundant "Posts" albums.</p>
                                <p><strong>All media is still available in users' Default Galleries.</strong></p>
                            </div>
                        <?php elseif ($results['albums_found'] === 0): ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>No Cleanup Needed</h5>
                                <p>No "Posts" albums found in the system.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Partial Cleanup</h5>
                                <p>Found <?= $results['albums_found'] ?> albums but only removed <?= $results['albums_removed'] ?>.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Errors -->
                        <?php if (!empty($results['errors'])): ?>
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Errors encountered:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($results['errors'] as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Instructions -->
                        <div class="mt-4">
                            <h5>What this script does:</h5>
                            <ul>
                                <li>Finds all automatically created "Posts" albums</li>
                                <li>Removes the redundant albums (not the media)</li>
                                <li>Preserves all media in the user_media table</li>
                                <li>Media remains accessible through Default Gallery (id=1)</li>
                            </ul>
                            
                            <h5>Why this cleanup was needed:</h5>
                            <ul>
                                <li>"Posts" albums were automatically created during media synchronization</li>
                                <li>They duplicated content already available in Default Gallery</li>
                                <li>This caused confusion and redundancy for users</li>
                                <li>Default Gallery already shows all user media automatically</li>
                            </ul>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Result:</strong> Users will now see a cleaner album structure without redundant "Posts" albums. All their media remains accessible through the Default Gallery.
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                            </a>
                            <div>
                                <a href="manage_albums.php" class="btn btn-primary me-2">
                                    <i class="fas fa-photo-video me-1"></i> Manage Albums
                                </a>
                                <a href="cleanup_posts_albums.php" class="btn btn-warning">
                                    <i class="fas fa-sync-alt me-1"></i> Run Again
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
