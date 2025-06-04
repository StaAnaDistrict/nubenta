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
$mediaUploader = new MediaUploader($pdo);

// Get media ID from URL
$mediaId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if this is being loaded in modal mode
$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';

// Handle privacy update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_privacy'])) {
    $mediaId = isset($_POST['media_id']) ? intval($_POST['media_id']) : 0;
    $privacy = isset($_POST['privacy']) ? $_POST['privacy'] : 'public';

    if ($mediaId > 0) {
        // Check if media belongs to user
        $checkStmt = $pdo->prepare("SELECT user_id FROM user_media WHERE id = ?");
        $checkStmt->execute([$mediaId]);
        $mediaOwner = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($mediaOwner && $mediaOwner['user_id'] == $user['id']) {
            // Update privacy setting
            $updateStmt = $pdo->prepare("UPDATE user_media SET privacy = ? WHERE id = ?");
            $updateStmt->execute([$privacy, $mediaId]);

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Privacy settings updated successfully'
            ];

            // Redirect to refresh the page
            header("Location: view_media.php?id=" . $mediaId);
            exit();
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to update this media'
            ];
        }
    }
}

// Get media details
$media = null;
if ($mediaId > 0) {
    // Update query to include privacy field
    $stmt = $pdo->prepare("SELECT * FROM user_media WHERE id = ?");
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    // If media not found or doesn't belong to user and is private, redirect
    if (!$media ||
        ($media['user_id'] != $user['id'] && $user['role'] !== 'admin' &&
         (($media['privacy'] ?? 'public') === 'private' ||
          (($media['privacy'] ?? 'public') === 'friends' && !isFriend($pdo, $user['id'], $media['user_id']))))) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Media not found or you do not have permission to view it'
        ];
        header("Location: manage_media.php");
        exit();
    }
}

// Function to check if users are friends
function isFriend($pdo, $userId1, $userId2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as is_friend
        FROM friend_requests
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'accepted'
    ");
    $stmt->execute([$userId1, $userId2, $userId2, $userId1]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['is_friend'] > 0;
}

// Get previous and next media IDs for navigation
$prevMedia = null;
$nextMedia = null;

if ($media) {
    // Get previous media
    $prevStmt = $pdo->prepare("
        SELECT id FROM user_media
        WHERE user_id = ? AND id < ?
        ORDER BY id DESC LIMIT 1
    ");
    $prevStmt->execute([$media['user_id'], $mediaId]);
    $prevMedia = $prevStmt->fetch(PDO::FETCH_ASSOC);

    // Get next media
    $nextStmt = $pdo->prepare("
        SELECT id FROM user_media
        WHERE user_id = ? AND id > ?
        ORDER BY id ASC LIMIT 1
    ");
    $nextStmt->execute([$media['user_id'], $mediaId]);
    $nextMedia = $nextStmt->fetch(PDO::FETCH_ASSOC);
}

// Page title
$pageTitle = $media ? "Viewing Media" : "Media Not Found";

// If this is a modal request, only output the card content
if ($isModal) {
    // Only output the card content for modal
    if ($media) {
        ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php echo htmlspecialchars($media['caption'] ?? ''); ?>
                </h5>
                <div>
                    <?php if ($media['post_id']): ?>
                        <a href="view_post.php?id=<?php echo $media['post_id']; ?>" class="btn btn-sm btn-outline-light ms-2" target="_blank">
                            <i class="fas fa-link me-1"></i> View Post
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="modal-media-container">
                    <?php if ($media['media_type'] === 'image'): ?>
                        <img src="<?php echo htmlspecialchars($media['media_url']); ?>" alt="Media" class="img-fluid">
                    <?php elseif ($media['media_type'] === 'video'): ?>
                        <video controls autoplay class="img-fluid">
                            <source src="<?php echo htmlspecialchars($media['media_url']); ?>">
                            Your browser does not support the video tag.
                        </video>
                    <?php endif; ?>
                </div>

                <div class="media-info mt-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Uploaded: <?php echo date('F j, Y, g:i a', strtotime($media['created_at'])); ?></span>
                        </div>
                        <div class="d-flex">
                            <!-- Show privacy status for modal -->
                            <span class="badge bg-secondary me-2">
                                <i class="fas fa-<?php echo ($media['privacy'] ?? 'public') === 'public' ? 'globe' : (($media['privacy'] ?? 'public') === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                <?php echo ucfirst($media['privacy'] ?? 'public'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Reactions Section for Modal -->
                <div class="reactions-section mt-3" id="modal-reactions-<?php echo $media['id']; ?>">
                    <!-- Default React Button -->
                    <div class="d-flex align-items-center mb-2">
                        <button class="btn btn-sm btn-outline-light me-2 modal-react-btn post-react-btn"
                                data-media-id="<?php echo $media['id']; ?>"
                                data-post-id="media-<?php echo $media['id']; ?>">
                            <i class="far fa-smile me-1"></i> React
                        </button>
                        <span class="text-muted reaction-count-display">Loading reactions...</span>
                    </div>
                </div>

                <!-- Comments Section for Modal -->
                <div class="comments-section mt-3" id="modal-comments-<?php echo $media['id']; ?>">
                    <h6 class="mb-3 text-light">Comments</h6>

                    <!-- Comments Container with better styling -->
                    <div class="comments-container mb-3" data-media-id="<?php echo $media['id']; ?>"
                         style="max-height: 300px; overflow-y: auto; background: rgba(255,255,255,0.1); border-radius: 8px; padding: 15px;">
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p>Loading comments...</p>
                        </div>
                    </div>

                    <!-- Comment Form with better styling -->
                    <form class="comment-form" data-media-id="<?php echo $media['id']; ?>">
                        <div class="input-group">
                            <input type="text" class="form-control comment-input bg-dark text-light border-secondary"
                                   placeholder="Write a comment..." required
                                   style="border-radius: 20px 0 0 20px;">
                            <button type="submit" class="btn btn-primary" style="border-radius: 0 20px 20px 0;">
                                <i class="fas fa-paper-plane me-1"></i> Post
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    } else {
        ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                <h4>Media Not Found</h4>
                <p>The requested media item could not be found or you don't have permission to view it.</p>
            </div>
        </div>
        <?php
    }
    exit(); // Exit early for modal requests
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $pageTitle; ?> - Nubenta</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <link rel="stylesheet" href="assets/css/reactions.css">
    <link rel="stylesheet" href="assets/css/simple-reactions.css">
    <link rel="stylesheet" href="assets/css/comments.css">
    <style>
        .media-container {
            position: relative;
            max-height: 70vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .media-container img,
        .media-container video {
            max-height: 70vh;
            max-width: 100%;
            object-fit: contain;
        }

        .media-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            color: white;
            background-color: rgba(0, 0, 0, 0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .media-nav:hover {
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
        }

        .media-nav.prev {
            left: 10px;
        }

        .media-nav.next {
            right: 10px;
        }

        .media-info {
            margin-bottom: 20px;
        }

        .comments-section {
            margin-top: 30px;
        }

        /* Custom button styling for blackish theme */
        .btn-primary {
            background-color: #2c2c2c;
            border-color: #333;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #404040;
            border-color: #444;
            color: #fff;
        }

        .btn-outline-primary {
            color: #2c2c2c;
            border-color: #333;
        }

        .btn-outline-primary:hover {
            background-color: #2c2c2c;
            border-color: #333;
            color: #fff;
        }

        .btn-dark, .btn-outline-dark {
            background-color: #2c2c2c;
            border-color: #333;
            color: #fff;
        }

        .btn-dark:hover, .btn-outline-dark:hover {
            background-color: #404040;
            border-color: #444;
            color: #fff;
        }

        .btn-outline-dark {
            background-color: transparent;
            color: #2c2c2c;
        }

        .btn-outline-dark:hover {
            background-color: #2c2c2c;
            color: #fff;
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php
            $currentUser = $user;
            $currentPage = 'manage_media';
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash_message']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <?php if ($media): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php echo htmlspecialchars($media['caption'] ?? ''); ?>
                        </h5>
                        <div>
                            <a href="user_albums.php?id=<?= htmlspecialchars(\$media['user_id']) ?>" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-images me-1"></i> Back to Albums
                            </a>
                            <a href="view_user_media.php?id=<?= htmlspecialchars(\$media['user_id']) ?>&media_type=<?= htmlspecialchars(\$media['media_type']) ?>" class="btn btn-sm btn-outline-dark ms-2">
                                <i class="fas fa-photo-video me-1"></i> Back to Gallery
                            </a>
                            <?php if ($media['post_id']): ?>
                                <a href="view_post.php?id=<?php echo $media['post_id']; ?>" class="btn btn-sm btn-outline-dark ms-2">
                                    <i class="fas fa-link me-1"></i> View Post
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="media-container">
                            <?php if ($media['media_type'] === 'image'): ?>
                                <img src="<?php echo htmlspecialchars($media['media_url']); ?>" alt="Media" class="img-fluid">
                            <?php elseif ($media['media_type'] === 'video'): ?>
                                <video controls autoplay class="img-fluid">
                                    <source src="<?php echo htmlspecialchars($media['media_url']); ?>">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>

                            <?php if ($prevMedia): ?>
                                <a href="view_media.php?id=<?php echo $prevMedia['id']; ?>" class="media-nav prev">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php if ($nextMedia): ?>
                                <a href="view_media.php?id=<?php echo $nextMedia['id']; ?>" class="media-nav next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="media-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted">Uploaded: <?php echo date('F j, Y, g:i a', strtotime($media['created_at'])); ?></span>
                                </div>
                                <div class="d-flex">
                                    <?php if ($media['user_id'] === $user['id']): ?>
                                        <!-- Privacy settings dropdown -->
                                        <div class="dropdown me-2">
                                            <form method="POST" id="updatePrivacyForm">
                                                <input type="hidden" name="media_id" value="<?php echo $media['id']; ?>">
                                                <div class="input-group">
                                                    <select class="form-select form-select-sm" name="privacy" id="privacySelect" onchange="document.getElementById('updatePrivacyForm').submit();">
                                                        <option value="public" <?php echo ($media['privacy'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public</option>
                                                        <option value="friends" <?php echo ($media['privacy'] ?? 'public') === 'friends' ? 'selected' : ''; ?>>Friends Only</option>
                                                        <option value="private" <?php echo ($media['privacy'] ?? 'public') === 'private' ? 'selected' : ''; ?>>Private</option>
                                                    </select>
                                                    <button type="submit" name="update_privacy" class="btn btn-sm btn-outline-dark">
                                                        <i class="fas fa-save me-1"></i> Save
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <!-- Show privacy status for non-owners -->
                                        <span class="badge bg-secondary me-2">
                                            <i class="fas fa-<?php echo ($media['privacy'] ?? 'public') === 'public' ? 'globe' : (($media['privacy'] ?? 'public') === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                            <?php echo ucfirst($media['privacy'] ?? 'public'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Delete button (existing) -->
                                    <?php if ($media && ($media['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')): ?>
                                    <form method="POST" action="manage_media.php" onsubmit="return confirm('Are you sure you want to delete this media?');" class="d-inline">
                                        <input type="hidden" name="media_id" value="<?php echo $media['id']; ?>">
                                        <button type="submit" name="delete_media" class="btn btn-sm btn-outline-dark">
                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Comments Section (Placeholder for now) -->
                        <div class="comments-section">
                            <h5 class="mb-3">Comments</h5>
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted text-center">Comments feature coming soon!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center p-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>Media Not Found</h4>
                        <p>The requested media item could not be found or you don't have permission to view it.</p>
                        <a href="manage_media.php" class="btn btn-dark mt-3">Back to Media Gallery</a>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
        <?php
        // Include the modular right sidebar
        include 'assets/add_ons.php';
        ?>
    </div>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Function to toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('.left-sidebar').classList.toggle('show');
    }

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Left arrow key
        if (e.keyCode === 37) {
            const prevLink = document.querySelector('.media-nav.prev');
            if (prevLink) prevLink.click();
        }
        // Right arrow key
        else if (e.keyCode === 39) {
            const nextLink = document.querySelector('.media-nav.next');
            if (nextLink) nextLink.click();
        }
    });
    </script>
</body>
</html>
