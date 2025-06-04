<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// 1. Initial Setup
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$currentUser = $_SESSION['user'];

// 2. Parameter Handling
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
}

if (!$userId || $userId <= 0) {
    $_SESSION['error_message'] = "Invalid user ID specified.";
    header("Location: dashboard.php");
    exit();
}

$mediaTypeFilter = filter_input(INPUT_GET, 'media_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($mediaTypeFilter) || !in_array($mediaTypeFilter, ['photo', 'video'])) {
    $mediaTypeFilter = 'photo'; // Default to 'photo'
}

// 3. Fetch Target User Details
$targetUser = null;
$errorMessage = null;
try {
    $userStmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    if ($userStmt) {
        $userStmt->execute([$userId]);
        $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $errorMessage = "Error fetching user details (prepare failed).";
    }
} catch (PDOException $e) {
    error_log("PDO Error (User Fetch view_user_media.php): " . $e->getMessage());
    $errorMessage = "Database error fetching user details.";
}

$pageTitle = "User Media - Nubenta";
$headerTitle = "User's Media";

if (!$errorMessage && !$targetUser) {
    $errorMessage = "User not found.";
} elseif (!$errorMessage && $targetUser) {
    $targetUserName = htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']);
    $pageTitle = $targetUserName . "'s " . ucfirst($mediaTypeFilter) . "s - Nubenta";
    $headerTitle = $targetUserName . "'s " . ucfirst($mediaTypeFilter) . "s";
}

// 4. Determine Friendship Status
$areFriends = false;
if ($targetUser && !$errorMessage && $userId != $currentUser['id']) {
    try {
        $_currentUserId = intval($currentUser['id']);
        $_targetUserId = intval($userId);
        $friendSql = "
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = {$_currentUserId} AND receiver_id = {$_targetUserId}) OR (sender_id = {$_targetUserId} AND receiver_id = {$_currentUserId}))
            AND status = 'accepted'
        ";
        $friendStmt = $pdo->prepare($friendSql);
        if ($friendStmt) {
            $friendStmt->execute();
            $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
            if ($friendship) {
                $areFriends = ($friendship['is_friend'] > 0);
            }
        } else {
             // Silently fail or log minimal error, $areFriends remains false
        }
    } catch (PDOException $e) {
        error_log("PDO Error (Friend Check view_user_media.php): " . $e->getMessage());
        // $areFriends remains false
    }
} elseif ($targetUser && !$errorMessage && $userId == $currentUser['id']) {
    $areFriends = true;
}

// 5. Fetch Media Items
$mediaItems = [];
if ($targetUser && !$errorMessage) {
    $sql = "SELECT DISTINCT um.id, um.user_id as media_owner_id, um.media_url, um.media_type, um.thumbnail_url, um.created_at, 
                   um.privacy as media_item_privacy, uma.privacy as album_privacy, uma.user_id as album_owner_id
            FROM user_media um
            LEFT JOIN album_media am ON um.id = am.media_id 
            LEFT JOIN user_media_albums uma ON am.album_id = uma.id
            WHERE um.user_id = " . intval($userId);

    if ($mediaTypeFilter === 'photo') {
        $sql .= " AND TRIM(um.media_type) = 'image'";
    } elseif ($mediaTypeFilter === 'video') {
        $sql .= " AND TRIM(um.media_type) = 'video'";
    }
    
    $privacySqlParts = [];
    if ($currentUser['id'] == $userId) {
        $privacySqlParts[] = "1=1";
    } else {
        $privacySqlParts[] = "TRIM(um.privacy) = 'public'";
        $privacySqlParts[] = "(am.album_id IS NULL AND TRIM(um.privacy) = 'public')"; 
        $privacySqlParts[] = "TRIM(uma.privacy) = 'public'";
        
        if ($areFriends) {
            $privacySqlParts[] = "TRIM(um.privacy) = 'friends'";
            $privacySqlParts[] = "(am.album_id IS NULL AND TRIM(um.privacy) = 'friends')";
            $privacySqlParts[] = "TRIM(uma.privacy) = 'friends'";
        }
    }
    
    if (!empty($privacySqlParts)) {
      $sql .= " AND (" . implode(" OR ", $privacySqlParts) . ")";
    }

    $sql .= " ORDER BY um.created_at DESC";
    
    try {
        $mediaStmt = $pdo->prepare($sql);
        if ($mediaStmt) {
            $mediaStmt->execute();
            $mediaItems = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMessage = "Error fetching media content (prepare failed).";
        }
    } catch (PDOException $e) {
        error_log("PDO Error (Media Fetch view_user_media.php): " . $e->getMessage());
        $errorMessage = "Database error fetching media content. Details: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <style>
        .main-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .media-item-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden; /* To contain image/video within rounded corners */
        }
        .media-item-card img, .media-item-card video {
            width: 100%;
            height: 200px; /* Fixed height for uniformity */
            object-fit: cover; /* Crop to fit, maintain aspect ratio */
            display: block;
        }
        .media-item-card .card-body {
            padding: 10px;
        }
        .media-item-card .card-title {
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .media-item-card .card-text {
            font-size: 0.85rem;
            color: #555;
            margin-bottom: 5px;
        }
        .media-item-card .text-muted {
            font-size: 0.75rem;
        }
        .page-header {
            margin-bottom: 20px;
        }
        .back-link-btn {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
    <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php 
                $user = $currentUser; // Make $currentUser available as $user for navigation.php
                $currentPage = 'view_user_media'; // Or $currentPage = ''; if you prefer
            ?>
            <?php include 'assets/navigation.php'; ?>
        </aside>

        <main class="main-content">
            <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage) ?>
                    <p class="mt-2"><a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a></p>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <h2><?= $headerTitle ?></h2>
                    <p class="text-muted">Total items: <?= count($mediaItems) ?></p>
                    <a href="view_profile.php?id=<?= htmlspecialchars($userId) ?>" class="btn btn-outline-secondary btn-sm back-link-btn">
                        <i class="fas fa-arrow-left me-1"></i> Back to <?= htmlspecialchars($targetUser['first_name']) ?>'s Profile
                    </a>
                </div>

                <?php if (empty($mediaItems)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-<?= ($mediaTypeFilter === 'photo' ? 'camera-retro' : 'video-slash') ?> fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No <?= htmlspecialchars($mediaTypeFilter) ?>s found for <?= $targetUserName ?> that are visible to you.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                        <?php foreach ($mediaItems as $item): ?>
                            <div class="col">
                                <div class="media-item-card">
                                <a href="view_media.php?id=<?= htmlspecialchars($item['id']) ?>" class="text-decoration-none">
                                     <?php if ($item['media_type'] === 'image'): ?>
                                         <img src="<?= htmlspecialchars($item['media_url']) ?>" alt="User media image">
                                         <?php elseif ($item['media_type'] === 'video'): ?>
                                            <?php 
                                                $videoThumbnail = !empty($item['thumbnail_url']) ? htmlspecialchars($item['thumbnail_url']) : 'assets/images/default_video_thumb.png'; 
                                            ?>
                                            <div style="position: relative; width: 100%; height: 200px;"> 
                                                <img src="<?= $videoThumbnail ?>" 
                                                     alt="Video thumbnail for <?= htmlspecialchars($item['id']) ?>" 
                                                     style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 3em; color: rgba(255,255,255,0.8); pointer-events: none;">
                                                    <i class="fas fa-play-circle"></i>
                                                </div>
                                            </div>
                                     <?php endif; ?>
                                 </a>
                                 <div class="card-body">
                                     <p class="text-muted mb-0">Uploaded: <?= date('M d, Y', strtotime($item['created_at'])) ?></p>
                                 </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if(count($mediaItems) > 20) : // Simple message for many items ?>
                         <p class="text-center mt-3 text-muted">Showing <?= count($mediaItems) ?> items.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <aside class="right-sidebar">
            <?php include 'assets/add_ons.php'; ?>
        </aside>
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (sidebar && hamburger && !sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });       
    </script>
</body>
</html>
