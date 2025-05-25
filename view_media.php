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

// Get media details
$media = null;
if ($mediaId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM user_media WHERE id = ?");
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If media not found or doesn't belong to user, redirect
    if (!$media || ($media['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Media not found or you do not have permission to view it'
        ];
        header("Location: manage_media.php");
        exit();
    }
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
                            <?php echo $media['media_type'] === 'image' ? 'Image' : 'Video'; ?> Viewer
                        </h5>
                        <div>
                            <a href="manage_media.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-th me-1"></i> Back to Gallery
                            </a>
                            <?php if ($media['post_id']): ?>
                                <a href="view_post.php?id=<?php echo $media['post_id']; ?>" class="btn btn-sm btn-outline-primary ms-2">
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
                                    <source src="<?php echo htmlspecialchars($media['media_url']); ?>" type="video/mp4">
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
                                <div>
                                    <form method="POST" action="manage_media.php" onsubmit="return confirm('Are you sure you want to delete this media?');" class="d-inline">
                                        <input type="hidden" name="media_id" value="<?php echo $media['id']; ?>">
                                        <button type="submit" name="delete_media" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                        </button>
                                    </form>
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
                        <a href="manage_media.php" class="btn btn-primary mt-3">Back to Media Gallery</a>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h4>📢 Ads</h4>
                <p>(Coming Soon)</p>
            </div>
            <div class="sidebar-section">
                <h4>🕑 Activity Feed</h4>
                <p>(Coming Soon)</p>
            </div>
            <div class="sidebar-section">
                <h4>🟢 Online Friends</h4>
                <p>(Coming Soon)</p>
            </div>
        </aside>
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