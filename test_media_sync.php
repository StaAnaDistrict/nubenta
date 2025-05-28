<?php
/**
 * test_media_sync.php - Quick test to verify media synchronization
 * This script shows the current state of posts vs user_media
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    die('Please log in to view this page.');
}

$user = $_SESSION['user'];

try {
    // Get posts with media for current user
    $postsStmt = $pdo->prepare("
        SELECT id, content, media, created_at
        FROM posts 
        WHERE user_id = ? 
        AND media IS NOT NULL 
        AND media != '' 
        AND media != '[]'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $postsStmt->execute([$user['id']]);
    $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user_media entries for current user
    $mediaStmt = $pdo->prepare("
        SELECT id, media_url, media_type, post_id, created_at
        FROM user_media 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $mediaStmt->execute([$user['id']]);
    $userMedia = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for posts with media but no user_media entries
    $missingStmt = $pdo->prepare("
        SELECT p.id as post_id, p.media
        FROM posts p
        WHERE p.user_id = ?
        AND p.media IS NOT NULL 
        AND p.media != '' 
        AND p.media != '[]'
        AND NOT EXISTS (
            SELECT 1 FROM user_media um 
            WHERE um.post_id = p.id
        )
    ");
    $missingStmt->execute([$user['id']]);
    $missingMedia = $missingStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Sync Test - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-vial me-2"></i>Media Synchronization Test</h2>
                <p class="text-muted">Testing synchronization between posts.media and user_media table for user: <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong></p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?= count($posts) ?></h4>
                        <small>Posts with Media</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?= count($userMedia) ?></h4>
                        <small>User Media Entries</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card <?= count($missingMedia) > 0 ? 'bg-warning' : 'bg-info' ?> text-white">
                    <div class="card-body text-center">
                        <h4><?= count($missingMedia) ?></h4>
                        <small>Missing Sync</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missing Media Alert -->
        <?php if (count($missingMedia) > 0): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Synchronization Needed</h5>
                <p>You have <?= count($missingMedia) ?> posts with media that aren't synchronized with the user_media system.</p>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="sync_media.php" class="btn btn-warning">
                        <i class="fas fa-sync-alt me-1"></i> Run Synchronization
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle me-2"></i>All Synchronized</h5>
                <p>All your posts with media are properly synchronized with the user_media system!</p>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Posts with Media -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-newspaper me-2"></i>Recent Posts with Media</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($posts)): ?>
                            <p class="text-muted">No posts with media found.</p>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong>Post #<?= $post['id'] ?></strong>
                                    <br>
                                    <small class="text-muted"><?= $post['created_at'] ?></small>
                                    <br>
                                    <code><?= htmlspecialchars(substr($post['media'], 0, 100)) ?>...</code>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- User Media Entries -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-images me-2"></i>User Media Entries</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userMedia)): ?>
                            <p class="text-muted">No user media entries found.</p>
                        <?php else: ?>
                            <?php foreach ($userMedia as $media): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong>Media #<?= $media['id'] ?></strong>
                                    <span class="badge bg-secondary"><?= $media['media_type'] ?></span>
                                    <?php if ($media['post_id']): ?>
                                        <span class="badge bg-primary">Post #<?= $media['post_id'] ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?= $media['created_at'] ?></small>
                                    <br>
                                    <code><?= htmlspecialchars($media['media_url']) ?></code>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missing Media Details -->
        <?php if (!empty($missingMedia)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Posts Missing from User Media</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($missingMedia as $missing): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong>Post #<?= $missing['post_id'] ?></strong>
                                    <br>
                                    <code><?= htmlspecialchars($missing['media']) ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                    <div>
                        <a href="view_album.php" class="btn btn-primary me-2">
                            <i class="fas fa-images me-1"></i> View Album
                        </a>
                        <a href="test_media_sync.php" class="btn btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
