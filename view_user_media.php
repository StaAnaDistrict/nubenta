<?php
error_log("DEBUG_VM_1: Script Start (ATTEMPT_4)");
// session_start(); // Commented out as per previous fix

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if session is already active before starting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

error_log("DEBUG_VM_2: After includes (ATTEMPT_4)");

// 1. Initial Setup
if (!isset($_SESSION['user'])) {
    error_log("DEBUG_VM_ERROR: User not in session (ATTEMPT_4)");
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
    error_log("DEBUG_VM_ERROR: Invalid User ID. UserId: " . print_r($userId, true) . " (ATTEMPT_4)");
    $_SESSION['error_message'] = "Invalid user ID specified.";
    header("Location: dashboard.php");
    exit();
}

$mediaTypeFilter = filter_input(INPUT_GET, 'media_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($mediaTypeFilter) || !in_array($mediaTypeFilter, ['photo', 'video'])) {
    $mediaTypeFilter = 'photo'; // Default to 'photo'
}

error_log("DEBUG_VM_3: Parameters processed. UserID: {$userId}, MediaType: {$mediaTypeFilter} (ATTEMPT_4)");

// 3. Fetch Target User Details
$targetUser = null;
$errorMessage = null;
try {
    $userStmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    if ($userStmt) {
        $userStmt->execute([$userId]);
        $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("DEBUG_VM_ERROR: Failed to prepare user statement. (ATTEMPT_4)");
        $errorMessage = "Error fetching user details.";
    }
} catch (PDOException $e) {
    error_log("DEBUG_VM_PDO_ERROR (User Fetch): " . $e->getMessage() . " (ATTEMPT_4)");
    $errorMessage = "Database error fetching user details.";
}

$pageTitle = "User Media - Nubenta";
$headerTitle = "User's Media";

if (!$errorMessage && !$targetUser) {
    $errorMessage = "User not found.";
    error_log("DEBUG_VM_ERROR: User not found after query. UserID: {$userId} (ATTEMPT_4)");
} elseif (!$errorMessage && $targetUser) {
    $targetUserName = htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']);
    $pageTitle = $targetUserName . "'s " . ucfirst($mediaTypeFilter) . "s - Nubenta";
    $headerTitle = $targetUserName . "'s " . ucfirst($mediaTypeFilter) . "s";
}

error_log("DEBUG_VM_4: Target user details fetched. ErrorMessage: " . print_r($errorMessage, true) . " (ATTEMPT_4)");

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
        error_log("DEBUG_VM_FRIEND_CHECK_SQL (ATTEMPT_4): " . $friendSql);
        $friendStmt = $pdo->prepare($friendSql);
        if ($friendStmt) {
            $friendStmt->execute();
            $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
            if ($friendship) {
                $areFriends = ($friendship['is_friend'] > 0);
            }
        } else {
            error_log("DEBUG_VM_ERROR: Failed to prepare friend statement. (ATTEMPT_4)");
        }
    } catch (PDOException $e) {
        error_log("DEBUG_VM_PDO_ERROR (Friend Check): " . $e->getMessage() . " (ATTEMPT_4)");
    }
} elseif ($targetUser && !$errorMessage && $userId == $currentUser['id']) {
    $areFriends = true;
}

error_log("DEBUG_VM_5: Friendship status determined. AreFriends: " . ($areFriends ? 'Yes' : 'No') . " (ATTEMPT_4)");

// 5. Fetch Media Items (Restored complex query with TRIM())
$mediaItems = [];
if ($targetUser && !$errorMessage) {
    $sql = "SELECT DISTINCT um.id, um.user_id as media_owner_id, um.media_url, um.media_type, um.thumbnail_url, um.created_at,
                   um.privacy as media_item_privacy, uma.privacy as album_privacy, uma.user_id as album_owner_id
            FROM user_media um
            LEFT JOIN album_media am ON um.id = am.media_id 
            LEFT JOIN user_media_albums uma ON am.album_id = uma.id
            WHERE um.user_id = " . intval($userId); // $userId is validated int

    if ($mediaTypeFilter === 'photo') {
        $sql .= " AND TRIM(um.media_type) = 'image'"; // Use TRIM and precise '=' for enum 'image'
    } elseif ($mediaTypeFilter === 'video') {
        $sql .= " AND TRIM(um.media_type) = 'video'"; // Use TRIM and precise '=' for enum 'video'
    }
    
    $privacySqlParts = [];
    if ($currentUser['id'] == $userId) { // User is viewing their own media
        $privacySqlParts[] = "1=1"; // Can see everything
    } else { // Not the owner
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

    error_log("DEBUG_VM_6_SQL (ATTEMPT_4): " . $sql);
    
    try {
        $mediaStmt = $pdo->prepare($sql);
        if ($mediaStmt) {
            $mediaStmt->execute(); // No parameters as $userId is embedded
            $mediaItems = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("DEBUG_VM_7_EXECUTE_SUCCESS (ATTEMPT_4): Media items count: " . count($mediaItems));
        } else {
            error_log("DEBUG_VM_ERROR: Failed to prepare media statement. (ATTEMPT_4)");
            $errorMessage = "Error fetching media content.";
        }
    } catch (PDOException $e) {
        error_log("DEBUG_VM_PDO_ERROR (Media Fetch): " . $e->getMessage() . " (ATTEMPT_4)");
        $errorMessage = "Database error fetching media content. Details: " . $e->getMessage();
    }
}
error_log("DEBUG_VM_8: Script End of PHP block. ErrorMessage: " . print_r($errorMessage, true) . " (ATTEMPT_4)");
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
