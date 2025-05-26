<?php
// Enable error reporting during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
require_once 'includes/MediaUploader.php';
require_once 'includes/album_helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$error = null;
$success = null;

// Initialize MediaUploader
$mediaUploader = new MediaUploader($pdo);

// Clean up duplicate default albums (run this every time)
$cleanupResult = $mediaUploader->cleanupDuplicateDefaultAlbums($user['id']);
if (!$cleanupResult['success']) {
    error_log("Error cleaning up duplicate albums: " . $cleanupResult['message']);
}

// Ensure default album exists
$defaultAlbumResult = $mediaUploader->ensureDefaultAlbum($user['id']);
if (!$defaultAlbumResult['success']) {
    error_log("Error ensuring default album: " . $defaultAlbumResult['message']);
}

// Handle album creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_album'])) {
    $albumName = trim($_POST['album_name']);
    $description = trim($_POST['description'] ?? '');
    $privacy = $_POST['privacy'] ?? 'public';
    $mediaIds = isset($_POST['media_ids']) && !empty($_POST['media_ids']) 
        ? explode(',', $_POST['media_ids']) 
        : [];
    
    if (empty($albumName)) {
        $error = "Album name is required";
    } else {
        $result = $mediaUploader->createAlbum($user['id'], $albumName, $description, $privacy, $mediaIds);
        
        if ($result['success']) {
            $success = $result['message'];
            
            // Redirect to the album page
            if (file_exists('view_album.php')) {
                header("Location: view_album.php?id=" . $result['album_id']);
                exit();
            } else {
                header("Location: manage_albums.php?success=Album created successfully");
                exit();
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Handle album deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_album'])) {
    $albumId = intval($_POST['album_id']);
    
    $result = $mediaUploader->deleteAlbum($albumId, $user['id']);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get current page for pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12; // Number of albums per page

// Get user albums with pagination
$albumsData = $mediaUploader->getUserAlbums($user['id'], $page, $perPage);
$albums = $albumsData['albums'];
$pagination = $albumsData['pagination'];

// Format albums for display
foreach ($albums as &$album) {
    $album = formatAlbumForDisplay($album);
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
    <style>
        /* Custom colors to replace blue */
        :root {
            --bs-primary: #212529;
            --bs-primary-rgb: 33, 37, 41;
            --bs-primary-text-emphasis: #212529;
            --bs-primary-bg-subtle: #e9ecef;
            --bs-primary-border-subtle: #ced4da;
        }
        
        /* Override form switch colors */
        .form-check-input:checked {
            background-color: #212529;
            border-color: #212529;
        }
        
        .form-switch .form-check-input:focus {
            border-color: #212529;
            box-shadow: 0 0 0 0.25rem rgba(33, 37, 41, 0.25);
        }
        
        /* Override link colors */
        a {
            color: #212529;
        }
        
        a:hover {
            color: #495057;
        }
        
        /* Override button colors */
        .btn-primary {
            background-color: #212529;
            border-color: #212529;
        }
        
        .btn-primary:hover {
            background-color: #495057;
            border-color: #495057;
        }
        
        .btn-outline-primary {
            color: #212529;
            border-color: #212529;
        }
        
        .btn-outline-primary:hover {
            background-color: #212529;
            border-color: #212529;
        }
        
        /* Override border colors */
        .border-primary {
            border-color: #212529 !important;
        }
        
        /* Media item selection */
        .media-item.selected {
            border: 2px solid #212529;
        }
    </style>
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
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash_message']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>My Albums</h2>
                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                    <i class="fas fa-plus"></i> Create New Album
                </button>
            </div>
            
            <?php if (empty($albums)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-photo-album fa-3x mb-3 text-muted"></i>
                    <p class="text-muted">You don't have any albums yet.</p>
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                        Create Your First Album
                    </button>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <?php foreach ($albums as $album): ?>
                        <div class="col">
                            <div class="card h-100 album-card">
                                <div class="position-relative" style="height: 150px; background-color: #f8f9fa;">
                                    <?php if (!empty($album['cover_image_url'])): ?>
                                        <a href="view_album.php?id=<?php echo $album['id']; ?>">
                                            <img src="<?php echo htmlspecialchars($album['cover_image_url']); ?>" 
                                                 class="card-img-top" alt="Album Cover" 
                                                 style="height: 150px; object-fit: cover;">
                                        </a>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100">
                                            <i class="fas fa-images fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <span class="badge bg-dark">
                                            <i class="fas fa-photo-film"></i> <?php echo $album['media_count']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="view_album.php?id=<?php echo $album['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($album['album_name']); ?>
                                        </a>
                                    </h5>
                                    <?php if (!empty($album['description'])): ?>
                                        <p class="card-text small text-muted">
                                            <?php echo nl2br(htmlspecialchars(substr($album['description'], 0, 100))); ?>
                                            <?php echo (strlen($album['description']) > 100) ? '...' : ''; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($album['id'] == 1): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="defaultGalleryPrivacy" 
                                                       <?php echo ($album['privacy'] === 'public') ? 'checked' : ''; ?>
                                                       data-album-id="<?php echo $album['id']; ?>">
                                                <label class="form-check-label" for="defaultGalleryPrivacy">
                                                    <?php echo ($album['privacy'] === 'public') ? 'Public' : 'Private'; ?>
                                                </label>
                                            </div>
                                            <div>
                                                <a href="#" class="text-decoration-none small make-default-public-link">
                                                    Make default gallery always public
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-<?php echo $album['privacy_icon']; ?> me-1"></i> 
                                        <?php echo $album['privacy_label']; ?>
                                    </small>
                                    <div>
                                        <a href="view_album.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-dark">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-dark delete-album-btn" 
                                                data-album-id="<?php echo $album['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="mt-4">
                        <?php echo generatePaginationHtml($pagination); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
        <?php
        // Include the modular right sidebar
        include 'assets/add_ons.php';
        ?>
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
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="privacy" class="form-label">Privacy</label>
                            <select class="form-select" id="privacy" name="privacy">
                                <option value="public">Public (Everyone)</option>
                                <option value="friends">Friends Only</option>
                                <option value="private">Private (Only Me)</option>
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
                        <button type="submit" name="create_album" class="btn btn-dark">Create Album</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/album-management.js"></script>
    <script>
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
    .media-item.border-dark {
        border-color: #212529 !important;
    }
    </style>
</body>
</html>
