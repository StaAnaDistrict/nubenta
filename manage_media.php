<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// Ensure the privacy column exists in the user_media table
try {
    $mediaUploader->ensureMediaPrivacyColumn();
} catch (Exception $e) {
    echo "Error ensuring privacy column: " . $e->getMessage();
    exit();
}

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

// Handle privacy update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_privacy'])) {
    $mediaId = intval($_POST['media_id']);
    $privacy = $_POST['privacy'];
    
    try {
        // Check if media belongs to user
        $checkStmt = $pdo->prepare("SELECT id FROM user_media WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$mediaId, $user['id']]);
        
        if ($checkStmt->fetch()) {
            // Update privacy
            $updateStmt = $pdo->prepare("UPDATE user_media SET privacy = ? WHERE id = ?");
            $updateStmt->execute([$privacy, $mediaId]);
            
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Privacy updated successfully'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to update this media'
            ];
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Error updating privacy: ' . $e->getMessage()
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

// Get user media directly from database instead of using MediaUploader
try {
    $params = [$user['id']];
    $sql = "SELECT * FROM user_media WHERE user_id = ?";
    
    if ($mediaType) {
        $sql .= " AND media_type LIKE ?";
        $params[] = $mediaType . '%';
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total media
    $countSql = "SELECT COUNT(*) as total FROM user_media WHERE user_id = ?";
    $countParams = [$user['id']];
    
    if ($mediaType) {
        $countSql .= " AND media_type LIKE ?";
        $countParams[] = $mediaType . '%';
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalMedia = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalMedia / $limit);
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit();
}

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
    <style>
        .video-placeholder {
            background-color: #212529;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .media-card {
            transition: transform 0.2s;
        }
        .media-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
    <script>
        window.isAdmin = <?php echo (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') ? 'true' : 'false'; ?>;
    </script>
</head>
<body>
    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php
            $currentUser = $user;
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>Manage Media</h1>
            
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['flash_message']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>
            
            <div class="btn-group mb-3">
                <a href="manage_media.php" class="btn btn-sm <?php echo !$mediaType ? 'btn-dark' : 'btn-outline-dark'; ?>">All</a>
                <a href="manage_media.php?type=image" class="btn btn-sm <?php echo $mediaType === 'image' ? 'btn-dark' : 'btn-outline-dark'; ?>">Images</a>
                <a href="manage_media.php?type=video" class="btn btn-sm <?php echo $mediaType === 'video' ? 'btn-dark' : 'btn-outline-dark'; ?>">Videos</a>
            </div>
            
            <?php if (empty($media)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="fas fa-photo-video fa-3x mb-3"></i>
                    <p>No media found. Start sharing photos and videos in your posts!</p>
                    <a href="dashboardv2.php" class="btn btn-dark">Go to Dashboard</a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($media as $item): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 media-card">
                                <div class="position-relative">
                                    <?php if ($item['media_type'] === 'image'): ?>
                                        <a href="view_media.php?id=<?php echo $item['id']; ?>">
                                            <img src="<?php echo htmlspecialchars($item['media_url']); ?>" class="card-img-top" alt="Media" style="height: 160px; object-fit: cover;">
                                        </a>
                                    <?php elseif ($item['media_type'] === 'video'): ?>
                                        <a href="view_media.php?id=<?php echo $item['id']; ?>">
                                            <div class="position-relative">
                                                <?php if (!empty($item['thumbnail_url']) && file_exists($item['thumbnail_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>" 
                                                         class="card-img-top" alt="Video thumbnail" style="height: 160px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="video-placeholder card-img-top">
                                                        <i class="fas fa-video fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="position-absolute top-50 start-50 translate-middle">
                                                    <i class="fas fa-play-circle fa-2x text-white"></i>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Media actions -->
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
                                                <li>
                                                    <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#privacyModal<?php echo $item['id']; ?>">
                                                        <i class="fas fa-lock me-2"></i> Privacy
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['id']; ?>">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text small text-muted">
                                        <i class="fas <?php echo $item['media_type'] === 'image' ? 'fa-image' : 'fa-video'; ?> me-1"></i>
                                        <?php echo ucfirst($item['media_type']); ?> • 
                                        <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                    </p>
                                    <p class="card-text small">
                                        <span class="badge bg-<?php echo $item['privacy'] === 'public' ? 'success' : 'warning'; ?>">
                                            <i class="fas <?php echo $item['privacy'] === 'public' ? 'fa-globe' : 'fa-lock'; ?> me-1"></i>
                                            <?php echo ucfirst($item['privacy']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Privacy Modal -->
                            <div class="modal fade" id="privacyModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Privacy</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="media_id" value="<?php echo $item['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Privacy Setting</label>
                                                    <select name="privacy" class="form-select">
                                                        <option value="public" <?php echo $item['privacy'] === 'public' ? 'selected' : ''; ?>>Public</option>
                                                        <option value="private" <?php echo $item['privacy'] === 'private' ? 'selected' : ''; ?>>Private</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="update_privacy" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Modal -->
                            <div class="modal fade" id="deleteModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this media? This action cannot be undone.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form method="post">
                                                <input type="hidden" name="media_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" name="delete_media" class="btn btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $mediaType ? '&type=' . $mediaType : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $mediaType ? '&type=' . $mediaType : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $mediaType ? '&type=' . $mediaType : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
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
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.left-sidebar').classList.toggle('show');
        }
    </script>
</body>
</html>
