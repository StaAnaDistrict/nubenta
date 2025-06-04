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

// Ensure Profile Pictures album exists
$profileAlbumResult = $mediaUploader->ensureProfilePicturesAlbum($user['id']);
if (!$profileAlbumResult['success']) {
    error_log("Error ensuring Profile Pictures album: " . $profileAlbumResult['message']);
} else {
    // Store the Profile Pictures album ID for reference
    $profileAlbumId = $profileAlbumResult['album_id'];
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

    // Fetch all albums for the user, ordered by album_type and then creation date
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            m.media_url as cover_image_url, 
            (SELECT COUNT(*) FROM album_media WHERE album_id = a.id) as media_count
        FROM user_media_albums a
        LEFT JOIN user_media m ON a.cover_image_id = m.id
        WHERE a.user_id = ?
        ORDER BY 
            CASE a.album_type
                WHEN 'default_gallery' THEN 0
                WHEN 'profile_pictures' THEN 1
                ELSE 2
            END, 
            a.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user['id'], $perPage, $offset]);
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format albums for display (this helper might need adjustment or removal if names are handled directly)
    // For now, we will handle name and description overrides directly in the loop.
    // $formattedAlbums = [];
    // foreach ($albums as $album) {
    //     $formattedAlbums[] = formatAlbumForDisplay($album);
    // }
    // $albums = $formattedAlbums;

    // Create pagination data
    $pagination = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalCount,
        'per_page' => $perPage
    ];

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

/*
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
*/
$userMedia = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <style>
        .album-card {
            transition: transform 0.2s;
        }
        .album-card:hover {
            transform: scale(1.05);
        }
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
        /* Override Bootstrap's blue colors */
        .btn-primary {
            background-color: #212529;
            border-color: #212529;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: #343a40 !important;
            border-color: #343a40 !important;
        }
        /* Override form switch color */
        .form-check-input:checked {
            background-color: #212529;
            border-color: #212529;
        }
        .form-check-input:focus {
            border-color: #495057;
            box-shadow: 0 0 0 0.25rem rgba(33, 37, 41, 0.25);
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Manage Gallery</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                    <i class="fas fa-plus me-2"></i> Create New Album
                </button>
            </div>

            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    Debug Information
                </div>
                <div class="card-body">
                    <!-- Debug information here -->
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Album Cards -->
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php
                foreach ($albums as $album):
                    $albumName = htmlspecialchars($album['album_name']);
                    if (!empty($album['description'])) {
                        $desc_trimmed = htmlspecialchars(substr($album['description'], 0, 100));
                        $desc_formatted = nl2br($desc_trimmed);
                        if (strlen($album['description']) > 100) {
                            $albumDescription = $desc_formatted . '...';
                        } else {
                            $albumDescription = $desc_formatted;
                        }
                    } else {
                        $albumDescription = '';
                    }
                    $privacyIcon = ($album['privacy'] === 'public') ? 'globe-americas' : (($album['privacy'] === 'friends') ? 'users' : 'lock');
                    $privacyLabel = ucfirst($album['privacy']);

                    if ($album['album_type'] === 'default_gallery') {
                        $albumName = 'My Gallery'; // Override display name
                        $albumDescription = 'Your default media gallery containing all your uploaded photos and videos.';
                    } elseif ($album['album_type'] === 'profile_pictures') {
                        $albumName = 'Profile Pictures'; // Override display name
                        $albumDescription = 'Your profile pictures collection.';
                        $privacyIcon = 'globe-americas'; // Profile pictures usually public
                        $privacyLabel = 'Public';
                    }
                    // Ensure the diagnostic echo is the only active output in the loop for this test
                    // echo "<div>Album ID: " . htmlspecialchars($album['id'] ?? 'N/A') . "; Type: " . htmlspecialchars($album['album_type'] ?? 'N/A') . "</div><br>";
                ?>
                <!-- Processing Album ID: <?php echo htmlspecialchars($album['id'] ?? 'N/A'); ?> -->
                    <div class="col">
                        <div class="card h-100 album-card">
                            <div class="position-relative" style="height: 150px; background-color: #f8f9fa;">
                                <?php if (!empty($album['cover_image_url'])): ?>
                                    <a href="view_album.php?id=<?php echo $album['id']; ?>">
                                        <?php
                                            // Prepare attributes for the img tag to ensure clarity
                                            $img_src = htmlspecialchars($album['cover_image_url']);
                                            $img_class = "card-img-top";
                                            $img_alt = "Album Cover";
                                            $img_style = "height: 150px; object-fit: cover;";
                                        ?>
                                        <img src="<?php echo $img_src; ?>" class="<?php echo $img_class; ?>" alt="<?php echo $img_alt; ?>" style="<?php echo $img_style; ?>">
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
                                        <?php echo $albumName; ?>
                                    </a>
                                </h5>
                                <?php if (!empty($albumDescription)): ?>
                                    <p class="card-text small text-muted">
                                        <?php echo $albumDescription; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <?php if ($album['album_type'] === 'default_gallery'): ?>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="privacyToggle_<?php echo $album['id']; ?>"
                                           <?php echo ($album['privacy'] === 'public') ? 'checked' : ''; ?>
                                           data-album-id="<?php echo $album['id']; ?>">
                                    <label class="form-check-label" for="privacyToggle_<?php echo $album['id']; ?>">
                                        <?php echo ($album['privacy'] === 'public') ? 'Public' : 'Private'; ?>
                                    </label>
                                </div>
                                <?php elseif ($album['album_type'] === 'profile_pictures'): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-globe-americas me-1"></i> Public
                                    </small>
                                <?php else: // Custom album ?>
                                <small class="text-muted">
                                    <i class="fas fa-<?php echo $privacyIcon; ?> me-1"></i>
                                    <?php echo $privacyLabel; ?>
                                </small>
                                <?php endif; ?>
                                <div>
                                    <a href="view_album.php?id=<?php echo $album['id']; ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($album['album_type'] === 'custom'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-dark delete-album-btn"
                                                data-album-id="<?php echo $album['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-dark" disabled data-bs-toggle="tooltip" title="System albums cannot be deleted">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div> <!-- End of div class="row g-4" -->
        </main> <!-- End of main class="main-content" -->

        <aside class="right-sidebar">
            <?php
            // You can customize the sidebar by setting these variables
            // $topElementTitle = "Custom Ads Title";
            // $showAdditionalContent = true;

            // Include the modular right sidebar
            include 'assets/add_ons.php';
            ?>
        </aside>
    </div> <!-- End of div class="dashboard-grid" -->

    <!-- Create Album Modal -->
    <div class="modal fade" id="createAlbumModal" tabindex="-1" aria-labelledby="createAlbumModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAlbumModalLabel">Create New Album</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
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
                            <label class="form-label">Privacy</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="privacy" id="privacy_public" value="public" checked>
                                <label class="form-check-label" for="privacy_public">
                                    Public
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="privacy" id="privacy_private" value="private">
                                <label class="form-check-label" for="privacy_private">
                                    Private
                                </label>
                            </div>
                        </div>

                        <?php if (!empty($userMedia)): ?>
                            <div class="mb-3">
                                <label class="form-label">Select Media (optional)</label>
                                <div class="row g-2" style="max-height: 300px; overflow-y: auto;">
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_album" class="btn btn-primary">Create Album</button>
                    </div>
                </form>
            </div>
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

                // Add event listeners to checkboxes
                mediaCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const mediaItem = this.closest('.media-item');
                        if (this.checked) {
                            mediaItem.classList.add('selected');
                        } else {
                            mediaItem.classList.remove('selected');
                        }
                        updateSelectedMediaIds();
                    });
                });

                // Add event listeners to media items
                mediaItems.forEach(item => {
                    item.addEventListener('click', function(e) {
                        // Don't toggle if clicking on the checkbox itself
                        if (e.target.type !== 'checkbox') {
                            const checkbox = this.querySelector('.media-checkbox');
                            checkbox.checked = !checkbox.checked;

                            // Trigger change event
                            const event = new Event('change');
                            checkbox.dispatchEvent(event);
                        }
                    });
                });
            }

            // Album deletion
            const deleteButtons = document.querySelectorAll('.delete-album-btn');

            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
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

            // Default gallery privacy toggle
            const defaultGalleryPrivacyToggle = document.getElementById('defaultGalleryPrivacy');

            if (defaultGalleryPrivacyToggle) {
                defaultGalleryPrivacyToggle.addEventListener('change', function() {
                    const albumId = this.getAttribute('data-album-id');
                    const isPublic = this.checked;

                    fetch('api/album_management.php?action=update_privacy', {
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
                        if (data.success) {
                            // Update label next to toggle
                            const label = this.nextElementSibling;
                            if (label) {
                                label.textContent = isPublic ? 'Public' : 'Private';
                            }
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update privacy'));
                            // Revert toggle
                            this.checked = !this.checked;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating privacy');
                        // Revert toggle
                        this.checked = !this.checked;
                    });
                });
            }

            // Remove the "Make default gallery always public" code since we removed that link
            // const makeDefaultPublicLink = document.querySelector('.make-default-public-link');
            //
            // if (makeDefaultPublicLink) {
            //     makeDefaultPublicLink.addEventListener('click', function(e) {
            //         e.preventDefault();
            //
            //         if (confirm('Do you want to make your gallery always public? This setting will apply to all future uploads.')) {
            //             fetch('api/set_default_gallery_public.php', {
            //                 method: 'POST',
            //                 headers: {
            //                     'Content-Type': 'application/json',
            //                 }
            //             })
            //             .then(response => response.json())
            //             .then(data => {
            //                 if (data.success) {
            //                     alert('Your gallery will now always be public.');
            //                     // Update UI
            //                     const toggle = document.getElementById('defaultGalleryPrivacy');
            //                     if (toggle) {
            //                         toggle.checked = true;
            //                         const label = toggle.nextElementSibling;
            //                         if (label) {
            //                             label.textContent = 'Public';
            //                         }
            //                     }
            //                 } else {
            //                     alert('Error: ' + (data.message || 'Unknown error'));
            //                 }
            //             })
            //             .catch(error => {
            //                 console.error('Error:', error);
            //                 alert('An error occurred. Please try again.');
            //             });
            //         }
            //     });
            // }

            // Toggle sidebar on mobile
            function toggleSidebar() {
                const sidebar = document.querySelector('.left-sidebar');
                sidebar.classList.toggle('show');
            }

            // Make toggleSidebar function global
            window.toggleSidebar = toggleSidebar;
        });
    </script>
</body>
</html>
