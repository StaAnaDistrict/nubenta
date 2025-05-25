<?php
// Enable error reporting during development
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

// Get album ID from URL
$albumId = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
            SELECT m.*, am.created_at as added_at
            FROM user_media m
            JOIN album_media am ON m.id = am.media_id
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
                SELECT m.*, am.created_at as added_at
                FROM user_media m
                JOIN album_media am ON m.id = am.media_id
                WHERE am.album_id = ? AND (m.privacy = 'public' OR m.privacy = 'friends')
                ORDER BY am.created_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT m.*, am.created_at as added_at
                FROM user_media m
                JOIN album_media am ON m.id = am.media_id
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
            header("Location: view_album.php?id=" . $albumId . "&success=Media removed from album");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Failed to remove media: " . $e->getMessage();
    }
}

// Page title
$pageTitle = htmlspecialchars($album['album_name']);
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
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($album['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($album['profile_picture']); ?>" class="rounded-circle me-2" width="40" height="40" alt="Profile picture">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($album['album_name']); ?></h5>
                            <small class="text-muted">
                                By <?php echo htmlspecialchars($album['username']); ?> · 
                                <i class="fas fa-<?php echo $album['privacy'] === 'public' ? 'globe' : ($album['privacy'] === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                <?php echo ucfirst($album['privacy']); ?>
                            </small>
                        </div>
                    </div>
                    
                    <div>
                        <a href="manage_albums.php" class="btn btn-sm btn-outline-dark">
                            <i class="fas fa-arrow-left"></i> Back to Albums
                        </a>
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
                            <?php foreach ($media as $item): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <div class="position-relative">
                                            <?php if (strpos($item['media_type'] ?? '', 'image') !== false): ?>
                                                <a href="<?php echo htmlspecialchars($item['media_url']); ?>" target="_blank">
                                                    <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="card-img-top" alt="Image" style="height: 200px; object-fit: cover;">
                                                </a>
                                            <?php elseif (strpos($item['media_type'] ?? '', 'video') !== false): ?>
                                                <a href="<?php echo htmlspecialchars($item['media_url']); ?>" target="_blank">
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
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <small class="text-muted">
                                                Added: <?php echo date('M j, Y', strtotime($item['added_at'])); ?>
                                            </small>
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
    </script>
</body>
</html>
