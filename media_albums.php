<?php
require_once 'db.php';
require_once 'includes/auth_check.php';
require_once 'includes/functions.php';

// Get user albums
$stmt = $pdo->prepare("
    SELECT a.*, 
           COUNT(DISTINCT am.media_id) AS media_count,
           m.media_url AS cover_image
    FROM user_media_albums a
    LEFT JOIN album_media am ON a.id = am.album_id
    LEFT JOIN user_media m ON a.cover_image_id = m.id
    WHERE a.user_id = ?
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute([$user['id']]);
$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent media for creating new albums
$stmt = $pdo->prepare("
    SELECT * FROM user_media 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$user['id']]);
$userMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle album creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_album'])) {
    $albumName = trim($_POST['album_name']);
    $description = trim($_POST['description']);
    $privacy = $_POST['privacy'];
    $selectedMediaIds = isset($_POST['selected_media_ids']) ? explode(',', $_POST['selected_media_ids']) : [];
    
    if (empty($albumName)) {
        $error = "Album name is required";
    } else {
        // Create new album
        $stmt = $pdo->prepare("
            INSERT INTO user_media_albums 
            (user_id, album_name, description, privacy, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $albumName, $description, $privacy]);
        $albumId = $pdo->lastInsertId();
        
        // Add selected media to album
        if (!empty($selectedMediaIds)) {
            $stmt = $pdo->prepare("
                INSERT INTO album_media 
                (album_id, media_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            foreach ($selectedMediaIds as $mediaId) {
                $stmt->execute([$albumId, $mediaId]);
            }
            
            // Set first media as cover image
            $updateStmt = $pdo->prepare("
                UPDATE user_media_albums 
                SET cover_image_id = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$selectedMediaIds[0], $albumId]);
        }
        
        // Redirect to refresh page
        header("Location: media_albums.php?success=Album created successfully");
        exit();
    }
}

// Page title
$pageTitle = "My Albums";
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Albums</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                        <i class="fas fa-plus me-1"></i> Create Album
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_GET['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($albums)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="fas fa-photo-album fa-3x mb-3"></i>
                            <p>You don't have any albums yet. Create your first album to organize your photos and videos!</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                                <i class="fas fa-plus me-1"></i> Create Album
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-3 g-3 album-gallery">
                            <?php foreach ($albums as $album): ?>
                                <div class="col">
                                    <div class="card h-100 album-card">
                                        <a href="view_album.php?id=<?php echo $album['id']; ?>" class="text-decoration-none">
                                            <div class="album-cover">
                                                <?php if (!empty($album['cover_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($album['cover_image']); ?>" class="card-img-top" alt="Album cover">
                                                <?php else: ?>
                                                    <div class="album-placeholder">
                                                        <i class="fas fa-images"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="album-overlay">
                                                    <span class="badge bg-dark">
                                                        <i class="fas fa-image me-1"></i> <?php echo $album['media_count']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($album['album_name']); ?></h6>
                                                <p class="card-text small text-muted">
                                                    <?php echo !empty($album['description']) ? htmlspecialchars(substr($album['description'], 0, 50)) . (strlen($album['description']) > 50 ? '...' : '') : 'No description'; ?>
                                                </p>
                                            </div>
                                        </a>
                                        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-<?php echo $album['privacy'] === 'public' ? 'globe' : ($album['privacy'] === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                                <?php echo ucfirst($album['privacy']); ?>
                                            </small>
                                            <div class="btn-group">
                                                <a href="edit_album.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="view_album.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Sidebar -->
        <div class="col-lg-3">
            <?php include 'includes/sidebar_right.php'; ?>
        </div>
    </div>
</div>

<!-- Create Album Modal -->
<div class="modal fade" id="createAlbumModal" tabindex="-1" aria-labelledby="createAlbumModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createAlbumModalLabel">Create New Album</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="album_name" class="form-label">Album Name</label>
                        <input type="text" class="form-control" id="album_name" name="album_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="privacy" class="form-label">Privacy</label>
                        <select class="form-select" id="privacy" name="privacy">
                            <option value="public">Public</option>
                            <option value="friends">Friends Only</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Media for Album</label>
                        <div class="row g-2 media-selection-container" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($userMedia)): ?>
                                <div class="col-12 text-center p-3 text-muted">
                                    <p>You don't have any media yet. Start sharing photos and videos in your posts!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($userMedia as $media): ?>
                                    <div class="col-4 col-md-3">
                                        <div class="card h-100 media-item" data-media-id="<?php echo $media['id']; ?>">
                                            <div class="position-relative">
                                                <?php if ($media['media_type'] === 'image'): ?>
                                                    <img src="<?php echo htmlspecialchars($media['media_url']); ?>" class="card-img-top" alt="Media" style="height: 100px; object-fit: cover;">
                                                <?php elseif ($media['media_type'] === 'video'): ?>
                                                    <div class="position-relative">
                                                        <img src="<?php echo htmlspecialchars($media['thumbnail_url'] ?? 'assets/images/video_thumbnail.jpg'); ?>" class="card-img-top" alt="Video thumbnail" style="height: 100px; object-fit: cover;">
                                                        <div class="position-absolute top-50 start-50 translate-middle text-white">
                                                            <i class="fas fa-play-circle fa-2x"></i>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="position-absolute top-0 end-0 p-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input media-checkbox" type="checkbox" value="<?php echo $media['id']; ?>" data-media-id="<?php echo $media['id']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="selected_media_ids" id="selected_media_ids" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_album" class="btn btn-primary">Create Album</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.album-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 10px;
    overflow: hidden;
}

.album-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.album-cover {
    position: relative;
    height: 160px;
    overflow: hidden;
    background-color: #f8f9fa;
}

.album-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.album-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #adb5bd;
}

.album-placeholder i {
    font-size: 3rem;
}

.album-overlay {
    position: absolute;
    bottom: 10px;
    right: 10px;
}

.media-item {
    cursor: pointer;
    transition: border 0.2s ease;
}

.media-item:hover {
    border-color: #0d6efd;
}

.media-item.selected {
    border: 2px solid #0d6efd;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle media selection for creating albums
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
        checkbox.addEventListener('change', function() {
            updateSelectedMediaIds();
            
            // Toggle selected class on parent card
            const mediaItem = this.closest('.media-item');
            if (this.checked) {
                mediaItem.classList.add('border-primary', 'border-2');
            } else {
                mediaItem.classList.remove('border-primary', 'border-2');
            }
        });
    });
    
    // Add click handler to media items
    mediaItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't toggle if the checkbox itself was clicked
            if (e.target.type === 'checkbox') return;
            
            const mediaId = this.getAttribute('data-media-id');
            const checkbox = document.querySelector(`.media-checkbox[data-media-id="${mediaId}"]`);
            
            // Toggle checkbox
            checkbox.checked = !checkbox.checked;
            
            // Update selected IDs
            updateSelectedMediaIds();
            
            // Toggle selected class
            this.classList.toggle('border-primary', checkbox.checked);
            this.classList.toggle('border-2', checkbox.checked);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>