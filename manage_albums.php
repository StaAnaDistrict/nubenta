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

// Check if MediaUploader class exists and include it if needed
if (!class_exists('MediaUploader') && file_exists('includes/MediaUploader.php')) {
    require_once 'includes/MediaUploader.php';
}

// Initialize MediaUploader if available
$mediaUploader = null;
if (class_exists('MediaUploader')) {
    $mediaUploader = new MediaUploader($pdo);
}

// Handle album creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_album'])) {
    $albumName = trim($_POST['album_name']);
    $description = trim($_POST['description'] ?? '');
    $privacy = $_POST['privacy'] ?? 'public';
    
    if (empty($albumName)) {
        $error = "Album name is required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_media_albums 
                (user_id, album_name, description, privacy, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $albumName, $description, $privacy]);
            $albumId = $pdo->lastInsertId();
            
            // Add selected media to album if any
            if (isset($_POST['media_ids']) && !empty($_POST['media_ids'])) {
                $mediaIds = explode(',', $_POST['media_ids']);
                
                $stmt = $pdo->prepare("
                    INSERT INTO album_media 
                    (album_id, media_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                
                foreach ($mediaIds as $mediaId) {
                    $stmt->execute([$albumId, $mediaId]);
                }
                
                // Set first media as cover image
                $stmt = $pdo->prepare("
                    UPDATE user_media_albums 
                    SET cover_image_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$mediaIds[0], $albumId]);
            }
            
            $success = "Album created successfully";
            header("Location: view_album.php?id=" . $albumId);
            exit();
        } catch (PDOException $e) {
            $error = "Failed to create album: " . $e->getMessage();
        }
    }
}

// Handle album deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_album'])) {
    $albumId = intval($_POST['album_id']);
    
    try {
        // First check if the album belongs to the user
        $stmt = $pdo->prepare("SELECT id FROM user_media_albums WHERE id = ? AND user_id = ?");
        $stmt->execute([$albumId, $user['id']]);
        
        if ($stmt->fetch()) {
            // Delete album media associations
            $stmt = $pdo->prepare("DELETE FROM album_media WHERE album_id = ?");
            $stmt->execute([$albumId]);
            
            // Delete the album
            $stmt = $pdo->prepare("DELETE FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $user['id']]);
            
            $success = "Album deleted successfully";
        } else {
            $error = "Album not found or you don't have permission to delete it";
        }
    } catch (PDOException $e) {
        $error = "Failed to delete album: " . $e->getMessage();
    }
}

// Get user albums
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               (SELECT COUNT(*) FROM album_media WHERE album_id = a.id) AS media_count,
               m.media_url AS cover_image_url
        FROM user_media_albums a
        LEFT JOIN user_media m ON a.cover_image_id = m.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading albums: " . $e->getMessage();
    $albums = [];
}

// Get user media for creating a new album
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_media 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    $userMedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $userMedia = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Albums - Nubenta</title>
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
            $currentPage = 'manage_albums';
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>My Albums</h3>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                    <i class="fas fa-plus"></i> Create Album
                </button>
            </div>
            
            <?php if (empty($albums)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-photo-album fa-3x mb-3 text-muted"></i>
                    <p class="text-muted">You don't have any albums yet.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                        Create Your First Album
                    </button>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php foreach ($albums as $album): ?>
                        <div class="col">
                            <div class="card h-100">
                                <div class="position-relative" style="height: 150px; background-color: #f8f9fa;">
                                    <?php if (!empty($album['cover_image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($album['cover_image_url']); ?>" class="card-img-top" alt="Album cover" style="height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100">
                                            <i class="fas fa-images fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="position-absolute bottom-0 end-0 p-2">
                                        <span class="badge bg-dark">
                                            <i class="fas fa-image"></i> <?php echo $album['media_count']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($album['album_name']); ?></h5>
                                    <p class="card-text small">
                                        <?php if (!empty($album['description'])): ?>
                                            <?php echo htmlspecialchars(substr($album['description'], 0, 100)); ?>
                                            <?php echo (strlen($album['description']) > 100) ? '...' : ''; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No description</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent d-flex justify-content-between">
                                    <small class="text-muted">
                                        <i class="fas fa-<?php echo $album['privacy'] === 'public' ? 'globe' : ($album['privacy'] === 'friends' ? 'user-friends' : 'lock'); ?>"></i>
                                        <?php echo ucfirst($album['privacy']); ?>
                                    </small>
                                    <div>
                                        <a href="view_album.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAlbumModal<?php echo $album['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delete Album Modal -->
                        <div class="modal fade" id="deleteAlbumModal<?php echo $album['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Delete Album</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete the album "<?php echo htmlspecialchars($album['album_name']); ?>"?</p>
                                        <p class="text-danger">This will remove the album but not the media files themselves.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST">
                                            <input type="hidden" name="album_id" value="<?php echo $album['id']; ?>">
                                            <button type="submit" name="delete_album" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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

    <!-- Create Album Modal -->
    <div class="modal fade" id="createAlbumModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Album</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="album_name" class="form-label">Album Name</label>
                            <input type="text" class="form-control" id="album_name" name="album_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (optional)</label>
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
                        
                        <?php if (!empty($userMedia)): ?>
                            <div class="mb-3">
                                <label class="form-label">Add Media to Album</label>
                                <div class="row g-2" style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($userMedia as $media): ?>
                                        <div class="col-4 col-md-3">
                                            <div class="card h-100 media-item" data-media-id="<?php echo $media['id']; ?>">
                                                <?php if (strpos($media['media_type'] ?? '', 'image') !== false): ?>
                                                    <img src="<?php echo htmlspecialchars($media['media_url']); ?>" class="card-img-top" alt="Media" style="height: 100px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                                        <i class="fas fa-file-video fa-2x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="card-footer p-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input media-checkbox" type="checkbox" value="<?php echo $media['id']; ?>" id="media_<?php echo $media['id']; ?>">
                                                        <label class="form-check-label small" for="media_<?php echo $media['id']; ?>">
                                                            Select
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="media_ids" id="selected_media_ids">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_album" class="btn btn-primary">Create Album</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle media selection for album creation
        const mediaCheckboxes = document.querySelectorAll('.media-checkbox');
        const selectedMediaIdsInput = document.getElementById('selected_media_ids');
        
        if (mediaCheckboxes.length > 0 && selectedMediaIdsInput) {
            // Update selected media IDs when checkboxes change
            mediaCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const selectedIds = Array.from(mediaCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    
                    selectedMediaIdsInput.value = selectedIds.join(',');
                    
                    // Highlight selected items
                    const mediaItem = this.closest('.media-item');
                    if (mediaItem) {
                        if (this.checked) {
                            mediaItem.classList.add('border-primary');
                        } else {
                            mediaItem.classList.remove('border-primary');
                        }
                    }
                });
            });
            
            // Allow clicking on the card to toggle checkbox
            document.querySelectorAll('.media-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    // Don't toggle if the checkbox itself was clicked
                    if (e.target.type !== 'checkbox' && !e.target.classList.contains('form-check-label')) {
                        const checkbox = this.querySelector('.media-checkbox');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }
                });
            });
        }
    });

    // Function to toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('.left-sidebar').classList.toggle('show');
    }
    </script>

    <style>
    .media-item {
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }
    .media-item:hover {
        border-color: #ddd;
    }
    .media-item.border-primary {
        border-color: #0d6efd !important;
    }
    </style>
</body>
</html>
