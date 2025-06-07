Goal: To adopt and implement the same "Follow Account" Feature in Facebook with our Project Nubenta.

General Statemet: This document provides a comprehensive schematic and analysis of Facebook's "Follow Account" feature, detailing its underlying SQL structure, PHP logic for implementation, integration with other Facebook systems, and potential areas for improvement.

1. Feature Description: 'Follow' vs. 'Friend'
On Facebook, "following" an account (a personal profile or a public page) allows you to see their public updates in your News Feed without requiring a mutual friendship. This is distinct from "friending," which is a mutual connection primarily used for personal profiles, granting access to 'Friends'-level content and typically implying a closer relationship.

* For Personal Profiles:
- If you friend someone, you automatically follow them. You see their public and 'Friends'-level posts.
- If you don't friend someone but they have "Followers" enabled in their privacy settings, you can follow them. You will then only see their Public posts in your News Feed.
The "Follow" feature is crucial for content creators, public figures, and businesses to disseminate content to a broader audience without requiring individual friendship requests.

2. SQL Schema for 'Follow' Functionality

To implement the 'Follow Account' feature, we need a dedicated table to record these relationships. We will assume the existence of a Users table (for personal profiles) and potentially a Pages table (for public pages). For simplicity, we'll focus on users following other users or pages, requiring a flexible foreign key or separate tables if strict distinction is needed.

# Option A: Single Follows Table (More Generic)
This approach is more flexible if you consider both users and pages as 'followable entities' that can be referenced by a common ID.

## Follows Table
* Purpose: To record who is following whom (user following user, user following page).
* Columns (Tags):
- follow_id (Primary Key, INT/UUID): Unique identifier for each follow relationship.
- follower_id (Foreign Key to Users.user_id): The ID of the user who is doing the following.
- followed_entity_id (VARCHAR): The ID of the user or page being followed. This might be a user_id or a page_id.
- followed_entity_type (ENUM('user', 'page')): Specifies whether the followed_entity_id refers to a user or a page. This allows a single table to handle both.
- created_at (DATETIME): Timestamp when the follow relationship was established.
- *Constraints*: A unique composite key (follower_id, followed_entity_id, followed_entity_type) to prevent duplicate follow relationships.

# Option B: Separate UserFollows and PageFollows Tables (More Strict)

If Users and Pages have completely different ID structures or more complex relationships, separate tables might be cleaner.

## UserFollows Table
- user_follow_id (PK, INT/UUID)
- follower_user_id (FK to Users.user_id)
- followed_user_id (FK to Users.user_id)
- created_at (DATETIME)
- Unique composite key (follower_user_id, followed_user_id)

## PageFollows Table
- page_follow_id (PK, INT/UUID)
- follower_user_id (FK to Users.user_id)
- followed_page_id (FK to Pages.page_id) - Requires a Pages table
- created_at (DATETIME)
- Unique composite key (follower_user_id, followed_page_id)

For the subsequent PHP logic, we'll generally assume Option A (Single Follows Table) for simplicity and commonality.

3. PHP Logic for 'Follow' & Newsfeed Display
The PHP logic involves managing the follow relationship and, crucially, integrating it into the Newsfeed retrieval process. (the php code below is AI generated as how it understood the schematics in facebook, use this as guide only, do not treat it as an obligatory file to copy-paste; adapt only as described in the schematics here in the TaskLog)

<?php

// Assume a PDO database connection ($pdo) is established and available.
// Assume get_current_user_id() returns the ID of the logged-in user.
// Assume generate_uuid_v4() helper function.
// Assume PostManager class from previous schematics (now fetching media).

class FollowManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Toggles a follow relationship.
     * @param int $followerId The ID of the user performing the follow/unfollow.
     * @param string $followedEntityId The ID of the user/page to follow.
     * @param string $followedEntityType 'user' or 'page'.
     * @return bool True if follow/unfollow was successful.
     */
    public function toggleFollow(int $followerId, string $followedEntityId, string $followedEntityType): bool {
        // Prevent following oneself
        if ($followedEntityType === 'user' && (int)$followedEntityId === $followerId) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT follow_id FROM Follows
            WHERE follower_id = ? AND followed_entity_id = ? AND followed_entity_type = ?
        ");
        $stmt->execute([$followerId, $followedEntityId, $followedEntityType]);
        $existingFollow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingFollow) {
            // Unfollow
            $deleteStmt = $this->pdo->prepare("DELETE FROM Follows WHERE follow_id = ?");
            return $deleteStmt->execute([$existingFollow['follow_id']]);
        } else {
            // Follow
            $followId = generate_uuid_v4();
            $insertStmt = $this->pdo->prepare("
                INSERT INTO Follows (follow_id, follower_id, followed_entity_id, followed_entity_type, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            return $insertStmt->execute([$followId, $followerId, $followedEntityId, $followedEntityType]);
        }
    }

    /**
     * Checks if a user is following a specific entity.
     * @param int $followerId The ID of the user.
     * @param string $followedEntityId The ID of the entity to check.
     * @param string $followedEntityType 'user' or 'page'.
     * @return bool True if following, false otherwise.
     */
    public function isFollowing(int $followerId, string $followedEntityId, string $followedEntityType): bool {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM Follows
            WHERE follower_id = ? AND followed_entity_id = ? AND followed_entity_type = ?
        ");
        $stmt->execute([$followerId, $followedEntityId, $followedEntityType]);
        return (bool)$stmt->fetch();
    }
}

// --- Modified PostManager (Newsfeed Retrieval) ---
// This class would be updated to fetch posts from followed accounts.

class PostManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetches posts for a given viewing user, respecting visibility settings, including
     * posts from friends and PUBLIC posts from followed users/pages.
     * @param int $viewerId The ID of the user viewing the newsfeed.
     * @param int $limit Max number of posts to fetch.
     * @param int $offset Offset for pagination.
     * @return array An array of post data, with 'media' array for each post.
     */
    public function getNewsfeedPosts(int $viewerId, int $limit = 10, int $offset = 0) {
        // Subquery to get IDs of friends
        $friendsSubquery = "
            SELECT user_id_1 FROM Friendships WHERE user_id_2 = ? AND status = 'accepted'
            UNION
            SELECT user_id_2 FROM Friendships WHERE user_id_1 = ? AND status = 'accepted'
        ";

        // Subquery to get IDs of followed users/pages (only relevant if they are users)
        $followedUsersSubquery = "
            SELECT followed_entity_id FROM Follows
            WHERE follower_id = ? AND followed_entity_type = 'user'
        ";

        $sql = "
            SELECT p.*, u.username AS author_username, u.profile_picture_url AS author_profile_pic
            FROM Posts p
            JOIN Users u ON p.user_id = u.user_id
            WHERE
                -- Rule 1: Always show my own posts
                p.user_id = ?
                OR
                -- Rule 2: Show public posts from anyone (friends, followed, or just public)
                p.visibility = 'public'
                OR
                -- Rule 3: Show friends-only posts from my accepted friends
                (p.visibility = 'friends' AND p.user_id IN ($friendsSubquery))
                OR
                -- Rule 4: Show public posts from users I follow (already covered by p.visibility = 'public',
                -- but explicitly showing the follow condition if more granular control was needed)
                (p.visibility = 'public' AND p.user_id IN ($followedUsersSubquery))
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?;
        ";
        $stmt = $this->pdo->prepare($sql);
        try {
            // Bind parameters for the main query and subqueries
            // Note: Parameters for subqueries are duplicated if they are used multiple times.
            $stmt->execute([
                $viewerId, // for p.user_id = ?
                $viewerId, $viewerId, // for friendsSubquery
                $viewerId, // for followedUsersSubquery
                $limit, $offset
            ]);
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // (Media fetching logic remains the same as in the previous media schematics)
            // If no posts, return early
            if (empty($posts)) {
                return [];
            }

            // Get all post IDs to fetch media in a single query
            $postIds = array_column($posts, 'post_id');
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));

            $mediaSql = "
                SELECT media_id, post_id, media_url, media_type, thumbnail_url, order_index, file_size_bytes
                FROM PostMedia
                WHERE post_id IN ($placeholders)
                ORDER BY post_id, order_index ASC;
            ";
            $mediaStmt = $this->pdo->prepare($mediaSql);
            $mediaStmt->execute($postIds);
            $allMedia = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

            // Organize media by post_id
            $mediaByPost = [];
            foreach ($allMedia as $mediaItem) {
                $mediaByPost[$mediaItem['post_id']][] = $mediaItem;
            }

            // Attach media to their respective posts
            foreach ($posts as &$post) {
                $post['media'] = $mediaByPost[$post['post_id']] ?? [];
            }

            return $posts;

        } catch (PDOException $e) {
            error_log("Error fetching newsfeed posts: " . $e->getMessage());
            return [];
        }
    }
}

// --- Example Usage (Conceptual) ---
/*
// Initialize
$database = new Database();
$pdo = $database->getConnection();
$followManager = new FollowManager($pdo);
$postManager = new PostManager($pdo); // Assuming updated PostManager

$currentUserId = get_current_user_id(); // User ID 1

// Simulate a user (User ID 4) to follow
$stmt = $pdo->prepare("INSERT IGNORE INTO Users (user_id, username, email, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([4, 'PublicFigure', 'public.figure@example.com']);

// PublicFigure (User 4) makes a public post
$publicFigurePostId = $postManager->createPost(4, "Check out my new public announcement!", [], 'public');

// --- Scenario: User 1 follows User 4 ---
echo "--- User " . $currentUserId . " tries to follow User 4 ---\n";
if ($followManager->toggleFollow($currentUserId, '4', 'user')) {
    echo "User " . $currentUserId . " is now following User 4.\n";
} else {
    echo "Failed to follow User 4.\n";
}

// --- Scenario: User 1 views Newsfeed (should see User 4's public post) ---
echo "\n--- Newsfeed for User " . $currentUserId . " (after following User 4) ---\n";
$newsfeedPosts = $postManager->getNewsfeedPosts($currentUserId);
foreach ($newsfeedPosts as $post) {
    echo "Post from " . $post['author_username'] . ": " . $post['content'] . " (Visibility: " . $post['visibility'] . ")\n";
}

// --- Scenario: User 1 unfollows User 4 ---
echo "\n--- User " . $currentUserId . " tries to unfollow User 4 ---\n";
if ($followManager->toggleFollow($currentUserId, '4', 'user')) {
    echo "User " . $currentUserId . " has unfollowed User 4.\n";
} else {
    echo "Failed to unfollow User 4.\n";
}

// --- Newsfeed for User 1 again (should NOT see User 4's post unless it was public anyway) ---
// Note: If User 4's post was 'public', it will still be visible due to p.visibility = 'public' condition.
// The 'follow' logic is primarily for distinguishing between 'friends' vs. 'public' content from profiles.
// For purely 'follow' based visibility, posts would need a 'followers' visibility option.
echo "\n--- Newsfeed for User " . $currentUserId . " (after unfollowing User 4) ---\n";
$newsfeedPosts = $postManager->getNewsfeedPosts($currentUserId);
foreach ($newsfeedPosts as $post) {
    echo "Post from " . $post['author_username'] . ": " . $post['content'] . " (Visibility: " . $post['visibility'] . ")\n";
}
*/
?>

4. Integration with Other Facebook Systems
The 'Follow Account' feature doesn't exist in a vacuum; it deeply integrates with other core Facebook functionalities:

# News Feed Ranking (Algorithm):
- * Mechanism : Posts from followed accounts (both profiles and pages) are fed into the News Feed algorithm alongside posts from friends. The algorithm then ranks content based on factors like engagement (likes, comments, shares), recency, content type, and the user's past interactions with the source.
- * Integration: The SQL query in getNewsfeedPosts merely selects eligible posts. The actual ranking would happen after this raw selection, with a more sophisticated algorithm adjusting the order. Posts from entities a user explicitly follows are generally prioritized over random public posts from unknown sources.
# Privacy Settings (Users.default_post_visibility, Posts.visibility):
- * Mechanism: A user's profile settings determine if they can be followed and what content followers can see. If a personal profile is set to "Friends Only" for posts, followers will see very little to nothing. If it's "Public," followers see all public posts.
- * Integration: The visibility column in the Posts table and the followed_entity_type in the Follows table are critical. The News Feed query intelligently filters based on these.

# Notifications:
- * Mechanism: When a followed account posts new content, followers often receive notifications (e.g., "Public Figure posted a new photo").
- * Integration: A notification system would listen for new Posts where the user_id or page_id matches an entry in the Follows.followed_entity_id for active followers.

# Profile/Page UI:
- * Mechanism: The "Follow" button (or "Like" button for pages) is prominently displayed on profiles and pages. For personal profiles, if you're already friends, the "Follow" button might not appear, or it might change to "Following" implicitly.
- * Integration: The UI would call FollowManager::isFollowing() to determine the state of the button.

5. Suggestions for Improvement
# Granular Follower Settings:
- * Current: Mainly 'Public' vs. 'Friends' content.
- * Improvement: Allow users to define specific privacy levels for followers, e.g., "Followers can see posts from my personal life," "Followers can see my professional updates," or even custom lists. This would add more control beyond just "Public."

# Enhanced Notification Controls:
- * Current: Basic "get all notifications" or "get none."
- * Improvement: Allow followers to fine-tune notifications (e.g., "notify me only for live videos," "notify me only for posts with photos," "daily digest of new posts"). This reduces notification fatigue.

# Follower Categories/Lists (for popularly followed accounts):
- * Current: Followers are a single undifferentiated group.
- * Improvement: Enable content creators (especially public figures) to categorize or list their followers (e.g., "Super Fans," "Casual Followers") to understand their audience better or even target content to specific follower segments.

# Follower-Only Engagement:
- * Current: Comments are often open to public/friends based on post visibility.
- * Improvement: Allow creators to enable "Follower-only comments" on their public posts, fostering a more controlled community discussion.
These suggestions aim to provide more flexibility and control for both the follower and the followed entity, enhancing the overall user experience and potentially enabling new content strategies.

Again, Let me remind you that:

1. Make sure that you protect the existing capabilities and functionalities that are working in my current codebase. 
2. Do not distort any functionality that does not relate to my concern.
3. Lastly, please follow these procedures when making an attempt to fix any of these issues or possible issues in the future:

Review - Evaluate - Plan - Anticipate - Review Anticipations vs Plan - Revise - Replan - Execute - Document

### DOCUMENT?

The current project has 2 important .md files which are CHANGELOG.md and TaskLog.md. As mentioned recently, when you will be executing modifications, alterations, additions, or deletions on the existing codes and files, always document it in our CHANGELOG.md. Once you've successfully made your implemented your task or subtasks, make sure to document it on a new .md file named "Actual_Accomplishment". What is this?

For example, you've made your creation of files, and then modified some files etc, you will modify the CHANGELOG.md immediately. I will test the implementations you've provided and see if it was successful or it was able to address a few things but not all of it; all of my feedbacks should be recorded in the Actual_Accomplishment.md file as a validation of your CHANGELOG.md if it was TRULY successful or not. Since you will now make a new plan or revise your approach, these parts will be recorded in the CHANGELOG.md again; and of course, I'll test and verify your new modifications and provide feedback etc; and you'd again record it inside the Actual_Accomplishment.md file. This will be the cycle of our activities until we will be able to fully implement this recording in our TaskLog.md