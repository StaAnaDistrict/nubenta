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

// Ensure default album exists
$defaultAlbumResult = $mediaUploader->ensureDefaultAlbum($user['id']);
if (!$defaultAlbumResult['success']) {
    error_log("Error ensuring default album: " . $defaultAlbumResult['message']);
} else {
    // Store the default album ID for reference
    $defaultAlbumId = $defaultAlbumResult['album_id'];
}

// Clean up duplicate default albums (run this every time)
$cleanupResult = $mediaUploader->cleanupDuplicateDefaultAlbums($user['id']);
if (!$cleanupResult['success']) {
    error_log("Error cleaning up duplicate albums: " . $cleanupResult['message']);
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
        // Call the correct method: createMediaAlbum instead of createAlbum
        $albumId = $mediaUploader->createMediaAlbum($user['id'], $albumName, $description, $mediaIds, $privacy);
        
        if ($albumId) {
            $success = "Album created successfully";
            
            // Update album_id in user_media table for selected media
            if (!empty($mediaIds)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
                    $params = array_merge([$albumId], $mediaIds);
                    
                    $stmt = $pdo->prepare("
                        UPDATE user_media 
                        SET album_id = ? 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($params);
                } catch (PDOException $e) {
                    error_log("Error updating album_id in user_media: " . $e->getMessage());
                }
            }
            
            // Redirect to the album page
            if (file_exists('view_album.php')) {
                header("Location: view_album.php?id=" . $albumId);
                exit();
            } else {
                header("Location: manage_albums.php?success=Album created successfully");
                exit();
            }
        } else {
            $error = "Failed to create album";
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

// Get user albums directly from the database
try {
    // Calculate offset for pagination
    $offset = ($page - 1) * $perPage;
    
    // Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM user_media_albums 
        WHERE user_id = ?
    ");
    $countStmt->execute([$user['id']]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalCount / $perPage);
    
    // First, let's debug what albums exist in the database
    $debugStmt = $pdo->prepare("
        SELECT id, album_name, user_id 
        FROM user_media_albums 
        WHERE user_id = ?
        ORDER BY id ASC
    ");
    $debugStmt->execute([$user['id']]);
    $allDbAlbums = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Store debug info
    $debugInfo = [
        'all_db_albums' => $allDbAlbums,
        'default_album_id' => $defaultAlbumId
    ];
    
    // First, get the default album to ensure it's included
    $defaultStmt = $pdo->prepare("
        SELECT a.*, 
               m.media_url as cover_image_url,
               (SELECT COUNT(*) FROM user_media WHERE user_id = ?) as media_count,
               'Default Gallery' as album_name,
               'Your default media gallery containing all your uploaded photos and videos' as description
        FROM user_media_albums a
        LEFT JOIN user_media m ON a.cover_image_id = m.id
        WHERE a.user_id = ? AND a.id = ?
    ");
    $defaultStmt->execute([$user['id'], $user['id'], $defaultAlbumId]);
    $defaultAlbum = $defaultStmt->fetch(PDO::FETCH_ASSOC);

    // Then get all other albums - get ALL albums, not just a limited number
    $stmt = $pdo->prepare("
        SELECT a.*, 
               m.media_url as cover_image_url,
               (SELECT COUNT(*) FROM album_media WHERE album_id = a.id) as media_count
        FROM user_media_albums a
        LEFT JOIN user_media m ON a.cover_image_id = m.id
        WHERE a.user_id = ? AND a.id != ?
        ORDER BY a.id ASC
    ");
    $stmt->execute([$user['id'], $defaultAlbumId]);
    $otherAlbums = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug the other albums
    error_log("Other albums: " . json_encode($otherAlbums));

    // Combine default album with other albums - rebuild array manually
    $albums = [];
    if ($defaultAlbum) {
        $albums[] = $defaultAlbum;
    }

    // Add other albums one by one
    foreach ($otherAlbums as $otherAlbum) {
        $albums[] = $otherAlbum;
    }

    // Format albums for display
    $formattedAlbums = [];
    foreach ($albums as $album) {
        $formattedAlbums[] = formatAlbumForDisplay($album);
    }
    $albums = $formattedAlbums;
    
    // Create pagination data
    $pagination = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalCount,
        'per_page' => $perPage
    ];
    
    // Format albums for display
    foreach ($albums as &$album) {
        // Debug before formatting
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            error_log("Before formatting: Album ID " . $album['id'] . ", Name: " . $album['album_name']);
        }
        
        $album = formatAlbumForDisplay($album);
        
        // Debug after formatting
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            error_log("After formatting: Album ID " . $album['id'] . ", Name: " . $album['album_name']);
        }
    }

    // IMPORTANT: Unset the reference to avoid issues
    unset($album);
    
} catch (PDOException $e) {
    error_log("Error fetching albums: " . $e->getMessage());
    $albums = [];
    $pagination = [
        'current_page' => 1,
        'total_pages' => 1,
        'total_items' => 0,
        'per_page' => $perPage
    ];
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
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Albums</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .album-card {
            transition: transform 0.2s;
        }
        .album-card:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Manage Albums</h1>
        
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                Debug Information
            </div>
            <div class="card-body">
                <h5>All Albums in Database:</h5>
                <pre><?php echo json_encode($debugInfo['all_db_albums'], JSON_PRETTY_PRINT); ?></pre>
                
                <h5>Default Album ID: <?php echo $debugInfo['default_album_id']; ?></h5>
                
                <h5>Other Albums (raw):</h5>
                <pre><?php echo json_encode($otherAlbums, JSON_PRETTY_PRINT); ?></pre>
                
                <h5>Albums Array (after processing):</h5>
                <pre><?php 
                    $debugAlbums = [];
                    foreach ($albums as $album) {
                        $debugAlbums[] = [
                            'id' => $album['id'],
                            'album_name' => $album['album_name']
                        ];
                    }
                    echo json_encode($debugAlbums, JSON_PRETTY_PRINT); 
                ?></pre>

                <h5>Albums Array IDs:</h5>
                <pre><?php echo json_encode(array_column($albums, 'id'), JSON_PRETTY_PRINT); ?></pre>
                
                <h5>formatAlbumForDisplay Function:</h5>
                <pre><?php 
                    if (function_exists('formatAlbumForDisplay')) {
                        $func = new ReflectionFunction('formatAlbumForDisplay');
                        $filename = $func->getFileName();
                        $start_line = $func->getStartLine();
                        $end_line = $func->getEndLine();
                        $length = $end_line - $start_line;
                        
                        $file = file($filename);
                        $code = implode("", array_slice($file, $start_line - 1, $length));
                        echo htmlspecialchars($code);
                    } else {
                        echo "Function not found";
                    }
                ?></pre>
                
                <h5>Check for Duplicate IDs:</h5>
                <pre><?php
                    $allIds = array_column($albums, 'id');
                    $duplicateIds = array_diff_assoc($allIds, array_unique($allIds));
                    echo "Duplicate IDs: " . json_encode($duplicateIds) . "\n";
                    
                    // Check if album 8 exists in the array
                    echo "Album 8 exists in array: " . (in_array(8, $allIds) ? "Yes" : "No") . "\n";
                    
                    // Check the raw data for album 8
                    $album8Exists = false;
                    foreach ($otherAlbums as $album) {
                        if ($album['id'] == 8) {
                            $album8Exists = true;
                            break;
                        }
                    }
                    echo "Album 8 exists in raw data: " . ($album8Exists ? "Yes" : "No");
                ?></pre>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Create New Album</h5>
                        <form action="manage_albums.php" method="post">
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
                                    <option value="private">Private</option>
                                </select>
                            </div>
                            
                            <?php if (!empty($userMedia)): ?>
                                <div class="mb-3">
                                    <label class="form-label">Select Media (optional)</label>
                                    <div class="row g-2" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach ($userMedia as $media): ?>
                                            <div class="col-4 col-md-3">
                                                <div class="card h-100 media-item" data-media-id="<?php echo $media['id']; ?>">
                                                    <?php if (strpos($media['media_type'] ?? '', 'image') !== false): ?>
                                                        <img src="<?php echo htmlspecialchars($media['media_url']); ?>" class="card-img-top" alt="Media" style="height: 80px; object-fit: cover;">
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
                                    <input type="hidden" name="media_ids" id="selected_media_ids" value="">
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" name="create_album" class="btn btn-primary">Create Album</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Albums</h5>
                        <div class="list-group">
                            <?php 
                            // Track album IDs to prevent duplicates
                            $displayedAlbumIds = [];
                            
                            // Display all albums in the sidebar
                            foreach ($albums as $album): 
                                // Skip if already displayed
                                if (in_array($album['id'], $displayedAlbumIds)) {
                                    continue;
                                }
                                
                                // Add to displayed list
                                $displayedAlbumIds[] = $album['id'];
                                
                                // Get the album name (use the original name for non-default albums)
                                $albumName = ($album['id'] == $defaultAlbumId) ? 'Default Gallery' : $album['album_name'];
                            ?>
                                <a href="view_album.php?id=<?php echo $album['id']; ?>" class="list-group-item list-group-item-action">
                                    <?php echo htmlspecialchars($albumName); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php 
            // Track album IDs to prevent duplicates
            $displayedAlbumIds = [];
            
            // Debug the albums array before display
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<pre>Albums before display: " . json_encode(array_column($albums, 'id')) . "</pre>";
            }
            
            // Display all albums as cards
            foreach ($albums as $album): 
                // Skip if already displayed
                if (in_array($album['id'], $displayedAlbumIds)) {
                    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                        echo "<p>Skipping duplicate album ID: " . $album['id'] . "</p>";
                    }
                    continue;
                }
                
                // Add to displayed list
                $displayedAlbumIds[] = $album['id'];
                
                // Special handling for default gallery
                $isDefaultGallery = ($album['id'] == $defaultAlbumId);
                $albumName = $isDefaultGallery ? 'Default Gallery' : $album['album_name'];
            ?>
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
                                    <?php echo htmlspecialchars($albumName); ?>
                                </a>
                            </h5>
                            <?php if (!empty($album['description'])): ?>
                                <p class="card-text small text-muted">
                                    <?php echo nl2br(htmlspecialchars(substr($album['description'], 0, 100))); ?>
                                    <?php echo (strlen($album['description']) > 100) ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($isDefaultGallery): ?>
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
                                <?php if (!$isDefaultGallery): // Don't allow deleting the default album ?>
                                    <button type="button" class="btn btn-sm btn-outline-dark delete-album-btn" 
                                            data-album-id="<?php echo $album['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Media selection for album creation
            const mediaItems = document.querySelectorAll('.media-item');
            const mediaCheckboxes = document.querySelectorAll('.media-checkbox');
            const selectedMediaIdsInput = document.getElementById('selected_media_ids');
            
            if (selectedMediaIdsInput) {
                // Function to update selected media IDs
                function updateSelectedMediaIds() {
                    const selectedIds = Array.from(mediaCheckboxes)
                        .filter(checkbox => checkbox.checked)
                        .map(checkbox => checkbox.value);
                    
                    selectedMediaIdsInput.value = selectedIds.join(',');
                }
                
                // Add click event to media items (for better UX)
                mediaItems.forEach(item => {
                    item.addEventListener('click', function(e) {
                        // Don't toggle if the checkbox itself was clicked
                        if (e.target.type === 'checkbox' || e.target.classList.contains('form-check-label')) {
                            return;
                        }
                        
                        const checkbox = this.querySelector('.media-checkbox');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            
                            // Toggle selected class
                            if (checkbox.checked) {
                                this.classList.add('selected');
                            } else {
                                this.classList.remove('selected');
                            }
                            
                            // Update hidden input
                            updateSelectedMediaIds();
                        }
                    });
                });
                
                // Add change event to checkboxes
                mediaCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const mediaItem = this.closest('.media-item');
                        
                        // Toggle selected class
                        if (this.checked) {
                            mediaItem.classList.add('selected');
                        } else {
                            mediaItem.classList.remove('selected');
                        }
                        
                        // Update hidden input
                        updateSelectedMediaIds();
                    });
                });
            }
            
            // Handle album deletion
            document.querySelectorAll('.delete-album-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const albumId = this.getAttribute('data-album-id');
                    
                    if (confirm('Are you sure you want to delete this album? This action cannot be undone.')) {
                        fetch('api/album_management.php?action=delete', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                album_id: albumId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Remove album from DOM or reload page
                                window.location.reload();
                            } else {
                                alert('Error: ' + (data.message || 'Failed to delete album'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while deleting the album');
                        });
                    }
                });
            });
            
            // Handle default gallery privacy toggle
            const defaultGalleryPrivacyToggle = document.getElementById('defaultGalleryPrivacy');
            if (defaultGalleryPrivacyToggle) {
                defaultGalleryPrivacyToggle.addEventListener('change', function() {
                    const albumId = this.getAttribute('data-album-id');
                    const isPublic = this.checked;
                    
                    // Update the label
                    const label = this.nextElementSibling;
                    if (label) {
                        label.textContent = isPublic ? 'Public' : 'Private';
                    }
                    
                    // Send request to update album privacy
                    fetch('api/update_album_privacy.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            album_id: albumId,
                            privacy: isPublic ? 'public' : 'private'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error('Error updating privacy:', data.message);
                            // Revert the toggle if there was an error
                            this.checked = !this.checked;
                            if (label) {
                                label.textContent = this.checked ? 'Public' : 'Private';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Revert the toggle if there was an error
                        this.checked = !this.checked;
                        if (label) {
                            label.textContent = this.checked ? 'Public' : 'Private';
                        }
                    });
                });
            }
            
            // Make default gallery always public
            const makeDefaultPublicLink = document.querySelector('.make-default-public-link');
            if (makeDefaultPublicLink) {
                makeDefaultPublicLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    if (confirm('Do you want to make your default gallery always public? This setting will apply to all future uploads.')) {
                        fetch('api/set_default_gallery_public.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Default gallery will now always be public.');
                                // Update UI
                                const toggle = document.getElementById('defaultGalleryPrivacy');
                                if (toggle) {
                                    toggle.checked = true;
                                    const label = toggle.nextElementSibling;
                                    if (label) {
                                        label.textContent = 'Public';
                                    }
                                }
                            } else {
                                alert('Error: ' + (data.message || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                    }
                });
            }
        });
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
        .media-item.selected {
            border-color: #212529 !important;
        }
    </style>
</body>
</html>
