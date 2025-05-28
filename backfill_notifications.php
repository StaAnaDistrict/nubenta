<?php
/**
 * backfill_notifications.php - Create notifications for existing media activities
 * This script finds media comments and reactions that don't have notifications
 * and creates them retroactively
 */

session_start();
require_once 'db.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in and is admin (for safety)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Access denied. Admin access required.');
}

$results = [
    'media_comments_processed' => 0,
    'media_comments_notifications_created' => 0,
    'media_reactions_processed' => 0,
    'media_reactions_notifications_created' => 0,
    'errors' => []
];

try {
    $notificationHelper = new NotificationHelper($pdo);
    
    // 1. Find media comments without notifications
    echo "<h3>Processing Media Comments...</h3>";
    
    $mediaCommentsStmt = $pdo->prepare("
        SELECT mc.id, mc.media_id, mc.user_id, mc.content, mc.created_at,
               um.user_id as media_owner_id
        FROM media_comments mc
        JOIN user_media um ON mc.media_id = um.id
        WHERE NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.type = 'comment' 
            AND n.media_id = mc.media_id 
            AND n.comment_id = mc.id
            AND n.actor_id = mc.user_id
        )
        AND mc.user_id != um.user_id  -- Don't create notifications for self-comments
        ORDER BY mc.created_at DESC
    ");
    
    $mediaCommentsStmt->execute();
    $mediaComments = $mediaCommentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($mediaComments) . " media comments without notifications.</p>";
    
    foreach ($mediaComments as $comment) {
        $results['media_comments_processed']++;
        
        try {
            // Create notification with original timestamp
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, actor_id, media_id, comment_id, content, created_at, updated_at)
                VALUES (?, 'comment', ?, ?, ?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                $comment['media_owner_id'],
                $comment['user_id'],
                $comment['media_id'],
                $comment['id'],
                substr($comment['content'], 0, 100),
                $comment['created_at'],
                $comment['created_at']
            ]);
            
            if ($success) {
                $results['media_comments_notifications_created']++;
                echo "<p>✅ Created notification for comment ID {$comment['id']} on media {$comment['media_id']}</p>";
            } else {
                $results['errors'][] = "Failed to create notification for comment ID {$comment['id']}";
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Error processing comment ID {$comment['id']}: " . $e->getMessage();
        }
    }
    
    // 2. Find media reactions without notifications
    echo "<h3>Processing Media Reactions...</h3>";
    
    $mediaReactionsStmt = $pdo->prepare("
        SELECT mr.reaction_id, mr.media_id, mr.user_id, mr.reaction_type_id, mr.created_at,
               um.user_id as media_owner_id,
               rt.name as reaction_name
        FROM media_reactions mr
        JOIN user_media um ON mr.media_id = um.id
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.type = 'reaction' 
            AND n.media_id = mr.media_id 
            AND n.actor_id = mr.user_id
            AND n.created_at >= DATE_SUB(mr.created_at, INTERVAL 1 HOUR)
            AND n.created_at <= DATE_ADD(mr.created_at, INTERVAL 1 HOUR)
        )
        AND mr.user_id != um.user_id  -- Don't create notifications for self-reactions
        ORDER BY mr.created_at DESC
    ");
    
    $mediaReactionsStmt->execute();
    $mediaReactions = $mediaReactionsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($mediaReactions) . " media reactions without notifications.</p>";
    
    foreach ($mediaReactions as $reaction) {
        $results['media_reactions_processed']++;
        
        try {
            // Create notification with original timestamp
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, actor_id, media_id, reaction_type, created_at, updated_at)
                VALUES (?, 'reaction', ?, ?, ?, ?, ?)
            ");
            
            $success = $stmt->execute([
                $reaction['media_owner_id'],
                $reaction['user_id'],
                $reaction['media_id'],
                $reaction['reaction_name'],
                $reaction['created_at'],
                $reaction['created_at']
            ]);
            
            if ($success) {
                $results['media_reactions_notifications_created']++;
                echo "<p>✅ Created notification for reaction ID {$reaction['reaction_id']} ({$reaction['reaction_name']}) on media {$reaction['media_id']}</p>";
            } else {
                $results['errors'][] = "Failed to create notification for reaction ID {$reaction['reaction_id']}";
            }
            
        } catch (Exception $e) {
            $results['errors'][] = "Error processing reaction ID {$reaction['reaction_id']}: " . $e->getMessage();
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
    <title>Backfill Notifications - Nubenta</title>
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
                            <i class="fas fa-history me-2"></i>
                            Notification Backfill Results
                        </h2>
                    </div>
                    <div class="card-body">
                        
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['media_comments_notifications_created'] ?> / <?= $results['media_comments_processed'] ?></h5>
                                        <small>Media Comment Notifications Created</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5><?= $results['media_reactions_notifications_created'] ?> / <?= $results['media_reactions_processed'] ?></h5>
                                        <small>Media Reaction Notifications Created</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <?php 
                        $totalCreated = $results['media_comments_notifications_created'] + $results['media_reactions_notifications_created'];
                        if ($totalCreated > 0): 
                        ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Backfill Complete!</h5>
                                <p>Successfully created <?= $totalCreated ?> notifications for existing media activities!</p>
                                <p><strong>These notifications will now appear in users' notification feeds with their original timestamps.</strong></p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>No Backfill Needed</h5>
                                <p>All existing media activities already have notifications.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Errors -->
                        <?php if (!empty($results['errors'])): ?>
                            <div class="alert alert-warning">
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
                                <li>Finds media comments and reactions that don't have notifications</li>
                                <li>Creates notifications with <strong>original timestamps</strong></li>
                                <li>Excludes self-comments and self-reactions</li>
                                <li>Maintains chronological order in notification feeds</li>
                            </ul>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Next steps:</strong> Users will now see notifications for all historical media activities in their notification feeds, properly ordered by date.
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                            </a>
                            <div>
                                <a href="notifications.php" class="btn btn-primary me-2">
                                    <i class="fas fa-bell me-1"></i> View Notifications
                                </a>
                                <a href="test_media_notifications.php" class="btn btn-info">
                                    <i class="fas fa-vial me-1"></i> Test Notifications
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
