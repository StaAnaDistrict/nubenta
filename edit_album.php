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

// Check if album ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'No album ID specified'
    ];
    header("Location: manage_albums.php");
    exit();
}

$albumId = intval($_GET['id']);

// Get album details
$stmt = $pdo->prepare("
    SELECT * FROM user_media_albums
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$albumId, $user['id']]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);

// If album doesn't exist or doesn't belong to user
if (!$album) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Album not found or you don\'t have permission to edit it'
    ];
    header("Location: manage_albums.php");
    exit();
}

// Get album media
$albumMedia = $mediaUploader->getAlbumMedia($albumId, $user['id']);

// Handle album update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_album'])) {
    $albumName = trim($_POST['album_name']);
    $description = trim($_POST['description'] ?? '');
    $privacy = $_POST['privacy'] ?? 'public';
    $coverImageId = !empty($_POST['cover_image_id']) ? intval($_POST['cover_image_id']) : null;
    
    if (empty($albumName)) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Album name is required'
        ];
    } else {
        $success = $mediaUploader->updateAlbum($albumId, $user['id'], $albumName, $description, $privacy, $coverImageId);
        
        if ($success) {
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Album updated successfully'
            ];
            
            // Redirect to view the album
            header("Location: view_album.php?id=" . $albumId);
            exit();
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Failed to update album'
            ];
        }
    }
}

// Set page title
$pageTitle = "Edit Album: " . htmlspecialchars($album['album_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <style>
        .cover-image-item {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .cover-image-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .cover-image-radio {
            cursor: pointer;
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
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Album: <?php echo htmlspecialchars($album['album_name']); ?></h5>
                    <a href="view_album.php?id=<?php echo $albumId; ?>" class="btn btn-sm btn-outline-dark">
                        <i class="fas fa-arrow-left me-1"></i> Back to Album
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="album_name" class="form-label">Album Name</label>
                            <input type="text" class="form-control" id="album_name" name="album_name" value="<?php echo htmlspecialchars($album['album_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($album['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="privacy" class="form-label">Privacy</label>
                            <select class="form-select" id="privacy" name="privacy">
                                <option value="public" <?php echo ($album['privacy'] === 'public') ? 'selected' : ''; ?>>Public</option>
                                <option value="friends" <?php echo ($album['privacy'] === 'friends') ? 'selected' : ''; ?>>Friends Only</option>
                                <option value="private" <?php echo ($album['privacy'] === 'private') ? 'selected' : ''; ?>>Only Me</option>
                            </select>
                        </div>
                        
                        <?php if (!empty($albumMedia)): ?>
                            <div class="mb-3">
                                <label class="form-label">Choose Cover Image</label>
                                <div class="row g-2">
                                    <?php foreach ($albumMedia as $media): ?>
                                        <?php if ($media['media_type'] === 'image'): ?>
                                            <div class="col-4 col-md-3 col-lg-2">
                                                <div class="card h-100 cover-image-item <?php echo ($album['cover_image_id'] == $media['id']) ? 'border-primary border-2' : ''; ?>" data-media-id="<?php echo $media['id']; ?>">
                                                    <div class="position-relative">
                                                        <img src="<?php echo htmlspecialchars($media['media_url']); ?>" class="card-img-top" alt="Media" style="height: 100px; object-fit: cover;">
                                                        <div class="position-absolute top-0 end-0 p-1">
                                                            <div class="form-check">
                                                                <input class="form-check-input cover-image-radio" type="radio" name="cover_image_id" value="<?php echo $media['id']; ?>" id="cover_<?php echo $media['id']; ?>" <?php echo ($album['cover_image_id'] == $media['id']) ? 'checked' : ''; ?>>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="manage_albums.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="update_album" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <!-- Right Sidebar -->
        <?php
        // Include the modular right sidebar
        include 'assets/add_ons.php';
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle cover image selection
            const coverImageItems = document.querySelectorAll('.cover-image-item');
            const coverImageRadios = document.querySelectorAll('.cover-image-radio');
            
            coverImageItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    // Don't toggle if the radio itself was clicked
                    if (e.target.type === 'radio') return;
                    
                    const mediaId = this.getAttribute('data-media-id');
                    const radio = document.querySelector(`#cover_${mediaId}`);
                    
                    // Select radio
                    radio.checked = true;
                    
                    // Update UI
                    coverImageItems.forEach(item => {
                        item.classList.remove('border-primary', 'border-2');
                    });
                    this.classList.add('border-primary', 'border-2');
                });
            });
            
            // Function to toggle sidebar on mobile
            function toggleSidebar() {
                document.querySelector('.left-sidebar').classList.toggle('show');
            }
        });
    </script>
</body>
</html>
