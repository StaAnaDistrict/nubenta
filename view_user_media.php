<?php
error_log("DEBUG_VM_1: Script Start");
session_start(); // Make sure this is here
error_log("DEBUG_SESSION_USER_STRUCTURE: " . print_r($_SESSION['user'], true)); // <-- ADD THIS LINE

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

error_log("DEBUG_VM_2: After includes");

// 1. Initial Setup
if (!isset($_SESSION['user'])) {
    error_log("DEBUG_VM_ERROR: User not in session");
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
    error_log("DEBUG_VM_ERROR: Invalid User ID. UserId: " . print_r($userId, true));
    $_SESSION['error_message'] = "Invalid user ID specified.";
    header("Location: dashboard.php");
    exit();
}

$mediaTypeFilter = filter_input(INPUT_GET, 'media_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($mediaTypeFilter) || !in_array($mediaTypeFilter, ['photo', 'video'])) {
    $mediaTypeFilter = 'photo'; // Default to 'photo'
}

error_log("DEBUG_VM_3: Parameters processed. UserID: {$userId}, MediaType: {$mediaTypeFilter}");

// 3. Fetch Target User Details
$targetUser = null;
$errorMessage = null;
try {
    $userStmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = ?");
    if ($userStmt) {
        $userStmt->execute([$userId]);
        $targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        error_log("DEBUG_VM_ERROR: Failed to prepare user statement.");
        $errorMessage = "Error fetching user details.";
    }
} catch (PDOException $e) {
    error_log("DEBUG_VM_PDO_ERROR (User Fetch): " . $e->getMessage());
    $errorMessage = "Database error fetching user details.";
}

$pageTitle = "User Media - Nubenta";
$headerTitle = "User's Media";

if (!$errorMessage && !$targetUser) { // Check $errorMessage first
    $errorMessage = "User not found.";
    error_log("DEBUG_VM_ERROR: User not found after query. UserID: {$userId}");
} elseif (!$errorMessage) {
    $targetUserName = htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']);
    $pageTitle = $targetUserName . "'s " . ucfirst($mediaTypeFilter) . "s - Nubenta";
    $headerTitle = $targetUserName . "'s " . ucfirst($mediaTypeFilter) . "s";
}

error_log("DEBUG_VM_4: Target user details fetched. ErrorMessage: " . print_r($errorMessage, true));

// 4. Determine Friendship Status
$areFriends = false;
if ($targetUser && !$errorMessage && $userId != $currentUser['id']) {
    try {
        $friendStmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = :currentUserId AND receiver_id = :targetUserId) OR (sender_id = :targetUserId AND receiver_id = :currentUserId))
            AND status = 'accepted'
        ");
        if ($friendStmt) {
            $friendStmt->execute(['currentUserId' => $currentUser['id'], 'targetUserId' => $userId]);
            $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
            if ($friendship) {
                $areFriends = ($friendship['is_friend'] > 0);
            }
        } else {
            error_log("DEBUG_VM_ERROR: Failed to prepare friend statement.");
            // Decide if this is a fatal error or if $areFriends can remain false
        }
    } catch (PDOException $e) {
        error_log("DEBUG_VM_PDO_ERROR (Friend Check): " . $e->getMessage());
        // Decide if this is a fatal error or if $areFriends can remain false
    }
} elseif ($targetUser && !$errorMessage && $userId == $currentUser['id']) {
    $areFriends = true;
}

error_log("DEBUG_VM_5: Friendship status determined. AreFriends: " . ($areFriends ? 'Yes' : 'No'));

// 5. Fetch Media Items
$mediaItems = [];
if ($targetUser && !$errorMessage) {
    // Using direct embedding for $userId as it's validated as INT.
    // This is the query we are trying to make absolutely safe from param count errors.
     $sql = "SELECT DISTINCT um.id, um.user_id as media_owner_id, um.media_url, um.media_type, um.created_at, um.privacy as media_visibility, uma.privacy as album_privacy, uma.user_id as album_owner_id
            FROM user_media um
            LEFT JOIN album_media am ON um.id = am.media_id 
            LEFT JOIN user_media_albums uma ON am.album_id = uma.id
            WHERE um.user_id = " . intval($userId); // Ensuring $userId is int, then embedding.

    if ($mediaTypeFilter === 'photo') {
        $sql .= " AND um.media_type LIKE 'image/%'";
    } elseif ($mediaTypeFilter === 'video') {
        $sql .= " AND um.media_type LIKE 'video/%'";
    }
    
    $privacySqlParts = [];
    if ($currentUser['id'] == $userId) {
        $privacySqlParts[] = "1=1";
    } else {
        $privacySqlParts[] = "um.privacy = 'public'";
        $privacySqlParts[] = "(am.album_id IS NULL AND um.privacy = 'public')";
        $privacySqlParts[] = "uma.privacy = 'public'";
        if ($areFriends) {
            $privacySqlParts[] = "um.privacy = 'friends'";
            $privacySqlParts[] = "(am.album_id IS NULL AND um.privacy = 'friends')";
            $privacySqlParts[] = "uma.privacy = 'friends'";
        }
    }
    
    if (!empty($privacySqlParts)) {
      $sql .= " AND (" . implode(" OR ", $privacySqlParts) . ")";
    }

    $sql .= " ORDER BY um.created_at DESC";

    // DEBUGGING CODE - START
    error_log("VIEW_USER_MEDIA_DEBUG_ATTEMPT_2: SQL Query: >>>" . $sql . "<<<");
    error_log("VIEW_USER_MEDIA_DEBUG_ATTEMPT_2: Parameters: >>>" . print_r($params, true) . "<<<");
    // DEBUGGING CODE - END
    
    try {
        $mediaStmt = $pdo->prepare($sql);
        if ($mediaStmt) {
            // Execute with an empty array as all parameters are embedded or part of the SQL string logic.
            $mediaStmt->execute(); 
            $mediaItems = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("DEBUG_VM_7_EXECUTE_SUCCESS: Media items count: " . count($mediaItems));
        } else {
            error_log("DEBUG_VM_ERROR: Failed to prepare media statement.");
            $errorMessage = "Error fetching media content.";
        }
    } catch (PDOException $e) {
        error_log("DEBUG_VM_PDO_ERROR (Media Fetch): " . $e->getMessage());
        $errorMessage = "Database error fetching media content. Details: " . $e->getMessage(); // Provide more detail
    }
}
error_log("DEBUG_VM_8: Script End of PHP block. ErrorMessage: " . print_r($errorMessage, true));
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
                                    <?php if (strpos($item['media_type'], 'image/') === 0): ?>
                                        <a href="<?= htmlspecialchars($item['media_url']) ?>" data-bs-toggle="modal" data-bs-target="#mediaModal" data-media-url="<?= htmlspecialchars($item['media_url']) ?>" data-media-type="image">
                                            <img src="<?= htmlspecialchars($item['media_url']) ?>" alt="<?= htmlspecialchars($item['title'] ?? 'User media') ?>">
                                        </a>
                                    <?php elseif (strpos($item['media_type'], 'video/') === 0): ?>
                                        <video controls>
                                            <source src="<?= htmlspecialchars($item['media_url']) ?>" type="<?= htmlspecialchars($item['media_type']) ?>">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        
                                        <p class="text-muted">Uploaded: <?= date('M d, Y', strtotime($item['created_at'])) ?></p>
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

    <!-- Modal for displaying media -->
    <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mediaModalLabel">View Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid" alt="Media content" style="display:none; max-height: 80vh;">
                    <video src="" id="modalVideo" class="img-fluid" controls style="display:none; max-height: 80vh;"></video>
                </div>
            </div>
        </div>
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

        // Modal media display logic
        const mediaModal = document.getElementById('mediaModal');
        if (mediaModal) {
            mediaModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const mediaUrl = button.getAttribute('data-media-url');
                const mediaType = button.getAttribute('data-media-type');
                
                const modalImage = mediaModal.querySelector('#modalImage');
                const modalVideo = mediaModal.querySelector('#modalVideo');
                const modalTitle = mediaModal.querySelector('.modal-title');

                modalImage.style.display = 'none';
                modalVideo.style.display = 'none';

                if (mediaType === 'image') {
                    modalImage.src = mediaUrl;
                    modalImage.style.display = 'block';
                    modalTitle.textContent = 'View Image';
                } else if (mediaType === 'video') { // Though video is not directly opening modal in this iteration
                    modalVideo.src = mediaUrl;
                    modalVideo.style.display = 'block';
                    modalTitle.textContent = 'View Video';
                    modalVideo.load(); // Ensure video loads
                }
            });
            // Stop video when modal is closed
            mediaModal.addEventListener('hide.bs.modal', function () {
                const modalVideo = mediaModal.querySelector('#modalVideo');
                if (!modalVideo.paused) {
                    modalVideo.pause();
                }
                modalVideo.src = ""; // Clear src to stop background loading
            });
        }
    </script>
</body>
</html>
