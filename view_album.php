<?php
// At the top of your file, add error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Make sure $media_id is properly defined
$media_id = isset($_GET['media_id']) ? intval($_GET['media_id']) : null;

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

// Get album ID from URL
$albumId = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Get media ID from URL if viewing a specific media
$mediaId = isset($_GET['media_id']) ? intval($_GET['media_id']) : 0;

if ($albumId <= 0) {
    header("Location: manage_albums.php");
    exit();
}

// Get album details
$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name, u.profile_pic as profile_picture
    FROM user_media_albums a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$albumId]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) {
    header("Location: manage_albums.php?error=Album not found");
    exit();
}

// Create a username from first_name and last_name
$album['username'] = $album['first_name'] . ' ' . $album['last_name'];

// For default album (id=1), get all user media
if ($albumId == 1) {
    // Check if viewing own album or has admin role
    if ($album['user_id'] === $user['id'] || $user['role'] === 'admin') {
        // Show all media for owner or admin
        $stmt = $pdo->prepare("
            SELECT m.*, m.created_at as added_at
            FROM user_media m
            WHERE m.user_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$album['user_id']]);
    } else {
        // Check if users are friends
        $areFriends = false;
        $friendStmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status = 'accepted'
        ");
        $friendStmt->execute([$user['id'], $album['user_id'], $album['user_id'], $user['id']]);
        $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
        $areFriends = ($friendship['is_friend'] > 0);

        // Show only public media for non-friends, or public+friends for friends
        if ($areFriends) {
            $stmt = $pdo->prepare("
                SELECT m.*, m.created_at as added_at
                FROM user_media m
                WHERE m.user_id = ? AND (m.privacy = 'public' OR m.privacy = 'friends')
                ORDER BY m.created_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT m.*, m.created_at as added_at
                FROM user_media m
                WHERE m.user_id = ? AND m.privacy = 'public'
                ORDER BY m.created_at DESC
            ");
        }
        $stmt->execute([$album['user_id']]);
    }
} else {
    // Get album media with privacy filtering
    if ($album['user_id'] === $user['id'] || $user['role'] === 'admin') {
        // Show all media for owner or admin
        $stmt = $pdo->prepare("
            SELECT m.*, am.created_at as added_at, p.content as post_content, p.id as linked_post_id
            FROM user_media m
            JOIN album_media am ON m.id = am.media_id
            LEFT JOIN posts p ON m.post_id = p.id
            WHERE am.album_id = ?
            ORDER BY am.created_at DESC
        ");
        $stmt->execute([$albumId]);
    } else {
        // Check if users are friends
        $areFriends = false;
        $friendStmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status = 'accepted'
        ");
        $friendStmt->execute([$user['id'], $album['user_id'], $album['user_id'], $user['id']]);
        $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
        $areFriends = ($friendship['is_friend'] > 0);

        // Show only public media for non-friends, or public+friends for friends
        if ($areFriends) {
            $stmt = $pdo->prepare("
                SELECT m.*, am.created_at as added_at, p.content as post_content, p.id as linked_post_id
                FROM user_media m
                JOIN album_media am ON m.id = am.media_id
                LEFT JOIN posts p ON m.post_id = p.id
                WHERE am.album_id = ? AND (m.privacy = 'public' OR m.privacy = 'friends')
                ORDER BY am.created_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT m.*, am.created_at as added_at, p.content as post_content, p.id as linked_post_id
                FROM user_media m
                JOIN album_media am ON m.id = am.media_id
                LEFT JOIN posts p ON m.post_id = p.id
                WHERE am.album_id = ? AND m.privacy = 'public'
                ORDER BY am.created_at DESC
            ");
        }
        $stmt->execute([$albumId]);
    }
}
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle removing media from album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_media']) && $albumId != 1) {
    $mediaId = intval($_POST['media_id']);

    try {
        // Check if the album belongs to the user
        $stmt = $pdo->prepare("SELECT id FROM user_media_albums WHERE id = ? AND user_id = ?");
        $stmt->execute([$albumId, $user['id']]);

        if ($stmt->fetch()) {
            // Remove media from album
            $stmt = $pdo->prepare("DELETE FROM album_media WHERE album_id = ? AND media_id = ?");
            $stmt->execute([$albumId, $mediaId]);

            // If this was the cover image, update the album
            $stmt = $pdo->prepare("
                SELECT cover_image_id FROM user_media_albums WHERE id = ? AND cover_image_id = ?
            ");
            $stmt->execute([$albumId, $mediaId]);

            if ($stmt->fetch()) {
                // Find a new cover image
                $stmt = $pdo->prepare("
                    SELECT m.id FROM user_media m
                    JOIN album_media am ON m.id = am.media_id
                    WHERE am.album_id = ? AND m.media_type LIKE 'image%'
                    ORDER BY am.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$albumId]);
                $newCover = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($newCover) {
                    $stmt = $pdo->prepare("
                        UPDATE user_media_albums SET cover_image_id = ? WHERE id = ?
                    ");
                    $stmt->execute([$newCover['id'], $albumId]);
                } else {
                    // No images left, clear cover
                    $stmt = $pdo->prepare("
                        UPDATE user_media_albums SET cover_image_id = NULL WHERE id = ?
                    ");
                    $stmt->execute([$albumId]);
                }
            }

            // Redirect to refresh the page
            header("Location: view_album.php?id=" . $albumId);
            exit();
        }
    } catch (Exception $e) {
        $error = "Error removing media: " . $e->getMessage();
    }
}

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
            header("Location: view_album.php?id=" . $albumId . "&media_id=" . $mediaId);
            exit();
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to update this media'
            ];
        }
    }
}

// Get specific media details if viewing a single media
$currentMedia = null;
$prevMedia = null;
$nextMedia = null;

if ($mediaId > 0) {
    // Find the current media in the album
    foreach ($media as $index => $item) {
        if ($item['id'] == $mediaId) {
            $currentMedia = $item;

            // Get previous media (cycle to last if at beginning)
            if ($index > 0) {
                $prevMedia = $media[$index - 1];
            } else {
                // If at first item, cycle to last item
                $prevMedia = count($media) > 1 ? $media[count($media) - 1] : null;
            }

            // Get next media (cycle to first if at end)
            if ($index < count($media) - 1) {
                $nextMedia = $media[$index + 1];
            } else {
                // If at last item, cycle to first item
                $nextMedia = count($media) > 1 ? $media[0] : null;
            }

            break;
        }
    }

    // If media not found in this album, redirect
    if (!$currentMedia) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Media not found in this album'
        ];
        header("Location: view_album.php?id=" . $albumId);
        exit();
    }
}

// Set page title and display name
$displayAlbumName = ($album['album_name'] === 'Default Gallery') ? 'My Gallery' : $album['album_name'];
$pageTitle = $currentMedia ? "Viewing Media" : $displayAlbumName;
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
    <!-- Replace simple-reactions.css with our new dedicated CSS file -->
    <link rel="stylesheet" href="assets/css/media-reactions.css">
    <link rel="stylesheet" href="assets/css/comments.css">
    <link rel="stylesheet" href="assets/css/view_album.css">
    <style>
    /* Fix reaction picker positioning - position it right above the React button */
    .reactions-section {
        position: relative;
    }

    .post-react-btn {
        position: relative;
    }

    #simple-reaction-picker {
        z-index: 9999 !important;
        display: none;
        position: absolute !important;
        background: #242526;
        border: 1px solid #3E4042;
        border-radius: 30px;
        padding: 8px 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        width: auto !important;
        max-width: 90vw;
        /* Position it directly above the React button */
        bottom: 100% !important;
        left: 0 !important;
        margin-bottom: 10px !important;
        /* Reset any other positioning */
        top: auto !important;
        right: auto !important;
        transform: none !important;
    }

    /* Ensure reaction options are visible and interactive */
    .reaction-option {
        transition: transform 0.15s ease-out;
        cursor: pointer;
        z-index: 10000;
        display: inline-block;
        margin: 0 2px;
    }

    .reaction-option:hover {
        transform: scale(1.3);
    }

    /* Ensure reaction picker stays visible when hovered */
    #simple-reaction-picker:hover {
        display: flex !important;
    }

    /* Make sure the reactions section has enough space */
    .reactions-section .d-flex {
        margin-bottom: 50px; /* Give space for the picker above */
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
            $currentPage = 'manage_albums';
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

            <?php if ($currentMedia): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php
                            // Debug: Show what we have
                            if (!empty($currentMedia['post_content'])) {
                                echo htmlspecialchars($currentMedia['post_content']);
                            } elseif (!empty($currentMedia['caption'])) {
                                echo htmlspecialchars($currentMedia['caption']);
                            } else {
                                echo 'Media Viewer';
                            }

                            // Debug output (remove in production)
                            if (isset($_GET['debug'])) {
                                echo '<br><small style="color: red;">DEBUG: post_id=' . ($currentMedia['post_id'] ?? 'NULL') .
                                     ', linked_post_id=' . ($currentMedia['linked_post_id'] ?? 'NULL') .
                                     ', post_content=' . ($currentMedia['post_content'] ?? 'NULL') .
                                     ', caption=' . ($currentMedia['caption'] ?? 'NULL') . '</small>';
                            }
                            ?>
                        </h5>
                        <div>
                            <a href="view_album.php?id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-images me-1"></i> Back to Album
                            </a>
                            <?php if ($album['user_id'] === $user['id']): ?>
                                <a href="manage_albums.php" class="btn btn-sm btn-outline-dark ms-2">
                                    <i class="fas fa-photo-video me-1"></i> Back to Albums
                                </a>
                            <?php else: ?>
                                <a href="user_albums.php?id=<?php echo $album['user_id']; ?>" class="btn btn-sm btn-outline-dark ms-2">
                                    <i class="fas fa-images me-1"></i> View Albums
                                </a>
                                <a href="view_profile.php?id=<?php echo $album['user_id']; ?>" class="btn btn-sm btn-outline-dark ms-2">
                                    <i class="fas fa-user me-1"></i> Back to Profile
                                </a>
                            <?php endif; ?>
                            <?php if ($currentMedia['post_id']): ?>
                                <?php
                                // Get additional post details for better identification
                                $postStmt = $pdo->prepare("SELECT user_id, created_at FROM posts WHERE id = ?");
                                $postStmt->execute([$currentMedia['post_id']]);
                                $postDetails = $postStmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <a href="dashboard.php"
                                   onclick="openPostModal(<?php echo $currentMedia['post_id']; ?>, <?php echo $currentMedia['id']; ?>, <?php echo $postDetails['user_id'] ?? 'null'; ?>, '<?php echo $postDetails['created_at'] ?? ''; ?>'); return false;"
                                   class="btn btn-sm btn-outline-dark ms-2">
                                    <i class="fas fa-link me-1"></i> View Post
                                </a>
                                <script>
                                function openPostModal(postId, mediaId, userId, createdAt) {
                                    // Enhanced redirect with more post identification parameters
                                    const params = new URLSearchParams({
                                        open_post: postId,
                                        media_id: mediaId,
                                        user_id: userId,
                                        created_at: createdAt,
                                        source: 'view_album'
                                    });
                                    window.location.href = `dashboard.php?${params.toString()}`;
                                }
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="media-container">
                            <?php if (strpos($currentMedia['media_type'] ?? '', 'image') !== false): ?>
                                <img src="<?php echo htmlspecialchars($currentMedia['media_url']); ?>" alt="Media" class="img-fluid">
                            <?php elseif (strpos($currentMedia['media_type'] ?? '', 'video') !== false): ?>
                                <video controls autoplay class="img-fluid">
                                    <source src="<?php echo htmlspecialchars($currentMedia['media_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>

                            <?php if ($prevMedia && count($media) > 1): ?>
                                <a href="view_album.php?id=<?php echo $albumId; ?>&media_id=<?php echo $prevMedia['id']; ?>" class="media-nav prev" title="Previous media">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php if ($nextMedia && count($media) > 1): ?>
                                <a href="view_album.php?id=<?php echo $albumId; ?>&media_id=<?php echo $nextMedia['id']; ?>" class="media-nav next" title="Next media">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="media-info">
                            <!-- Post Caption -->
                            <?php if (!empty($currentMedia['post_content'])): ?>
                                <div class="mb-3">
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($currentMedia['post_content'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted">Uploaded: <?php echo date('F j, Y, g:i a', strtotime($currentMedia['added_at'])); ?></span>
                                </div>
                                <div class="d-flex">
                                    <?php if ($currentMedia['user_id'] === $user['id']): ?>
                                        <!-- Privacy settings dropdown -->
                                        <form method="POST" id="updatePrivacyForm">
                                            <input type="hidden" name="media_id" value="<?php echo $currentMedia['id']; ?>">
                                            <div class="input-group">
                                                <select class="form-select form-select-sm" name="privacy" id="privacySelect" onchange="document.getElementById('updatePrivacyForm').submit();">
                                                    <option value="public" <?php echo ($currentMedia['privacy'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public</option>
                                                    <option value="friends" <?php echo ($currentMedia['privacy'] ?? 'public') === 'friends' ? 'selected' : ''; ?>>Friends Only</option>
                                                    <option value="private" <?php echo ($currentMedia['privacy'] ?? 'public') === 'private' ? 'selected' : ''; ?>>Private</option>
                                                </select>
                                                <button type="submit" name="update_privacy" class="btn btn-sm btn-outline-dark">Update</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-<?php echo ($currentMedia['privacy'] ?? 'public') === 'public' ? 'globe' : (($currentMedia['privacy'] ?? 'public') === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                            <?php echo ucfirst($currentMedia['privacy'] ?? 'public'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Reactions section -->
                        <div class="reactions-section mt-3">
                            <div class="d-flex">
                                <button class="btn btn-sm btn-outline-dark me-2 post-react-btn" data-post-id="<?php echo $currentMedia['id']; ?>" data-content-type="media">
                                    <i class="far fa-smile me-1"></i> React
                                </button>

                                <button class="btn btn-sm btn-outline-dark me-2">
                                    <i class="far fa-comment me-1"></i> Comment
                                </button>

                                <button class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-share me-1"></i> Share
                                </button>
                            </div>

                            <!-- Add a container for reaction summary -->
                            <div class="reaction-summary" data-media-id="<?php echo $currentMedia['id']; ?>" style="display: none; align-items: center; margin-top: 10px;"></div>
                        </div>

                        <div id="reactionsContainer" class="mt-2">
                            <!-- Reactions will be displayed here -->
                        </div>

                        <!-- Comments Section -->
                        <div class="comments-section mt-3">
                            <h6 class="mb-3">
                                <i class="fas fa-comments me-2"></i>Comments
                                <small class="text-muted ms-2" id="comment-count-<?php echo $currentMedia['id']; ?>">Loading...</small>
                            </h6>

                            <!-- Comments Container -->
                            <div class="comments-container mb-3"
                                 data-media-id="<?php echo $currentMedia['id']; ?>"
                                 style="max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.05); border-radius: 8px; padding: 15px;">
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-comments fa-2x mb-2"></i>
                                    <p>Loading comments...</p>
                                </div>
                            </div>

                            <!-- Comment Form -->
                            <form class="comment-form" data-media-id="<?php echo $currentMedia['id']; ?>">
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
            <?php else: ?>
                <!-- Album Overview -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <?php if (!empty($album['profile_picture'])): ?>
                                <?php
                                $profilePicPath = (strpos($album['profile_picture'], 'uploads/') === 0)
                                    ? $album['profile_picture']
                                    : 'uploads/profile_pics/' . $album['profile_picture'];
                                ?>
                                <img src="<?php echo htmlspecialchars($profilePicPath); ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile picture">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($displayAlbumName); ?></h5>
                                <small class="text-muted">
                                    By <?php echo htmlspecialchars($album['username']); ?> •
                                    <?php echo count($media); ?> item<?php echo count($media) != 1 ? 's' : ''; ?>
                                </small>
                            </div>
                        </div>

                        <div>
                            <?php if ($album['user_id'] === $user['id']): ?>
                                <a href="manage_albums.php" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-arrow-left"></i> Back to Albums
                                </a>
                            <?php else: ?>
                                <a href="user_albums.php?id=<?php echo $album['user_id']; ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-images"></i> View Albums
                                </a>
                                <a href="view_profile.php?id=<?php echo $album['user_id']; ?>" class="btn btn-sm btn-outline-dark ms-2">
                                    <i class="fas fa-arrow-left"></i> Back to Profile
                                </a>
                            <?php endif; ?>
                            <?php if ($album['user_id'] === $user['id'] && $albumId != 1): ?>
                                <a href="edit_album.php?id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-dark">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($album['description'])): ?>
                            <div class="mb-4">
                                <p><?php echo nl2br(htmlspecialchars($album['description'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($media)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-photo-video fa-3x mb-3 text-muted"></i>
                                <p class="text-muted">This album is empty.</p>
                                <?php if ($album['user_id'] === $user['id'] && $albumId != 1): ?>
                                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addMediaModal">
                                        <i class="fas fa-plus"></i> Add Media
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-3">
                                <?php foreach ($media as $index => $item): ?>
                                    <div class="col">
                                        <div class="card h-100 media-item" data-media-id="<?php echo $item['id']; ?>">
                                            <div class="position-relative">
                                                <?php if (strpos($item['media_type'] ?? '', 'image') !== false): ?>
                                                    <a href="view_album.php?id=<?php echo $albumId; ?>&media_id=<?php echo $item['id']; ?>" class="media-link">
                                                        <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="card-img-top" alt="Image" style="height: 200px; object-fit: cover;">
                                                    </a>
                                                <?php elseif (strpos($item['media_type'] ?? '', 'video') !== false): ?>
                                                    <a href="view_album.php?id=<?php echo $albumId; ?>&media_id=<?php echo $item['id']; ?>" class="media-link">
                                                        <?php if (!empty($item['thumbnail_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>" class="card-img-top" alt="Video thumbnail" style="height: 200px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="d-flex align-items-center justify-content-center bg-dark text-white" style="height: 200px;">
                                                                <i class="fas fa-video fa-3x"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="position-absolute top-50 start-50 translate-middle">
                                                            <i class="fas fa-play-circle fa-3x text-white"></i>
                                                        </div>
                                                    </a>
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                                        <i class="fas fa-file fa-3x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($album['user_id'] === $user['id'] && $albumId != 1): ?>
                                                    <div class="position-absolute top-0 end-0 p-2">
                                                        <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#removeMediaModal<?php echo $item['id']; ?>">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Privacy indicator -->
                                                <div class="position-absolute bottom-0 start-0 p-2">
                                                    <span class="badge bg-dark">
                                                        <i class="fas fa-<?php echo ($item['privacy'] ?? 'public') === 'public' ? 'globe' : (($item['privacy'] ?? 'public') === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-transparent">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Added: <?php echo date('M j, Y', strtotime($item['added_at'])); ?>
                                                    </small>
                                                    <button class="btn btn-sm btn-outline-dark post-react-btn" data-post-id="<?php echo $item['id']; ?>" data-content-type="media">
                                                        <i class="far fa-smile me-1"></i> React
                                                    </button>
                                                </div>
                                                <!-- Container for reactions summary -->
                                                <div id="reactions-container-<?php echo $item['id']; ?>" class="media-reactions-container mt-2"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($album['user_id'] === $user['id'] && $albumId != 1): ?>
                                        <!-- Remove Media Modal -->
                                        <div class="modal fade" id="removeMediaModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Remove Media</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to remove this media from the album?</p>
                                                        <p class="text-muted">This will only remove it from this album, not delete the media itself.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form method="POST">
                                                            <input type="hidden" name="media_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="remove_media" class="btn btn-dark">Remove</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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

    <!-- Add Media Modal -->
    <?php if ($album['user_id'] === $user['id'] && $albumId != 1): ?>
    <div class="modal fade" id="addMediaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Media to Album</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Select media from your library to add to this album:</p>
                    <div class="media-selector">
                        <p class="text-center text-muted">Loading your media...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-dark" id="addSelectedMedia">Add Selected</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Global flag to track reaction system initialization -->
    <script>
        window.reactionSystemInitialized = false;
    </script>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/utils.js"></script>

    <!-- Load only view-album-reactions.js which includes all needed functionality -->
    <script src="assets/js/view-album-reactions.js"></script>

    <!-- Disable other reaction-related scripts -->
    <!-- <script src="assets/js/simple-reactions.js"></script> -->
    <!-- <script src="assets/js/reactions-v2.js"></script> -->
    <!-- <script src="assets/js/reaction-integration.js"></script> -->
    <!-- <script src="assets/js/reaction-init.js"></script> -->

    <script>
    // Function to toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('.left-sidebar').classList.toggle('show');
    }

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Left arrow key
        if (e.key === 'ArrowLeft') {
            const prevLink = document.querySelector('.media-nav.prev');
            if (prevLink) prevLink.click();
        }
        // Right arrow key
        else if (e.key === 'ArrowRight') {
            const nextLink = document.querySelector('.media-nav.next');
            if (nextLink) nextLink.click();
        }
        // Escape key
        else if (e.key === 'Escape') {
            // If viewing a single media, go back to album
            <?php if ($currentMedia): ?>
            window.location.href = 'view_album.php?id=<?php echo $albumId; ?>';
            <?php endif; ?>
        }
    });

    <?php if ($album['user_id'] === $user['id'] && $albumId != 1): ?>
    // Load user media for the Add Media modal
    document.getElementById('addMediaModal').addEventListener('show.bs.modal', function () {
        const mediaSelector = document.querySelector('.media-selector');

        // Fetch user media that's not already in this album
        fetch('api/get_user_media.php?exclude_album=<?php echo $albumId; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.media.length > 0) {
                    let html = '<div class="row row-cols-2 row-cols-md-4 g-3">';

                    data.media.forEach(item => {
                        html += `
                            <div class="col">
                                <div class="card h-100 media-item">
                                    <div class="form-check position-absolute top-0 end-0 m-2">
                                        <input class="form-check-input media-checkbox" type="checkbox" value="${item.id}" id="media${item.id}">
                                    </div>
                                    ${item.media_type.includes('image')
                                        ? `<img src="${item.media_url}" class="card-img-top" alt="Image" style="height: 120px; object-fit: cover;">`
                                        : (item.media_type.includes('video')
                                            ? `<div class="position-relative">
                                                ${item.thumbnail_url
                                                    ? `<img src="${item.thumbnail_url}" class="card-img-top" alt="Video thumbnail" style="height: 120px; object-fit: cover;">`
                                                    : `<div class="d-flex align-items-center justify-content-center bg-dark text-white" style="height: 120px;"><i class="fas fa-video fa-2x"></i></div>`
                                                }
                                                <div class="position-absolute top-50 start-50 translate-middle">
                                                    <i class="fas fa-play-circle fa-2x text-white"></i>
                                                </div>
                                              </div>`
                                            : `<div class="d-flex align-items-center justify-content-center bg-light" style="height: 120px;"><i class="fas fa-file fa-2x text-muted"></i></div>`
                                        )
                                    }
                                    <div class="card-body p-2">
                                        <p class="card-text small text-truncate">${item.caption || 'No caption'}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += '</div>';
                    html += '<div class="mt-3 d-flex justify-content-end">';
                    html += '<button type="button" class="btn btn-primary" id="addSelectedMedia">Add Selected Media</button>';
                    html += '</div>';

                    mediaSelector.innerHTML = html;

                    // Add event listeners to checkboxes
                    document.querySelectorAll('.media-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', function() {
                            const mediaItem = this.closest('.media-item');
                            if (this.checked) {
                                mediaItem.classList.add('border-primary', 'border-2');
                            } else {
                                mediaItem.classList.remove('border-primary', 'border-2');
                            }
                        });
                    });

                    // Add event listener to media items for easier selection
                    document.querySelectorAll('.media-item').forEach(item => {
                        item.addEventListener('click', function(e) {
                            // Don't toggle if clicking on the checkbox directly
                            if (e.target.type !== 'checkbox') {
                                const checkbox = this.querySelector('.media-checkbox');
                                checkbox.checked = !checkbox.checked;

                                // Trigger change event
                                const event = new Event('change');
                                checkbox.dispatchEvent(event);
                            }
                        });
                    });
                } else {
                    mediaSelector.innerHTML = '<div class="alert alert-info">No media available to add. Upload some media first!</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mediaSelector.innerHTML = '<div class="alert alert-danger">Error loading media. Please try again.</div>';
            });
    });

    // Handle adding selected media to album
    document.getElementById('addSelectedMedia').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.media-selector input[type="checkbox"]:checked');
        const mediaIds = Array.from(checkboxes).map(cb => cb.value);

        if (mediaIds.length === 0) {
            alert('Please select at least one media item to add.');
            return;
        }

        // Send request to add media to album
        fetch('api/add_media_to_album.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                album_id: <?php echo $albumId; ?>,
                media_ids: mediaIds
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show updated album
                window.location.reload();
            } else {
                alert('Error adding media: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });
    <?php endif; ?>

    // Safe initialization function that handles errors
    function safeInitReactionSystem() {
      try {
        console.log('Checking SimpleReactionSystem');

        // Initialize SimpleReactionSystem if available and not already initialized
        if (window.SimpleReactionSystem && !window.reactionSystemInitialized) {
          console.log('Initializing SimpleReactionSystem');
          window.SimpleReactionSystem.init();

          // Load reactions for the current media
          const mediaId = <?php echo isset($currentMedia) ? $currentMedia['id'] : 'null'; ?>;
          if (mediaId) {
            console.log('Loading reactions for media ID:', mediaId);
            window.SimpleReactionSystem.loadReactions(mediaId);
          }
        } else if (window.reactionSystemInitialized) {
          console.log('SimpleReactionSystem already initialized');

          // Still load reactions for the current media
          const mediaId = <?php echo isset($currentMedia) ? $currentMedia['id'] : 'null'; ?>;
          if (mediaId) {
            console.log('Loading reactions for media ID:', mediaId);
            window.SimpleReactionSystem.loadReactions(mediaId);
          }
        }
      } catch (error) {
        console.error('Error initializing reaction system:', error);
      }
    }

    // Initialize comment system for view_album.php
    function initCommentSystem() {
      const mediaId = <?php echo isset($currentMedia) ? $currentMedia['id'] : 'null'; ?>;
      if (!mediaId) return;

      console.log('Initializing comment system for media:', mediaId);

      // Load existing comments
      loadComments(mediaId);

      // Set up comment form
      const commentForm = document.querySelector(`form[data-media-id="${mediaId}"]`);
      if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
          e.preventDefault();
          submitComment(mediaId);
        });
      }
    }

    // Load comments for media
    async function loadComments(mediaId) {
      try {
        const response = await fetch(`api/get_media_comments.php?media_id=${mediaId}`);
        if (!response.ok) {
          throw new Error('Failed to load comments');
        }

        const data = await response.json();
        if (data.success) {
          displayComments(mediaId, data.comments);
        } else {
          console.error('Error loading comments:', data.error);
        }
      } catch (error) {
        console.error('Error loading comments:', error);
      }
    }

    // Display comments
    function displayComments(mediaId, comments) {
      const commentsContainer = document.querySelector(`[data-media-id="${mediaId}"].comments-container`);
      const countDisplay = document.getElementById(`comment-count-${mediaId}`);

      if (!commentsContainer) return;

      // Update comment count
      if (countDisplay) {
        countDisplay.textContent = `(${comments.length})`;
      }

      if (comments.length === 0) {
        commentsContainer.innerHTML = `
          <div class="text-center text-muted py-4">
            <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
            <p class="mb-0">No comments yet.</p>
            <small>Be the first to share your thoughts!</small>
          </div>
        `;
        return;
      }

      let commentsHTML = '';
      comments.forEach(comment => {
        const timeAgo = formatTimeAgo(comment.created_at);

        commentsHTML += `
          <div class="comment mb-3 p-3 rounded" data-comment-id="${comment.id}"
               style="background: rgba(0,0,0,0.05); border-left: 3px solid #007bff;">
            <div class="d-flex">
              <a href="view_profile.php?id=${comment.author_id}" class="text-decoration-none">
                <img src="${comment.profile_pic}" alt="${comment.author}"
                     class="rounded-circle me-3"
                     style="width: 40px; height: 40px; object-fit: cover; cursor: pointer;"
                     title="View ${comment.author}'s profile">
              </a>
              <div class="comment-content flex-grow-1">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <a href="view_profile.php?id=${comment.author_id}" class="text-decoration-none">
                      <strong class="d-block" style="cursor: pointer; color: #2c3e50;" title="View ${comment.author}'s profile">${comment.author}</strong>
                    </a>
                    <small class="text-muted">
                      <i class="fas fa-clock me-1"></i>${timeAgo}
                    </small>
                  </div>
                  ${comment.is_own_comment ?
                    `<button class="btn btn-sm btn-outline-danger delete-comment-btn"
                             data-comment-id="${comment.id}"
                             data-media-id="${mediaId}"
                             title="Delete comment">
                        <i class="fas fa-trash-alt"></i>
                     </button>` :
                    ''
                  }
                </div>
                <p class="mb-0" style="line-height: 1.4;">${comment.content}</p>
              </div>
            </div>
          </div>
        `;
      });

      commentsContainer.innerHTML = commentsHTML;

      // Set up delete comment handlers
      commentsContainer.querySelectorAll('.delete-comment-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const commentId = this.getAttribute('data-comment-id');
          const mediaId = this.getAttribute('data-media-id');
          deleteComment(commentId, mediaId);
        });
      });
    }

    // Submit comment
    async function submitComment(mediaId) {
      const commentForm = document.querySelector(`form[data-media-id="${mediaId}"]`);
      if (!commentForm) return;

      const commentInput = commentForm.querySelector('.comment-input');
      if (!commentInput) return;

      const content = commentInput.value.trim();
      if (!content) return;

      const submitButton = commentForm.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Posting...';
      }

      try {
        const formData = new FormData();
        formData.append('media_id', mediaId);
        formData.append('content', content);

        const response = await fetch('api/post_media_comment.php', {
          method: 'POST',
          body: formData
        });

        if (!response.ok) {
          throw new Error('Failed to post comment');
        }

        const data = await response.json();
        if (data.success) {
          commentInput.value = '';
          // Reload comments to show the new comment
          await loadComments(mediaId);

          // Show brief success feedback
          if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-check me-1"></i> Posted!';
            setTimeout(() => {
              submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Post';
            }, 2000);
          }
        } else {
          console.error('Error posting comment:', data.error);
          alert('Error posting comment: ' + (data.error || 'Unknown error'));
        }
      } catch (error) {
        console.error('Error posting comment:', error);
        alert('An error occurred while posting your comment: ' + error.message);
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          if (!submitButton.innerHTML.includes('Posted!')) {
            submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Post';
          }
        }
      }
    }

    // Delete comment
    async function deleteComment(commentId, mediaId) {
      if (!confirm('Are you sure you want to delete this comment?')) {
        return;
      }

      try {
        const response = await fetch('api/delete_media_comment.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `comment_id=${commentId}`
        });

        if (!response.ok) {
          throw new Error('Failed to delete comment');
        }

        const data = await response.json();
        if (data.success) {
          // Reload comments to reflect the deletion
          await loadComments(mediaId);
        } else {
          console.error('Error deleting comment:', data.error);
          alert('Error deleting comment: ' + data.error);
        }
      } catch (error) {
        console.error('Error deleting comment:', error);
        alert('An error occurred while deleting your comment.');
      }
    }

    // Utility function to format time ago
    function formatTimeAgo(dateString) {
      const now = new Date();
      const date = new Date(dateString);
      const diffInSeconds = Math.floor((now - date) / 1000);

      if (diffInSeconds < 60) return 'Just now';
      if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
      if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
      if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' days ago';

      return date.toLocaleDateString();
    }

    // Initialize reaction system when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded for view_album.php');

      // Remove any existing reaction pickers to prevent duplicates
      document.querySelectorAll('.reaction-picker:not(#simple-reaction-picker)').forEach(picker => {
        console.log('Removing duplicate reaction picker:', picker);
        picker.remove();
      });

      // Safely initialize the reaction system
      safeInitReactionSystem();

      // Initialize comment system
      initCommentSystem();
    });

    // Add window resize handler to reposition picker if needed
    window.addEventListener('resize', function() {
        const picker = document.getElementById('simple-reaction-picker');
        if (picker && picker.style.display !== 'none') {
            const postId = picker.getAttribute('data-post-id');
            const button = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
            if (button && window.SimpleReactionSystem) {
                window.SimpleReactionSystem.showReactionPicker(postId, button);
            }
        }
    });

    // Add scroll handler to reposition picker if needed
    window.addEventListener('scroll', function() {
        const picker = document.getElementById('simple-reaction-picker');
        if (picker && picker.style.display !== 'none') {
            const postId = picker.getAttribute('data-post-id');
            const button = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
            if (button && window.SimpleReactionSystem) {
                window.SimpleReactionSystem.showReactionPicker(postId, button);
            }
        }
    });
    </script>

    <!-- Remove any debug buttons that might be added directly in HTML -->
    <script>
    // Enhanced debug button removal script
    document.addEventListener('DOMContentLoaded', function() {
        // Function to remove debug buttons
        function removeDebugButtons() {
            // More comprehensive selector to catch all debug buttons
            const debugButtons = document.querySelectorAll(
                '.debug-button, #debug-reactions-btn, [id*="debug"], [class*="debug"], ' +
                'button[style*="position: fixed"][style*="bottom: 10px"][style*="right: 10px"], ' +
                'button[style*="background-color: #f44336"]'
            );

            debugButtons.forEach(button => {
                if (button.textContent &&
                    (button.textContent.toLowerCase().includes('debug') ||
                    button.getAttribute('id')?.toLowerCase().includes('debug') ||
                    button.getAttribute('class')?.toLowerCase().includes('debug'))) {
                    console.log('Removing debug button:', button);
                    button.remove();
                }
            });
        }

        // Override any methods that might add debug buttons
        if (window.SimpleReactionSystem) {
            window.SimpleReactionSystem.addDebugButton = function() {
                console.log('Debug button creation prevented');
                return;
            };
        }

        // Run immediately and after delays
        removeDebugButtons();
        setTimeout(removeDebugButtons, 500);
        setTimeout(removeDebugButtons, 1000);
    });
    </script>
</body>
</html>
