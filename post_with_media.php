<?php
session_start();
require_once 'db.php';
require_once 'includes/MediaUploader.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$response = ['success' => false, 'message' => ''];

// Process post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';

    if (empty($content) && empty($_FILES['media']['name'][0])) {
        $response['message'] = 'Post must have content or media';
    } else {
        try {
            // Handle media uploads
            $mediaUploader = new MediaUploader($pdo);
            $uploadedMedia = [];
            $mediaIds = [];

            if (!empty($_FILES['media']['name'][0])) {
                $uploadedMedia = $mediaUploader->handleMediaUploads($_FILES['media']);

                // Save each media to user_media table and collect IDs
                foreach ($uploadedMedia as $media) {
                    $mediaId = $mediaUploader->saveUserMedia(
                        $user['id'],
                        $media['url'],
                        $media['type'],
                        $media['thumbnail_url'] ?? null,
                        $media['file_size_bytes']
                    );

                    if ($mediaId) {
                        $mediaIds[] = $mediaId;
                    }
                }

                // If media was uploaded, add it to an auto-generated album
                if (!empty($mediaIds)) {
                    // Check if "Posts" album exists for this user
                    $stmt = $pdo->prepare("
                        SELECT id FROM user_media_albums
                        WHERE user_id = ? AND album_name = 'Posts'
                    ");
                    $stmt->execute([$user['id']]);
                    $postsAlbum = $stmt->fetch(PDO::FETCH_ASSOC);

                    $albumId = null;
                    if ($postsAlbum) {
                        // Use existing Posts album
                        $albumId = $postsAlbum['id'];
                        // Add media to existing album
                        $mediaUploader->addMediaToAlbum($albumId, $mediaIds, $user['id']);
                    } else {
                        // Create a new "Posts" album
                        $albumId = $mediaUploader->createMediaAlbum(
                            $user['id'],
                            'Posts',
                            'Media shared in posts',
                            $mediaIds,
                            'public'
                        );
                    }
                }
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Insert post
            $stmt = $pdo->prepare("
                INSERT INTO posts (user_id, content, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $content]);
            $postId = $pdo->lastInsertId();

            // Save media if any
            if (!empty($uploadedMedia)) {
                // Save to user_media table
                $mediaUploader->saveUserMedia($user['id'], $uploadedMedia, $postId);

                // Also save to post_media table if it exists
                try {
                    $mediaStmt = $pdo->prepare("
                        INSERT INTO post_media (post_id, media_url, created_at)
                        VALUES (?, ?, NOW())
                    ");

                    foreach ($uploadedMedia as $media) {
                        $mediaStmt->execute([$postId, $media['url']]);
                    }
                } catch (PDOException $e) {
                    // If post_media table doesn't exist or has different structure, log error but continue
                    error_log("Error saving to post_media: " . $e->getMessage());
                }
            }

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Post created successfully';
            $response['post_id'] = $postId;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creating post: " . $e->getMessage());
            $response['message'] = 'Error creating post';
        }
    }

    // Return JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Redirect for regular form submissions
    if ($response['success']) {
        header("Location: dashboard.php?success=post_created");
    } else {
        header("Location: dashboard.php?error=" . urlencode($response['message']));
    }
    exit();
}

// If not a POST request, redirect to dashboard
header("Location: dashboard.php");
exit();
?>
