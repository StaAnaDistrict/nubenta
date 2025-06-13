<?php
/**
 * Activity Feed API - Middle element for right sidebar
 * Returns activity notifications in JSON format for AJAX loading
 */

session_start();
require_once '../db.php'; // Ensure this path is correct for your db connection file

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user']['id']; // Use a consistent variable name

try {
    $current_user_id = (int)$current_user_id;
    
    $activity_sql_main = "
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
            p.id as post_id_for_activity
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
        -- Removed: AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    "; 

    // All other UNION ALL blocks are commented out for now
    /* -- Start of commented out SQL blocks
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
            NULL AS comment_content, 
            rt.name AS reaction_type, 
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
          -- Removed: AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    -- ... (all other UNION ALL blocks remain commented out here) ...
    UNION ALL
    (
        -- Friend reacts to media associated with a public post
        SELECT
            mr.reaction_id AS activity_id, 
            'media_reaction' AS activity_type,
            mr.user_id AS actor_user_id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS actor_name,
            u.profile_pic AS actor_profile_pic,
            u.gender AS actor_gender,
            p.user_id AS target_owner_user_id, 
            p.id AS target_content_id, 
            LEFT(p.content, 50) AS target_content_summary, 
            um.id AS media_id,
            um.filename AS media_url, 
            um.media_type AS media_type,
            um.album_id AS album_id,
            NULL AS comment_content,
            rt.name AS reaction_type,
            mr.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, 
            p.id as post_id_for_activity
        FROM media_reactions mr
        JOIN users u ON mr.user_id = u.id 
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        JOIN user_media um ON mr.media_id = um.id
        JOIN posts p ON um.post_id = p.id 
        JOIN users orig_u ON p.user_id = orig_u.id 
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id21 AND f.receiver_id = mr.user_id) OR (f.receiver_id = :current_user_id22 AND f.sender_id = mr.user_id))
        WHERE p.visibility = 'public' 
          AND um.post_id IS NOT NULL 
          AND f.status = 'accepted' 
          AND mr.user_id != :current_user_id23 
          AND p.user_id != :current_user_id24 
          -- Removed: AND mr.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
    -- End of commented out SQL blocks */

    // Append ORDER BY and LIMIT to the main SQL string
    $activity_sql = $activity_sql_main . " ORDER BY activity_created_at DESC LIMIT 20;";

    $activity_stmt = $pdo->prepare($activity_sql);
    
    $activity_stmt->bindParam(":current_user_id1", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id2", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id3", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id4", $current_user_id, PDO::PARAM_INT);
    
    /* -- Start of commented out bindParam calls
    $activity_stmt->bindParam(":current_user_id5", $current_user_id, PDO::PARAM_INT);
    // ... (rest of the commented out binds) ...
    $activity_stmt->bindParam(":current_user_id24", $current_user_id, PDO::PARAM_INT);
    -- End of commented out bindParam calls */
    
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

        $actorProfilePic = null; // Profile pictures will not be sent to the client

        $item = [
            'type' => $activity['activity_type'],
            'actor_name' => $activity['actor_name'],
            'actor_profile_pic' => $actorProfilePic, // Will be null
            'actor_user_id' => $activity['actor_user_id'],
            'activity_time' => $activity['activity_created_at'],
            'timestamp' => strtotime($activity['activity_created_at']),

            'post_id_for_activity' => $activity['target_content_id'],
            'post_id' => $activity['target_content_id'], 
            'post_content_preview' => $activity['target_content_summary'],
            'post_author_name' => $activity['target_owner_name'],
            'post_author_id' => $activity['target_owner_user_id'],
            
            'content' => $activity['comment_content'],
            'comment_id' => ($activity['activity_type'] === 'comment') ? $activity['activity_id'] : null,
            
            'reaction_type' => $activity['reaction_type'],
            'reaction_id' => ($activity['activity_type'] === 'reaction') ? $activity_id : null,

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
            'media_id' => $activity['media_id'] ?? null,
            'media_url' => $activity['media_url'] ?? null,
            'media_type' => $activity['media_type'] ?? null,
            'album_id' => $activity['album_id'] ?? null,
        ];
        
        if ($item['type'] === 'comment') {
            $item['friend_name'] = $item['actor_name']; 
            $item['friend_user_id'] = $item['actor_user_id'];
            $item['post_author'] = $item['post_author_name']; 
        }
        
        $all_activities[] = $item;
    }

    $pendingTestimonialsStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM testimonials
        WHERE recipient_user_id = :user_id AND status = 'pending'
    ");
    $pendingTestimonialsStmt->bindParam(":user_id", $current_user_id, PDO::PARAM_INT);
    $pendingTestimonialsStmt->execute();
    $pendingCount = $pendingTestimonialsStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    echo json_encode([
        'success' => true,
        'activities' => $all_activities,
        'count' => count($all_activities),
        'pending_testimonials_count' => $pendingCount
    ]);
    
    $jsonError = json_last_error();
    if ($jsonError !== JSON_ERROR_NONE) {
        error_log('JSON Encoding Error in api/add_ons_middle_element.php: ' . json_last_error_msg() . ' (Code: ' . $jsonError . ')');
    }

} catch (PDOException $e) {
    error_log("SQL Error in api/add_ons_middle_element.php: " . $e->getMessage() . " SQL was: " . $activity_sql); // Log the SQL
    echo json_encode([
        'success' => false,
        'error' => 'Database error while loading activities.',
        'debug_pdo' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General Error in api/add_ons_middle_element.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load activities due to a general error.',
        'debug_exception' => $e->getMessage()
    ]);
}
?>