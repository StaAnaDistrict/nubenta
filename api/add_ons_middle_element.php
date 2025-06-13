<?php
/**
 * Activity Feed API - Middle element for right sidebar
 * Returns activity notifications in JSON format for AJAX loading
 */

error_reporting(0); 
ob_start(); 

session_start();
require_once '../db.php'; 

$response = [
    'success' => false,
    'activities' => [],
    'count' => 0,
    'pending_testimonials_count' => 0,
    'error' => 'Initialization error', 
    'debug' => null
];

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    $response['error'] = 'Not logged in';
    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$current_user_id = (int)$_SESSION['user']['id'];
$activity_sql = ""; 

try {
    $activity_sql_block1 = "
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
            NULL AS media_url, -- For this block, media fields are NULL
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
    "; 

    $activity_sql_block2 = "
    UNION ALL
    (
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
            pr.reaction_type AS reaction_type, 
            pr.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name,
            p.id as post_id_for_activity
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id
        JOIN posts p ON pr.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON 
            ((f.sender_id = :current_user_id5 AND f.receiver_id = pr.user_id) OR (f.receiver_id = :current_user_id6 AND f.sender_id = pr.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND pr.user_id != :current_user_id7
          AND p.user_id != :current_user_id8
    )
    ";

    $activity_sql_block3 = "
    UNION ALL
    (
        SELECT
            c.id AS activity_id,
            'comment_on_friend_post' AS activity_type, 
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
            ((f.sender_id = :current_user_id9 AND f.receiver_id = p.user_id) OR (f.receiver_id = :current_user_id10 AND f.sender_id = p.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND p.user_id != :current_user_id11 
          AND c.user_id != :current_user_id12 
    )
    ";

    $activity_sql_block4 = "
    UNION ALL
    (
        SELECT
            pr.id AS activity_id,
            'reaction_on_friend_post' AS activity_type, 
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
            pr.reaction_type AS reaction_type, 
            pr.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, 
            p.id as post_id_for_activity
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id 
        JOIN posts p ON pr.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id 
        JOIN friend_requests f ON 
            ((f.sender_id = :current_user_id13 AND f.receiver_id = p.user_id) OR (f.receiver_id = :current_user_id14 AND f.sender_id = p.user_id))
        WHERE p.visibility = 'public'
          AND f.status = 'accepted'
          AND p.user_id != :current_user_id15 
          AND pr.user_id != :current_user_id16 
    )
    ";

    $activity_sql_block5 = "
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
            p.user_id AS target_owner_user_id, 
            p.id AS target_content_id, 
            LEFT(p.content, 50) AS target_content_summary, 
            um.id AS media_id,
            um.media_url AS media_url, -- Corrected from um.filename
            um.media_type AS media_type,
            um.album_id AS album_id,
            mc.content AS comment_content,
            NULL AS reaction_type,
            mc.created_at AS activity_created_at,
            CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS target_owner_name, 
            p.id as post_id_for_activity 
        FROM media_comments mc
        JOIN users u ON mc.user_id = u.id 
        JOIN user_media um ON mc.media_id = um.id
        JOIN posts p ON um.post_id = p.id 
        JOIN users orig_u ON p.user_id = orig_u.id 
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id17 AND f.receiver_id = mc.user_id) OR (f.receiver_id = :current_user_id18 AND f.sender_id = mc.user_id))
        WHERE p.visibility = 'public' 
          AND um.post_id IS NOT NULL 
          AND f.status = 'accepted' 
          AND mc.user_id != :current_user_id19 
          AND p.user_id != :current_user_id20 
    )
    ";

    /* -- Start of commented out SQL blocks for block 6
    UNION ALL 
    ( 
        -- ... SQL for block 6 ... 
    )
    -- End of commented out SQL blocks */

    $activity_sql = $activity_sql_block1 . $activity_sql_block2 . $activity_sql_block3 . $activity_sql_block4 . $activity_sql_block5 . " ORDER BY activity_created_at DESC LIMIT 20;";
    
    $log_prefix = "[ActivityFeed_OB_Test_B5_Fix1]"; 
    error_log($log_prefix . " Preparing SQL: (Length: " . strlen($activity_sql) . ")");
    $activity_stmt = $pdo->prepare($activity_sql);
    
    if (!$activity_stmt) {
        $pdo_error = $pdo->errorInfo();
        throw new PDOException("PDO::prepare() failed: " . ($pdo_error[2] ?? 'Unknown error during prepare'));
    }

    // Bind for Block 1
    $activity_stmt->bindParam(":current_user_id1", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id2", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id3", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id4", $current_user_id, PDO::PARAM_INT);
    
    // Bind for Block 2
    $activity_stmt->bindParam(":current_user_id5", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id6", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id7", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id8", $current_user_id, PDO::PARAM_INT);

    // Bind for Block 3
    $activity_stmt->bindParam(":current_user_id9", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id10", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id11", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id12", $current_user_id, PDO::PARAM_INT);

    // Bind for Block 4
    $activity_stmt->bindParam(":current_user_id13", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id14", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id15", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id16", $current_user_id, PDO::PARAM_INT);
    
    // Bind for Block 5
    $activity_stmt->bindParam(":current_user_id17", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id18", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id19", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id20", $current_user_id, PDO::PARAM_INT);

    /* -- Commented out bindParam calls for block 6 (21-24) -- */
    
    $execute_success = $activity_stmt->execute();
    if (!$execute_success) {
        $stmt_error = $activity_stmt->errorInfo();
        throw new PDOException("PDOStatement::execute() failed: " . ($stmt_error[2] ?? 'Unknown error during execute'));
    }
    
    $fetched_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log($log_prefix . " Fetched " . count($fetched_activities) . " raw activities (Blocks 1-5).");

    $all_activities_processed = [];
    $processed_event_ids = []; 

    foreach ($fetched_activities as $activity_row) {
        $unique_event_key = $activity_row['activity_type'] . '_' . $activity_row['activity_id'];
        if (isset($processed_event_ids[$unique_event_key])) {
            continue;
        }
        $processed_event_ids[$unique_event_key] = true;
        $actorProfilePic = null; 
        $item = [
            'type' => $activity_row['activity_type'],
            'actor_name' => $activity_row['actor_name'],
            'actor_profile_pic' => $actorProfilePic,
            'actor_user_id' => $activity_row['actor_user_id'],
            'activity_time' => $activity_row['activity_created_at'],
            'timestamp' => strtotime($activity_row['activity_created_at']),
            'post_id_for_activity' => $activity_row['target_content_id'],
            'post_id' => $activity_row['target_content_id'], 
            'post_content_preview' => $activity_row['target_content_summary'],
            'post_author_name' => $activity_row['target_owner_name'], 
            'post_author_id' => $activity_row['target_owner_user_id'], 
            'content' => $activity_row['comment_content'], 
            'comment_id' => ($activity_row['activity_type'] === 'comment' || $activity_row['activity_type'] === 'comment_on_friend_post' || $activity_row['activity_type'] === 'media_comment') ? $activity_row['activity_id'] : null,
            'reaction_type' => $activity_row['reaction_type'], 
            'reaction_id' => ($activity_row['activity_type'] === 'reaction' || $activity_row['activity_type'] === 'reaction_on_friend_post' || $activity_row['activity_type'] === 'media_reaction') ? $activity_row['activity_id'] : null,
            'friend_name' => $activity_row['actor_name'], 
            'friend_user_id' => $activity_row['actor_user_id'],
            'target_friend_name' => null, 'target_friend_user_id' => null, 'other_friend_name' => null,
            'other_friend_user_id' => null, 'testimonial_id' => null, 'rating' => null,
            'writer_name' => null, 'writer_id' => null, 'recipient_name' => null, 'recipient_id' => null,
            'media_id' => $activity_row['media_id'] ?? null, 
            'media_url' => $activity_row['media_url'] ?? null, 
            'media_type' => $activity_row['media_type'] ?? null, 
            'album_id' => $activity_row['album_id'] ?? null,
        ];
        
        if ($item['type'] === 'comment' || $item['type'] === 'reaction') {
            // Default assignment for friend_name (actor_name) is correct here.
        } elseif ($item['type'] === 'comment_on_friend_post' || $item['type'] === 'reaction_on_friend_post') {
            $item['friend_name'] = $item['post_author_name']; 
            $item['friend_user_id'] = $item['post_author_id'];
        } elseif ($item['type'] === 'media_comment' || $item['type'] === 'media_reaction') {
            // Actor is the friend. Post author is target_owner_name.
        }
        
        $all_activities_processed[] = $item;
    }
    
    error_log($log_prefix . " Processed " . count($all_activities_processed) . " activities for JSON output (Blocks 1-5).");

    $response['activities'] = $all_activities_processed;
    $response['count'] = count($all_activities_processed);
    $response['success'] = true;
    $response['error'] = null; 

    if (isset($pdo)) {
        $pendingTestimonialsStmt = $pdo->prepare("SELECT COUNT(*) as count FROM testimonials WHERE recipient_user_id = :user_id AND status = 'pending'");
        if ($pendingTestimonialsStmt) {
            $pendingTestimonialsStmt->bindParam(":user_id", $current_user_id, PDO::PARAM_INT);
            if ($pendingTestimonialsStmt->execute()) {
                $response['pending_testimonials_count'] = (int)$pendingTestimonialsStmt->fetchColumn();
            } else { error_log($log_prefix . " Testimonial count exec failed: " . implode(" ", $pendingTestimonialsStmt->errorInfo())); }
        } else { error_log($log_prefix . " Testimonial count prep failed: " . implode(" ", $pdo->errorInfo())); }
    } else {
        error_log($log_prefix . " PDO object not available for testimonial count.");
    }

} catch (PDOException $e) {
    error_log($log_prefix . " PDOException: " . $e->getMessage() . " SQL that failed (if available): " . $activity_sql);
    $response['error'] = 'Database error.'; 
    $response['debug'] = $e->getMessage(); 
    $response['success'] = false; 
    $response['activities'] = []; 
    $response['count'] = 0;
} catch (Exception $e) {
    error_log($log_prefix . " General Exception: " . $e->getMessage());
    $response['error'] = 'General error.'; 
    $response['debug'] = $e->getMessage(); 
    $response['success'] = false; 
    $response['activities'] = []; 
    $response['count'] = 0;
}

ob_clean(); 
header('Content-Type: application/json'); 
echo json_encode($response);

$jsonError = json_last_error();
if ($jsonError !== JSON_ERROR_NONE) {
    error_log($log_prefix . ' JSON Encoding Error: ' . json_last_error_msg() . ' (Code: ' . $jsonError . ')');
}
exit; 
?>