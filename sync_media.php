<?php
/**
 * sync_media.php - Synchronize existing posts media with user_media system
 * This script will find all posts with media that aren't in user_media table
 * and add them to ensure view_album.php shows all user media
 */

session_start();
require_once 'db.php';
require_once 'includes/MediaUploader.php';

// Check if user is logged in and is admin (for safety)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Access denied. Admin access required.');
}

$results = [];
$errors = [];

try {
    // Initialize MediaUploader
    $mediaUploader = new MediaUploader($pdo);
    
    // Find all posts with media that don't have corresponding user_media entries
    $stmt = $pdo->prepare("
        SELECT p.id as post_id, p.user_id, p.media, p.created_at
        FROM posts p
        WHERE p.media IS NOT NULL 
        AND p.media != '' 
        AND p.media != '[]'
        AND p.media != 'null'
        AND NOT EXISTS (
            SELECT 1 FROM user_media um 
            WHERE um.post_id = p.id
        )
        ORDER BY p.created_at DESC
    ");
    
    $stmt->execute();
    $postsWithMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results['total_posts_found'] = count($postsWithMedia);
    $results['processed'] = 0;
    $results['successful'] = 0;
    $results['failed'] = 0;
    
    foreach ($postsWithMedia as $post) {
        $results['processed']++;
        
        try {
            // Parse media JSON
            $mediaData = json_decode($post['media'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try as single string
                $mediaData = [$post['media']];
            }
            
            if (!is_array($mediaData)) {
                $mediaData = [$mediaData];
            }
            
            // Filter out empty or invalid paths
            $validMedia = array_filter($mediaData, function($path) {
                return !empty($path) && is_string($path) && $path !== 'null';
            });
            
            if (!empty($validMedia)) {
                // Track this media in user_media system
                $trackResult = $mediaUploader->trackPostMedia(
                    $post['user_id'], 
                    $validMedia, 
                    $post['post_id']
                );
                
                if ($trackResult) {
                    $results['successful']++;
                    $results['details'][] = [
                        'post_id' => $post['post_id'],
                        'user_id' => $post['user_id'],
                        'media_count' => count($validMedia),
                        'status' => 'success'
                    ];
                } else {
                    $results['failed']++;
                    $errors[] = "Failed to track media for post {$post['post_id']}";
                }
            }
            
        } catch (Exception $e) {
            $results['failed']++;
            $errors[] = "Error processing post {$post['post_id']}: " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Synchronization - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2 class="h4 mb-0">
                            <i class="fas fa-sync-alt me-2"></i>
                            Media Synchronization Results
                        </h2>
                    </div>
                    <div class="card-body">
                        
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['total_posts_found'] ?? 0 ?></h5>
                                        <small>Posts Found</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['processed'] ?? 0 ?></h5>
                                        <small>Processed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['successful'] ?? 0 ?></h5>
                                        <small>Successful</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['failed'] ?? 0 ?></h5>
                                        <small>Failed</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <?php if (($results['successful'] ?? 0) > 0): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Successfully synchronized <?= $results['successful'] ?> posts with the user_media system!
                                <br><small>These media files will now appear in users' Default Gallery in view_album.php</small>
                            </div>
                        <?php endif; ?>

                        <!-- Errors -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Errors encountered:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Details -->
                        <?php if (!empty($results['details'])): ?>
                            <h5>Processed Posts Details:</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Post ID</th>
                                            <th>User ID</th>
                                            <th>Media Count</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results['details'] as $detail): ?>
                                            <tr>
                                                <td><?= $detail['post_id'] ?></td>
                                                <td><?= $detail['user_id'] ?></td>
                                                <td><?= $detail['media_count'] ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>
                                                        <?= ucfirst($detail['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Instructions -->
                        <div class="mt-4">
                            <h5>What this script does:</h5>
                            <ul>
                                <li>Finds all posts with media that aren't in the <code>user_media</code> table</li>
                                <li>Adds them to the <code>user_media</code> table with proper relationships</li>
                                <li>Creates or updates "Posts" albums for users</li>
                                <li>Ensures media appears in <code>view_album.php</code> Default Gallery</li>
                            </ul>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Next steps:</strong> All synchronized media will now appear in users' Default Gallery when they visit their view_album.php page.
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                            </a>
                            <a href="sync_media.php" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-1"></i> Run Again
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
