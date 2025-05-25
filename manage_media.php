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

// Handle media deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {
    $mediaId = intval($_POST['media_id']);
    $success = $mediaUploader->deleteMedia($mediaId, $user['id']);
    
    if ($success) {
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Media deleted successfully'
        ];
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Failed to delete media'
        ];
    }
    
    // Redirect to avoid form resubmission
    header("Location: manage_media.php");
    exit();
}

// Get media type filter
$mediaType = isset($_GET['type']) ? $_GET['type'] : null;
$validTypes = ['image', 'video', 'audio'];
if ($mediaType && !in_array($mediaType, $validTypes)) {
    $mediaType = null;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get user media
$media = $mediaUploader->getUserMediaByType($user['id'], $mediaType, $limit, $offset);
$totalMedia = $mediaUploader->getUserMediaCount($user['id'], $mediaType);
$totalPages = ceil($totalMedia / $limit);

// Page title
$pageTitle = "Manage Media";
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
    <script>
        // Set global variables for JavaScript modules
        window.isAdmin = <?php echo (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') ? 'true' : 'false'; ?>;
    </script>
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
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Manage Your Media</h5>
                    <div class="btn-group">
                        <a href="manage_media.php" class="btn btn-sm <?php echo !$mediaType ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                        <a href="manage_media.php?type=image" class="btn btn-sm <?php echo $mediaType === 'image' ? 'btn-primary' : 'btn-outline-primary'; ?>">Images</a>
                        <a href="manage_media.php?type=video" class="btn btn-sm <?php echo $mediaType === 'video' ? 'btn-primary' : 'btn-outline-primary'; ?>">Videos</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['flash_message']['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (empty($media)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-photo-video fa-3x mb-3"></i>
                            <p>No media found. Start sharing photos and videos in your posts!</p>
                            <a href="dashboardv2.php" class="btn btn-primary">Go to Dashboard</a>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($media as $item): ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="card h-100">
                                        <div class="position-relative">
                                            <?php if ($item['media_type'] === 'image'): ?>
                                                <a href="view_media.php?id=<?php echo $item['id']; ?>">
                                                    <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="card-img-top" alt="Media" style="height: 160px; object-fit: cover;">
                                                </a>
                                            <?php elseif ($item['media_type'] === 'video'): ?>
                                                <a href="view_media.php?id=<?php echo $item['id']; ?>">
                                                    <div class="position-relative">
                                                        <?php 
                                                        // If no thumbnail exists, try to generate one now
                                                        if (empty($item['thumbnail_url']) || !file_exists($item['thumbnail_url'])) {
                                                            if (file_exists($item['media_url'])) {
                                                                $item['thumbnail_url'] = $mediaUploader->generateVideoThumbnail($item['media_url']);
                                                                
                                                                // If thumbnail was generated, update the database
                                                                if ($item['thumbnail_url']) {
                                                                    $updateStmt = $pdo->prepare("UPDATE user_media SET thumbnail_url = ? WHERE id = ?");
                                                                    $updateStmt->execute([$item['thumbnail_url'], $item['id']]);
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Determine what to display
                                                        $thumbnailSrc = '';
                                                        if (!empty($item['thumbnail_url']) && file_exists($item['thumbnail_url'])) {
                                                            $thumbnailSrc = htmlspecialchars($item['thumbnail_url']);
                                                        } else {
                                                            $thumbnailSrc = 'https://via.placeholder.com/320x180/000000/FFFFFF?text=Video';
                                                        }
                                                        ?>
                                                        <img src="<?php echo $thumbnailSrc; ?>" 
                                                             class="card-img-top" alt="Video thumbnail" style="height: 160px; object-fit: cover;">
                                                        <div class="position-absolute top-50 start-50 translate-middle">
                                                            <i class="fas fa-play-circle fa-2x text-white"></i>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Media actions overlay -->
                                            <div class="position-absolute top-0 end-0 p-2">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-dark bg-opacity-75" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="view_media.php?id=<?php echo $item['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i> View
                                                            </a>
                                                        </li>
                                                        <?php if ($item['post_id']): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="view_post.php?id=<?php echo $item['post_id']; ?>">
                                                                    <i class="fas fa-link me-2"></i> View Post
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this media?');">
                                                                <input type="hidden" name="media_id" value="<?php echo $item['id']; ?>">
                                                                <button type="submit" name="delete_media" class="dropdown-item text-danger">
                                                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer small text-muted">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><?php echo date('M d, Y', strtotime($item['created_at'])); ?></span>
                                                <span>
                                                    <?php if ($item['media_type'] === 'image'): ?>
                                                        <i class="fas fa-image"></i>
                                                    <?php elseif ($item['media_type'] === 'video'): ?>
                                                        <i class="fas fa-video"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Media pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $mediaType ? '&type=' . $mediaType : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $mediaType ? '&type=' . $mediaType : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $mediaType ? '&type=' . $mediaType : ''; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
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

    <!-- Media Viewer Modal -->
    <div class="modal fade" id="mediaViewerModal" tabindex="-1" aria-labelledby="mediaViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mediaViewerModalLabel">Media Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center" id="mediaViewerContent">
                    <!-- Media content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize media viewer
        const mediaItems = document.querySelectorAll('[data-media-type]');
        const mediaViewerModal = new bootstrap.Modal(document.getElementById('mediaViewerModal'));
        const mediaViewerContent = document.getElementById('mediaViewerContent');
        
        mediaItems.forEach(item => {
            item.addEventListener('click', function() {
                const mediaType = this.getAttribute('data-media-type');
                const mediaUrl = this.getAttribute('data-media-url');
                
                mediaViewerContent.innerHTML = '';
                
                if (mediaType === 'image') {
                    const img = document.createElement('img');
                    img.src = mediaUrl;
                    img.className = 'img-fluid';
                    mediaViewerContent.appendChild(img);
                } else if (mediaType === 'video') {
                    const video = document.createElement('video');
                    video.controls = true;
                    video.autoplay = true;
                    video.className = 'w-100';
                    
                    const source = document.createElement('source');
                    source.src = mediaUrl;
                    source.type = 'video/mp4';
                    
                    video.appendChild(source);
                    mediaViewerContent.appendChild(video);
                }
                
                mediaViewerModal.show();
            });
        });
    });

    // Function to toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('.left-sidebar').classList.toggle('show');
    }
    </script>
</body>
</html>
