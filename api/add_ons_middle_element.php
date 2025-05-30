<?php
/**
 * Activity Feed API - Middle element for right sidebar
 * Returns activity notifications in JSON format for AJAX loading
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    // Get friend activities (same logic as newsfeed.php but only for notifications)
    $friend_activities = [];

    // FRIEND ACTIVITIES (comments and reactions on posts)
    $activity_stmt = $pdo->prepare("
        (
            -- 1. Friend comments on any public post
            SELECT DISTINCT posts.*,
                   CONCAT_WS(' ', post_author.first_name, post_author.middle_name, post_author.last_name) as author_name,
                   post_author.profile_pic,
                   post_author.id as author_id,
                   'comment' as activity_type,
                   CONCAT_WS(' ', friend_user.first_name, friend_user.middle_name, friend_user.last_name) as friend_name,
                   friend_user.profile_pic as friend_profile_pic,
                   comments.created_at as activity_time,
                   comments.id as comment_id,
                   NULL as media_id,
                   NULL as reaction_type,
                   friend_user.id as friend_user_id
            FROM posts
            JOIN users post_author ON posts.user_id = post_author.id
            JOIN comments ON posts.id = comments.post_id
            JOIN users friend_user ON comments.user_id = friend_user.id
            WHERE posts.visibility = 'public'
              AND comments.user_id IN (
                SELECT CASE WHEN sender_id = :user_id1 THEN receiver_id WHEN receiver_id = :user_id2 THEN sender_id END
                FROM friend_requests WHERE (sender_id = :user_id3 OR receiver_id = :user_id4) AND status = 'accepted'
              )
              AND comments.user_id != :user_id5
              AND comments.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )

        UNION ALL

        (
            -- 2. Friend reactions on any public post
            SELECT DISTINCT posts.*,
                   CONCAT_WS(' ', post_author.first_name, post_author.middle_name, post_author.last_name) as author_name,
                   post_author.profile_pic,
                   post_author.id as author_id,
                   'reaction_on_friend_post' as activity_type,
                   CONCAT_WS(' ', reactor.first_name, reactor.middle_name, reactor.last_name) as friend_name,
                   reactor.profile_pic as friend_profile_pic,
                   pr.created_at as activity_time,
                   NULL as comment_id,
                   NULL as media_id,
                   pr.reaction_type as reaction_type,
                   reactor.id as friend_user_id
            FROM posts
            JOIN users post_author ON posts.user_id = post_author.id
            JOIN post_reactions pr ON posts.id = pr.post_id
            JOIN users reactor ON pr.user_id = reactor.id
            WHERE posts.visibility = 'public'
              AND pr.user_id IN (
                SELECT CASE WHEN sender_id = :user_id6 THEN receiver_id WHEN receiver_id = :user_id7 THEN sender_id END
                FROM friend_requests WHERE (sender_id = :user_id8 OR receiver_id = :user_id9) AND status = 'accepted'
              )
              AND pr.user_id != :user_id10
              AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )

        ORDER BY activity_time DESC
        LIMIT 20
    ");

    for ($i = 1; $i <= 10; $i++) {
        $activity_stmt->bindParam(":user_id$i", $user_id, PDO::PARAM_INT);
    }

    $activity_stmt->execute();
    $friend_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

    // SOCIAL ACTIVITIES (friend connections, profile updates, etc.)
    $social_activities = [];
    $social_stmt = $pdo->prepare("
        (
            -- Your direct friend connections
            SELECT 'friend_request' as activity_type,
                   CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as friend_name,
                   u.profile_pic as friend_profile_pic,
                   COALESCE(fr.accepted_at, fr.created_at) as activity_time,
                   fr.id as activity_id,
                   'accepted' as extra_info,
                   NULL as other_friend_name,
                   u.id as friend_user_id,
                   NULL as other_friend_user_id,
                   NULL as post_id,
                   NULL as comment_id,
                   NULL as media_id,
                   NULL as reaction_type
            FROM friend_requests fr
            JOIN users u ON (u.id = CASE WHEN fr.sender_id = :user_id1 THEN fr.receiver_id ELSE fr.sender_id END)
            WHERE (fr.sender_id = :user_id2 OR fr.receiver_id = :user_id3)
              AND fr.status = 'accepted'
              AND COALESCE(fr.accepted_at, fr.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )

        UNION ALL

        (
            -- Friends making new friends
            SELECT 'friend_connection' as activity_type,
                   CONCAT_WS(' ', friend1.first_name, friend1.middle_name, friend1.last_name) as friend_name,
                   friend1.profile_pic as friend_profile_pic,
                   COALESCE(fr.accepted_at, fr.created_at) as activity_time,
                   fr.id as activity_id,
                   'connected' as extra_info,
                   CONCAT_WS(' ', friend2.first_name, friend2.middle_name, friend2.last_name) as other_friend_name,
                   friend1.id as friend_user_id,
                   friend2.id as other_friend_user_id,
                   NULL as post_id,
                   NULL as comment_id,
                   NULL as media_id,
                   NULL as reaction_type
            FROM friend_requests fr
            JOIN users friend1 ON friend1.id = fr.sender_id
            JOIN users friend2 ON friend2.id = fr.receiver_id
            WHERE fr.status = 'accepted'
              AND COALESCE(fr.accepted_at, fr.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND (fr.sender_id IN (
                    SELECT CASE WHEN sender_id = :user_id4 THEN receiver_id WHEN receiver_id = :user_id5 THEN sender_id END
                    FROM friend_requests WHERE (sender_id = :user_id6 OR receiver_id = :user_id7) AND status = 'accepted'
                  ) OR fr.receiver_id IN (
                    SELECT CASE WHEN sender_id = :user_id8 THEN receiver_id WHEN receiver_id = :user_id9 THEN sender_id END
                    FROM friend_requests WHERE (sender_id = :user_id10 OR receiver_id = :user_id11) AND status = 'accepted'
                  ))
              AND fr.sender_id != :user_id12
              AND fr.receiver_id != :user_id13
        )

        ORDER BY activity_time DESC
        LIMIT 15
    ");

    for ($i = 1; $i <= 13; $i++) {
        $social_stmt->bindParam(":user_id$i", $user_id, PDO::PARAM_INT);
    }

    $social_stmt->execute();
    $social_activities = $social_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get testimonial activities
    $testimonial_activities = [];
    $testimonial_stmt = $pdo->prepare("
        (
            -- 1. Friends writing testimonials for others
            SELECT 'testimonial_written' as activity_type,
                   CONCAT_WS(' ', writer.first_name, writer.middle_name, writer.last_name) as friend_name,
                   writer.profile_pic as friend_profile_pic,
                   writer.id as friend_user_id,
                   CONCAT_WS(' ', recipient.first_name, recipient.middle_name, recipient.last_name) as recipient_name,
                   recipient.id as recipient_user_id,
                   t.created_at as activity_time,
                   t.testimonial_id,
                   t.content,
                   t.rating
            FROM testimonials t
            JOIN users writer ON t.writer_user_id = writer.id
            JOIN users recipient ON t.recipient_user_id = recipient.id
            WHERE t.writer_user_id IN (
                SELECT CASE WHEN sender_id = :user_id1 THEN receiver_id WHEN receiver_id = :user_id2 THEN sender_id END
                FROM friend_requests WHERE (sender_id = :user_id3 OR receiver_id = :user_id4) AND status = 'accepted'
            )
            AND t.writer_user_id != :user_id5
            AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        
        UNION ALL
        
        (
            -- 2. Friends receiving testimonials
            SELECT 'testimonial_received' as activity_type,
                   CONCAT_WS(' ', recipient.first_name, recipient.middle_name, recipient.last_name) as friend_name,
                   recipient.profile_pic as friend_profile_pic,
                   recipient.id as friend_user_id,
                   CONCAT_WS(' ', writer.first_name, writer.middle_name, writer.last_name) as writer_name,
                   writer.id as writer_user_id,
                   t.created_at as activity_time,
                   t.testimonial_id,
                   t.content,
                   t.rating
            FROM testimonials t
            JOIN users writer ON t.writer_user_id = writer.id
            JOIN users recipient ON t.recipient_user_id = recipient.id
            WHERE t.recipient_user_id IN (
                SELECT CASE WHEN sender_id = :user_id6 THEN receiver_id WHEN receiver_id = :user_id7 THEN sender_id END
                FROM friend_requests WHERE (sender_id = :user_id8 OR receiver_id = :user_id9) AND status = 'accepted'
            )
            AND t.recipient_user_id != :user_id10
            AND t.status = 'approved'
            AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        
        ORDER BY activity_time DESC
        LIMIT 15
    ");
    
    for ($i = 1; $i <= 10; $i++) {
        $testimonial_stmt->bindParam(":user_id$i", $user_id, PDO::PARAM_INT);
    }
    
    $testimonial_stmt->execute();
    $testimonial_activities = $testimonial_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine and format all activities
    $all_activities = [];

    // Format friend activities (post-related)
    foreach ($friend_activities as $activity) {
        $profilePic = !empty($activity['friend_profile_pic'])
            ? 'uploads/profile_pics/' . $activity['friend_profile_pic']
            : 'assets/images/MaleDefaultProfilePicture.png';

        $all_activities[] = [
            'type' => $activity['activity_type'],
            'friend_name' => $activity['friend_name'],
            'friend_profile_pic' => $profilePic,
            'friend_user_id' => $activity['friend_user_id'],
            'activity_time' => $activity['activity_time'],
            'post_id' => $activity['id'],
            'post_author' => $activity['author_name'],
            'comment_id' => $activity['comment_id'],
            'media_id' => $activity['media_id'],
            'reaction_type' => $activity['reaction_type'],
            'timestamp' => strtotime($activity['activity_time'])
        ];
    }
    
    // Format testimonial activities
    foreach ($testimonial_activities as $activity) {
        $profilePic = !empty($activity['friend_profile_pic'])
            ? 'uploads/profile_pics/' . $activity['friend_profile_pic']
            : 'assets/images/MaleDefaultProfilePicture.png';
            
        $all_activities[] = [
            'type' => $activity['activity_type'],
            'friend_name' => $activity['friend_name'],
            'friend_profile_pic' => $profilePic,
            'friend_user_id' => $activity['friend_user_id'],
            'activity_time' => $activity['activity_time'],
            'testimonial_id' => $activity['testimonial_id'],
            'recipient_name' => $activity['recipient_name'] ?? null,
            'recipient_user_id' => $activity['recipient_user_id'] ?? null,
            'writer_name' => $activity['writer_name'] ?? null,
            'writer_user_id' => $activity['writer_user_id'] ?? null,
            'content' => $activity['content'],
            'rating' => $activity['rating'],
            'timestamp' => strtotime($activity['activity_time']),
            // Ensure we have both writer and recipient names for testimonial activities
            'display_writer_name' => $activity['writer_name'] ?? $activity['friend_name'] ?? 'Unknown User',
            'display_recipient_name' => $activity['recipient_name'] ?? $activity['friend_name'] ?? 'Unknown User'
        ];
    }

    // Format social activities (non-post related)
    foreach ($social_activities as $activity) {
        $profilePic = !empty($activity['friend_profile_pic'])
            ? 'uploads/profile_pics/' . $activity['friend_profile_pic']
            : 'assets/images/MaleDefaultProfilePicture.png';

        $all_activities[] = [
            'type' => $activity['activity_type'],
            'friend_name' => $activity['friend_name'],
            'friend_profile_pic' => $profilePic,
            'friend_user_id' => $activity['friend_user_id'],
            'activity_time' => $activity['activity_time'],
            'other_friend_name' => $activity['other_friend_name'] ?? null,
            'other_friend_user_id' => $activity['other_friend_user_id'] ?? null,
            'timestamp' => strtotime($activity['activity_time'])
        ];
    }

    // Sort by timestamp (newest first)
    usort($all_activities, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    // Limit to 25 activities
    $all_activities = array_slice($all_activities, 0, 25);
    
    // Count pending testimonials for notification badge
    $pendingTestimonialsStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM testimonials
        WHERE recipient_user_id = ? AND status = 'pending'
    ");
    $pendingTestimonialsStmt->execute([$user_id]);
    $pendingCount = $pendingTestimonialsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'activities' => $all_activities,
        'count' => count($all_activities),
        'pending_testimonials_count' => $pendingCount
    ]);

} catch (Exception $e) {
    error_log("Activity feed error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load activities',
        'debug' => $e->getMessage()
    ]);
}