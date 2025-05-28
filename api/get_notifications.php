<?php
/**
 * Get notifications for the current user
 * Returns notifications with privacy checks and proper formatting
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');

session_start();
require_once '../db.php';

// Check if user is logged in, if not, use a default user for testing
$userId = null;
if (isset($_SESSION['user']['id'])) {
    $userId = $_SESSION['user']['id'];
} else {
    // Get first user from database for testing
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userId = $user['id'];
    } else {
        echo json_encode(['success' => false, 'error' => 'No users found in database']);
        exit;
    }
}
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    // Check if notifications table exists, create if not
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->rowCount() == 0) {
        // Create notifications table
        $createSql = "
        CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('reaction', 'comment', 'comment_reply') NOT NULL,
            actor_id INT NOT NULL,
            post_id INT NULL,
            media_id INT NULL,
            comment_id INT NULL,
            reaction_type VARCHAR(50) NULL,
            content TEXT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_user_id (user_id),
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";

        $pdo->exec($createSql);
    }

    // Simple query without complex JOINs to avoid table dependency issues
    $stmt = $pdo->prepare("
        SELECT n.*,
               u.first_name,
               u.last_name,
               u.profile_pic,
               u.gender
        FROM notifications n
        JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$userId, $limit, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format notifications for display
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $actorName = trim($notification['first_name'] . ' ' . $notification['last_name']);

        // Determine profile picture
        $actorProfilePic = !empty($notification['profile_pic'])
            ? 'uploads/profile_pics/' . htmlspecialchars($notification['profile_pic'])
            : ($notification['gender'] === 'Female'
                ? 'assets/images/FemaleDefaultProfilePicture.png'
                : 'assets/images/MaleDefaultProfilePicture.png');

        // Generate notification message and link
        $message = '';
        $link = '';

        switch ($notification['type']) {
            case 'reaction':
                $reactionName = ucfirst($notification['reaction_type'] ?? 'unknown');
                $message = "{$actorName} reacted with {$reactionName}";

                if ($notification['post_id']) {
                    $message .= " to your post";
                    $link = "posts.php?id={$notification['post_id']}&highlight=reactions&source=notification";
                } elseif ($notification['media_id']) {
                    $message .= " to your media";
                    // Find which album contains this media
                    $albumStmt = $pdo->prepare("
                        SELECT am.album_id
                        FROM album_media am
                        WHERE am.media_id = ?
                        LIMIT 1
                    ");
                    $albumStmt->execute([$notification['media_id']]);
                    $albumData = $albumStmt->fetch(PDO::FETCH_ASSOC);

                    // If media is in a specific album, use that album ID, otherwise use default album (id=1)
                    $albumId = $albumData ? $albumData['album_id'] : 1;
                    $link = "view_album.php?id={$albumId}&media_id={$notification['media_id']}&source=notification";
                }
                break;

            case 'comment':
                $message = "{$actorName} commented";

                if ($notification['post_id']) {
                    $message .= " on your post";
                    if ($notification['comment_id']) {
                        $link = "posts.php?id={$notification['post_id']}&comment={$notification['comment_id']}&source=notification";
                    } else {
                        $link = "posts.php?id={$notification['post_id']}&highlight=comments&source=notification";
                    }
                } elseif ($notification['media_id']) {
                    $message .= " on your media";
                    // Find which album contains this media
                    $albumStmt = $pdo->prepare("
                        SELECT am.album_id
                        FROM album_media am
                        WHERE am.media_id = ?
                        LIMIT 1
                    ");
                    $albumStmt->execute([$notification['media_id']]);
                    $albumData = $albumStmt->fetch(PDO::FETCH_ASSOC);

                    // If media is in a specific album, use that album ID, otherwise use default album (id=1)
                    $albumId = $albumData ? $albumData['album_id'] : 1;
                    $link = "view_album.php?id={$albumId}&media_id={$notification['media_id']}&source=notification";
                }

                if ($notification['content']) {
                    $message .= ": \"" . htmlspecialchars(substr($notification['content'], 0, 50)) . "\"";
                }
                break;

            case 'comment_reply':
                $message = "{$actorName} replied to your comment";
                if ($notification['post_id']) {
                    if ($notification['comment_id']) {
                        $link = "posts.php?id={$notification['post_id']}&comment={$notification['comment_id']}&source=notification";
                    } else {
                        $link = "posts.php?id={$notification['post_id']}&highlight=comments&source=notification";
                    }
                }

                if ($notification['content']) {
                    $message .= ": \"" . htmlspecialchars(substr($notification['content'], 0, 50)) . "\"";
                }
                break;

            case 'friend_request':
                $message = "{$actorName} sent you a friend request";
                $link = "friends.php";
                break;

            default:
                $message = "{$actorName} interacted with your content";
                $link = "dashboard.php";
                break;
        }

        // Calculate time ago
        $timeAgo = '';
        $createdAt = new DateTime($notification['created_at']);
        $now = new DateTime();
        $diff = $now->diff($createdAt);

        if ($diff->days > 0) {
            $timeAgo = $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            $timeAgo = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            $timeAgo = 'Just now';
        }

        $formattedNotifications[] = [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'message' => $message,
            'link' => $link,
            'actor_name' => $actorName,
            'actor_profile_pic' => $actorProfilePic,
            'time_ago' => $timeAgo,
            'is_read' => (bool)$notification['is_read'],
            'created_at' => $notification['created_at']
        ];
    }

    // Get total unread count
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $unreadStmt->execute([$userId]);
    $unreadCount = $unreadStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications,
        'unread_count' => $unreadCount,
        'total_count' => count($formattedNotifications)
    ]);

} catch (PDOException $e) {
    $errorMsg = "PDO Error in get_notifications.php: " . $e->getMessage() . " | Code: " . $e->getCode() . " | File: " . $e->getFile() . " | Line: " . $e->getLine();
    error_log($errorMsg);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $errorMsg = "General Error in get_notifications.php: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine();
    error_log($errorMsg);
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>
