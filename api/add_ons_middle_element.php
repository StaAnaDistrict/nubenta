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
    $user_id = $_SESSION['user']['id']; 
    
    $defaultMalePic_path = '../assets/images/MaleDefaultProfilePicture.png';
    $defaultFemalePic_path = '../assets/images/FemaleDefaultProfilePicture.png';

    // Simplified SQL query for "Friend comments on any public post"
    $activity_sql = "
        SELECT
            c.id AS activity_id,
            'comment' AS activity_type,
            c.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id,
            p.id AS target_content_id,
            LEFT(p.content, 50) AS target_content_summary,
            NULL AS media_id,
            NULL AS media_url,
            NULL AS media_type,
            NULL AS album_id,
            c.content AS comment_content,
            NULL AS reaction_type,
            c.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name,
            p.id as post_id_for_activity -- Added for compatibility with existing processing
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN posts p ON c.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id1 AND f.receiver_id = c.user_id) OR (f.receiver_id = :current_user_id2 AND f.sender_id = c.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND c.user_id != :current_user_id3
          AND p.user_id != :current_user_id4
          AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) -- Keep recent activities
    )
    UNION ALL
    (
        -- Friend reactions on any public post
        SELECT
            pr.id AS activity_id,
            'reaction' AS activity_type,
            pr.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id,
            p.id AS target_content_id,
            LEFT(p.content, 50) AS target_content_summary,
            NULL AS media_id,
            NULL AS media_url,
            NULL AS media_type,
            NULL AS album_id,
            NULL AS comment_content, -- No comment content for reactions
            rt.name AS reaction_type, -- Get reaction type name
            pr.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name,
            p.id as post_id_for_activity
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id
        JOIN posts p ON pr.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN reaction_types rt ON pr.reaction_type_id = rt.reaction_type_id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id5 AND f.receiver_id = pr.user_id) OR (f.receiver_id = :current_user_id6 AND f.sender_id = pr.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND pr.user_id != :current_user_id7
          AND p.user_id != :current_user_id8
          AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    UNION ALL
    (
        -- Anyone comments on a friend's public post
        SELECT
            c.id AS activity_id,
            'comment_on_friend_post' AS activity_type, -- Different type
            c.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id, -- This is the friend
            p.id AS target_content_id,
            LEFT(p.content, 50) AS target_content_summary,
            NULL AS media_id,
            NULL AS media_url,
            NULL AS media_type,
            NULL AS album_id,
            c.content AS comment_content,
            NULL AS reaction_type,
            c.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, -- Friend's name
            p.id as post_id_for_activity
        FROM comments c
        JOIN users u ON c.user_id = u.id -- The commenter (anyone)
        JOIN posts p ON c.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id -- The post owner (friend)
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id9 AND f.receiver_id = p.user_id) OR (f.receiver_id = :current_user_id10 AND f.sender_id = p.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND p.user_id != :current_user_id11 -- Post owner is not the current user (already implied by "friend's post")
          AND c.user_id != :current_user_id12 -- Commenter is not the current user
          AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    UNION ALL
    (
        -- Anyone reacts to a friend's public post
        SELECT
            pr.id AS activity_id,
            'reaction_on_friend_post' AS activity_type, -- Different type
            pr.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id, -- This is the friend
            p.id AS target_content_id,
            LEFT(p.content, 50) AS target_content_summary,
            NULL AS media_id,
            NULL AS media_url,
            NULL AS media_type,
            NULL AS album_id,
            NULL AS comment_content,
            rt.name AS reaction_type,
            pr.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, -- Friend's name
            p.id as post_id_for_activity
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id -- The reactor (anyone)
        JOIN posts p ON pr.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id -- The post owner (friend)
        JOIN reaction_types rt ON pr.reaction_type_id = rt.reaction_type_id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id13 AND f.receiver_id = p.user_id) OR (f.receiver_id = :current_user_id14 AND f.sender_id = p.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND p.user_id != :current_user_id15 -- Post owner is not the current user
          AND pr.user_id != :current_user_id16 -- Reactor is not the current user
          AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    UNION ALL
    (
        -- Friend comments on media associated with a public post
        SELECT
            mc.id AS activity_id,
            'media_comment' AS activity_type,
            mc.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id, -- Post owner
            p.id AS target_content_id, -- Post ID
            LEFT(p.content, 50) AS target_content_summary, -- Post summary
            um.id AS media_id,
            um.filename AS media_url, -- Or thumbnail_url if available and preferred
            um.media_type AS media_type,
            um.album_id AS album_id,
            mc.content AS comment_content,
            NULL AS reaction_type,
            mc.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, -- Post owner's name
            p.id as post_id_for_activity -- Redundant, same as target_content_id
        FROM media_comments mc
        JOIN users u ON mc.user_id = u.id -- Commenter (friend)
        JOIN user_media um ON mc.media_id = um.id
        JOIN posts p ON um.post_id = p.id -- Assuming media is always linked to a post
        JOIN users orig_u ON p.user_id = orig_u.id -- Post owner
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id17 AND f.receiver_id = mc.user_id) OR (f.receiver_id = :current_user_id18 AND f.sender_id = mc.user_id))
        WHERE p.visibility = 'public' -- Post is public
          AND um.post_id IS NOT NULL -- Media is associated with a post
          AND f.status = 'accepted' -- Commenter is a friend
          AND mc.user_id != :current_user_id19 -- Commenter is not the current user
          AND p.user_id != :current_user_id20 -- Post owner is not the current user (can be friend or self, if friend comments on own post media)
          AND mc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    UNION ALL
    (
        -- Friend reacts to media associated with a public post
        SELECT
            mr.reaction_id AS activity_id, -- Note: media_reactions uses reaction_id as PK
            'media_reaction' AS activity_type,
            mr.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id, -- Post owner
            p.id AS target_content_id, -- Post ID
            LEFT(p.content, 50) AS target_content_summary, -- Post summary
            um.id AS media_id,
            um.filename AS media_url, -- Or thumbnail_url
            um.media_type AS media_type,
            um.album_id AS album_id,
            NULL AS comment_content,
            rt.name AS reaction_type,
            mr.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, -- Post owner's name
            p.id as post_id_for_activity
        FROM media_reactions mr
        JOIN users u ON mr.user_id = u.id -- Reactor (friend)
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        JOIN user_media um ON mr.media_id = um.id
        JOIN posts p ON um.post_id = p.id -- Assuming media is always linked to a post
        JOIN users orig_u ON p.user_id = orig_u.id -- Post owner
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id21 AND f.receiver_id = mr.user_id) OR (f.receiver_id = :current_user_id22 AND f.sender_id = mr.user_id))
        WHERE p.visibility = 'public' -- Post is public
          AND um.post_id IS NOT NULL -- Media is associated with a post
          AND f.status = 'accepted' -- Reactor is a friend
          AND mr.user_id != :current_user_id23 -- Reactor is not the current user
          AND p.user_id != :current_user_id24 -- Post owner is not the current user
          AND mr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    ORDER BY activity_created_at DESC
    LIMIT 20;
    ";

    $activity_stmt = $pdo->prepare($activity_sql);
    // Bind the current user ID to all placeholders
    $activity_stmt->bindParam(":current_user_id1", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id2", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id3", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id4", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id5", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id6", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id7", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id8", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id9", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id10", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id11", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id12", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id13", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id14", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id15", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id16", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id17", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id18", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id19", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id20", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id21", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id22", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id23", $user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id24", $user_id, PDO::PARAM_INT);

    $activity_stmt->execute();
    $fetched_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

    $all_activities = [];
    $processed_event_ids = [];

    foreach ($fetched_activities as $activity) {
        $unique_event_key = $activity['activity_type'] . '_' . $activity['activity_id'];
        if (isset($processed_event_ids[$unique_event_key])) {
            continue;
        }
        $processed_event_ids[$unique_event_key] = true;

        $actorProfilePic = $activity['actor_profile_pic']
            ? '../uploads/profile_pics/' . htmlspecialchars($activity['actor_profile_pic'])
            : (($activity['actor_gender'] === 'Female') ? $defaultFemalePic_path : $defaultMalePic_path);

        $item = [
            'type' => $activity['activity_type'],
            'actor_name' => $activity['actor_name'],
            'actor_profile_pic' => $actorProfilePic,
            'actor_user_id' => $activity['actor_user_id'],
            'activity_time' => $activity['activity_created_at'],
            'timestamp' => strtotime($activity['activity_created_at']),

            'post_id_for_activity' => $activity['target_content_id'],
            'post_id' => $activity['target_content_id'],
            'post_content_preview' => $activity['target_content_summary'],
            'post_author_name' => $activity['target_owner_name'],
            'post_author_id' => $activity['target_owner_user_id'],

            'content' => $activity['comment_content'], // Will be NULL for reactions
            'comment_id' => ($activity['activity_type'] === 'comment') ? $activity['activity_id'] : null,

            'reaction_type' => $activity['reaction_type'], // Will be NULL for comments
            'reaction_id' => ($activity['activity_type'] === 'reaction') ? $activity['activity_id'] : null,


            'friend_name' => $activity['actor_name'],
            'friend_user_id' => $activity['actor_user_id'],
            'target_friend_name' => null,
            'target_friend_user_id' => null,
            'other_friend_name' => null,
            'other_friend_user_id' => null,
            'testimonial_id' => null,
            'rating' => null,
            'writer_name' => null,
            'writer_id' => null,
            'recipient_name' => null,
            'recipient_id' => null,
            // Media related fields, null for now
            'media_id' => $activity['media_id'] ?? null,
            'media_url' => $activity['media_url'] ?? null,
            'media_type' => $activity['media_type'] ?? null,
            'album_id' => $activity['album_id'] ?? null,
        ];
        
        if ($item['type'] === 'comment') {
            $item['friend_name'] = $item['actor_name'];
            $item['friend_user_id'] = $item['actor_user_id'];
            $item['post_author'] = $item['post_author_name'];
        } else if ($item['type'] === 'reaction') {
            $item['friend_name'] = $item['actor_name'];
            $item['friend_user_id'] = $item['actor_user_id'];
            $item['post_author'] = $item['post_author_name'];
        } else if ($item['type'] === 'comment_on_friend_post') {
            // For 'comment_on_friend_post', the 'actor' is the commenter (anyone)
            // and 'target_owner_name' is the friend whose post it is.
            // The JS might expect 'friend_name' to be the post owner in this context.
            $item['friend_name'] = $item['post_author_name'];
            $item['friend_user_id'] = $item['post_author_id'];
            // actor_name is already the commenter.
        } else if ($item['type'] === 'reaction_on_friend_post') {
            // Similar to 'comment_on_friend_post', actor is the reactor,
            // and 'target_owner_name' is the friend whose post it is.
            $item['friend_name'] = $item['post_author_name'];
            $item['friend_user_id'] = $item['post_author_id'];
            // actor_name is already the reactor.
        } else if ($item['type'] === 'media_comment'){
            // For 'media_comment', actor is the friend commenting.
            // Post author is 'target_owner_name'.
            // The JS might expect 'friend_name' to be the actor (commenter).
            $item['friend_name'] = $item['actor_name'];
            $item['friend_user_id'] = $item['actor_user_id'];
            if ($item['media_url'] && !filter_var($item['media_url'], FILTER_VALIDATE_URL)) {
                $item['media_url'] = '../uploads/media/' . htmlspecialchars($item['media_url']);
            }
        } else if ($item['type'] === 'media_reaction'){
            // For 'media_reaction', actor is the friend reacting.
            // Post author is 'target_owner_name'.
            // The JS might expect 'friend_name' to be the actor (reactor).
            $item['friend_name'] = $item['actor_name'];
            $item['friend_user_id'] = $item['actor_user_id'];
            if ($item['media_url'] && !filter_var($item['media_url'], FILTER_VALIDATE_URL)) {
                $item['media_url'] = '../uploads/media/' . htmlspecialchars($item['media_url']);
            }
        }


        $all_activities[] = $item;
    }

    // Sort by timestamp (newest first) - already ordered by SQL's outer ORDER BY
    // usort($all_activities, function($a, $b) {
    //     return $b['timestamp'] - $a['timestamp'];
    // });

    // Limit to 20 activities (already done in SQL's outer LIMIT)
    // $all_activities = array_slice($all_activities, 0, 20);

    // Count pending testimonials for notification badge - Keep this functionality
    $pendingTestimonialsStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM testimonials
        WHERE recipient_user_id = :user_id AND status = 'pending'
    ");
    $pendingTestimonialsStmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $pendingTestimonialsStmt->execute();
    $pendingCount = $pendingTestimonialsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'activities' => $all_activities,
        'count' => count($all_activities),
        'pending_testimonials_count' => $pendingCount
    ]);

} catch (Exception $e) {
    error_log("Activity feed error: " . $e->getMessage()); // Keep detailed server-side logging
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load activities',
        'debug' => $e->getMessage()
    ]);
}