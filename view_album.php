<?php
require_once 'db.php';
require_once 'includes/auth_check.php';
require_once 'includes/functions.php';

// Get album ID from URL
$albumId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($albumId <= 0) {
    header("Location: media_albums.php");
    exit();
}

// Get album details
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.profile_picture 
    FROM user_media_albums a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$albumId]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) {
    header("Location: media_albums.php");
    exit();
}

// Check if user has access to this album
$canView = false;
if ($album['privacy'] === 'public') {
    $canView = true;
} elseif ($album['privacy'] === 'friends' && isFriend($pdo, $user['id'], $album['user_id'])) {
    $canView = true;
} elseif ($album['user_id'] === $user['id']) {
    $canView = true;
}

if (!$canView) {
    header("Location: media_albums.php?error=You don't have permission to view this album");
    exit();
}

// Get album media
$stmt = $pdo->prepare("
    SELECT m.*, am.created_at as added_at
    FROM user_media m
    JOIN album_media am ON m.id = am.media_id
    WHERE am.album_id = ?
    ORDER BY am.display_order, am.created_at DESC
");
$stmt->execute([$albumId]);
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's media for adding to album (if owner)
if ($album['user_id'] === $user['id']) {
    $stmt = $pdo->prepare("
        SELECT m.*
        FROM user_media m
        WHERE m.user_id = ?
        AND m.id NOT IN (
            SELECT media_id FROM album_media WHERE album_id = ?
        )
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id'], $albumId]);
    $userMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle adding media to album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_media']) && $album['user_id'] === $user['id']) {
    $selectedMediaIds = isset($_POST['selected_media_ids']) ? explode(',', $_POST['selected_media_ids']) : [];
    
    if (!empty($selectedMediaIds)) {
        $stmt = $pdo->prepare("
            INSERT INTO album_media 
            (album_id, media_id, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        foreach ($selectedMediaIds as $mediaId) {
            $stmt->execute([$albumId, $mediaId]);
        }
        
        // If album has no cover image, set the first media as cover
        if (empty($album['cover_image_id'])) {
            $updateStmt = $pdo->prepare("
                UPDATE user_media_albums 
                SET cover_image_id = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$selectedMediaIds[0], $albumId]);
        }
        
        // Redirect to refresh page
        header("Location: view_album.php?id=$albumId&success=Media added to album");
        exit();
    }
}

// Page title
$pageTitle = htmlspecialchars($album['album_name']);
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Left Sidebar -->
        <div class="col-lg-3">
            <?php include 'includes/sidebar_left.php'; ?>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($album['album_name']); ?></h5>
                            <div class="small text-muted mb-0">
                                By <?php echo htmlspecialchars($album['username']); ?> · 
                                <?php echo count($media); ?> items · 
                                Created <?php echo date('M d, Y', strtotime($album['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($album['user_id'] === $user['id']): ?>
                            <div class="btn-group">
                                <a href="edit_album.php?id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit me-1"></i> Edit Album
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMediaModal">
                                    <i class="fas fa-plus me-1"></i> Add Media
                                </button>
                            </div>
                        <?php endif; ?>
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
                    
                    <?php if (!empty($album['description'])): ?>
                        <div class="mb-4">
                            <p><?php echo nl2br(htmlspecialchars($album['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($media)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="fas fa-photo-video fa-3x mb-3"></i>
                            <p>This album is empty. Add some media to get started!</p>
                            <?php if ($album['user_id'] === $user['id']): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMediaModal">
                                    <i class="fas fa-plus me-1"></i> Add Media
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="row g-3 media-gallery">
                            <?php foreach ($media as $item): ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="card h-100 media-item" data-media-id="<?php echo $item['id']; ?>">
                                        <div class="position-relative">
                                            <?php if ($item['media_type'] === 'image'): ?>
                                                <a href="<?php echo htmlspecialchars($item['media_url']); ?>" data-lightbox="album-<?php echo $albumId; ?>" data-title="<?php echo htmlspecialchars($item['caption'] ?? ''); ?>">
                                                    <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="card-img-top" alt="Media" style="height: 160px; object-fit: cover;">
                                                </a>
                                            <?php elseif ($item['media_type'] === 'video'): ?>
                                                <a href="#" class="video-preview" data-bs-toggle="modal" data-bs-target="#videoModal" data-video-url="<?php echo htmlspecialchars($item['media_url']); ?>">
                                                    <img src="<?php echo htmlspecialchars($item['thumbnail_url'] ?? 'assets/images/video_thumbnail.jpg'); ?>" class="card-img-top" alt="Video thumbnail" style="height: 160px; object-fit: cover;">
                                                    <div class="position-absolute top-50 start-50 translate-middle">
                                                        <i class="fas fa-play-circle fa-2x text-white"></i>
                                                    </div>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($album['user_id'] === $user['id']): ?>
                                                <div class="position-absolute top-0 end-0 p-2">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-dark bg-opacity-75" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <form method="POST" onsubmit="return confirm('Are you sure you want to remove this media from the album?');">
                                                                    <input type="hidden" name="media_id" value="<?php echo $item['id']; ?>">
                                                                    <button type="submit" name="remove_media" class="dropdown-item text-danger">
                                                                        <i class="fas fa-trash-alt me-2"></i> Remove from Album
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Video Modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel">Media Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <video id="videoPlayer" controls class="w-100" style="max-height: 70vh;">
                    <source src="" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
    </div>
</div>

<!-- Add Media Modal -->
<?php if ($album['user_id'] === $user['id'] && isset($userMedia)): ?>
<div class="modal fade" id="addMediaModal" tabindex="-1" aria-labelledby="addMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMediaModalLabel">Add Media to Album</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Media to Add</label>
                        <div class="row g-2 media-selection-container" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($userMedia)): ?>
                                <div class="col-12 text-center p-3 text-muted">
                                    <p>You don't have any media yet. Start sharing photos and videos in your posts!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($userMedia as $item): ?>
                                    <div class="col-4 col-md-3">
                                        <div class="card h-100 media-item" data-media-id="<?php echo $item['id']; ?>">
                                            <div class="position-relative">
                                                <?php if ($item['media_type'] === 'image'): ?>
                                                    <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="card-img-top" alt="Media" style="height: 100px; object-fit: cover;">
                                                <?php elseif ($item['media_type'] === 'video'): ?>
                                                    <div class="position-relative">
                                                        <img src="<?php echo htmlspecialchars($item['thumbnail_url'] ?? 'assets/images/video_thumbnail.jpg'); ?>" class="card-img-top" alt="Video thumbnail" style="height: 100px; object-fit: cover;">
                                                        <div class="position-absolute top-50 start-50 translate-middle">
                                                            <i class="fas fa-play-circle text-white"></i>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Selection overlay -->
                                                <div class="position-absolute top-0 end-0 p-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input media-checkbox" type="checkbox" value="<?php echo $item['id']; ?>" id="media_<?php echo $item['id']; ?>" data-media-id="<?php echo $item['id']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="media_ids" id="selected_media_ids" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_media" class="btn btn-primary">Add to Album</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle media selection for adding to album
    const mediaCheckboxes = document.querySelectorAll('.media-checkbox');
    const selectedMediaIdsInput = document.getElementById('selected_media_ids');
    const mediaItems = document.querySelectorAll('.media-item');
    
    // Update selected media IDs when checkboxes change
    function updateSelectedMediaIds() {
        const selectedIds = Array.from(mediaCheckboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
        
        selectedMediaIdsInput.value = selectedIds.join(',');
    }
    
    mediaCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedMediaIds);
    });
    
    // Handle video preview
    const videoLinks = document.querySelectorAll('.video-preview');
    const videoPlayer = document.getElementById('videoPlayer');
    
    videoLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const videoUrl = this.getAttribute('data-video-url');
            videoPlayer.querySelector('source').src = videoUrl;
            videoPlayer.load();
        });
    });
    
    // Reset video when modal is closed
    const videoModal = document.getElementById('videoModal');
    if (videoModal) {
        videoModal.addEventListener('hidden.bs.modal', function() {
            videoPlayer.pause();
            videoPlayer.querySelector('source').src = '';
            videoPlayer.load();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
