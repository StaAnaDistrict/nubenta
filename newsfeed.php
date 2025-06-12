<?php
// Enable error reporting at the top of the file
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// Check if JSON format is requested
$json_requested = isset($_GET['format']) && $_GET['format'] === 'json';

// If JSON is requested, don't output any HTML or debug information
if (!$json_requested) {
  // Debug information - only output for HTML view
  echo "<!-- Debug: User ID: " . $user_id . " -->";
}

try {
    // Fetch posts from the user and their friends (RESTORED WORKING VERSION)
    $stmt = $pdo->prepare("
      SELECT posts.*,
             CONCAT_WS(' ', users.first_name, users.middle_name, users.last_name) as author_name,
             users.profile_pic,
             users.gender,
             posts.is_removed,
             posts.removed_reason,
             posts.is_flagged,
             posts.flag_reason,
             users.id as author_id,
             COALESCE(MAX(c.created_at), MAX(pr.created_at), posts.created_at) as last_activity_at,
             posts.original_post_id, -- Selected for shared post logic
             posts.post_type,        -- Selected for shared post logic
             orig_p.id as original_id,
             orig_p.content as original_content,
             orig_p.media as original_media,
             orig_p.created_at as original_created_at,
             orig_p.visibility as original_visibility,
             orig_u.id as original_author_id,
             CONCAT_WS(' ', orig_u.first_name, orig_u.middle_name, orig_u.last_name) as original_author_name,
             orig_u.profile_pic as original_author_profile_pic,
             orig_u.gender as original_author_gender
      FROM posts
      JOIN users ON posts.user_id = users.id
      LEFT JOIN comments c ON posts.id = c.post_id
      LEFT JOIN post_reactions pr ON posts.id = pr.post_id
      LEFT JOIN posts orig_p ON posts.original_post_id = orig_p.id AND posts.post_type = 'shared'
      LEFT JOIN users orig_u ON orig_p.user_id = orig_u.id
      WHERE
        -- Posts from the current user
        posts.user_id = :user_id1

        OR

        -- Posts from friends
        (posts.user_id IN (
          -- Get all friends (users with accepted friend requests)
          SELECT
            CASE
              WHEN sender_id = :user_id2 THEN receiver_id
              WHEN receiver_id = :user_id3 THEN sender_id
            END as friend_id
          FROM friend_requests
          WHERE (sender_id = :user_id4 OR receiver_id = :user_id5)
            AND status = 'accepted'
        )
        -- Only show public or friends-only posts from friends
        AND (posts.visibility = 'public' OR posts.visibility = 'friends'))

        OR

        -- Public posts from users the current user follows
        (
            posts.visibility = 'public' AND
            posts.user_id IN (
                SELECT fe.followed_entity_id FROM follows fe
                WHERE fe.follower_id = :user_id_followed
                  AND fe.followed_entity_type = 'user'
            )
            -- Note: posts.user_id != :user_id1 -- This simple exclusion might be useful
            -- to avoid showing user's own posts if they somehow follow themselves,
            -- though FollowManager should prevent self-follow.
            -- More complex exclusions for friends are omitted for now.
        )
      GROUP BY
            posts.id,
            users.first_name, users.middle_name, users.last_name, users.profile_pic, users.gender, users.id,
            orig_p.id, orig_u.id, orig_u.first_name, orig_u.middle_name, orig_u.last_name, orig_u.profile_pic, orig_u.gender
            -- Add all selected non-aggregated columns from original post author and original post itself
            -- posts.* columns are covered by posts.id (PK)
      ORDER BY last_activity_at DESC, posts.created_at DESC
      LIMIT 20
    ");

    // Bind each parameter separately with unique names
    $stmt->bindParam(':user_id1', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id3', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id4', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id5', $user_id, PDO::PARAM_INT);
    // Add binding for the new parameter
    $stmt->bindParam(':user_id_followed', $user_id, PDO::PARAM_INT);

    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PHASE 1: Get recent friend activities (separate query for safety)
    $friend_activities = [];
    try {
        // FIXED VERSION - Friend comments + reactions with correct column names
        $activity_stmt = $pdo->prepare("
            (
                -- 1. Friend comments on any public post
                SELECT DISTINCT posts.*,
                       CONCAT_WS(' ', post_author.first_name, post_author.middle_name, post_author.last_name) as author_name,
                       post_author.profile_pic,
                       post_author.gender,
                       post_author.id as author_id,
                       'comment' as activity_type,
                       CONCAT_WS(' ', friend_user.first_name, friend_user.middle_name, friend_user.last_name) as friend_name,
                       friend_user.profile_pic as friend_profile_pic,
                       comments.created_at as activity_time,
                       comments.id as comment_id,
                       NULL as media_id,
                       NULL as reaction_type
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
                  AND comments.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            )

            UNION ALL

            (
                -- 2. Anyone reacts to friend's posts (YOUR TEST CASE - FIXED!)
                SELECT DISTINCT posts.*,
                       CONCAT_WS(' ', post_author.first_name, post_author.middle_name, post_author.last_name) as author_name,
                       post_author.profile_pic,
                       post_author.gender,
                       post_author.id as author_id,
                       'reaction_on_friend_post' as activity_type,
                       CONCAT_WS(' ', reactor.first_name, reactor.middle_name, reactor.last_name) as friend_name,
                       reactor.profile_pic as friend_profile_pic,
                       pr.created_at as activity_time,
                       NULL as comment_id,
                       NULL as media_id,
                       pr.reaction_type as reaction_type
                FROM posts
                JOIN users post_author ON posts.user_id = post_author.id
                JOIN post_reactions pr ON posts.id = pr.post_id
                JOIN users reactor ON pr.user_id = reactor.id
                WHERE posts.visibility = 'public'
                  AND posts.user_id IN (
                    SELECT CASE WHEN sender_id = :user_id6 THEN receiver_id WHEN receiver_id = :user_id7 THEN sender_id END
                    FROM friend_requests WHERE (sender_id = :user_id8 OR receiver_id = :user_id9) AND status = 'accepted'
                  )
                  AND pr.user_id != :user_id10
                  AND posts.user_id != :user_id11
                  AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            )

            ORDER BY activity_time DESC
            LIMIT 15
        ");

        for ($i = 1; $i <= 11; $i++) {
            $activity_stmt->bindParam(":user_id$i", $user_id, PDO::PARAM_INT);
        }

        $activity_stmt->execute();
        $friend_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

        // DEBUG: Log successful execution
        error_log("DEBUG: Activity query executed successfully, found " . count($friend_activities) . " activities");

        // ADDITIONAL SOCIAL ACTIVITIES (non-post related)
        $social_activities = [];
        try {
            // Get friend requests, profile updates, etc.
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
                           NULL as other_friend_user_id
                    FROM friend_requests fr
                    JOIN users u ON (u.id = CASE WHEN fr.sender_id = :user_id1 THEN fr.receiver_id ELSE fr.sender_id END)
                    WHERE (fr.sender_id = :user_id2 OR fr.receiver_id = :user_id3)
                      AND fr.status = 'accepted'
                      AND COALESCE(fr.accepted_at, fr.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                )

                UNION ALL

                (
                    -- Friends making new friends (SIMPLIFIED DEBUG VERSION!)
                    SELECT 'friend_connection' as activity_type,
                           CONCAT_WS(' ', friend1.first_name, friend1.middle_name, friend1.last_name) as friend_name,
                           friend1.profile_pic as friend_profile_pic,
                           COALESCE(fr.accepted_at, fr.created_at) as activity_time,
                           fr.id as activity_id,
                           'connected' as extra_info,
                           CONCAT_WS(' ', friend2.first_name, friend2.middle_name, friend2.last_name) as other_friend_name,
                           friend1.id as friend_user_id,
                           friend2.id as other_friend_user_id
                    FROM friend_requests fr
                    JOIN users friend1 ON friend1.id = fr.sender_id
                    JOIN users friend2 ON friend2.id = fr.receiver_id
                    WHERE fr.status = 'accepted'
                      AND COALESCE(fr.accepted_at, fr.created_at) >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                      AND fr.sender_id != :user_id4
                      AND fr.receiver_id != :user_id5
                )

                ORDER BY activity_time DESC
                LIMIT 10
            ");

            for ($i = 1; $i <= 5; $i++) {
                $social_stmt->bindParam(":user_id$i", $user_id, PDO::PARAM_INT);
            }

            $social_stmt->execute();
            $social_activities = $social_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Social activities query failed: " . $e->getMessage());
        }

        // DEBUG: Log friend activities
        error_log("DEBUG: User $user_id - Found " . count($friend_activities) . " friend activities");
        error_log("DEBUG: User $user_id - Found " . count($social_activities) . " social activities");
        if (count($friend_activities) > 0) {
            error_log("DEBUG: First activity: " . json_encode($friend_activities[0]));
        } else {
            // Debug why no activities found
            error_log("DEBUG: No friend activities found. Checking query components...");

            // Check if user has friends
            $friends_check = $pdo->prepare("
                SELECT COUNT(*) as friend_count
                FROM friend_requests
                WHERE (sender_id = :user_id1 OR receiver_id = :user_id2)
                  AND status = 'accepted'
            ");
            $friends_check->bindParam(':user_id1', $user_id, PDO::PARAM_INT);
            $friends_check->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
            $friends_check->execute();
            $friend_count = $friends_check->fetch(PDO::FETCH_ASSOC)['friend_count'];
            error_log("DEBUG: User $user_id has $friend_count friends");

            // Check if there are recent comments
            $comments_check = $pdo->prepare("
                SELECT COUNT(*) as comment_count
                FROM comments c
                JOIN posts p ON c.post_id = p.id
                WHERE p.visibility = 'public'
                  AND c.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
            ");
            $comments_check->execute();
            $comment_count = $comments_check->fetch(PDO::FETCH_ASSOC)['comment_count'];
            error_log("DEBUG: Found $comment_count recent public comments");
        }
    } catch (Exception $e) {
        // If friend activities fail, continue with regular posts
        error_log("Friend activities query failed: " . $e->getMessage());
        error_log("Friend activities query error details: " . print_r($e, true));
    }

    // Define default profile pictures
    $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
    $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

    // PHASE 2: Merge posts with friend activities and prioritize by activity
    // $posts (from main query) is already sorted by last_activity_at DESC.
    // Initialize $all_posts with these already sorted posts.
    $all_posts = $posts;
    // Set a default 'activity_priority' for these posts based on their 'last_activity_at' or 'created_at'
    // This helps in the final sort if other activity types don't have 'last_activity_at'.
    foreach ($all_posts as &$post_item) { // Use reference to modify array directly
        $post_item['activity_priority'] = strtotime($post_item['last_activity_at'] ?? $post_item['created_at']);
        $post_item['is_friend_activity'] = false; // Mark these as not being "friend_activity" type items initially
    }
    unset($post_item); // Unset reference

    // Add friend activity posts (avoid duplicates)
    $existing_post_ids = array_column($posts, 'id');
    foreach ($friend_activities as $activity) {
        if (!in_array($activity['id'], $existing_post_ids)) {
            $activity['is_friend_activity'] = true;
            $activity['activity_priority'] = strtotime($activity['activity_time']);
            $all_posts[] = $activity;
        } else {
            // Post already exists, just add activity metadata
            foreach ($all_posts as &$existing_post) {
                if ($existing_post['id'] == $activity['id']) {
                    $existing_post['friend_activity'] = [
                        'type' => $activity['activity_type'],
                        'friend_name' => $activity['friend_name'],
                        'friend_profile_pic' => $activity['friend_profile_pic'],
                        'activity_time' => $activity['activity_time'],
                        'comment_id' => $activity['comment_id'],
                        'media_id' => $activity['media_id'],
                        'reaction_type' => $activity['reaction_type']
                    ];
                    $existing_post['activity_priority'] = strtotime($activity['activity_time']);
                    break;
                }
            }
        }
    }

    // Add social activities as special posts (for friend connections, etc.)
    foreach ($social_activities as $social_activity) {
        // Create a fake post structure for social activities
        $fake_post = [
            'id' => 'social_' . $social_activity['activity_id'],
            'user_id' => 0, // No specific user
            'content' => '', // No content
            'media' => '[]', // No media
            'created_at' => $social_activity['activity_time'],
            'visibility' => 'public',
            'is_removed' => false,
            'removed_reason' => '',
            'is_flagged' => false,
            'flag_reason' => '',
            'author_name' => 'NubentaUpdates',
            'profile_pic' => null,
            'gender' => null,
            'author_id' => 0,
            'is_friend_activity' => true,
            'activity_priority' => strtotime($social_activity['activity_time']),
            'friend_activity' => [
                'type' => $social_activity['activity_type'],
                'friend_name' => $social_activity['friend_name'],
                'friend_profile_pic' => $social_activity['friend_profile_pic'],
                'activity_time' => $social_activity['activity_time'],
                'comment_id' => null,
                'media_id' => null,
                'reaction_type' => null,
                'other_friend_name' => $social_activity['other_friend_name'] ?? null,
                'friend_user_id' => $social_activity['friend_user_id'] ?? null,
                'other_friend_user_id' => $social_activity['other_friend_user_id'] ?? null
            ]
        ];
        $all_posts[] = $fake_post;
    }

    // Sort by activity priority (recent activities first)
    // 'last_activity_at' from main query posts, 'activity_priority' from other activity types
    usort($all_posts, function($a, $b) {
        $time_a = $a['activity_priority'] ?? strtotime($a['created_at'] ?? 0);
        $time_b = $b['activity_priority'] ?? strtotime($b['created_at'] ?? 0);
        return $time_b - $time_a;
    });

    // Limit to 20 posts total after merging all types
    $all_posts = array_slice($all_posts, 0, 20);

    // DEBUG: Log merged posts
    error_log("DEBUG: User $user_id - Total merged posts: " . count($all_posts));
    $activity_posts = array_filter($all_posts, function($post) {
        return !empty($post['friend_activity']);
    });
    error_log("DEBUG: User $user_id - Posts with friend activity: " . count($activity_posts));

    // Format posts for JSON output
    $formatted_posts = [];
    foreach ($all_posts as $post) {
        // Determine profile picture
        $profilePic = !empty($post['profile_pic'])
            ? 'uploads/profile_pics/' . htmlspecialchars($post['profile_pic'])
            : ($post['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);

        // Fix media path - don't add uploads/post_media/ if it's already a complete path
        $mediaPath = null;
        if (!empty($post['media'])) {
            // Check if it's a JSON string
            if (substr($post['media'], 0, 1) === '[') {
                // It's already a JSON array, use as is
                $mediaPath = $post['media'];
            } else if (strpos($post['media'], 'uploads/') === 0 || strpos($post['media'], 'http') === 0) {
                // Already has uploads/ prefix or is a full URL, use as is
                $mediaPath = $post['media'];
            } else {
                // Add the prefix
                $mediaPath = 'uploads/post_media/' . $post['media'];
            }
        }

        // Handle friend activity data
        $friendActivityData = null;
        if (!empty($post['friend_activity'])) {
            $friendProfilePic = !empty($post['friend_activity']['friend_profile_pic'])
                ? 'uploads/profile_pics/' . htmlspecialchars($post['friend_activity']['friend_profile_pic'])
                : $defaultMalePic;

            $friendActivityData = [
                'type' => $post['friend_activity']['type'],
                'friend_name' => htmlspecialchars($post['friend_activity']['friend_name']),
                'friend_profile_pic' => $friendProfilePic,
                'activity_time' => $post['friend_activity']['activity_time'],
                'comment_id' => $post['friend_activity']['comment_id'],
                'media_id' => $post['friend_activity']['media_id'],
                'reaction_type' => $post['friend_activity']['reaction_type'],
                'other_friend_name' => $post['friend_activity']['other_friend_name'] ?? null,
                'friend_user_id' => $post['friend_activity']['friend_user_id'] ?? null,
                'other_friend_user_id' => $post['friend_activity']['other_friend_user_id'] ?? null
            ];
        }

        $formatted_posts[] = [
            'id' => $post['id'],
            'user_id' => $post['user_id'],
            'author' => htmlspecialchars($post['author_name']),
            'profile_pic' => $profilePic,
            'content' => htmlspecialchars($post['content']),
            'media' => $mediaPath,
            'created_at' => $post['created_at'],
            'visibility' => $post['visibility'] ?? 'public',
            'is_own_post' => ($post['user_id'] == $user_id),
            'is_removed' => (bool)($post['is_removed'] ?? false),
            'removed_reason' => $post['removed_reason'] ?? '',
            'is_flagged' => (bool)($post['is_flagged'] ?? false),
            'flag_reason' => $post['flag_reason'] ?? '',
            'friend_activity' => $friendActivityData,
            'is_system_post' => ($post['author_name'] === 'NubentaUpdates'),
            // Add new fields for shared post data
            'post_type' => $post['post_type'] ?? 'original', // Default to original if not set
            'original_post_id_val' => $post['original_post_id'] ?? null, // Renamed to avoid conflict if $post['original_post_id'] is an array key from a join
            'original_id' => $post['original_id'] ?? null,
            'original_content' => $post['original_content'] ?? null,
            'original_media' => $post['original_media'] ?? null,
            'original_created_at' => $post['original_created_at'] ?? null,
            'original_visibility' => $post['original_visibility'] ?? null,
            'original_author_id' => $post['original_author_id'] ?? null,
            'original_author_name' => $post['original_author_name'] ?? null,
            'original_author_profile_pic' => $post['original_author_profile_pic'] ?? null,
            'original_author_gender' => $post['original_author_gender'] ?? null
        ];
    }

    // DEBUG: Log final output
    $posts_with_activity = array_filter($formatted_posts, function($post) {
        return !empty($post['friend_activity']);
    });
    error_log("DEBUG: User $user_id - Final formatted posts with activity: " . count($posts_with_activity));

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'posts' => $formatted_posts,
        'debug' => [
            'total_posts' => count($formatted_posts),
            'posts_with_activity' => count($posts_with_activity),
            'friend_activities_found' => count($friend_activities),
            'social_activities_found' => count($social_activities),
            'user_id' => $user_id,
            'friend_activities_raw' => $friend_activities,
            'social_activities_raw' => $social_activities,
            'query_executed' => true
        ]
    ]);
    exit;
} catch (PDOException $e) {
    // Log the error and display a user-friendly message
    error_log("Database error in newsfeed.php: " . $e->getMessage());

    if ($json_requested) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred: ' . $e->getMessage(),
            'debug' => [
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'user_id' => $user_id ?? 'unknown'
            ]
        ]);
        exit;
    }

    $error_message = "Sorry, we encountered a database error. Please try again later.";
} catch (Exception $e) {
    // Catch any other errors
    error_log("General error in newsfeed.php: " . $e->getMessage());

    if ($json_requested) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred: ' . $e->getMessage(),
            'debug' => [
                'error_details' => $e->getMessage(),
                'user_id' => $user_id ?? 'unknown'
            ]
        ]);
        exit;
    }

    $error_message = "Sorry, we encountered an error. Please try again later.";
}

// Only continue with HTML output if JSON was not requested
if (!$json_requested) {
    // HTML output starts here
?>
<!DOCTYPE html>
<html>
<head>
  <title>Newsfeed</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/dashboard_style.css">
  <style>
    body {
      background-color: #f0f2f5;
      color: #1c1e21;
      font-family: Arial, sans-serif;
    }

    .container {
      max-width: 800px;
      margin: 30px auto;
      padding: 0 15px;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px solid #dddfe2;
    }

    .page-title {
      font-size: 24px;
      font-weight: bold;
      color: #1877f2;
      margin: 0;
    }

    .newsfeed {
      margin-bottom: 30px;
    }

    .post {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
      padding: 16px;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .post:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .post-header {
      display: flex;
      align-items: center;
      margin-bottom: 12px;
    }

    .profile-pic {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #e4e6eb;
    }

    .author {
      font-weight: 600;
      color: #050505;
    }

    .text-muted {
      color: #65676b !important;
      font-size: 13px;
    }

    .post-content {
      margin-bottom: 15px;
      font-size: 15px;
      line-height: 1.5;
    }

    .media {
      margin: 12px 0;
      border-radius: 8px;
      overflow: hidden;
    }

    .media img, .media video {
      width: 100%;
      border-radius: 8px;
    }

    .post-actions {
      display: flex;
      gap: 8px;
      padding-top: 12px;
      border-top: 1px solid #ced0d4;
    }

    .btn-outline-primary {
      color: #1877f2;
      border-color: #e4e6eb;
    }

    .btn-outline-primary:hover {
      background-color: #e7f3ff;
      color: #1877f2;
      border-color: #e4e6eb;
    }

    .btn-outline-secondary {
      color: #65676b;
      border-color: #e4e6eb;
    }

    .btn-outline-secondary:hover {
      background-color: #f2f2f2;
      color: #050505;
      border-color: #e4e6eb;
    }

    .btn-outline-danger {
      color: #dc3545;
      border-color: #e4e6eb;
    }

    .btn-outline-danger:hover {
      background-color: #fff5f5;
      color: #dc3545;
      border-color: #e4e6eb;
    }

    .btn-secondary {
      background-color: #e4e6eb;
      color: #050505;
      border: none;
    }

    .btn-secondary:hover {
      background-color: #d8dadf;
      color: #050505;
    }

    .alert {
      border-radius: 8px;
      padding: 16px;
    }

    .alert-info {
      background-color: #e7f3ff;
      border-color: #cfe2ff;
      color: #084298;
    }

    .alert-danger {
      background-color: #fff5f5;
      border-color: #f8d7da;
      color: #842029;
    }

    .flagged-warning {
      display: inline-block;
      background-color: rgba(255, 193, 7, 0.2);
      color: #ffc107;
      padding: 5px 10px;
      border-radius: 4px;
      margin-bottom: 10px;
      font-size: 0.9em;
    }

    .blurred-image {
      filter: blur(10px);
      transition: filter 0.3s ease;
    }

    .blurred-image:hover {
      filter: blur(0);
    }

    .text-danger {
      color: #dc3545 !important;
    }

    @media (max-width: 576px) {
      .container {
        padding: 0 10px;
      }

      .post {
        padding: 12px;
      }

      .profile-pic {
        width: 40px;
        height: 40px;
      }

      .post-actions {
        flex-wrap: wrap;
      }

      .btn {
        flex: 1;
        font-size: 12px;
        padding: 6px 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="page-header">
      <h2 class="page-title">Your Newsfeed</h2>
      <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Dashboard
      </a>
    </div>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $error_message ?>
      </div>
    <?php else: ?>
      <div class="newsfeed">
        <?php if (count($posts) > 0): ?>
          <?php foreach ($formatted_posts as $post): ?>
            <article class="post">
              <div class="post-header">
                <img src="<?= $post['profile_pic'] ?>" alt="Profile" class="profile-pic me-3">
                <div>
                  <p class="author mb-0"><?= $post['author'] ?></p>
                  <small class="text-muted">
                    <i class="far fa-clock me-1"></i> <?= date('F j, Y, g:i a', strtotime($post['created_at'])) ?>
                    <?php if ($post['visibility'] === 'friends'): ?>
                      <span class="ms-2"><i class="fas fa-user-friends"></i> Friends only</span>
                    <?php elseif ($post['visibility'] === 'public'): ?>
                      <span class="ms-2"><i class="fas fa-globe-americas"></i> Public</span>
                    <?php endif; ?>
                  </small>
                </div>
              </div>

              <div class="post-content">
                <?php if ($post['is_flagged']): ?>
                  <div class="flagged-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.
                  </div>
                <?php endif; ?>

                <?php if ($post['is_removed']): ?>
                  <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?= nl2br($post['content']) ?>
                  </p>
                <?php else: ?>
                  <p><?= nl2br($post['content']) ?></p>

                  <?php if (!empty($post['media'])): ?>
                    <?php
                      $media_items = json_decode($post['media'], true);
                      if (is_array($media_items) && count($media_items) > 0):
                        $item_count = count($media_items);
                        $display_count = min($item_count, 4); // Show max 4 items in grid preview
                        $data_count_modifier = $item_count > 4 ? '+' : '';
                        $more_count_text = $item_count > 4 ? '+' . ($item_count - 3) : '';
                    ?>
                      <div class="post-multiple-media-container" data-count="<?= $item_count <= 4 ? $item_count : $display_count ?>" data-count-modifier="<?= $data_count_modifier ?>">
                        <?php for ($i = 0; $i < $display_count; $i++):
                                $media_item_path = $media_items[$i];
                                // Ensure path is correct (e.g., prefix with uploads/post_media/ if not already)
                                if (strpos($media_item_path, 'uploads/') !== 0 && strpos($media_item_path, 'http') !== 0) {
                                    $media_item_path = 'uploads/post_media/' . $media_item_path;
                                }
                                $is_last_visible_item = ($i === $display_count - 1);
                        ?>
                          <div class="media-grid-item" <?= ($is_last_visible_item && $data_count_modifier === '+') ? "data-more-count='{$more_count_text}'" : '' ?>>
                            <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $media_item_path)): ?>
                              <img src="<?= htmlspecialchars($media_item_path) ?>" alt="Post media <?= $i+1 ?>" class="<?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                            <?php elseif (preg_match('/\.mp4$/i', $media_item_path)): ?>
                              <video controls class="<?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                                <source src="<?= htmlspecialchars($media_item_path) ?>" type="video/mp4">
                                Your browser does not support the video tag.
                              </video>
                            <?php else: ?>
                                <!-- Fallback for unknown media types in array -->
                                <img src="assets/images/default_media_placeholder.png" alt="Media" class="<?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                            <?php endif; ?>
                          </div>
                        <?php endfor; ?>
                      </div>
                    <?php else: // Single media item (not JSON array or empty array) ?>
                      <div class="media"> <?php // Existing container for single media ?>
                        <?php
                          // Use $post['media'] directly as it's a single path string
                          $single_media_path = $post['media'];
                          // Path already handled by PHP formatting logic before this loop
                        ?>
                        <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $single_media_path)): ?>
                          <img src="<?= htmlspecialchars($single_media_path) ?>" alt="Post media" class="img-fluid <?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                        <?php elseif (preg_match('/\.mp4$/i', $single_media_path)): ?>
                          <video controls class="img-fluid <?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                            <source src="<?= htmlspecialchars($single_media_path) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                          </video>
                        <?php else: ?>
                           <!-- Fallback for unknown single media type if needed, though unlikely if path is direct -->
                           <img src="assets/images/default_media_placeholder.png" alt="Media" class="<?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <div class="post-actions">
                <button class="btn btn-outline-primary">
                  <i class="far fa-thumbs-up me-1"></i> Like
                </button>
                <button class="btn btn-outline-secondary">
                  <i class="far fa-comment me-1"></i> Comment
                </button>
                <button class="btn btn-outline-secondary">
                  <i class="far fa-comment me-1"></i> Comment
                </button>
                <?php if (($post['post_type'] ?? 'original') === 'original' && !($post['is_system_post'] ?? false) ): ?>
                  <button class="btn btn-outline-secondary share-btn" data-post-id="<?= $post['id'] ?>">
                    <i class="far fa-share-square me-1"></i> Share
                  </button>
                <?php endif; ?>
                <?php if ($post['is_own_post']): ?>
                  <button class="btn btn-outline-danger ms-auto">
                    <i class="far fa-trash-alt me-1"></i> Delete
                  </button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> No posts to show yet. Connect with friends or create your own posts!
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/share.js" defer></script>

  <!-- Share Post Modal -->
  <div id="sharePostModal" class="modal" style="display:none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
      <div class="modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; position: relative;">
          <span class="close-share-modal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
          <h3>Share Post</h3>
          <hr>
          <div id="originalPostPreview" style="margin-bottom: 15px; padding: 10px; background-color: #f9f9f9; border: 1px solid #eee; border-radius: 4px;">
              <!-- Original post preview will be loaded here by JavaScript -->
              <p>Loading post preview...</p>
          </div>
          <textarea id="sharerComment" placeholder="Say something about this..." style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; min-height: 80px; resize: vertical;"></textarea>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <select id="shareVisibility" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="friends">Friends</option>
                <option value="public">Public</option>
                <option value="only_me">Only Me</option>
            </select>
            <button id="confirmShareBtn" style="background-color: #1877f2; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">Share Now</button>
          </div>
      </div>
  </div>

</body>
</html>
<?php
}
?>
