<?php

// Assume a basic database connection ($pdo) and helper functions like generate_uuid_v4() are available.
// (Refer to the previous PHP Logic document for these setup details)

class MediaUploader {
    private $pdo;
    private $uploadDir = __DIR__ . '/uploads/'; // Local upload directory (for simple example)
                                                // In production, this would interact with cloud storage APIs (e.g., AWS S3 SDK)

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true); // Create upload directory if it doesn't exist
        }
    }

    /**
     * Handles the upload of multiple files and returns their URLs and types.
     * In a real system, this would be more robust with error handling, security checks,
     * and integration with cloud storage.
     *
     * @param array $files The $_FILES array for the uploaded files.
     * @return array An array of associative arrays, each representing a media item.
     * e.g., [['url' => '...', 'type' => 'image', 'thumbnail_url' => null], ...]
     */
    public function handleMediaUploads(array $files): array {
        $uploadedMedia = [];

        // Check if files were actually uploaded
        if (empty($files['name'][0])) { // Assuming 'media_files[]' as input name
            return [];
        }

        // Loop through each uploaded file
        foreach ($files['name'] as $index => $fileName) {
            $tmpFilePath = $files['tmp_name'][$index];
            $fileError = $files['error'][$index];
            $fileSize = $files['size'][$index];
            $fileType = $files['type'][$index];

            if ($fileError !== UPLOAD_ERR_OK) {
                error_log("File upload error: " . $fileError);
                continue; // Skip this file
            }

            // Determine media type (simplified)
            $mediaType = 'unknown';
            if (str_starts_with($fileType, 'image/')) {
                $mediaType = 'image';
            } elseif (str_starts_with($fileType, 'video/')) {
                $mediaType = 'video';
            } else {
                error_log("Unsupported file type: " . $fileType);
                continue;
            }

            // Generate a unique filename to prevent conflicts
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = generate_uuid_v4() . '.' . $extension;
            $destinationPath = $this->uploadDir . $uniqueFileName;

            // Move the uploaded file from temp directory to our storage
            if (move_uploaded_file($tmpFilePath, $destinationPath)) {
                $mediaUrl = '/uploads/' . $uniqueFileName; // This would be a public URL in a real app
                $thumbnailUrl = null;

                // For videos, generate a thumbnail (conceptual: requires FFmpeg or similar)
                if ($mediaType === 'video') {
                    // In a real scenario:
                    // 1. Use FFmpeg (shell_exec or a PHP library wrapper) to extract a frame.
                    // 2. Save the frame as an image file.
                    // 3. Get the URL for the generated thumbnail.
                    $thumbnailUrl = '/uploads/thumbnails/' . generate_uuid_v4() . '.jpg';
                    // For demo, just assign a placeholder
                    // echo "  (Conceptual: Generating thumbnail for video: " . $mediaUrl . ")\n";
                    // $thumbnailUrl = 'https://placehold.co/120x90/aabbcc/ffffff?text=VideoThumb';
                }

                $uploadedMedia[] = [
                    'url' => $mediaUrl,
                    'type' => $mediaType,
                    'thumbnail_url' => $thumbnailUrl,
                    'file_size_bytes' => $fileSize,
                    // 'duration_seconds' => (for videos, would extract metadata here)
                ];
            } else {
                error_log("Failed to move uploaded file: " . $fileName);
            }
        }
        return $uploadedMedia;
    }
}

class PostManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Creates a new post, optionally with multiple media items.
     * @param int $userId The ID of the user creating the post.
     * @param string $content The text content of the post.
     * @param array $mediaItems An array of media items, where each item is an associative array
     * like ['url' => '...', 'type' => 'image'|'video', 'thumbnail_url' => '...'].
     * @param string $visibility 'public', 'friends', or 'only_me'.
     * @return string|false The post_id if successful, false otherwise.
     */
    public function createPost(int $userId, string $content, array $mediaItems = [], string $visibility = 'friends') {
        $postId = generate_uuid_v4();

        // Start a transaction to ensure atomicity for post and media
        $this->pdo->beginTransaction();

        try {
            // 1. Insert into Posts table
            $stmt = $this->pdo->prepare("INSERT INTO Posts (post_id, user_id, content, visibility, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$postId, $userId, $content, $visibility]);

            // 2. Insert into PostMedia table for each media item
            if (!empty($mediaItems)) {
                $mediaOrder = 0;
                $mediaStmt = $this->pdo->prepare("INSERT INTO PostMedia (media_id, post_id, media_url, media_type, thumbnail_url, order_index, file_size_bytes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                foreach ($mediaItems as $media) {
                    $mediaId = generate_uuid_v4();
                    $mediaStmt->execute([
                        $mediaId,
                        $postId,
                        $media['url'],
                        $media['type'],
                        $media['thumbnail_url'] ?? null,
                        $mediaOrder++,
                        $media['file_size_bytes'] ?? null // Save file size
                    ]);
                }
            }

            $this->pdo->commit();
            return $postId;

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error creating post with media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches posts for a given viewing user, respecting visibility settings, including associated media.
     * @param int $viewerId The ID of the user viewing the newsfeed.
     * @param int $limit Max number of posts to fetch.
     * @param int $offset Offset for pagination.
     * @return array An array of post data, with 'media' array for each post.
     */
    public function getNewsfeedPosts(int $viewerId, int $limit = 10, int $offset = 0) {
        // First, fetch the posts based on visibility
        $postsSql = "
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
        $postsStmt = $this->pdo->prepare($postsSql);
        try {
            $postsStmt->execute([$viewerId, $viewerId, $viewerId, $limit, $offset]);
            $posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

            // If no posts, return early
            if (empty($posts)) {
                return [];
            }

            // Get all post IDs to fetch media in a single query for efficiency
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
            error_log("Error fetching newsfeed posts with media: " . $e->getMessage());
            return [];
        }
    }
}

// --- Example Usage ---

// 1. Initialize Database
$database = new Database();
$pdo = $database->getConnection();

// 2. Initialize Managers
$mediaUploader = new MediaUploader($pdo);
$postManager = new PostManager($pdo);

// Simulate current user
$currentUserId = get_current_user_id();

// --- Scenario: Simulating a Post Request with Media ---
// This would typically come from $_POST and $_FILES in a web environment.
// For this example, we'll manually construct the data.

// Simulate $_FILES array for two images and one video
$simulatedFiles = [
    'name' => [
        'photo1.jpg',
        'my_video.mp4',
        'photo2.png'
    ],
    'type' => [
        'image/jpeg',
        'video/mp4',
        'image/png'
    ],
    'tmp_name' => [
        '/tmp/php_upload_tmp_img1', // Placeholder for temp file path
        '/tmp/php_upload_tmp_video',
        '/tmp/php_upload_tmp_img2'
    ],
    'error' => [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_OK,
        UPLOAD_ERR_OK
    ],
    'size' => [
        1024 * 500, // 500KB
        1024 * 1024 * 10, // 10MB
        1024 * 700 // 700KB
    ]
];

echo "--- Simulating Media Upload Process ---\n";
// In a real application, this would be called when the form is submitted
$uploadedMediaInfo = $mediaUploader->handleMediaUploads($simulatedFiles);

if (!empty($uploadedMediaInfo)) {
    echo "Media files processed successfully. Ready to create post.\n";
    foreach ($uploadedMediaInfo as $media) {
        echo "  - URL: " . $media['url'] . ", Type: " . $media['type'] . ", Size: " . $media['file_size_bytes'] . " bytes\n";
    }
} else {
    echo "No media uploaded or an error occurred during upload.\n";
}

// --- Scenario: Creating a Post with the Uploaded Media ---
echo "\n--- Creating a Post with the Uploaded Media ---\n";
$newPostIdWithMedia = $postManager->createPost(
    $currentUserId,
    "Here's my new post with multiple media items!",
    $uploadedMediaInfo, // Pass the processed media info
    'public'
);

if ($newPostIdWithMedia) {
    echo "Post with media created successfully with ID: " . $newPostIdWithMedia . "\n";
} else {
    echo "Failed to create post with media.\n";
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


// --- Scenario: Displaying Newsfeed Posts with Media ---
echo "\n--- Displaying Newsfeed for User " . $currentUserId . " (including media) ---\n";
$newsfeedPosts = $postManager->getNewsfeedPosts($currentUserId);
if (!empty($newsfeedPosts)) {
    foreach ($newsfeedPosts as $post) {
        echo "Post ID: " . $post['post_id'] . "\n";
        echo "  Author: " . $post['author_username'] . " (ID: " . $post['user_id'] . ")\n";
        echo "  Content: " . $post['content'] . "\n";
        echo "  Visibility: " . $post['visibility'] . "\n";
        echo "  Posted: " . $post['created_at'] . "\n";
        if (!empty($post['media'])) {
            echo "  Media:\n";
            foreach ($post['media'] as $mediaItem) {
                echo "    - Type: " . $mediaItem['media_type'] . ", URL: " . $mediaItem['media_url'];
                if ($mediaItem['thumbnail_url']) {
                    echo ", Thumbnail: " . $mediaItem['thumbnail_url'];
                }
                echo ", Size: " . $mediaItem['file_size_bytes'] . " bytes\n";
            }
        }
        echo "--------------------------\n";
    }
} else {
    echo "No posts found for newsfeed.\n";
}

?>