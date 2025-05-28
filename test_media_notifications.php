<?php
/**
 * test_media_notifications.php - Test media notification system
 * This script helps verify that media comments and reactions generate notifications
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    die('Please log in to view this page.');
}

$user = $_SESSION['user'];

try {
    // Get recent media comments and reactions for current user
    $mediaCommentsStmt = $pdo->prepare("
        SELECT mc.*, um.user_id as media_owner_id, um.media_url,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as commenter_name
        FROM media_comments mc
        JOIN user_media um ON mc.media_id = um.id
        JOIN users u ON mc.user_id = u.id
        WHERE um.user_id = ?
        ORDER BY mc.created_at DESC
        LIMIT 10
    ");
    $mediaCommentsStmt->execute([$user['id']]);
    $mediaComments = $mediaCommentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent media reactions for current user
    $mediaReactionsStmt = $pdo->prepare("
        SELECT mr.*, um.user_id as media_owner_id, um.media_url,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as reactor_name,
               rt.name as reaction_name
        FROM media_reactions mr
        JOIN user_media um ON mr.media_id = um.id
        JOIN users u ON mr.user_id = u.id
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE um.user_id = ?
        ORDER BY mr.created_at DESC
        LIMIT 10
    ");
    $mediaReactionsStmt->execute([$user['id']]);
    $mediaReactions = $mediaReactionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent notifications for current user
    $notificationsStmt = $pdo->prepare("
        SELECT n.*, 
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as actor_name
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        AND (n.type = 'comment' OR n.type = 'reaction')
        AND n.media_id IS NOT NULL
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $notificationsStmt->execute([$user['id']]);
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Notifications Test - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-bell me-2"></i>Media Notifications Test</h2>
                <p class="text-muted">Testing notification system for media comments and reactions for user: <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong></p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><?= count($mediaComments) ?></h4>
                        <small>Media Comments Received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><?= count($mediaReactions) ?></h4>
                        <small>Media Reactions Received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><?= count($notifications) ?></h4>
                        <small>Media Notifications</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Check -->
        <?php 
        $totalMediaActivity = count($mediaComments) + count($mediaReactions);
        $notificationCoverage = $totalMediaActivity > 0 ? (count($notifications) / $totalMediaActivity) * 100 : 100;
        ?>

        <?php if ($notificationCoverage >= 80): ?>
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle me-2"></i>Notifications Working Well</h5>
                <p>Media notification coverage: <?= round($notificationCoverage) ?>%</p>
            </div>
        <?php elseif ($notificationCoverage >= 50): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Partial Notification Coverage</h5>
                <p>Media notification coverage: <?= round($notificationCoverage) ?>% - Some notifications may be missing</p>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h5><i class="fas fa-times-circle me-2"></i>Low Notification Coverage</h5>
                <p>Media notification coverage: <?= round($notificationCoverage) ?>% - Many notifications are missing</p>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Media Comments -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-comments me-2"></i>Recent Media Comments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($mediaComments)): ?>
                            <p class="text-muted">No media comments found.</p>
                        <?php else: ?>
                            <?php foreach ($mediaComments as $comment): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong><?= htmlspecialchars($comment['commenter_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">Media ID: <?= $comment['media_id'] ?></small>
                                    <br>
                                    <small class="text-muted"><?= $comment['created_at'] ?></small>
                                    <br>
                                    <em>"<?= htmlspecialchars(substr($comment['content'], 0, 50)) ?>..."</em>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Media Reactions -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-heart me-2"></i>Recent Media Reactions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($mediaReactions)): ?>
                            <p class="text-muted">No media reactions found.</p>
                        <?php else: ?>
                            <?php foreach ($mediaReactions as $reaction): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong><?= htmlspecialchars($reaction['reactor_name']) ?></strong>
                                    <span class="badge bg-primary"><?= htmlspecialchars($reaction['reaction_name']) ?></span>
                                    <br>
                                    <small class="text-muted">Media ID: <?= $reaction['media_id'] ?></small>
                                    <br>
                                    <small class="text-muted"><?= $reaction['created_at'] ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bell me-2"></i>Media Notifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted">No media notifications found.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong><?= htmlspecialchars($notification['actor_name']) ?></strong>
                                    <span class="badge bg-<?= $notification['type'] === 'comment' ? 'info' : 'success' ?>"><?= $notification['type'] ?></span>
                                    <br>
                                    <small class="text-muted">Media ID: <?= $notification['media_id'] ?></small>
                                    <br>
                                    <small class="text-muted"><?= $notification['created_at'] ?></small>
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge bg-warning">Unread</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>How to Test</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li><strong>Post media</strong> from dashboard.php (images/videos)</li>
                            <li><strong>Switch to another account</strong> and visit view_album.php</li>
                            <li><strong>Comment on the media</strong> in view_album.php</li>
                            <li><strong>React to the media</strong> in view_album.php</li>
                            <li><strong>Switch back to original account</strong> and check notifications</li>
                            <li><strong>Refresh this page</strong> to see updated statistics</li>
                        </ol>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Expected Result:</strong> Each media comment and reaction should generate a notification that links to view_album.php with the specific media highlighted.
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                        <a href="notifications.php" class="btn btn-info me-2">
                            <i class="fas fa-bell me-1"></i> View Notifications
                        </a>
                        <a href="test_media_notifications.php" class="btn btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
