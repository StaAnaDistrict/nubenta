<?php
/**
 * NotificationHelper - Handles creation and management of user notifications
 * Integrates with existing reaction and comment systems
 */

class NotificationHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a reaction notification
     */
    public function createReactionNotification($actorId, $postId, $mediaId, $reactionType) {
        try {
            // Get the content owner
            $ownerId = null;
            
            if ($postId) {
                // Get post owner
                $stmt = $this->pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                $ownerId = $post ? $post['user_id'] : null;
            } elseif ($mediaId) {
                // Get media owner
                $stmt = $this->pdo->prepare("SELECT user_id FROM user_media WHERE id = ?");
                $stmt->execute([$mediaId]);
                $media = $stmt->fetch(PDO::FETCH_ASSOC);
                $ownerId = $media ? $media['user_id'] : null;
            }
            
            // Don't create notification if no owner found or if actor is the owner
            if (!$ownerId || $ownerId == $actorId) {
                return false;
            }
            
            // Check if notification already exists (to prevent duplicates)
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM notifications 
                WHERE user_id = ? AND actor_id = ? AND type = 'reaction' 
                AND post_id = ? AND media_id = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $checkStmt->execute([$ownerId, $actorId, $postId, $mediaId]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing notification instead of creating new one
                $updateStmt = $this->pdo->prepare("
                    UPDATE notifications 
                    SET reaction_type = ?, updated_at = NOW(), is_read = FALSE
                    WHERE user_id = ? AND actor_id = ? AND type = 'reaction' 
                    AND post_id = ? AND media_id = ?
                ");
                $updateStmt->execute([$reactionType, $ownerId, $actorId, $postId, $mediaId]);
                return true;
            }
            
            // Create new notification
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, actor_id, post_id, media_id, reaction_type)
                VALUES (?, 'reaction', ?, ?, ?, ?)
            ");
            $stmt->execute([$ownerId, $actorId, $postId, $mediaId, $reactionType]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error creating reaction notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a comment notification
     */
    public function createCommentNotification($actorId, $postId, $mediaId, $commentId, $content) {
        try {
            // Get the content owner
            $ownerId = null;
            
            if ($postId) {
                // Get post owner
                $stmt = $this->pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
                $stmt->execute([$postId]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                $ownerId = $post ? $post['user_id'] : null;
            } elseif ($mediaId) {
                // Get media owner
                $stmt = $this->pdo->prepare("SELECT user_id FROM user_media WHERE id = ?");
                $stmt->execute([$mediaId]);
                $media = $stmt->fetch(PDO::FETCH_ASSOC);
                $ownerId = $media ? $media['user_id'] : null;
            }
            
            // Don't create notification if no owner found or if actor is the owner
            if (!$ownerId || $ownerId == $actorId) {
                return false;
            }
            
            // Create notification
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, actor_id, post_id, media_id, comment_id, content)
                VALUES (?, 'comment', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ownerId, $actorId, $postId, $mediaId, $commentId, substr($content, 0, 100)]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error creating comment notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a comment reply notification
     */
    public function createCommentReplyNotification($actorId, $commentId, $replyContent) {
        try {
            // Get the original comment owner
            $stmt = $this->pdo->prepare("SELECT user_id, post_id FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$comment || $comment['user_id'] == $actorId) {
                return false; // No comment found or replying to own comment
            }
            
            // Create notification
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, actor_id, post_id, comment_id, content)
                VALUES (?, 'comment_reply', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $comment['user_id'], 
                $actorId, 
                $comment['post_id'], 
                $commentId, 
                substr($replyContent, 0, 100)
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error creating comment reply notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['count'] : 0;
            
        } catch (PDOException $e) {
            error_log("Error getting unread notification count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = TRUE, updated_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            return true;
            
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = TRUE, updated_at = NOW() 
                WHERE user_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId]);
            return true;
            
        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
}
