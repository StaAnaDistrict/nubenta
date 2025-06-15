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
    // Block 1: Friend comments on any public post
    $activity_sql_block1 = "
        SELECT
            c.id AS activity_id,
            CAST('comment' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS activity_type,
            c.user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(u.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic,
            CAST(u.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            p.user_id AS target_owner_user_id,
            p.id AS target_content_id,
            CAST(LEFT(p.content, 50) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(c.content AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            c.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            p.id as post_id_for_activity,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN posts p ON c.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id1 AND f.receiver_id = c.user_id) OR (f.receiver_id = :current_user_id2 AND f.sender_id = c.user_id))
        WHERE p.visibility = 'public' AND f.status = 'accepted' AND c.user_id != :current_user_id3 AND p.user_id != :current_user_id4
    ";
    // Block 2: Friend reactions on any public post
    $activity_sql_block2 = "
    UNION ALL (
        SELECT
            pr.id AS activity_id, CAST('reaction' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS activity_type, pr.user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(u.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic, CAST(u.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            p.user_id AS target_owner_user_id, p.id AS target_content_id, CAST(LEFT(p.content, 50) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content, CAST(pr.reaction_type AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            pr.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            p.id as post_id_for_activity,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id
        JOIN posts p ON pr.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id5 AND f.receiver_id = pr.user_id) OR (f.receiver_id = :current_user_id6 AND f.sender_id = pr.user_id))
        WHERE p.visibility = 'public' AND f.status = 'accepted' AND pr.user_id != :current_user_id7 AND p.user_id != :current_user_id8
    )";
    // Block 3: Anyone comments on a friend's public post
    $activity_sql_block3 = "
    UNION ALL (
        SELECT
            c.id AS activity_id, CAST('comment_on_friend_post' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS activity_type, c.user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(u.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic, CAST(u.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            p.user_id AS target_owner_user_id, p.id AS target_content_id, CAST(LEFT(p.content, 50) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(c.content AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content, CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            c.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            p.id as post_id_for_activity,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN posts p ON c.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id9 AND f.receiver_id = p.user_id) OR (f.receiver_id = :current_user_id10 AND f.sender_id = p.user_id))
        WHERE p.visibility = 'public' AND f.status = 'accepted' AND p.user_id != :current_user_id11 AND c.user_id != :current_user_id12
    )";
    // Block 4: Anyone reacts to a friend's public post
    $activity_sql_block4 = "
    UNION ALL (
        SELECT
            pr.id AS activity_id, CAST('reaction_on_friend_post' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS activity_type, pr.user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(u.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic, CAST(u.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            p.user_id AS target_owner_user_id, p.id AS target_content_id, CAST(LEFT(p.content, 50) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content, CAST(pr.reaction_type AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            pr.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            p.id as post_id_for_activity,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id
        JOIN posts p ON pr.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id13 AND f.receiver_id = p.user_id) OR (f.receiver_id = :current_user_id14 AND f.sender_id = p.user_id))
        WHERE p.visibility = 'public' AND f.status = 'accepted' AND p.user_id != :current_user_id15 AND pr.user_id != :current_user_id16
    )";
    // Block 5: Friend comments on media associated with a public post
    $activity_sql_block5 = "
    UNION ALL (
        SELECT
            mc.id AS activity_id, CAST('media_comment' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS activity_type, mc.user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(u.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic, CAST(u.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            p.user_id AS target_owner_user_id, p.id AS target_content_id, CAST(LEFT(p.content, 50) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(um.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id, CAST(um.media_url AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url, CAST(um.media_type AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type, CAST(um.album_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(mc.content AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content, CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            mc.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            p.id as post_id_for_activity,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
        FROM media_comments mc
        JOIN users u ON mc.user_id = u.id
        JOIN user_media um ON mc.media_id = um.id
        JOIN posts p ON um.post_id = p.id
        JOIN users orig_u ON p.user_id = orig_u.id
        JOIN friend_requests f ON
            ((f.sender_id = :current_user_id17 AND f.receiver_id = mc.user_id) OR (f.receiver_id = :current_user_id18 AND f.sender_id = mc.user_id))
        WHERE p.visibility = 'public' AND um.post_id IS NOT NULL AND f.status = 'accepted' AND mc.user_id != :current_user_id19 AND p.user_id != :current_user_id20
    )";
    // Block 6: Friend reacts to media associated with a public post
    $activity_sql_block6 = "
    UNION ALL
    (
        SELECT
            mr.reaction_id AS activity_id, -- Corrected: was mr.id
            CAST('media_reaction' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS activity_type,
            mr.user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(u.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic,
            CAST(u.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            p.user_id AS target_owner_user_id,
            p.id AS target_content_id,
            CAST(LEFT(p.content, 50) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(um.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id,
            CAST(um.media_url AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url,
            CAST(um.media_type AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type,
            CAST(um.album_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content,
            CAST(rt.name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            mr.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            p.id as post_id_for_activity,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
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
    )
    ";
    // Block 7: Testimonial activities (written and received)
    $activity_sql_block7 = "
    UNION ALL (
        SELECT
            t.testimonial_id AS activity_id,
            CASE 
                WHEN t.writer_user_id = :current_user_id25 THEN 'testimonial_written'
                WHEN t.recipient_user_id = :current_user_id26 THEN 'testimonial_received'
                ELSE 'testimonial_other' END AS activity_type,
            t.writer_user_id AS actor_user_id,
            CAST(CONCAT_WS(' ', wu.first_name, wu.middle_name, wu.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_name,
            CAST(wu.profile_pic AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_profile_pic,
            CAST(wu.gender AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS actor_gender,
            t.recipient_user_id AS target_owner_user_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_content_summary,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_id,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_url,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS media_type,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS album_id,
            CAST(t.content AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS comment_content,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS reaction_type,
            t.created_at AS activity_created_at,
            CAST(CONCAT_WS(' ', ru.first_name, ru.middle_name, ru.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS target_owner_name,
            CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS post_id_for_activity,
            t.writer_user_id AS writer_id,
            CAST(CONCAT_WS(' ', wu.first_name, wu.middle_name, wu.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS writer_name,
            t.recipient_user_id AS recipient_id,
            CAST(CONCAT_WS(' ', ru.first_name, ru.middle_name, ru.last_name) AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS recipient_name
        FROM testimonials t
        JOIN users wu ON t.writer_user_id = wu.id
        JOIN users ru ON t.recipient_user_id = ru.id
        LEFT JOIN friend_requests f1 ON 
            ((f1.sender_id = :current_user_id27 AND f1.receiver_id = t.writer_user_id) OR 
             (f1.receiver_id = :current_user_id28 AND f1.sender_id = t.writer_user_id))
        LEFT JOIN friend_requests f2 ON 
            ((f2.sender_id = :current_user_id29 AND f2.receiver_id = t.recipient_user_id) OR 
             (f2.receiver_id = :current_user_id30 AND f2.sender_id = t.recipient_user_id))
        WHERE t.status = 'approved'
        AND (
            t.writer_user_id = :current_user_id31 
            OR t.recipient_user_id = :current_user_id32
            OR (f1.status = 'accepted' AND f1.id IS NOT NULL)
            OR (f2.status = 'accepted' AND f2.id IS NOT NULL)
        )
    )";

    $activity_sql = $activity_sql_block1 . $activity_sql_block2 . $activity_sql_block3 . $activity_sql_block4 . $activity_sql_block5 . $activity_sql_block6 . $activity_sql_block7 . " ORDER BY activity_created_at DESC LIMIT 20;";

    $log_prefix = "[ActivityFeed_OB_Test_B6_Fix1]";
    error_log($log_prefix . " Preparing SQL: (Length: " . strlen($activity_sql) . ")");
    $activity_stmt = $pdo->prepare($activity_sql);

    if (!$activity_stmt) {
        $pdo_error = $pdo->errorInfo();
        throw new PDOException("PDO::prepare() failed: " . ($pdo_error[2] ?? 'Unknown error during prepare'));
    }
    // Bind params for all 7 blocks
    $activity_stmt->bindParam(":current_user_id1", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id2", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id3", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id4", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id5", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id6", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id7", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id8", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id9", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id10", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id11", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id12", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id13", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id14", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id15", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id16", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id17", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id18", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id19", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id20", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id21", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id22", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id23", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id24", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id25", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id26", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id27", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id28", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id29", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id30", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id31", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id32", $current_user_id, PDO::PARAM_INT);

    $execute_success = $activity_stmt->execute();
    if (!$execute_success) {
        $stmt_error = $activity_stmt->errorInfo();
        throw new PDOException("PDOStatement::execute() failed: " . ($stmt_error[2] ?? 'Unknown error during execute'));
    }

    $fetched_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log($log_prefix . " Fetched " . count($fetched_activities) . " raw activities (All 7 Blocks).");

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
            'actor_name' => $activity_row['actor_name'] ?? $activity_row['writer_name'] ?? '',
            'actor_profile_pic' => $activity_row['actor_profile_pic'] ?? $activity_row['writer_profile_pic'] ?? '',
            'actor_user_id' => $activity_row['actor_user_id'] ?? $activity_row['writer_id'] ?? '',
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
            'writer_name' => $activity_row['writer_name'] ?? null,
            'writer_id' => $activity_row['writer_id'] ?? null,
            'recipient_name' => $activity_row['recipient_name'] ?? null,
            'recipient_id' => $activity_row['recipient_id'] ?? null,
            'media_id' => $activity_row['media_id'] ?? null,
            'media_url' => $activity_row['media_url'] ?? null,
            'media_type' => $activity_row['media_type'] ?? null,
            'album_id' => $activity_row['album_id'] ?? null,
        ];

        if ($item['type'] === 'comment' || $item['type'] === 'reaction') {
            // Default friend_name (actor_name) is correct
        } elseif ($item['type'] === 'comment_on_friend_post' || $item['type'] === 'reaction_on_friend_post') {
            $item['friend_name'] = $item['post_author_name'];
            $item['friend_user_id'] = $item['post_author_id'];
        } elseif ($item['type'] === 'media_comment' || $item['type'] === 'media_reaction') {
            // Default friend_name (actor_name) is correct
        }

        $all_activities_processed[] = $item;
    }

    error_log($log_prefix . " Processed " . count($all_activities_processed) . " activities for JSON output (All 7 Blocks).");

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