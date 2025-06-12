# PRIMARY RULE PRIOR TO IMPLEMENTATION OF MODFICATION OR FIXES IN ORDER TO ADDRESS AND ACCOMPLISH THE TASKS ON HAND [2025-06-12]

1. Make sure that you protect the existing capabilities and functionalities that are working in my current codebase. 
2. Do not distort any functionality that does not relate to my concern.
3. Follow these procedures when making an attempt to fix any of these issues or possible issues in the future: Review - Evaluate - Plan - Anticipate - Review Anticipations vs Plan - Revise - Replan - Execution Documentation - Implementation of Execution
4. Documentation refers to CONSTANTLY UPDATING THE Actual_Accomplishment.md logs i.e. all the things you will execute must be documented here first, regardless of the outcome of said modification/fix, be it success or failure, it must be documented.
5. Documentation ALSO refers to CONTSTANTLY UPDATING THE CHANGELOG.md file. All stipulations mentioned in the Actual_Accomplishment.md must be clarified here in order to have a specific tracking system of what was done to the project and what were the results of these executions: success or failure.
6. IT IS POINTLESS TO PROCEED WITH DIFFERENT COURSES OF ACTIONS IF IT IS NOT DOCUMENTED AND TRACKED, WE WILL END UP WITH REPEATING THE SAME MISTAKES AND ISSUES OVER AND OVER WHICH WILL COST US TIME AND EFFORT.

# Task Number 1: Fixing Activity Feed for User Activities which were executed inside Modals and Primary Location

The Main Goal for the Activity Feed: To make your sidebar Activity Feed (driven by api/add_ons_middle_element.php and api/add_ons_middle_element_html.php) correctly display a range of user activities. This includes:

1. Activities by your friends (e.g., your friend commented on a public post).
2. Activities to your friends (e.g., someone commented on your friend's public post).
3. Critically, activities (comments and reactions) that happen within the media modal (which are stored in media_comments and media_reactions tables).

Current Situation / What I've Tried: Identified that activities from the media modal weren't showing because they are in separate tables (media_comments, media_reactions).

In other words, items 1 and 2 are currently working. When a User comments or reacts on another User post inside the newsfeed (within the dashboard), it is recorded and displayed in the Activity Feed of those who are friends with that User or follows that User. However, when a user should click on a post in the newsfeeed that will trigger the modal to which he can comment and react on that particular media inside that modal, all these activities will not display in the Activity Feed even though this user's comments and reactions are recorded by the system. 

A structural form of how the Activity Feed displays these activities are already working in some areas, but in this particular situation/case, it won't. 

# Task Number 2: Refining Newsfeed for posts with Texts only and posts with Text and Media Types

Inside view_profile.php, the main content (middle grid) fetches activities between the currently logged-in user (User A), and all other users connected to him through friendship (connections i.e. friends.php) and accounts he/she currently follows. This section, also known as the newsfeed, gathers all these activities accordingly. 

Currently, the newsfeed fetches all the posts made by other users according to the latest ones down to the oldest one which is what I wanted by default.

However, it does not update itself if certain posts are activated (through reactions and/or comments) made by other users.

For example, User A is friends with User B and User C. User B made a post at around 10:00 AM and User C made another post at around 11:00 AM. In User A's newsfeed, the first one to be seen would be User C's post simply because it's the latest one, and it will be followed by the post of User B. This is correct. The issue comes when any other user registered in the system will make a comment or reacts to the post of User B AFTER User C made his post at 11:00 AM. Let us say, User D reacted with a "LaughLoud" on User B's post at 11:30 AM, therefore, in User A's newsfeed, it should update to have User B's post to be shown first (because of the latest reaction it received) and followed by User C's post even though User C's post is the latest one. The reaction and comment system should affect the ordinal arrangement of the newsfeed.

Note: Posts with media are clickable and will open a modal, make sure this function is not affected.

# Task Number 3: Refining of Newsfeed display for posts with Texts and Media Types

When User A (or any other user) should make a post that has media attachment, the newsfeed does not cater to these posts presentably. 

For example, User A makes a post with 1 image attachment that is in portrait mode (e.g. the height of the image is longer than the base), the container of the newsfeeds card will crop the image in the middle (i.e. only shows a portion of the middle of the image). This does not fully represent the image at all. So, what if a user will post his portrait picture of her's on the beach, what will the newsfeed display? The stomach and navel of that user? Isn't the preposterous?

The newsfeed should be smart enough to identify if the media type displayed is portrait or landscape. And it shouldn't crop it at all! Maybe resize image so that it wouldn't take a lot of screen display, but it should show the image.

The same problem occurs for posts with multiple images types uploaded, it's all cropped in the middle! It's like it's intentionally making an unpleasant newsfeed!

Note: Posts with media are clickable and will open a modal, make sure this function is not affected.

# Task Number 4: Implementing the Shared button for posts displayed in the newsfeed

Facebook 'Sharing Posts' Feature: SQL, PHP & Analysis
This document provides a comprehensive schematic and analysis of Facebook's "Sharing Posts" feature, detailing its underlying SQL structure, PHP logic for implementation, integration with other Facebook systems, and potential areas for improvement.

## 1. Feature Description: 'Sharing' Posts
Sharing a post on Facebook allows users to re-distribute content created by others to their own network (friends, public, specific lists), often with an optional personal commentary. It's a fundamental mechanism for content amplification and discovery.

* Key aspects of sharing:
- Amplification: Extends the reach of original content beyond the original poster's immediate audience.
- Contextualization: Allows the sharer to add their own thoughts or context to the shared post.
- Attribution: Crucially, shared posts always maintain a clear link back to the original author and post.
- Privacy Inheritance/Override: The shared post's visibility is subject to both the original post's privacy setting and the sharer's chosen privacy setting for their re-share. The most restrictive of these typically applies in terms of who can ultimately view the original content via the share.

## 2. SQL Schema for 'Sharing' Functionality
To implement sharing, we primarily need to extend the Posts table or introduce a new SharedPosts table that links back to original posts. Given that a shared post essentially is a new post (with its own comments, reactions, and privacy) that references an original, extending the Posts table is often the most efficient approach.

* Posts Table (Modified/Extended)
We will modify the existing Posts table to include columns for shared posts.
- Purpose: Now stores both original posts and shared instances of other posts.
- Columns (Tags):
- - post_id (Primary Key, INT/UUID): Unique identifier for each post (original or shared).
- - user_id (Foreign Key to Users.user_id): The ID of the user who created this specific post instance (either the original author or the sharer).
- - content (TEXT, NULLABLE): The main text content of this specific post instance. For shared posts, this is the sharer's commentary. For original posts, it's the original text.
- - original_post_id (Foreign Key to Posts.post_id, NULLABLE): CRITICAL for sharing. If this post is a share, this column holds the post_id of the original post being shared. If it's an original post, this is NULL.
- - created_at (DATETIME): Timestamp when this specific post instance was created.
- - updated_at (DATETIME): Timestamp when this specific post instance was last modified.
- - visibility (ENUM('public', 'friends', 'only_me'), DEFAULT 'friends'): The privacy setting for this specific post instance (the share itself).
- - post_type (ENUM('original', 'shared'), DEFAULT 'original'): A new column to easily distinguish between original posts and shared posts.

* Existing Tables (and their implicit roles in sharing):
- Users: Provides user information for both original authors and sharers.
- Friendships: Used to determine privacy for 'Friends' visibility on shared posts.
- PostMedia: Media attached to the original post will be implicitly linked via original_post_id lookup. The shared post itself usually doesn't have new media unless the sharer explicitly uploads it (which Facebook typically handles as a new, separate original post rather than a share).
- Comments & CommentReplies: Comments/replies made on a shared post instance are separate from comments on the original post.
- Reactions: Reactions on a shared post instance are separate from reactions on the original post.

## 3. User Interface (UI) and User Experience (UX) Flow for Sharing
* Clicking "Share" Button:
- UI: On any eligible post in the News Feed or on a profile/page, a "Share" button is prominently displayed (often next to "Like" and "Comment").
- UX: When clicked, a modal or pop-up appears.

* Share Intent (UI/UX):
- UI (Share Dialog):
- - Original Post Preview: A preview of the original post (text, media, author, timestamp) is shown. This makes it clear what is being shared.
- - Sharer's Commentary Input: A text area where the user can type their own comment or message to accompany the share.
- - Audience Selector: A dropdown or button to choose the privacy/visibility for this shared post (e.g., Public, Friends, Only Me, Custom Lists). This defaults to the user's last chosen privacy or their default post privacy.
- - "Share Now" / "Post" Button: The primary action button.
- - Other Options:
- - - "Share to Feed" (default)
- - - "Share to your Story"
- - - "Send in Messenger"
- - - "Share to a Group"
- - - "Share to a Page" (if the user manages pages)
- UX: The modal provides a clear, controlled environment for the user to decide how and to whom they want to share the content, encouraging them to add their personal touch.

* How the Post is Being Shared (Backend Process):
- PHP: When the "Share" button in the modal is confirmed, an API call is made to the server (e.g., share.php or a dedicated API endpoint).
- ShareManager::sharePost(): This function is invoked, receiving the sharerId, originalPostId, sharerComment, and visibility.
- Database Transaction: A new entry is created in the Posts table.
- - post_id: A new unique ID for this shared instance.
- - user_id: The ID of the sharer.
- - content: The sharerComment (can be NULL).
- - original_post_id: Crucially, the post_id of the original content is stored here.
- - post_type: Set to 'shared'.
- - visibility: Set to the visibility chosen by the sharer.
- No Duplication of Original Content: The original_post_id foreign key means the actual content and media of the original post are not duplicated. They are referenced.

* News Feed Display for Shared Post:
- PHP (PostManager::getNewsfeedPosts): When fetching posts for a user's News Feed, this function retrieves shared posts (where post_type = 'shared').
- ShareManager::getSinglePostById (or similar logic): For each shared post, the News Feed retrieval logic uses the original_post_id to fetch the details of the original post (its content, author, media, etc.).
- Privacy Check: A critical step is to check if the viewer of the shared post has permission to see the original post. If the original post's privacy (original_post_data.visibility) is more restrictive than the viewer's relationship to the original author, the original content might be hidden or replaced with a "Content Not Available" message.
- UI: The shared post is displayed as a new entry in the News Feed. It typically shows:
- - The sharer's profile picture and name.
- - The sharer's commentary.
- - Below the commentary, a visually distinct block showing the original post:
- - - Original author's profile picture and name.
- - - Original post's content and media.
- - - Original post's timestamp.

* Commenting, Reaction, and Resharing Buttons on a Shared Post:
- Behavior:
- - Commenting: Comments and replies made on a shared post belong specifically to that shared instance. They are stored in Comments and CommentReplies linked to the post_id of the shared post. They do not appear on the original post.
- - Reactions: Reactions (likes, loves, etc.) made on a shared post belong to that shared instance. They are stored in Reactions linked to the post_id of the shared post. They do not add to the original post's reaction count.
- - -Resharing: A "Share" button exists on the shared post itself. Clicking this allows a user to "re-share a share." This creates a new shared post, where its original_post_id would point to the first shared post's post_id, forming a chain. Facebook typically limits how many times this can be re-shared in a chain for clarity.
- UI: Buttons for "Like," "Comment," and "Share" appear at the bottom of the shared post block, operating on that specific instance. There's often also a prominent link or clickable area that leads directly to the original post's permalink.
* Clicking on a Shared Post:
- UI/UX:
- - Clicking on the sharer's commentary or profile picture, or the "Like/Comment/Share" buttons, typically interacts with the shared post instance.
- - -Clicking on the original content block (or a dedicated "See Original Post" link) navigates the user directly to the original post's permalink, where they can see all comments and reactions on the original post, regardless of how they discovered it.
* Notification System to Original Author:
- Mechanism: When a user shares a post, the original author receives a notification (e.g., "Alice shared your post," or "Alice shared your photo").
- Integration: A notification system would monitor INSERT operations on the Posts table where post_type = 'shared'. When such an insert occurs, it identifies the original_post_id and then looks up the user_id of that original_post_id in the Posts table (or Users table through a join). A notification record is then generated for the original author.

## 4. Integration with Other Facebook Systems
* News Feed Algorithm: Shared posts contribute to engagement signals and are ranked. The algorithm considers the relevance of the sharer, the original author, and the content itself. Viral content often leverages the sharing mechanism.
* Analytics/Insights: Original authors can see insights on how many times their content has been shared and potentially the reach of those shares.
* Search: Both original and shared posts are indexed for search.
* Saved Posts: Users can save shared posts (which effectively saves that specific shared instance).
* Reporting: Both the shared post (sharer's comment) and the original content within it can be reported separately.

## 5. Suggestions for Improvement
* Explicit Original Post Privacy Cues:
- Current: If an original post is private and gets shared publicly, the original content might just disappear for viewers without direct context.
- Improvement: Clearly indicate the original post's privacy setting or status when it's unavailable. E.g., "Original post was set to 'Friends Only' by Bob" or "Original content has been deleted by the author."

* "Chain" Management/Display:
- Current: Deeply nested re-shares can become visually cluttered or confusing.
- Improvement: Implement smarter collapsing or a more intuitive visual representation for long chains of shares, perhaps showing only the immediate previous share and the original.

* Sharer-Specific Analytics:
- Current: Original authors get share counts. Sharers themselves don't easily see detailed reach/engagement of their own shares.
- Improvement: Provide sharers with basic analytics on their shared posts (e.g., how many people saw their share, engaged with their commentary).

* Pre-emptive Privacy Warning:
- Current: User selects privacy for their share, but might not realize it's restricted by original post's privacy.
- -Improvement: In the share dialog, if the original post's privacy is more restrictive than the sharer's chosen audience, provide a warning like "Note: Your audience is wider than the original post's. Some viewers may not see the original content."

* Audience Targeting for Shares:
- Current: Shares go to personal News Feed audiences.
- Improvement: Allow sharing directly to specific "groups of friends" or custom lists within the share dialog itself, even if not a formal group.

## 6. PHP Logic for 'Sharing' & Newsfeed Display
The PHP logic needs to handle the creation of a shared post, displaying its content (including the original post's details), and managing subsequent interactions.

<?php

// Assume a PDO database connection ($pdo) is established and available.
// Assume get_current_user_id() returns the ID of the logged-in user.
// Assume generate_uuid_v4() helper function.
// Assume User, Friendship, and PostManager classes from previous schematics exist.
// The PostManager will be modified to handle fetching original posts for shared ones.

class ShareManager {
    private $pdo;
    private $postManager; // Dependency injection for PostManager

    public function __construct(PDO $pdo, PostManager $postManager) {
        $this->pdo = $pdo;
        $this->postManager = $postManager;
    }

    /**
     * Creates a new 'shared' post.
     * @param int $sharerId The ID of the user sharing the post.
     * @param string $originalPostId The ID of the original post being shared.
     * @param string|null $sharerComment Optional commentary from the sharer.
     * @param string $visibility 'public', 'friends', or 'only_me' for the shared post.
     * @return string|false The post_id of the new shared post if successful, false otherwise.
     */
    public function sharePost(int $sharerId, string $originalPostId, ?string $sharerComment = null, string $visibility = 'friends') {
        // First, check if the original post exists and is visible to the sharer.
        // This is a simplified check; a real system would have more robust visibility rules.
        $originalPost = $this->postManager->getSinglePostById($originalPostId, $sharerId);
        if (!$originalPost) {
            error_log("Attempt to share non-existent or inaccessible post: " . $originalPostId);
            return false;
        }

        $sharedPostId = generate_uuid_v4();

        $stmt = $this->pdo->prepare("
            INSERT INTO Posts (post_id, user_id, content, original_post_id, post_type, visibility, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'shared', ?, NOW(), NOW())
        ");
        try {
            $stmt->execute([$sharedPostId, $sharerId, $sharerComment, $originalPostId, $visibility]);
            return $sharedPostId;
        } catch (PDOException $e) {
            error_log("Error sharing post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a single post by its ID, enriching it with original post data if it's a share.
     * This method would ideally be part of or used by a PostManager.
     * @param string $postId The ID of the post (can be original or shared).
     * @param int $viewerId The ID of the user viewing the post. Crucial for privacy.
     * @return array|false The post data including original post details for shares, false if not found/inaccessible.
     */
    public function getSinglePostById(string $postId, int $viewerId) {
        $sql = "
            SELECT p.*, u.username AS author_username, u.profile_picture_url AS author_profile_pic
            FROM Posts p
            JOIN Users u ON p.user_id = u.user_id
            WHERE p.post_id = ?;
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            return false; // Post not found
        }

        // Add media (from PostManager's logic, adapted)
        $mediaSql = "
            SELECT media_id, post_id, media_url, media_type, thumbnail_url, order_index, file_size_bytes
            FROM PostMedia
            WHERE post_id = ?
            ORDER BY order_index ASC;
        ";
        $mediaStmt = $this->pdo->prepare($mediaSql);
        $mediaStmt->execute([$postId]);
        $post['media'] = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);


        // If it's a shared post, fetch the original post's details
        if ($post['post_type'] === 'shared' && !empty($post['original_post_id'])) {
            // Recursively call PostManager's getSinglePostById to get the original post's data
            // This is simplified. In a real system, you'd ensure circular references are handled
            // and apply original post's privacy against the *viewer* of the shared post.
            $originalPost = $this->postManager->getSinglePostById($post['original_post_id'], $viewerId); // Pass viewerId for privacy check

            // CRITICAL PRIVACY CHECK: If the original post is not visible to the viewer of the shared post,
            // then the shared post should *not* display the original content.
            // This requires a more complex visibility check function in PostManager.
            // For now, assume getSinglePostById handles this and returns false if not visible.
            if ($originalPost) {
                $post['original_post_data'] = $originalPost;
            } else {
                // Original post is not visible to the viewer of the shared post.
                // Display a "Content not available" message, or hide the original part.
                $post['original_post_data'] = [
                    'content' => 'The original post is no longer available or you do not have permission to view it.',
                    'author_username' => 'Unknown',
                    'privacy_blocked' => true // Custom flag for rendering logic
                ];
            }
        }

        // Apply shared post's own visibility rules here (similar to Newsfeed logic)
        // This is a simplified check assuming the user is the author or public.
        // A full visibility check would involve Friendships table for 'friends' visibility.
        if ($post['visibility'] === 'only_me' && $post['user_id'] !== $viewerId) {
            return false; // Not visible to this viewer
        }
        // Additional checks for 'friends' visibility etc. based on $viewerId and relationships.

        return $post;
    }
}

// --- Modified PostManager (Newsfeed Retrieval for Shared Posts) ---
// The getNewsfeedPosts method from the previous schematics would call getSinglePostById
// for each post to fetch its details, including the linked original post if it's a share.

/*
// Example of how Newsfeed would iterate and display:
class NewsfeedRenderer {
    private $postManager; // Injected dependency

    public function __construct(PostManager $postManager) {
        $this->postManager = $postManager;
    }

    public function renderNewsfeed(int $viewerId) {
        $posts = $this->postManager->getNewsfeedPosts($viewerId); // This now fetches media for original posts

        foreach ($posts as $post) {
            echo "--- POST ---\n";
            echo "Author: " . $post['author_username'] . "\n";
            echo "Time: " . $post['created_at'] . "\n";
            echo "My comment: " . $post['content'] . "\n"; // Sharer's comment if it's a shared post

            if ($post['post_type'] === 'shared' && isset($post['original_post_data'])) {
                $originalPost = $post['original_post_data'];
                if (isset($originalPost['privacy_blocked']) && $originalPost['privacy_blocked']) {
                    echo "Original Content: " . $originalPost['content'] . "\n";
                } else {
                    echo "--- ORIGINAL POST (Shared) ---\n";
                    echo "  Original Author: " . $originalPost['author_username'] . "\n";
                    echo "  Original Content: " . $originalPost['content'] . "\n";
                    if (!empty($originalPost['media'])) {
                        echo "  Original Media:\n";
                        foreach ($originalPost['media'] as $mediaItem) {
                            echo "    - Type: " . $mediaItem['media_type'] . ", URL: " . $mediaItem['media_url'] . "\n";
                        }
                    }
                    echo "--------------------------\n";
                }
            } else {
                 if (!empty($post['media'])) {
                    echo "  Media:\n";
                    foreach ($post['media'] as $mediaItem) {
                        echo "    - Type: " . $mediaItem['media_type'] . ", URL: " . $mediaItem['media_url'] . "\n";
                    }
                }
            }

            // Display Comment, Reaction, Reshare buttons (these refer to the SHARED post instance)
            echo "[Like] [Comment] [Share] [Original Post Link]\n"; // Original Post Link leads to original post's permalink
            echo "-------------------------------\n\n";
        }
    }
}
*/

// --- Example Usage (Conceptual for Demonstration) ---
/*
// Assuming Database, User, PostManager, FollowManager (from previous schematics) exist and are initialized.
// For this example, we'll need to define a simple PostManager for getSinglePostById.

class Database {
    private $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:'); // Use in-memory SQLite for simple demo
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, profile_picture_url TEXT)");
        $this->pdo->exec("INSERT INTO Users (user_id, username) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Charlie')");
        $this->pdo->exec("CREATE TABLE Posts (post_id TEXT PRIMARY KEY, user_id INTEGER, content TEXT, original_post_id TEXT, post_type TEXT, visibility TEXT, created_at DATETIME, updated_at DATETIME)");
        $this->pdo->exec("CREATE TABLE PostMedia (media_id TEXT PRIMARY KEY, post_id TEXT, media_url TEXT, media_type TEXT, thumbnail_url TEXT, order_index INTEGER, file_size_bytes INTEGER, created_at DATETIME)");
        $this->pdo->exec("CREATE TABLE Friendships (user_id_1 INTEGER, user_id_2 INTEGER, status TEXT, created_at DATETIME, PRIMARY KEY (user_id_1, user_id_2))");
        $this->pdo->exec("INSERT INTO Friendships (user_id_1, user_id_2, status, created_at) VALUES (1, 2, 'accepted', CURRENT_TIMESTAMP)"); // Alice and Bob are friends
    }
    public function getConnection() { return $this->pdo; }
}

function get_current_user_id() { return 1; } // Simulate Alice logged in

// Re-implement a simplified PostManager just for this example's execution
class SimplePostManager {
    private $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function createPost(int $userId, string $content, array $mediaItems = [], string $visibility = 'public', ?string $originalPostId = null, string $postType = 'original') {
        $postId = generate_uuid_v4();
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Posts (post_id, user_id, content, original_post_id, post_type, visibility, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $stmt->execute([$postId, $userId, $content, $originalPostId, $postType, $visibility]);

            $mediaOrder = 0;
            if (!empty($mediaItems)) {
                $mediaStmt = $this->pdo->prepare("INSERT INTO PostMedia (media_id, post_id, media_url, media_type, thumbnail_url, order_index, file_size_bytes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                foreach ($mediaItems as $media) {
                    $mediaId = generate_uuid_v4();
                    $mediaStmt->execute([$mediaId, $postId, $media['url'], $media['type'], $media['thumbnail_url'] ?? null, $mediaOrder++, $media['file_size_bytes'] ?? null]);
                }
            }
            $this->pdo->commit();
            return $postId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error creating post: " . $e->getMessage());
            return false;
        }
    }

    // Simplified for demonstration: assumes viewer can see. Real logic needs privacy checks.
    public function getSinglePostById(string $postId, int $viewerId) {
        $sql = "SELECT p.*, u.username AS author_username, u.profile_picture_url AS author_profile_pic FROM Posts p JOIN Users u ON p.user_id = u.user_id WHERE p.post_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) return false;

        $mediaSql = "SELECT media_id, post_id, media_url, media_type, thumbnail_url, order_index, file_size_bytes FROM PostMedia WHERE post_id = ? ORDER BY order_index ASC";
        $mediaStmt = $this->pdo->prepare($mediaSql);
        $mediaStmt->execute([$postId]);
        $post['media'] = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($post['post_type'] === 'shared' && !empty($post['original_post_id'])) {
            $originalPost = $this->getSinglePostById($post['original_post_id'], $viewerId); // Recursive call
            $post['original_post_data'] = $originalPost ?: ['content' => 'Original content unavailable.', 'privacy_blocked' => true];
        }
        return $post;
    }

    public function getNewsfeedPosts(int $viewerId, int $limit = 10, int $offset = 0) {
        $sql = "SELECT p.post_id FROM Posts p ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $posts = [];
        foreach($postIds as $postId) {
            $post = $this->getSinglePostById($postId, $viewerId);
            if ($post) $posts[] = $post;
        }
        return $posts;
    }
}

// Global functions for this demo
function generate_uuid_v4() {
    $data = random_bytes(16); $data[6] = chr(ord($data[6]) & 0x0f | 0x40); $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$database = new Database();
$pdo = $database->getConnection();
$postManager = new SimplePostManager($pdo); // Use SimplePostManager for this example
$shareManager = new ShareManager($pdo, $postManager);
$newsfeedRenderer = new NewsfeedRenderer($postManager); // Assuming NewsfeedRenderer exists

// Create an original post by Bob (User 2)
$bobPostId = $postManager->createPost(2, "Bob's original public post!", [['url' => 'https://example.com/bob_img.jpg', 'type' => 'image']], 'public');
echo "Bob created original post: " . $bobPostId . "\n";

// Alice (User 1) shares Bob's post with a comment
$aliceSharedPostId = $shareManager->sharePost(1, $bobPostId, "Look at this awesome post from Bob! #sharingiscaring", 'friends');
echo "Alice shared Bob's post: " . $aliceSharedPostId . "\n";

// Charlie (User 3) tries to share Bob's post
$charlieSharedPostId = $shareManager->sharePost(3, $bobPostId, "Charlie agrees with Bob!", 'public');
echo "Charlie shared Bob's post: " . $charlieSharedPostId . "\n";


echo "\n--- Alice's Newsfeed ---\n";
$newsfeedRenderer->renderNewsfeed(get_current_user_id()); // Alice's view

echo "\n--- Bob's Newsfeed ---\n";
// Create a new PostManager instance for Bob's view
$bobPostManager = new SimplePostManager($pdo);
$bobNewsfeedRenderer = new NewsfeedRenderer($bobPostManager);
$bobNewsfeedRenderer->renderNewsfeed(2); // Bob's view

echo "\n--- Charlie's Newsfeed ---\n";
// Create a new PostManager instance for Charlie's view
$charliePostManager = new SimplePostManager($pdo);
$charlieNewsfeedRenderer = new NewsfeedRenderer($charliePostManager);
$charlieNewsfeedRenderer->renderNewsfeed(3); // Charlie's view
*/
