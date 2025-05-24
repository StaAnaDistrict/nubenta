<?php

// --- Database Connection (Simplified for demonstration) ---
// In a real application, you would use PDO or an ORM (e.g., Eloquent in Laravel)
// and manage credentials securely.

class Database {
    private $host = 'localhost';
    private $db = 'newsfeed_db';
    private $user = 'root';
    private $pass = '';
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// --- Authentication (Conceptual) ---
// In a real app, this would involve sessions, tokens, etc.
// For this example, we'll assume a 'current_user_id' is available.
function get_current_user_id() {
    // Simulate a logged-in user. In a real app, this would come from a session.
    return 1; // Assuming User ID 1 is logged in for testing
}

// --- Helper Functions ---
function generate_uuid_v4() {
    // Generate a random UUID (RFC 4122 v4)
    // This is a simplified version; for production, consider a more robust library.
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// --- Core Logic Classes ---

class PostManager {
    private $db_conn;

    public function __construct(PDO $db_conn) {
        $this->db_conn = $db_conn;
    }

    /**
     * Creates a new post.
     * @param int $userId The ID of the user creating the post.
     * @param string $content The text content of the post.
     * @param string|null $mediaUrl Optional URL for media.
     * @param string $visibility 'public', 'friends', or 'only_me'.
     * @return string|false The post_id if successful, false otherwise.
     */
    public function createPost(int $userId, string $content, ?string $mediaUrl = null, string $visibility = 'friends') {
        $postId = generate_uuid_v4();
        $stmt = $this->db_conn->prepare("INSERT INTO Posts (post_id, user_id, content, media_url, visibility, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        try {
            $stmt->execute([$postId, $userId, $content, $mediaUrl, $visibility]);
            return $postId;
        } catch (PDOException $e) {
            error_log("Error creating post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches posts for a given viewing user, respecting visibility settings.
     * @param int $viewerId The ID of the user viewing the newsfeed.
     * @param int $limit Max number of posts to fetch.
     * @param int $offset Offset for pagination.
     * @return array An array of post data.
     */
    public function getNewsfeedPosts(int $viewerId, int $limit = 10, int $offset = 0) {
        // This query demonstrates the visibility logic.
        // It's simplified and might need optimization for very large datasets.
        $sql = "
            SELECT p.*, u.username AS author_username, u.profile_picture_url AS author_profile_pic
            FROM Posts p
            JOIN Users u ON p.user_id = u.user_id
            WHERE
                p.visibility = 'public' -- Public posts are always visible
                OR (p.visibility = 'only_me' AND p.user_id = ?) -- 'Only Me' posts visible to owner
                OR (
                    p.visibility = 'friends' AND EXISTS (
                        SELECT 1 FROM Friendships f
                        WHERE (f.user_id_1 = p.user_id AND f.user_id_2 = ?)
                           OR (f.user_id_1 = ? AND f.user_id_2 = p.user_id)
                        AND f.status = 'accepted'
                    )
                )
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?;
        ";
        $stmt = $this->db_conn->prepare($sql);
        try {
            $stmt->execute([$viewerId, $viewerId, $viewerId, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching newsfeed posts: " . $e->getMessage());
            return [];
        }
    }
}

class CommentManager {
    private $db_conn;

    public function __construct(PDO $db_conn) {
        $this->db_conn = $db_conn;
    }

    /**
     * Adds a new top-level comment to a post.
     * @param string $postId The ID of the post to comment on.
     * @param int $userId The ID of the user making the comment.
     * @param string $content The content of the comment.
     * @return string|false The comment_id if successful, false otherwise.
     */
    public function addComment(string $postId, int $userId, string $content) {
        $commentId = generate_uuid_v4();
        $stmt = $this->db_conn->prepare("INSERT INTO Comments (comment_id, post_id, user_id, content, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        try {
            $stmt->execute([$commentId, $postId, $userId, $content]);
            return $commentId;
        } catch (PDOException $e) {
            error_log("Error adding comment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Adds a reply to an existing comment.
     * @param string $commentId The ID of the parent comment.
     * @param int $userId The ID of the user making the reply.
     * @param string $content The content of the reply.
     * @return string|false The reply_id if successful, false otherwise.
     */
    public function addCommentReply(string $commentId, int $userId, string $content) {
        $replyId = generate_uuid_v4();
        $stmt = $this->db_conn->prepare("INSERT INTO CommentReplies (reply_id, comment_id, user_id, content, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        try {
            $stmt->execute([$replyId, $commentId, $userId, $content]);
            return $replyId;
        } catch (PDOException $e) {
            error_log("Error adding comment reply: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all comments and their replies for a specific post.
     * @param string $postId The ID of the post.
     * @return array An array of comments, each potentially containing replies.
     */
    public function getCommentsForPost(string $postId) {
        // Fetch top-level comments
        $commentsSql = "
            SELECT c.*, u.username AS commenter_username, u.profile_picture_url AS commenter_profile_pic
            FROM Comments c
            JOIN Users u ON c.user_id = u.user_id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC;
        ";
        $commentsStmt = $this->db_conn->prepare($commentsSql);
        $commentsStmt->execute([$postId]);
        $comments = $commentsStmt->fetchAll();

        // Fetch replies for each comment
        foreach ($comments as &$comment) {
            $repliesSql = "
                SELECT cr.*, u.username AS replier_username, u.profile_picture_url AS replier_profile_pic
                FROM CommentReplies cr
                JOIN Users u ON cr.user_id = u.user_id
                WHERE cr.comment_id = ?
                ORDER BY cr.created_at ASC;
            ";
            $repliesStmt = $this->db_conn->prepare($repliesSql);
            $repliesStmt->execute([$comment['comment_id']]);
            $comment['replies'] = $repliesStmt->fetchAll();
        }
        return $comments;
    }
}

// --- Example Usage ---

// 1. Initialize Database
$database = new Database();
$pdo = $database->getConnection();

// 2. Initialize Managers
$postManager = new PostManager($pdo);
$commentManager = new CommentManager($pdo);

// Simulate current user
$currentUserId = get_current_user_id();

// --- Scenario: Creating a Post ---
echo "--- Creating a Post ---\n";
$newPostId = $postManager->createPost($currentUserId, "Hello, world! This is my first post.", null, 'public');
if ($newPostId) {
    echo "Post created successfully with ID: " . $newPostId . "\n";
} else {
    echo "Failed to create post.\n";
}

// Simulate another user (User ID 2)
// For a real system, you'd have user registration/login
// For testing, let's manually add a user if not exists
$stmt = $pdo->prepare("INSERT IGNORE INTO Users (user_id, username, email, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([2, 'JaneDoe', 'jane.doe@example.com']);
$stmt->execute([3, 'JohnSmith', 'john.smith@example.com']);

// Establish friendship between User 1 and User 2 (if not exists)
$stmt = $pdo->prepare("INSERT IGNORE INTO Friendships (user_id_1, user_id_2, status, created_at) VALUES (?, ?, 'accepted', NOW())");
// Ensure user_id_1 is always smaller for consistency
$user1 = min($currentUserId, 2);
$user2 = max($currentUserId, 2);
$stmt->execute([$user1, $user2]);

// Create a 'friends' post by User 2
$friendPostId = $postManager->createPost(2, "This is a friends-only post!", null, 'friends');
if ($friendPostId) {
    echo "Friend post created by User 2 with ID: " . $friendPostId . "\n";
}

// --- Scenario: Displaying Newsfeed Posts ---
echo "\n--- Displaying Newsfeed for User " . $currentUserId . " ---\n";
$newsfeedPosts = $postManager->getNewsfeedPosts($currentUserId);
if (!empty($newsfeedPosts)) {
    foreach ($newsfeedPosts as $post) {
        echo "Post ID: " . $post['post_id'] . "\n";
        echo "  Author: " . $post['author_username'] . " (ID: " . $post['user_id'] . ")\n";
        echo "  Content: " . $post['content'] . "\n";
        echo "  Visibility: " . $post['visibility'] . "\n";
        echo "  Posted: " . $post['created_at'] . "\n";
        echo "--------------------------\n";
    }
} else {
    echo "No posts found for newsfeed.\n";
}

// --- Scenario: Adding Comments and Replies ---
echo "\n--- Adding Comments and Replies ---\n";
if ($newPostId) {
    $comment1Id = $commentManager->addComment($newPostId, $currentUserId, "Great post!");
    if ($comment1Id) {
        echo "Comment 1 added to post " . $newPostId . " by User " . $currentUserId . "\n";
    }

    $comment2Id = $commentManager->addComment($newPostId, 2, "I agree!"); // User 2 comments
    if ($comment2Id) {
        echo "Comment 2 added to post " . $newPostId . " by User 2\n";
    }

    if ($comment1Id) {
        $reply1Id = $commentManager->addCommentReply($comment1Id, 3, "Thanks for the feedback!"); // User 3 replies to comment 1
        if ($reply1Id) {
            echo "Reply 1 added to comment " . $comment1Id . " by User 3\n";
        }
    }
}

// --- Scenario: Displaying Comments and Replies for a Post ---
echo "\n--- Displaying Comments for Post " . $newPostId . " ---\n";
if ($newPostId) {
    $postComments = $commentManager->getCommentsForPost($newPostId);
    if (!empty($postComments)) {
        foreach ($postComments as $comment) {
            echo "Comment ID: " . $comment['comment_id'] . "\n";
            echo "  By: " . $comment['commenter_username'] . " (ID: " . $comment['user_id'] . ")\n";
            echo "  Content: " . $comment['content'] . "\n";
            echo "  Posted: " . $comment['created_at'] . "\n";

            if (!empty($comment['replies'])) {
                echo "  Replies:\n";
                foreach ($comment['replies'] as $reply) {
                    echo "    Reply ID: " . $reply['reply_id'] . "\n";
                    echo "      By: " . $reply['replier_username'] . " (ID: " . $reply['user_id'] . ")\n";
                    echo "      Content: " . $reply['content'] . "\n";
                    echo "      Posted: " . $reply['created_at'] . "\n";
                }
            }
            echo "--------------------------\n";
        }
    } else {
        echo "No comments found for this post.\n";
    }
}

?>

