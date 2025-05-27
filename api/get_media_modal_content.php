<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get media ID from query parameter
if (!isset($_GET['media_id']) || !is_numeric($_GET['media_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid media ID']);
    exit();
}

$media_id = intval($_GET['media_id']);
$user_id = $_SESSION['user']['id'];

try {
    // Get media details with post content - enhanced query
    $stmt = $pdo->prepare("
        SELECT um.*,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author_name,
               u.profile_pic,
               u.gender,
               p.content as post_content,
               p.created_at as post_created_at
        FROM user_media um
        LEFT JOIN users u ON um.user_id = u.id
        LEFT JOIN posts p ON um.post_id = p.id
        WHERE um.id = ?
    ");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Media not found']);
        exit();
    }

    // Set default profile picture
    if (empty($media['profile_pic'])) {
        $media['profile_pic'] = $media['gender'] === 'female'
            ? 'assets/images/FemaleDefaultProfilePicture.png'
            : 'assets/images/MaleDefaultProfilePicture.png';
    }

    // Get navigation media (previous/next in the same post if applicable)
    $prevMedia = null;
    $nextMedia = null;

    if ($media['post_id']) {
        // Get all media from the same post
        $stmt = $pdo->prepare("
            SELECT id, media_url, media_type, thumbnail_url
            FROM user_media
            WHERE post_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$media['post_id']]);
        $allPostMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Find current media position and get prev/next
        foreach ($allPostMedia as $index => $item) {
            if ($item['id'] == $media_id) {
                if ($index > 0) {
                    $prevMedia = $allPostMedia[$index - 1];
                }
                if ($index < count($allPostMedia) - 1) {
                    $nextMedia = $allPostMedia[$index + 1];
                }
                break;
            }
        }
    }

    // Generate HTML that replicates view_album.php structure EXACTLY
    $modalHTML = generateViewAlbumHTML($media, $prevMedia, $nextMedia, $user_id);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'html' => $modalHTML,
        'media_id' => $media_id,
        'post_content' => $media['post_content'],
        'prev_media' => $prevMedia,
        'next_media' => $nextMedia
    ]);

} catch (Exception $e) {
    error_log("Error in get_media_modal_content.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load media content: ' . $e->getMessage()
    ]);
}

// Generate HTML that exactly replicates view_album.php structure
function generateViewAlbumHTML($media, $prevMedia, $nextMedia, $user_id) {
    $mediaId = $media['id'];
    $isOwner = ($media['user_id'] == $user_id);

    $html = '
    <div class="modal-view-album-content">
        <!-- Media Display Section - EXACT replica of view_album.php -->
        <div class="media-section">
            <div class="card bg-dark text-light border-secondary">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Media Display -->
                            <div class="media-container position-relative mb-3">
    ';

    // Display media based on type - EXACT same as view_album.php
    if (strpos($media['media_type'] ?? '', 'image') !== false) {
        $html .= '<img src="' . htmlspecialchars($media['media_url']) . '" alt="Media" class="img-fluid rounded" style="max-height: 70vh; width: 100%; object-fit: contain; background: #000;">';
    } elseif (strpos($media['media_type'] ?? '', 'video') !== false) {
        $html .= '
            <video controls class="img-fluid rounded" style="max-height: 70vh; width: 100%; background: #000;">
                <source src="' . htmlspecialchars($media['media_url']) . '" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        ';
    }

    // Navigation arrows - EXACT same as view_album.php
    if ($prevMedia) {
        $html .= '
            <a href="#" class="media-nav prev btn btn-dark position-absolute"
               onclick="navigateModalMedia(-1); return false;"
               style="left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; opacity: 0.8;">
                <i class="fas fa-chevron-left"></i>
            </a>
        ';
    }

    if ($nextMedia) {
        $html .= '
            <a href="#" class="media-nav next btn btn-dark position-absolute"
               onclick="navigateModalMedia(1); return false;"
               style="right: 10px; top: 50%; transform: translateY(-50%); z-index: 10; opacity: 0.8;">
                <i class="fas fa-chevron-right"></i>
            </a>
        ';
    }

    $html .= '
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Media Info - EXACT same as view_album.php -->
                            <div class="media-info">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="' . htmlspecialchars($media['profile_pic']) . '" alt="' . htmlspecialchars($media['author_name']) . '"
                                         class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                    <div>
                                        <h6 class="mb-0">' . htmlspecialchars($media['author_name']) . '</h6>
                                        <small class="text-muted">' . date('F j, Y, g:i a', strtotime($media['created_at'])) . '</small>
                                    </div>
                                </div>

                                <!-- Post Caption -->
                                ' . (!empty($media['post_content']) ? '<p class="mb-3">' . nl2br(htmlspecialchars($media['post_content'])) . '</p>' : '') . '

                                <!-- Reactions Section - EXACT same as view_album.php -->
                                <div class="reactions-section mb-3">
                                    <button class="btn btn-outline-light btn-sm post-react-btn"
                                            data-post-id="' . $mediaId . '"
                                            data-content-type="media">
                                        <i class="far fa-smile me-1"></i> React
                                    </button>

                                    <!-- Add a container for reaction summary -->
                                    <div class="reaction-summary" data-media-id="' . $mediaId . '" style="display: none; align-items: center; margin-top: 10px;"></div>

                                    <div id="reactionsContainer" class="mt-2">
                                        <!-- Reactions will be displayed here by view-album-reactions.js -->
                                    </div>
                                </div>

                                <!-- Comments Section - NEW but using same structure -->
                                <div class="comments-section mt-3">
                                    <h6 class="mb-3">
                                        <i class="fas fa-comments me-2"></i>Comments
                                        <small class="text-muted ms-2" id="comment-count-' . $mediaId . '">Loading...</small>
                                    </h6>

                                    <!-- Comments Container -->
                                    <div class="comments-container mb-3"
                                         data-media-id="' . $mediaId . '"
                                         style="max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.05); border-radius: 8px; padding: 15px;">
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-comments fa-2x mb-2"></i>
                                            <p>Loading comments...</p>
                                        </div>
                                    </div>

                                    <!-- Comment Form -->
                                    <form class="comment-form" data-media-id="' . $mediaId . '">
                                        <div class="input-group">
                                            <input type="text" class="form-control comment-input"
                                                   placeholder="Write a comment..." required>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-1"></i> Post
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal content loaded - initialization handled by dashboardv2.php -->
    <script>
        // Set the current media ID for the reaction system
        window.currentModalMediaId = ' . $mediaId . ';
        console.log("Modal content loaded for media ID:", ' . $mediaId . ');
    </script>
    ';

    return $html;
}
?>
