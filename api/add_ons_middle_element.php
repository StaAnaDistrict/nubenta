<?php
/**
 * Activity Feed API - Middle element for right sidebar
 * Returns activity notifications in JSON format for AJAX loading
 */

// Attempt to control output and errors from the very start
error_reporting(0); // Suppress direct error output, rely on logs
ob_start(); // Start output buffering

session_start();
// db.php is critical. If it fails or outputs anything, it can break JSON.
// We'll assume it's included correctly and doesn't output on its own for now.
require_once '../db.php'; 

// Default response structure
$response = [
    'success' => false,
    'activities' => [],
    'count' => 0,
    'pending_testimonials_count' => 0,
    'error' => 'Initialization error', // Default error
    'debug' => null
];

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    $response['error'] = 'Not logged in';
    ob_clean(); // Clean buffer before this output
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$current_user_id = (int)$_SESSION['user']['id'];
$activity_sql = ""; // Initialize for logging

try {
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
    "; 

    /* -- All other UNION ALL blocks remain commented out -- */

    $activity_sql = $activity_sql_main . " ORDER BY activity_created_at DESC LIMIT 20;";
    
    error_log("[ActivityFeed_OB_Test] Preparing SQL: " . $activity_sql);
    $activity_stmt = $pdo->prepare($activity_sql);
    
    if (!$activity_stmt) {
        $pdo_error = $pdo->errorInfo();
        throw new PDOException("PDO::prepare() failed: " . ($pdo_error[2] ?? 'Unknown error during prepare'));
    }

    $activity_stmt->bindParam(":current_user_id1", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id2", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id3", $current_user_id, PDO::PARAM_INT);
    $activity_stmt->bindParam(":current_user_id4", $current_user_id, PDO::PARAM_INT);
    
    $execute_success = $activity_stmt->execute();
    if (!$execute_success) {
        $stmt_error = $activity_stmt->errorInfo();
        throw new PDOException("PDOStatement::execute() failed: " . ($stmt_error[2] ?? 'Unknown error during execute'));
    }
    
    $fetched_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("[ActivityFeed_OB_Test] Fetched " . count($fetched_activities) . " raw activities.");

    $all_activities_processed = [];
    // ... (processing loop remains the same as the last good version, ensuring actorProfilePic = null)
    foreach ($fetched_activities as $activity_row) {
        // Unique key, actorProfilePic = null, item array construction...
        // This part is copied from the previous version that had correct processing logic
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
            'comment_id' => ($activity_row['activity_type'] === 'comment') ? $activity_row['activity_id'] : null,
            'reaction_type' => $activity_row['reaction_type'],
            'reaction_id' => ($activity_row['activity_type'] === 'reaction') ? $activity_row['activity_id'] : null,
            'friend_name' => $activity_row['actor_name'], 
            'friend_user_id' => $activity_row['actor_user_id'],
            'target_friend_name' => null, 'target_friend_user_id' => null, 'other_friend_name' => null,
            'other_friend_user_id' => null, 'testimonial_id' => null, 'rating' => null,
            'writer_name' => null, 'writer_id' => null, 'recipient_name' => null, 'recipient_id' => null,
            'media_id' => $activity_row['media_id'] ?? null, 'media_url' => $activity_row['media_url'] ?? null,
            'media_type' => $activity_row['media_type'] ?? null, 'album_id' => $activity_row['album_id'] ?? null,
        ];
        if ($item['type'] === 'comment') {
            $item['friend_name'] = $item['actor_name']; 
            $item['friend_user_id'] = $item['actor_user_id'];
            $item['post_author'] = $item['post_author_name']; 
        }
        $all_activities_processed[] = $item;
    }
    // End of copied processing loop

    error_log("[ActivityFeed_OB_Test] Processed " . count($all_activities_processed) . " activities for JSON output.");

    $response['activities'] = $all_activities_processed;
    $response['count'] = count($all_activities_processed);
    $response['success'] = true;
    $response['error'] = null; // Clear default error

    // Testimonial count
    if (isset($pdo)) { // Ensure $pdo is available
        $pendingTestimonialsStmt = $pdo->prepare("SELECT COUNT(*) as count FROM testimonials WHERE recipient_user_id = :user_id AND status = 'pending'");
        if ($pendingTestimonialsStmt) {
            $pendingTestimonialsStmt->bindParam(":user_id", $current_user_id, PDO::PARAM_INT);
            if ($pendingTestimonialsStmt->execute()) {
                $response['pending_testimonials_count'] = (int)$pendingTestimonialsStmt->fetchColumn();
            } else { error_log("[ActivityFeed_OB_Test] Testimonial count exec failed: " . implode(" ", $pendingTestimonialsStmt->errorInfo())); }
        } else { error_log("[ActivityFeed_OB_Test] Testimonial count prep failed: " . implode(" ", $pdo->errorInfo())); }
    } else {
        error_log("[ActivityFeed_OB_Test] PDO object not available for testimonial count.");
    }

} catch (PDOException $e) {
    error_log("[ActivityFeed_OB_Test] PDOException: " . $e->getMessage() . " SQL that failed (if available): " . $activity_sql);
    $response['error'] = 'Database error.'; // Simplified error for client
    $response['debug'] = $e->getMessage(); // Debug info
    $response['success'] = false; // Ensure success is false
    $response['activities'] = []; // Ensure activities is empty on error
    $response['count'] = 0;
} catch (Exception $e) {
    error_log("[ActivityFeed_OB_Test] General Exception: " . $e->getMessage());
    $response['error'] = 'General error.'; // Simplified error for client
    $response['debug'] = $e->getMessage(); // Debug info
    $response['success'] = false; // Ensure success is false
    $response['activities'] = []; // Ensure activities is empty on error
    $response['count'] = 0;
}

ob_clean(); // Discard any previous output
header('Content-Type: application/json'); // Set header just before output
echo json_encode($response);

// JSON encoding error logging (less likely to be an issue now with default $response)
$jsonError = json_last_error();
if ($jsonError !== JSON_ERROR_NONE) {
    error_log('[ActivityFeed_OB_Test] JSON Encoding Error: ' . json_last_error_msg() . ' (Code: ' . $jsonError . ')');
}
exit; // Ensure script termination
?>