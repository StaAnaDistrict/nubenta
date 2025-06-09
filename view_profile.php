<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
require_once 'db.php';
require_once 'includes/MediaParser.php';                // PDO $pdo
require_once 'includes/FollowManager.php';

if(!isset($_SESSION['user'])){header('Location:login.php');exit;}
$current = $_SESSION['user'];
$profileId = intval($_GET['id'] ?? 0);
if($profileId===0){die('No ID');}

// fetch
$sql="SELECT id,
       profile_pic,
       custom_theme,
       CONCAT_WS(' ',first_name,middle_name,last_name) AS full_name,
       bio, gender, birthdate, relationship_status,
       location, hometown, company,
       schools, occupation, affiliations, hobbies,
       favorite_books, favorite_tv, favorite_movies, favorite_music,
       created_at, last_login, last_seen
FROM users WHERE id = ?";
$st=$pdo->prepare($sql);$st->execute([$profileId]);
$u=$st->fetch(PDO::FETCH_ASSOC);
if(!$u) die('User not found');

// --- FollowManager Integration ---
$followManager = new FollowManager($pdo);


// $current is defined earlier, $profileId is also defined.
// $pdo is available from db.php (FollowManager should be instantiated already, e.g., $followManager = new FollowManager($pdo);)

if (isset($current['id']) && $current['id'] != $profileId) { // Only check if logged in and not viewing own profile
    
    // DEBUG LINES TO ADD:
    error_log("[VIEW_PROFILE_DEBUG] Current User ID (followerId): " . $current['id'] . " (Type: " . gettype($current['id']) . ")");
    error_log("[VIEW_PROFILE_DEBUG] Profile User ID (followedEntityId): " . $profileId . " (Type: " . gettype($profileId) . ")");
    
    $isFollowing = $followManager->isFollowing((int)$current['id'], (string)$profileId, 'user');
    
    // DEBUG LINE TO ADD:
    error_log("[VIEW_PROFILE_DEBUG] Result of isFollowing() on page load: " . ($isFollowing ? 'true' : 'false'));

}
// This line is likely outside the if, which is fine
$followerCount = $followManager->getFollowersCount((string)$profileId, 'user'); 
// --- End FollowManager Integration ---

/* ---------------------------------------------------
   What is *my* relationship with the profile owner ?
   ---------------------------------------------------*/
   $relStmt = $pdo->prepare(
    "SELECT id, sender_id, receiver_id, status
     FROM friend_requests
     WHERE (sender_id = ? AND receiver_id = ?)
        OR (sender_id = ? AND receiver_id = ?)
     LIMIT 1");
 $relStmt->execute([$current['id'], $profileId, $profileId, $current['id']]);
 $rel = $relStmt->fetch(PDO::FETCH_ASSOC);

 $friendBtnState = 'add';           // default
 if ($rel) {
     if     ($rel['status'] === 'accepted')  $friendBtnState = 'friends';
     elseif ($rel['status'] === 'pending') {
         $friendBtnState = ($rel['sender_id'] == $current['id'])
                         ? 'pending_sent'     // I sent; waiting
                         : 'pending_recv';    // they sent; I must answer
     }
 }


// simple follower / friend counts (dummy until wired)
// $followerCount = 0;      // placeholder
$friendStatus  = 'none'; // placeholder
// $isFollowing   = false;  // placeholder
// $followerCount and $isFollowing are now handled by FollowManager integration above.

// Get user's albums for Media Gallery section
$albumStmt = $pdo->prepare("
    SELECT a.*,
           COUNT(DISTINCT am.media_id) AS media_count,
           m.media_url AS cover_image,
           CASE WHEN a.album_name = 'Default Gallery' THEN 'My Gallery' ELSE a.album_name END AS display_name,
           CASE WHEN a.album_name = 'Default Gallery' THEN 'Default media gallery containing all uploaded photos and videos' ELSE a.description END AS display_description
    FROM user_media_albums a
    LEFT JOIN album_media am ON a.id = am.album_id
    LEFT JOIN user_media m ON a.cover_image_id = m.id
    WHERE a.user_id = ?
    GROUP BY a.id
    ORDER BY CASE WHEN a.id = 1 THEN 0 ELSE 1 END, a.created_at DESC
");
$albumStmt->execute([$profileId]);
$userAlbums = $albumStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if current user can view albums (privacy check)
$canViewAlbums = false;
if ($current['id'] === $profileId) {
    // Own profile - can view all
    $canViewAlbums = true;
} else {
    // Check friendship status for privacy
    $areFriends = false;
    if ($rel && $rel['status'] === 'accepted') {
        $areFriends = true;
    }

    // Filter albums based on privacy and friendship
    $filteredAlbums = [];
    foreach ($userAlbums as $album) {
        if ($album['privacy'] === 'public' ||
            ($album['privacy'] === 'friends' && $areFriends)) {
            $filteredAlbums[] = $album;
        }
    }
    $userAlbums = $filteredAlbums; // This is the final list of viewable albums
    $canViewAlbums = !empty($userAlbums);
}

// For display purposes and "View All" button logic
$totalViewableAlbums = count($userAlbums);
$albumsToDisplay = $canViewAlbums ? array_slice($userAlbums, 0, 5) : [];
$displayLimitAlbums = 5;


// Get total number of friends for Connections section
$totalFriendsCountStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id) as total_friends
    FROM friend_requests fr
    JOIN users u ON (
        CASE
            WHEN fr.sender_id = :profileId1 THEN fr.receiver_id = u.id
            WHEN fr.receiver_id = :profileId2 THEN fr.sender_id = u.id
        END
    )
    WHERE (fr.sender_id = :profileId3 OR fr.receiver_id = :profileId4)
    AND fr.status = 'accepted'
");
$totalFriendsCountStmt->execute([
    ':profileId1' => $profileId,
    ':profileId2' => $profileId,
    ':profileId3' => $profileId,
    ':profileId4' => $profileId
]);
$totalFriendsCountResult = $totalFriendsCountStmt->fetch(PDO::FETCH_ASSOC);
$totalFriendsCount = $totalFriendsCountResult ? (int)$totalFriendsCountResult['total_friends'] : 0;
$displayLimitFriends = 6;

// Get user's friends for Connections section (limited display)
$friendsStmt = $pdo->prepare("
    SELECT
        u.id,
        CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name,
        u.profile_pic,
        u.gender,
        fr.created_at as friendship_date
    FROM friend_requests fr
    JOIN users u ON (
        CASE
            WHEN fr.sender_id = ? THEN fr.receiver_id = u.id
            WHEN fr.receiver_id = ? THEN fr.sender_id = u.id
        END
    )
    WHERE (fr.sender_id = ? OR fr.receiver_id = ?)
    AND fr.status = 'accepted'
    ORDER BY fr.created_at DESC
    LIMIT 6
");
$friendsStmt->execute([$profileId, $profileId, $profileId, $profileId]);
$userFriends = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if current user can view friends list
$canViewFriends = false;
if ($current['id'] === $profileId) {
    // Own profile - can view all friends
    $canViewFriends = true;
} else {
    // For other users, show friends if they are friends with the profile owner
    // or if the profile owner has public friend visibility (we'll assume public for now)
    $canViewFriends = true; // You can add privacy settings for friends list later
}

// Update current user's last_seen timestamp (for online status tracking)
if (isset($_SESSION['user']['id'])) {
    try {
        $updateLastSeenStmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
        $updateLastSeenStmt->execute([$_SESSION['user']['id']]);
    } catch (Exception $e) {
        // Silently handle if last_seen column doesn't exist yet
        error_log("Could not update last_seen: " . $e->getMessage());
    }
}

// Calculate online status for the profile user
function getOnlineStatus($lastSeen) {
    if (empty($lastSeen)) {
        return 'Never logged in';
    }

    // Set timezone to match your location
    date_default_timezone_set('Asia/Manila');

    try {
        // Use simple timestamp calculation for more accuracy
        $lastSeenTimestamp = strtotime($lastSeen);
        $currentTimestamp = time();
        $diffSeconds = $currentTimestamp - $lastSeenTimestamp;

        // Convert to minutes
        $diffMinutes = floor($diffSeconds / 60);

        // Consider user online if last seen within 5 minutes
        if ($diffMinutes < 5) {
            return 'Online';
        } elseif ($diffMinutes < 60) {
            return $diffMinutes . ' minute' . ($diffMinutes > 1 ? 's' : '') . ' ago';
        } elseif ($diffMinutes < (24 * 60)) { // Less than 24 hours
            $diffHours = floor($diffMinutes / 60);
            return $diffHours . ' hour' . ($diffHours > 1 ? 's' : '') . ' ago';
        } elseif ($diffMinutes < (7 * 24 * 60)) { // Less than 7 days
            $diffDays = floor($diffMinutes / (24 * 60));
            return $diffDays . ' day' . ($diffDays > 1 ? 's' : '') . ' ago';
        } elseif ($diffMinutes < (30 * 24 * 60)) { // Less than 30 days
            $diffWeeks = floor($diffMinutes / (7 * 24 * 60));
            return $diffWeeks . ' week' . ($diffWeeks > 1 ? 's' : '') . ' ago';
        } elseif ($diffMinutes < (365 * 24 * 60)) { // Less than 365 days
            $diffMonths = floor($diffMinutes / (30 * 24 * 60));
            return $diffMonths . ' month' . ($diffMonths > 1 ? 's' : '') . ' ago';
        } else {
            $diffYears = floor($diffMinutes / (365 * 24 * 60));
            return $diffYears . ' year' . ($diffYears > 1 ? 's' : '') . ' ago';
        }
    } catch (Exception $e) {
        return 'Status unavailable';
    }
}

// Check if last_seen column exists and get online status
$onlineStatus = 'Status unavailable';
try {
    $onlineStatus = getOnlineStatus($u['last_seen'] ?? null);
} catch (Exception $e) {
    // Fallback to last_login if last_seen doesn't exist
    if (!empty($u['last_login'])) {
        $lastLogin = new DateTime($u['last_login']);
        $now = new DateTime();
        $diff = $lastLogin->diff($now);

        if ($diff->days == 0 && $diff->h < 1) {
            $onlineStatus = 'Recently active';
        } else {
            $onlineStatus = 'Last seen ' . $lastLogin->format('M d, Y');
        }
    } else {
        $onlineStatus = 'Status unavailable';
    }
}

// (optional) log profile view here…

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($u['full_name']) ?>'s Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/viewprofile.css?v=<?= time() ?>" rel="stylesheet">
    <!-- Dashboard.php CSS files for reactions and comments -->
    <link rel="stylesheet" href="assets/css/reactions.css">
    <link rel="stylesheet" href="assets/css/simple-reactions.css">
    <link rel="stylesheet" href="assets/css/comments.css">

    <?php if (!empty($u['custom_theme'])): ?>
        <?= $u['custom_theme'] ?>
<?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="<?= isset($_SESSION['user']) ? 'dashboard.php' : 'index.html' ?>">Nubenta</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= isset($_SESSION['user']) ? 'dashboard.php' : 'index.html' ?>">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_profile.php?id=<?= $current['id'] ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="friends.php">
                            <i class="fas fa-users"></i> Connections
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">
                            <i class="fas fa-search"></i> Search
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="help.php">
                            <i class="fas fa-question-circle"></i> Help
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Header -->
  <div class="profile-header">
            <div class="row">
                <!-- Left Column: Profile Picture and Action Buttons -->
                <div class="col-md-4">
                    <div class="profile-pic-container">
  <?php if (!empty($u['profile_pic'])): ?>
   <img src="uploads/profile_pics/<?= htmlspecialchars($u['profile_pic']) ?>"
                                 alt="Profile Picture" class="profile-pic">
                        <?php else: ?>
                            <?php
                            $defaultPic = 'assets/images/MaleDefaultProfilePicture.png';
                            if (isset($u['gender']) && $u['gender'] === 'Female') {
                                $defaultPic = 'assets/images/FemaleDefaultProfilePicture.png';
                            }
                            ?>
                            <img src="<?= $defaultPic ?>" alt="Default Avatar" class="profile-pic">
                        <?php endif; ?>
                    </div>
                    <div class="profile-actions">
                        <div class="action-column">
                            <?php if ($current['id'] !== $profileId): ?>
                                <button class="btn btn-primary mb-2" onclick="startMessage(<?= $profileId ?>)">Send Message</button>
                            <?php endif; ?>
                            <?php if ($current['id'] !== $profileId): ?>
                                <?php if ($friendBtnState === 'add'): ?>
                                    <button id="addFriend"
                                            data-id="<?= $profileId ?>"
                                            class="btn btn-outline-primary mb-2">Add as Friend</button>

                                <?php elseif ($friendBtnState === 'pending_sent'): ?>
                                    <button class="btn btn-secondary mb-2" disabled>Request sent</button>

                                <?php elseif ($friendBtnState === 'pending_recv'): ?>
                                    <button id="acceptReq"
                                            data-req="<?= $rel['id'] ?>"
                                            class="btn btn-primary mb-2">Accept</button>
                                    <button id="declineReq"
                                            data-req="<?= $rel['id'] ?>"
                                            class="btn btn-outline-secondary mb-2">Decline</button>

                                <?php elseif ($friendBtnState === 'friends'): ?>
                                    <button id="unfriend"
                                            data-id="<?= $profileId ?>"
                                            class="btn btn-danger mb-2">Unfriend</button>
                                <?php endif; ?>

                            <?php endif; ?>
                            <button class="btn btn-outline-primary mb-2">Refer to Friend</button>
                            <?php if ($current['id'] !== $profileId): // Only show follow button if not viewing own profile ?>
                                <?php if ($isFollowing): ?>
                                    <button id="followButton" data-profile-id="<?= $profileId ?>" class="btn btn-primary mb-2">Following</button>
                                <?php else: ?>
                                    <button id="followButton" data-profile-id="<?= $profileId ?>" class="btn btn-outline-primary mb-2">Follow</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="action-column">
                            <button class="btn btn-outline-primary mb-2" onclick="openWriteTestimonialModal(<?= $profileId ?>)">Add Testimonial</button>
                            <a href="view_user_media.php?id=<?= htmlspecialchars($profileId) ?>&media_type=photo" class="btn btn-outline-primary mb-2">View Photos</a>
                            <a href="view_user_media.php?id=<?= htmlspecialchars($profileId) ?>&media_type=video" class="btn btn-outline-primary mb-2">View Videos</a>
                            <button class="btn btn-outline-primary">View Website</button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Profile Info -->
                <div class="col-md-8">
                    <div class="profile-info">
                        <h1 class="profile-name"><?= htmlspecialchars($u['full_name']) ?></h1>
                        <?php if (!empty($u['bio'])): ?>
                            <div class="profile-bio"><?= nl2br(htmlspecialchars($u['bio'])) ?></div>
<?php endif; ?>

                        <div class="basic-info">
                            <?php
                            // Calculate age
                            $age = 0;
                            if (!empty($u['birthdate'])) {
                                $birthdate = new DateTime($u['birthdate']);
                                $today = new DateTime();
                                $age = $birthdate->diff($today)->y;
                            }

                            // Format member since date
                            $memberSinceFormatted = 'Not available';
                            if (!empty($u['created_at'])) {
                                error_log("Created at value: " . $u['created_at']);
                                $memberSince = new DateTime($u['created_at']);
                                $memberSinceFormatted = $memberSince->format('F Y');
                            }

                            // Format last login
                            $lastLoginFormatted = 'Not available';
                            if (!empty($u['last_login'])) {
                                // Set timezone to match MySQL
                                date_default_timezone_set('Asia/Manila');

                                error_log("Last login value from DB: " . $u['last_login']);

                                $lastLogin = new DateTime($u['last_login']);
                                $now = new DateTime();

                                error_log("Last login DateTime: " . $lastLogin->format('Y-m-d H:i:s'));
                                error_log("Current DateTime: " . $now->format('Y-m-d H:i:s'));

                                $diff = $lastLogin->diff($now);
                                error_log("Time difference - Days: " . $diff->days . ", Hours: " . $diff->h . ", Minutes: " . $diff->i);

                                if ($diff->days == 0) {
                                    if ($diff->h == 0) {
                                        if ($diff->i == 0) {
                                            $lastLoginFormatted = "Just now";
                                        } else {
                                            $lastLoginFormatted = $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
                                        }
                                    } else {
                                        $lastLoginFormatted = $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
                                    }
                                } elseif ($diff->days < 7) {
                                    $lastLoginFormatted = $diff->days . " day" . ($diff->days > 1 ? "s" : "") . " ago";
                                } else {
                                    $lastLoginFormatted = $lastLogin->format('M d, Y');
                                }

                                error_log("Formatted last login: " . $lastLoginFormatted);
                            }
                            ?>

                            <div class="info-line">
                                <?= htmlspecialchars($u['gender'] ?? 'Not specified') ?> •
                                <?= $age > 0 ? $age . ' years old' : 'Age not specified' ?> •
                                <?= htmlspecialchars($u['relationship_status'] ?? 'Not specified') ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Member Since:</span> <?= $memberSinceFormatted ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Location:</span> <?= htmlspecialchars($u['location'] ?? 'Not specified') ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Hometown:</span> <?= htmlspecialchars($u['hometown'] ?? 'Not specified') ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Last Seen Online:</span>
                                <span class="<?= $onlineStatus === 'Online' ? 'text-success' : 'text-muted' ?>">
                                    <?php if ($onlineStatus === 'Online'): ?>
                                        <i class="fas fa-circle me-1" style="font-size: 0.7em;"></i>
                                    <?php endif; ?>
                                    <?= $onlineStatus ?>
                                </span>
                            </div>
                            <!-- Average Star Rating Display START -->
                            <?php
                            try {
                                $ratingStmt = $pdo->prepare("
                                    SELECT AVG(rating) as avg_rating, COUNT(rating) as total_ratings
                                    FROM testimonials
                                    WHERE recipient_user_id = ? AND status = 'approved' AND rating IS NOT NULL AND rating > 0
                                ");
                                $ratingStmt->execute([$profileId]);
                                $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
                                
                                echo '<div class="info-line" id="average-rating-section">'; // Added ID for potential JS interaction
                                echo '<span class="info-label">Average Rating:</span> ';
                                if ($ratingData && $ratingData['total_ratings'] > 0) {
                                    $avgRating = round($ratingData['avg_rating'], 1);
                                    echo '<span class="text-muted">';
                                    
                                    // Display stars (PHP rendering)
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= floor($avgRating)) {
                                            echo '<i class="fas fa-star" style="color: #2c3e50;"></i>';
                                        } elseif ($i - 0.5 <= $avgRating) {
                                            echo '<i class="fas fa-star-half-alt" style="color: #2c3e50;"></i>';
                                        } else {
                                            echo '<i class="far fa-star" style="color: #2c3e50;"></i>';
                                        }
                                    }
                                    
                                    echo ' ' . htmlspecialchars($avgRating) . ' ';
                                    echo '<span style="font-size: 0.9em;">(based on ' . htmlspecialchars($ratingData['total_ratings']) . ' rating' . ($ratingData['total_ratings'] > 1 ? 's' : '') . ')</span>';
                                    echo '</span>';
                                } else {
                                    echo '<span class="text-muted" style="font-size: 0.9em;">No ratings yet.</span>';
                                }
                                echo '</div>';
                            } catch (PDOException $e) {
                                error_log("Error getting average ratings: " . $e->getMessage());
                                echo '<div class="info-line" id="average-rating-section">';
                                echo '<span class="info-label">Average Rating:</span> ';
                                echo '<span class="text-muted" style="font-size: 0.9em;">Could not load ratings.</span>';
                                echo '</div>';
                            }
                            ?>
                            <!-- Average Star Rating Display END -->

                            <!-- Follower Count Display START -->
                            <div class="info-line" id="follower-count-section">
                                <span class="info-label"><i class="fas fa-users me-1"></i>Followers:</span>
                                <span id="followerCountDisplay"><?= htmlspecialchars($followerCount ?? 0) ?></span>
                            </div>
                            <!-- Follower Count Display END -->
                        </div>
                    </div>
                </div>
      </div>
  </div>

        <!-- Media Gallery Section -->
        <div class="profile-section" id="media-gallery-section">
            <h3 class="section-title">Media Gallery</h3>
            <?php if ($canViewAlbums && !empty($albumsToDisplay)): ?>
                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3 album-gallery">
                    <?php foreach ($albumsToDisplay as $album): ?>
                        <div class="col">
                            <div class="card h-100 album-card">
                                <a href="view_album.php?id=<?php echo $album['id']; ?>" class="text-decoration-none">
                                    <div class="album-cover position-relative">
                                        <?php if (!empty($album['cover_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($album['cover_image']); ?>"
                                                 class="card-img-top" alt="Album Cover"
                                                 style="height: 140px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="d-flex align-items-center justify-content-center bg-light"
                                                 style="height: 140px;">
                                                <i class="fas fa-images fa-2x text-muted"></i>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Media count overlay -->
                                        <div class="position-absolute bottom-0 end-0 m-2">
                                            <span class="badge bg-dark bg-opacity-75">
                                                <i class="fas fa-photo-video me-1"></i>
                                                <?php echo $album['media_count']; ?>
                                            </span>
                                        </div>

                                        <!-- Privacy indicator -->
                                        <?php if ($album['privacy'] !== 'public'): ?>
                                            <div class="position-absolute top-0 start-0 m-2">
                                                <span class="badge bg-secondary bg-opacity-75">
                                                    <i class="fas <?php echo $album['privacy'] === 'private' ? 'fa-lock' : 'fa-user-friends'; ?>"></i>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body">
                                        <h6 class="card-title mb-2 text-truncate" style="color: #2c3e50;">
                                            <?php echo htmlspecialchars($album['display_name']); ?>
                                        </h6>
                                        <?php if (!empty($album['display_description'])): ?>
                                            <p class="card-text text-muted small mb-2">
                                                <?php
                                                $description = $album['display_description'];
                                                echo htmlspecialchars(strlen($description) > 80 ? substr($description, 0, 77) . '...' : $description);
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($album['created_at'])); ?>
                                            </small>
                                            <small class="text-muted">
                                                <?php echo ucfirst($album['privacy']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalViewableAlbums > $displayLimitAlbums): ?>
                    <div class="text-center mt-3">
                        <a href="user_albums.php?id=<?= htmlspecialchars($profileId) ?>" class="btn btn-outline-secondary" style="color: #2c3e50; border-color: #2c3e50;">
                            <i class="fas fa-images me-1"></i> View All Albums (<?= $totalViewableAlbums ?>)
                        </a>
                    </div>
                <?php endif; ?>
            <?php elseif ($canViewAlbums && empty($totalViewableAlbums)): // Check against totalViewableAlbums for empty message ?>
                <div class="text-center py-4">
                    <i class="fas fa-photo-video fa-3x mb-3 text-muted"></i>
                    <p class="text-muted mb-0">No albums created yet.</p>
                    <?php if ($current['id'] === $profileId): ?>
                        <small class="text-muted">Create your first album to organize your photos and videos!</small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-lock fa-3x mb-3 text-muted"></i>
                    <p class="text-muted mb-0">This user's albums are private.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Connections Section -->
        <div class="profile-section" id="connections-section">
            <h3 class="section-title">Connections</h3>
            <?php if ($canViewFriends && !empty($userFriends)): ?>
                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-3 connections-gallery">
                    <?php foreach ($userFriends as $friend): ?>
                        <div class="col">
                            <div class="card h-100 friend-card">
                                <a href="view_profile.php?id=<?php echo $friend['id']; ?>" class="text-decoration-none">
                                    <div class="friend-avatar text-center py-4 px-2">
                                        <?php
                                        $friendProfilePic = !empty($friend['profile_pic'])
                                            ? 'uploads/profile_pics/' . htmlspecialchars($friend['profile_pic'])
                                            : ($friend['gender'] === 'Female' ? 'assets/images/FemaleDefaultProfilePicture.png' : 'assets/images/MaleDefaultProfilePicture.png');
                                        ?>
                                        <img src="<?php echo $friendProfilePic; ?>"
                                             alt="<?php echo htmlspecialchars($friend['full_name']); ?>"
                                             class="rounded-circle mb-3"
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                        <h6 class="friend-name mb-1 text-truncate" style="color: #2c3e50;">
                                            <?php echo htmlspecialchars($friend['full_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            Friends since <?php echo date('M Y', strtotime($friend['friendship_date'])); ?>
                                        </small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalFriendsCount > $displayLimitFriends): ?>
                    <div class="text-center mt-3">
                        <a href="user_connections.php?id=<?= htmlspecialchars($profileId) ?>" class="btn btn-outline-secondary" style="color: #2c3e50; border-color: #2c3e50;">
                            <i class="fas fa-users me-1"></i> View All Connections (<?= $totalFriendsCount ?>)
                        </a>
                    </div>
                <?php elseif (count($userFriends) > 0 && $totalFriendsCount <= $displayLimitFriends): ?>
                    <div class="text-center mt-3">
                        <small class="text-muted">Showing all connections</small>
                    </div>
                <?php endif; ?>

            <?php elseif ($canViewFriends && empty($userFriends)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-friends fa-3x mb-3 text-muted"></i>
                    <p class="text-muted mb-0">No connections yet.</p>
                    <?php if ($current['id'] === $profileId): ?>
                        <small class="text-muted">Connect with friends to build your network!</small>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-lock fa-3x mb-3 text-muted"></i>
                    <p class="text-muted mb-0">This user's connections are private.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- More About Me Section -->
  <div class="profile-section">
            <h3 class="section-title">More About Me</h3>
            <div class="row">
                <?php if (!empty($u['company'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Company / Affiliation</div>
                        <div class="info-value"><?= htmlspecialchars($u['company']) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['occupation'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Occupation</div>
                        <div class="info-value"><?= htmlspecialchars($u['occupation']) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['schools'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Schools Attended</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['schools'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['affiliations'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Affiliations</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['affiliations'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['hobbies'])): ?>
                    <div class="col-md-12 mb-3">
                        <div class="info-label">Hobbies and Interests</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['hobbies'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
  </div>
    
    <!-- Media Modal -->
    <div id="mediaModal" class="modal fade" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="mediaModalLabel">Media Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="mediaModalContent">
                        <!-- Content will be loaded here -->
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!-- Favorites Section -->
  <div class="profile-section">
            <h3 class="section-title">Favorites</h3>
            <div class="row">
                <?php if (!empty($u['favorite_books'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite Books</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_books'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['favorite_tv'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite TV Shows</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_tv'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['favorite_movies'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite Movies</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_movies'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['favorite_music'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite Music</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_music'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Testimonials Section -->
        <div class="profile-section" id="testimonials-section">
            <h3 class="section-title">Testimonials</h3>
            <div id="testimonials-container">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading testimonials...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading testimonials...</p>
                </div>
            </div>
            <div class="testimonials-actions mt-3 pt-3 border-top" style="display: block;">
                <div class="d-flex justify-content-between">
                    <a href="testimonials.php?user_id=<?= htmlspecialchars($profileId) ?>" class="btn btn-outline-secondary" style="color: #2c3e50; border-color: #2c3e50;">
                        <i class="fas fa-list me-1"></i>View All Testimonials
                    </a>
                    <?php if ($current['id'] !== $profileId): ?>
                        <button class="btn" style="background-color: #2c3e50; color: white;" onclick="openWriteTestimonialModal(<?= $profileId ?>)">
                            <i class="fas fa-star me-1"></i>Write a Testimonial
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Contents Section -->
        <div class="profile-section" id="contents-section">
            <h3 class="section-title">Contents</h3>
            <div class="row">
                <div class="col-12">
                    <div id="user-posts-container">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading posts...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading user contents...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="assets/js/utils.js" defer></script> 
    <script src="assets/js/view-album-reactions.js" defer></script> <!-- Key change here -->
    <script src="assets/js/media-handler.js" defer></script>
    <script src="assets/js/profile-tabs.js" defer></script>
    <script src="assets/js/popup-chat.js?v=<?= time() ?>" defer></script>

    <!-- Global flag to track reaction system initialization -->
    <script>
        window.reactionSystemInitialized = false;
    </script>

    <!-- Load only simple-reactions.js (SAME as dashboard.php) -->
    <script src="assets/js/simple-reactions.js"></script>

    <!-- Load other non-reaction scripts (SAME as dashboard.php) -->
    <script src="assets/js/comments.js?v=<?= time() ?>"></script>
    <script src="assets/js/comment-initializer.js"></script>
    <script src="assets/js/share.js"></script>
    <script src="assets/js/activity-tracker.js"></script>

    <!-- Custom JavaScript Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize album gallery functionality
        initializeAlbumGallery();

        // Load user posts in Contents section
        loadUserPosts();
        // Load testimonials
        loadTestimonials();
        // Handle anchor scrolling to specific posts
        handlePostAnchorScrolling();

        // Get the custom theme content
        const customTheme = <?= json_encode($u['custom_theme'] ?? '') ?>;

        // Extract JavaScript from custom theme
        if (customTheme) {
            const scriptMatch = customTheme.match(/<script[^>]*>([\s\S]*?)<\/script>/i);
            if (scriptMatch && scriptMatch[1]) {
                try {
                    // Create and execute the script
                    const script = document.createElement('script');
                    script.textContent = scriptMatch[1];
                    document.body.appendChild(script);
                } catch (error) {
                    console.error('Error executing custom script:', error);
                }
            }
        }
    });

    // Function to initialize album gallery - accessible for custom themes
    function initializeAlbumGallery() {
        const albumCards = document.querySelectorAll('.album-card');

        // Add custom event listeners that can be overridden by custom themes
        albumCards.forEach(card => {
            // Add data attributes for easy access in custom themes
            const link = card.querySelector('a[href*="view_album.php"]');
            if (link) {
                const albumId = link.href.match(/id=(\d+)/);
                if (albumId) {
                    card.setAttribute('data-album-id', albumId[1]);
                }
            }

            // Add hover effects that can be customized
            card.addEventListener('mouseenter', function() {
                this.classList.add('album-card-hover');
            });

            card.addEventListener('mouseleave', function() {
                this.classList.remove('album-card-hover');
            });
        });

        // Make album gallery section accessible for custom themes
        const gallerySection = document.getElementById('media-gallery-section');
        if (gallerySection) {
            gallerySection.setAttribute('data-customizable', 'true');
            gallerySection.setAttribute('data-section-type', 'album-gallery');
        }

        console.log('Album gallery initialized with', albumCards.length, 'albums');
    }

    // Function to load user posts
    async function loadUserPosts() {
        const profileUserId = <?= $profileId ?>;
        const currentUserId = <?= isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0 ?>;
        const postsContainer = document.getElementById('user-posts-container');

        try {
            console.log('Loading posts for user:', profileUserId);

            // Fetch user's posts
            const response = await fetch(`api/get_user_posts.php?user_id=${profileUserId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.posts && data.posts.length > 0) {
                console.log(`Loaded ${data.posts.length} posts for user ${profileUserId}`);

                let postsHTML = '';
                data.posts.forEach(post => {
                    postsHTML += renderUserPost(post, currentUserId);
                });

                postsContainer.innerHTML = postsHTML;

                // Initialize reactions and comments for the posts
                initializePostInteractions();

            } else {
                postsContainer.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-file-alt fa-3x mb-3 text-muted"></i>
                        <p class="text-muted mb-0">No posts to show yet.</p>
                        ${profileUserId == currentUserId ? '<small class="text-muted">Share your thoughts to get started!</small>' : ''}
                    </div>
                `;
            }

        } catch (error) {
            console.error('Error loading user posts:', error);
            postsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading posts: ${error.message}
                </div>
            `;
        }
    }

    // Function to render a user post (constrained to section width)
    function renderUserPost(post, currentUserId) {
        const isOwnPost = post.user_id == currentUserId;

        return `
            <article class="post mb-3" data-post-id="${post.id}" id="profile-post-${post.id}">
                <div class="card" style="max-width: 100%;">
                    <div class="card-body p-3">
                        <div class="post-header d-flex align-items-center mb-3">
                            <img src="${post.profile_pic || 'assets/images/default-profile.png'}"
                                 alt="Profile" class="rounded-circle me-3"
                                 style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <h6 class="mb-0" style="color: #2c3e50; font-size: 0.9rem;">${post.author}</h6>
                                <small class="text-muted" style="font-size: 0.8rem;">
                                    <i class="far fa-clock me-1"></i> ${new Date(post.created_at).toLocaleString()}
                                    ${post.visibility === 'friends' ? '<span class="ms-2"><i class="fas fa-user-friends"></i> Friends only</span>' : ''}
                                </small>
                            </div>
                        </div>

                        <div class="post-content">
                            ${post.is_flagged ? '<div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.</div>' : ''}
                            ${post.is_removed ? `<p class="text-danger mb-2"><i class="fas fa-exclamation-triangle me-1"></i> ${post.content}</p>` : `<p class="mb-2">${post.content}</p>`}
                            ${post.media && !post.is_removed ? renderPostMediaConstrained(post.media, post.is_flagged, post.id) : ''}
                        </div>

                        <div class="post-actions d-flex mt-3">
                            <button class="post-action-btn post-react-btn me-2" data-post-id="${post.id}">
                                <i class="far fa-smile me-1"></i> React
                            </button>
                            <button class="post-action-btn post-comment-btn me-2" data-post-id="${post.id}">
                                <i class="far fa-comment me-1"></i> Comment <span class="comment-count-badge"></span>
                            </button>
                            <button class="post-action-btn post-share-btn me-2" data-post-id="${post.id}">
                                <i class="far fa-share-square me-1"></i> Share
                            </button>
                            ${isOwnPost ? `
                                <button class="btn btn-sm btn-outline-danger me-2 post-delete-btn" data-post-id="${post.id}" style="font-size: 0.8rem;">
                                    <i class="far fa-trash-alt me-1"></i> Delete
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </article>
        `;
    }

    // Function to render post media constrained to section width
function renderPostMediaConstrained(media, isBlurred, postId) { // postId is crucial here
    if (!media) return '';

    const blurClass = isBlurred ? 'blurred-image' : '';
    let mediaArray;

    if (typeof media === 'string') {
        if (!media || media === '[]' || media === 'null') {
            return '';
        }
        try {
            const jsonDecoded = JSON.parse(media);
            if (Array.isArray(jsonDecoded)) {
                mediaArray = jsonDecoded.filter(path => path && path.trim() !== '');
            } else {
                const trimmedPath = media.trim();
                mediaArray = trimmedPath ? [trimmedPath] : [];
            }
        } catch (e) {
            const trimmedPath = media.trim();
            mediaArray = trimmedPath ? [trimmedPath] : [];
        }
    } else if (Array.isArray(media)) {
        mediaArray = media.filter(path => path && path.trim() !== '');
    } else {
        return '';
    }

    if (!mediaArray || mediaArray.length === 0) {
        return '';
    }

    if (mediaArray.length === 1) {
        const mediaItem = mediaArray[0];
        if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
            return `<div class="media mt-2">
                        <img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                             style="cursor: pointer; max-height: 250px; width: 100%; object-fit: cover; border-radius: 6px;"
                             onclick="openMediaModal('${postId}', 0)">
                    </div>`;
        } else if (mediaItem.match(/\.(mp4)$/i)) { // Added $ to mp4 to be more specific
            return `<div class="media mt-2 position-relative">
                        <video class="img-fluid ${blurClass} clickable-media"
                               style="cursor: pointer; max-height: 250px; width: 100%; object-fit: cover; border-radius: 6px;"
                               onclick="openMediaModal('${postId}', 0)">
                            <source src="${mediaItem}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <div class="play-icon-overlay" onclick="openMediaModal('${postId}', 0)"><i class="fas fa-play-circle"></i></div>
                    </div>`;
        }
    }

    let mediaHTML = '<div class="post-media-container mt-2"><div class="row g-1">';
    mediaArray.slice(0, 4).forEach((mediaItem, index) => {
        const colClass = mediaArray.length === 1 ? 'col-12' :
                       mediaArray.length === 2 ? 'col-6' :
                       (mediaArray.length === 3 && index === 0) ? 'col-12' : 'col-6';

        mediaHTML += `<div class="${colClass} ${ (mediaArray.length === 3 && index === 0) ? 'mb-1' : '' }">`;
        if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
            mediaHTML += `<img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                               style="cursor: pointer; height: 120px; width: 100%; object-fit: cover; border-radius: 6px;"
                               onclick="openMediaModal('${postId}', ${index})">`;
        } else if (mediaItem.match(/\.(mp4)$/i)) { // Added $ to mp4
            mediaHTML += `<div class="position-relative">
                            <video class="img-fluid ${blurClass} clickable-media"
                                 style="cursor: pointer; height: 120px; width: 100%; object-fit: cover; border-radius: 6px;"
                                 onclick="openMediaModal('${postId}', ${index})">
                              <source src="${mediaItem}" type="video/mp4">
                            </video>
                            <div class="play-icon-overlay" onclick="openMediaModal('${postId}', ${index})"><i class="fas fa-play-circle"></i></div>
                          </div>`;
        }
        
        if (mediaArray.length > 4 && index === 3) {
             mediaHTML = mediaHTML.replace(/<img src[^>]+>/, `
                <div class="position-relative clickable-media" onclick="openMediaModal('${postId}', ${index})">
                    <img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass}" style="cursor: pointer; height: 120px; width: 100%; object-fit: cover; border-radius: 6px;">
                    <div class="more-media-overlay">+${mediaArray.length - 4}</div>
                </div>
            `);
        }
        mediaHTML += '</div>';
    });

    mediaHTML += '</div></div>';
    return mediaHTML;
}
    // Function to render post media (reuse from dashboard)
    function renderPostMedia(media, isBlurred, postId) {
        if (!media) return '';

        const blurClass = isBlurred ? 'blurred-image' : '';
        let mediaArray;

        // Universal media parsing (matches PHP MediaParser logic)
        if (typeof media === 'string') {
            // Handle null or empty values
            if (!media || media === '[]' || media === 'null') {
                return '';
            }

            try {
                // Try to decode as JSON first
                const jsonDecoded = JSON.parse(media);
                if (Array.isArray(jsonDecoded)) {
                    // It's a JSON array - filter out empty values
                    mediaArray = jsonDecoded.filter(path => path && path.trim() !== '');
                } else {
                    // JSON but not array, treat as single string
                    const trimmedPath = media.trim();
                    mediaArray = trimmedPath ? [trimmedPath] : [];
                }
            } catch (e) {
                // If JSON decode failed, treat as single string path
                const trimmedPath = media.trim();
                mediaArray = trimmedPath ? [trimmedPath] : [];
            }
        } else if (Array.isArray(media)) {
            // Already an array - filter out empty values
            mediaArray = media.filter(path => path && path.trim() !== '');
        } else {
            return '';
        }

        if (!mediaArray || mediaArray.length === 0) {
            return '';
        }

        // For single media item
        if (mediaArray.length === 1) {
            const mediaItem = mediaArray[0];
            if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
                return `<div class="media mt-3">
                    <img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                         style="cursor: pointer; max-height: 400px; width: 100%; object-fit: cover; border-radius: 8px;">
                </div>`;
            } else if (mediaItem.match(/\.mp4$/i)) {
                return `<div class="media mt-3">
                    <video controls class="img-fluid ${blurClass}"
                           style="max-height: 400px; width: 100%; border-radius: 8px;">
                        <source src="${mediaItem}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>`;
            }
        }

        // For multiple media items - simplified grid
        let mediaHTML = '<div class="post-media-container mt-3"><div class="row g-2">';
        mediaArray.slice(0, 4).forEach((mediaItem, index) => {
            const colClass = mediaArray.length === 1 ? 'col-12' :
                           mediaArray.length === 2 ? 'col-6' :
                           index === 0 ? 'col-12' : 'col-6';

            mediaHTML += `<div class="${colClass}">`;
            if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
                mediaHTML += `<img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                                   style="cursor: pointer; height: 200px; width: 100%; object-fit: cover; border-radius: 8px;">`;
            } else if (mediaItem.match(/\.mp4$/i)) {
                mediaHTML += `<video controls class="img-fluid ${blurClass}"
                                     style="height: 200px; width: 100%; object-fit: cover; border-radius: 8px;">
                                  <source src="${mediaItem}" type="video/mp4">
                              </video>`;
            }
            mediaHTML += '</div>';
        });

        if (mediaArray.length > 4) {
            mediaHTML += `<div class="col-6 position-relative">
                <div class="d-flex align-items-center justify-content-center bg-dark text-white"
                     style="height: 200px; border-radius: 8px; cursor: pointer;">
                    <span class="h4">+${mediaArray.length - 4} more</span>
                </div>
            </div>`;
        }

        mediaHTML += '</div></div>';
        return mediaHTML;
    }

    // Function to initialize post interactions (connected to dashboard systems)
    function initializePostInteractions() {
        console.log('Initializing post interactions for Contents section');

        // Load comment counts for all posts
        document.querySelectorAll('.post[data-post-id]').forEach(post => {
            const postId = post.getAttribute('data-post-id');
            if (postId) {
                loadCommentCount(postId);
            }
        });

        // EXACT SAME event listeners as dashboard.php setupPostActionListeners()
        // First, remove any existing event listeners to prevent duplicates
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });

        // Now add fresh event listeners
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                console.log("Comment button clicked for post:", postId);

                if (window.CommentSystem && typeof window.CommentSystem.toggleCommentForm === 'function') {
                    window.CommentSystem.toggleCommentForm(postId);
                } else {
                    // Fallback to inline implementation
                    toggleCommentForm(postId);
                }
            });
        });

        // React button - handled by ReactionSystem
        // The ReactionSystem will attach its own event listeners

        // Set up share button listeners
        document.querySelectorAll('.post-share-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');

                if (window.ShareSystem && window.ShareSystem.sharePost) {
                    console.log('Using dashboard ShareSystem for post:', postId);
                    window.ShareSystem.sharePost(postId);
                } else {
                    console.log('Dashboard ShareSystem not available');
                    alert('Share functionality will be available soon!');
                }
            });
        });

        // Initialize reaction system for the newly loaded posts
        if (window.SimpleReactionSystem) {
            console.log("Initializing reactions for posts in Contents section (view_profile.php)");
            document.querySelectorAll('#user-posts-container .post').forEach(postElement => {
                const postId = postElement.getAttribute('data-post-id');
                if (postId && !postId.startsWith('social_')) { // Ensure it's a regular post
                    // console.log("Loading reactions for post (in profile contents):", postId);
                    try {
                        window.SimpleReactionSystem.loadReactions(postId, 'post'); // Assuming 'post' is the contentType
                    } catch (error) {
                        console.error("Error loading reactions for post " + postId + ":", error);
                    }
                }
            });
        } else {
            console.error("SimpleReactionSystem not found on view_profile.php for Contents section.");
        }
        // Inside initializePostInteractions() in view_profile.php
        document.querySelectorAll('#user-posts-container .post-delete-btn').forEach(btn => {
            // Remove existing listener to avoid duplicates
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                if (typeof handleDeletePost === 'function') {
                    handleDeletePost(postId);
                } else {
                    console.error('handleDeletePost function is not defined. Make sure dashboard-init.js is included.');
                    alert('Error: Delete function not available.');
                }
            });
        });
    }

    // EXACT SAME loadCommentCount function as dashboard.php
    async function loadCommentCount(postId) {
        try {
            console.log(`Loading comment count for post ${postId}`);
            const response = await fetch(`api/get_comment_count.php?post_id=${postId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success) {
                const count = data.count;
                const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);

                if (commentBtn) {
                    const countBadge = commentBtn.querySelector('.comment-count-badge');
                    if (countBadge) {
                        if (count > 0) {
                            countBadge.textContent = `(${count})`;
                        } else {
                            countBadge.textContent = '';
                        }
                    }
                }
            }
        } catch (error) {
            console.error(`Error loading comment count for post ${postId}:`, error);
        }
    }

    // EXACT SAME toggleCommentForm function as dashboard.php (adapted for view_profile.php)
    function toggleCommentForm(postId) {
        // Find the post element - use profile-specific selector
        const postElement = document.querySelector(`#profile-post-${postId}`);

        if (!postElement) {
            console.error(`Post element not found for ID: ${postId}`);
            return;
        }

        // Check if comments section already exists
        let commentsSection = postElement.querySelector('.comments-section');

        if (commentsSection) {
            // Toggle visibility
            if (commentsSection.classList.contains('d-none')) {
                commentsSection.classList.remove('d-none');
            } else {
                commentsSection.classList.add('d-none');
            }
            return;
        }

        // Create comments section
        commentsSection = document.createElement('div');
        commentsSection.className = 'comments-section mt-3';

        // Create comments container FIRST
        const commentsContainer = document.createElement('div');
        commentsContainer.className = 'comments-container mb-3';
        commentsContainer.dataset.postId = postId;

        // Add loading indicator
        commentsContainer.innerHTML = `
            <div class="text-center p-2">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading comments...</span>
                </div>
                <span class="ms-2">Loading comments...</span>
            </div>
        `;

        // Create comment form LAST
        const commentForm = document.createElement('form');
        commentForm.className = 'comment-form mb-3';
        commentForm.dataset.postId = postId;
        commentForm.id = `comment-form-${postId}`;

        commentForm.innerHTML = `
            <div class="input-group">
                <input type="text" class="form-control comment-input" placeholder="Write a comment...">
                <button type="submit" class="btn btn-primary">Post</button>
            </div>
        `;

        // Add elements in the CORRECT ORDER:
        // 1. Comments container (shows existing comments)
        // 2. Comment form (at the bottom)
        commentsSection.appendChild(commentsContainer);
        commentsSection.appendChild(commentForm);

        // Add to post
        const postActions = postElement.querySelector('.post-actions');
        if (postActions) {
            postActions.after(commentsSection);
        } else {
            postElement.appendChild(commentsSection);
        }

        // Load existing comments
        loadComments(postId);

        // Set up form submission
        setupCommentFormSubmission(postId);
    }

    // EXACT SAME setupCommentFormSubmission function as dashboard.php
    function setupCommentFormSubmission(postId) {
        const formId = `comment-form-${postId}`;
        const form = document.getElementById(formId);

        if (!form) {
            console.error(`Comment form not found with ID: ${formId}`);
            return;
        }

        // Remove any existing event listeners by cloning and replacing
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        // Add a flag to track if this form already has a listener
        if (newForm.dataset.hasListener === 'true') {
            console.log(`Form ${formId} already has a listener, skipping`);
            return;
        }
        newForm.dataset.hasListener = 'true';

        // Add the event listener to the fresh form
        newForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log(`Comment form submitted for post ${postId}`);

            const commentInput = this.querySelector('.comment-input');
            const commentContent = commentInput.value.trim();

            if (!commentContent) return;

            // Disable the submit button to prevent multiple submissions
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                console.log(`Submitting comment for post ${postId}: ${commentContent}`);

                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', commentContent);

                const response = await fetch('api/post_comment.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Comment submission response:', data);

                if (data.success) {
                    // Clear input
                    commentInput.value = '';

                    // Find the comments container
                    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
                    if (commentsContainer) {
                        // Reload comments
                        loadComments(postId, commentsContainer);

                        // Update comment count
                        loadCommentCount(postId);
                    }
                } else {
                    alert('Error posting comment: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while posting your comment.');
            } finally {
                // Re-enable the submit button
                submitButton.disabled = false;
            }
        });
    }

    // EXACT SAME loadComments function as dashboard.php
    async function loadComments(postId, commentsContainer = null) {
        try {
            console.log(`Loading comments for post ${postId}`);

            // If no container is provided, find it
            if (!commentsContainer) {
                commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
            }

            if (!commentsContainer) {
                console.error(`Comments container not found for post ${postId}`);
                return;
            }

            const response = await fetch(`api/get_comments.php?post_id=${postId}`);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            console.log('Comments data:', data);

            if (data.success && data.comments) {
                commentsContainer.innerHTML = '';

                if (data.comments.length === 0) {
                    commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
                    return;
                }

                data.comments.forEach(comment => {
                    const commentElement = document.createElement('div');
                    commentElement.className = 'comment mb-3';
                    commentElement.dataset.commentId = comment.id;

                    // Format the date
                    const commentDate = new Date(comment.created_at);
                    const formattedDate = commentDate.toLocaleString();

                    // Create the comment HTML with clickable profile elements
                    commentElement.innerHTML = `
                        <div class="d-flex comment-item">
                            <a href="view_profile.php?id=${comment.user_id}" class="text-decoration-none">
                                <img src="${comment.profile_pic || 'assets/images/default-profile.png'}" alt="${comment.author}"
                                     class="rounded-circle me-2" width="32" height="32" style="cursor: pointer;"
                                     title="View ${comment.author}'s profile">
                            </a>
                            <div class="comment-content flex-grow-1">
                                <div class="comment-bubble p-2 rounded">
                                    <a href="view_profile.php?id=${comment.user_id}" class="text-decoration-none">
                                        <div class="fw-bold" style="cursor: pointer; color: #2c3e50;" title="View ${comment.author}'s profile">${comment.author}</div>
                                    </a>
                                    <div>${comment.content}</div>
                                </div>
                                <div class="comment-actions mt-1">
                                    <small class="text-muted">${formattedDate}</small>
                                    <button class="reply-button" data-comment-id="${comment.id}">Reply</button>
                                    ${comment.is_own_comment ? '<button class="delete-comment-button" data-comment-id="' + comment.id + '">Delete</button>' : ''}
                                </div>
                                <div class="replies-container mt-2">
                                    ${comment.replies ? this.renderReplies(comment.replies) : ''}
                                </div>
                            </div>
                        </div>
                    `;

                    commentsContainer.appendChild(commentElement);
                });

                // Add event listeners for reply buttons and forms
                setupCommentInteractions(commentsContainer, postId);
            } else {
                commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            if (commentsContainer) {
                commentsContainer.innerHTML = '<p class="text-danger">Error loading comments. Please try again.</p>';
            }
        }
    }

    // EXACT SAME setupCommentInteractions function as dashboard.php
    function setupCommentInteractions(commentsContainer, postId) {
        // This function would handle reply buttons and other comment interactions
        // For now, we'll keep it simple to match the basic functionality
        console.log(`Setting up comment interactions for post ${postId}`);
    }

    // Fallback reaction picker when dashboard system isn't available
    function showSimpleReactionPicker(postId, button) {
        const reactions = [
            { type: 'love', emoji: '❤️', name: 'Love' },
            { type: 'like', emoji: '👍', name: 'Like' },
            { type: 'laugh', emoji: '😂', name: 'Laugh' },
            { type: 'wow', emoji: '😮', name: 'Wow' },
            { type: 'sad', emoji: '😢', name: 'Sad' },
            { type: 'angry', emoji: '😠', name: 'Angry' }
        ];

        // Create simple reaction picker
        const picker = document.createElement('div');
        picker.className = 'simple-reaction-picker';
        picker.style.cssText = `
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            gap: 5px;
        `;

        reactions.forEach(reaction => {
            const btn = document.createElement('button');
            btn.innerHTML = reaction.emoji;
            btn.title = reaction.name;
            btn.style.cssText = `
                border: none;
                background: none;
                font-size: 20px;
                padding: 5px;
                border-radius: 4px;
                cursor: pointer;
            `;
            btn.addEventListener('click', () => {
                submitReaction(postId, reaction.type);
                picker.remove();
            });
            picker.appendChild(btn);
        });

        // Position picker near button
        const rect = button.getBoundingClientRect();
        picker.style.left = rect.left + 'px';
        picker.style.top = (rect.top - 60) + 'px';

        document.body.appendChild(picker);

        // Remove picker when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function removePicker(e) {
                if (!picker.contains(e.target)) {
                    picker.remove();
                    document.removeEventListener('click', removePicker);
                }
            });
        }, 100);
    }

    // Submit reaction using dashboard API
    async function submitReaction(postId, reactionType) {
        try {
            const response = await fetch('api/post_reaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    post_id: postId,
                    reaction_type: reactionType
                })
            });

            const data = await response.json();
            if (data.success) {
                console.log('Reaction submitted successfully');
                // Reload reactions if dashboard system is available
                if (window.ReactionSystem && window.ReactionSystem.loadReactionsForVisiblePosts) {
                    window.ReactionSystem.loadReactionsForVisiblePosts();
                }
            } else {
                console.error('Error submitting reaction:', data.error);
            }
        } catch (error) {
            console.error('Error submitting reaction:', error);
        }
    }

    // Function to handle anchor scrolling to specific posts
    function handlePostAnchorScrolling() {
        // Check if there's a hash in the URL pointing to a specific post
        const hash = window.location.hash;
        if (hash && hash.startsWith('#profile-post-')) {
            const postId = hash.replace('#profile-post-', '');
            console.log('Attempting to scroll to post:', postId);

            // Wait for posts to load, then scroll
            let attempts = 0;
            const maxAttempts = 10;

            function attemptScroll() {
                attempts++;
                const postElement = document.getElementById(`profile-post-${postId}`);

                if (postElement) {
                    console.log('Post found, scrolling to:', postId);

                    // Scroll to the post with smooth animation
                    postElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // Highlight the post temporarily
                    postElement.style.border = '3px solid #2c3e50';
                    postElement.style.borderRadius = '8px';
                    postElement.style.transition = 'border 0.3s ease';

                    setTimeout(() => {
                        postElement.style.border = '';
                        postElement.style.borderRadius = '';
                    }, 3000);

                    // Auto-expand comments if available
                    setTimeout(() => {
                        const commentBtn = postElement.querySelector('.post-comment-btn');
                        if (commentBtn) {
                            console.log('Auto-expanding comments for post:', postId);
                            commentBtn.click();
                        }
                    }, 1000);

                } else if (attempts < maxAttempts) {
                    console.log(`Post ${postId} not found yet, retrying in 1 second... (attempt ${attempts})`);
                    setTimeout(attemptScroll, 1000);
                } else {
                    console.log(`Failed to find post ${postId} after ${maxAttempts} attempts`);
                }
            }

            // Start attempting after a short delay
            setTimeout(attemptScroll, 1500);
        }
    }
    /* --- Friends Scripts --- */
                    async function hit(url, data) {
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest' // Ensure this line is present
                        },
                        body: new URLSearchParams(data)
                    });
                    return r.json();
                }

                        /* --- Add friend --- */
                        document.getElementById('addFriend')?.addEventListener('click', async e => {
                            const id = e.target.dataset.id;
                            const j  = await hit('assets/send_request.php', { id });
                            if (j.ok) location.reload();
                        });

                        /* --- Accept / Decline --- */
                        ['acceptReq','declineReq'].forEach(btnId => {
                            document.getElementById(btnId)?.addEventListener('click', async e => {
                                const req_id = e.target.dataset.req;
                                const action = (btnId === 'acceptReq') ? 'accept' : 'decline';
                                const j = await hit('assets/respond_request.php', { req_id, action });
                                if (j.ok) location.reload();
                            });
                        });

                        /* --- Unfriend --- */
                        document.getElementById('unfriend')?.addEventListener('click', async e => {
                            if (!confirm('Remove this friend?')) return;
                            const id = e.target.dataset.id;
                            const j  = await hit('assets/unfriend.php', { id });
                            if (j.ok) location.reload();
                        });

        /* --- Follow / Unfollow Button (Event Delegation) --- */
        const profileActionsContainer = document.querySelector('.profile-actions'); 

        if (profileActionsContainer) {
            profileActionsContainer.addEventListener('click', async e => {
                console.log('Clicked inside .profile-actions, target is:', e.target, 'target.id is:', e.target.id); // <-- This is the existing outer log

                if (e.target && e.target.id === 'followButton') {
                    console.log('[FollowBtn] "followButton" condition met. Event target:', e.target); // Log 1
                    const button = e.target;
                    const profileId = button.dataset.profileId;
                    console.log('[FollowBtn] profileId extracted:', profileId, 'Type:', typeof profileId); // Log 2

                    if (!profileId) { // Check if profileId is null, undefined, or empty string
                        console.error('[FollowBtn] Profile ID is missing or empty. Cannot proceed.');
                        return; // Exit if no profileId
                    }
                    console.log('[FollowBtn] Profile ID check passed. Attempting to change button state...'); // Log 3

                    const originalButtonText = button.textContent;
                    console.log('[FollowBtn] Original button text captured:', originalButtonText); // Log 4

                    button.disabled = true;
                    console.log('[FollowBtn] button.disabled set to:', button.disabled); // Log 5
                    
                    button.textContent = 'Processing...';
                    console.log('[FollowBtn] button.textContent set to "Processing..."'); // Log 6

                    // This is where the try/catch/finally block for hit() call would start
                    try {
                        console.log('[FollowBtn] Entering try block for hit() call...'); // Log 7
                        const responseData = await hit('process_follow.php.php', { followed_id: profileId });
                        console.log('[FollowBtn] hit() call completed. Response data:', responseData); // Log 8

                        if (responseData && responseData.success) {
                            console.log('[FollowBtn] hit() successful. Updating UI.'); // Log 9
                            if (responseData.isFollowing) {
                                button.textContent = 'Following';
                                button.classList.remove('btn-outline-primary');
                                button.classList.add('btn-primary');
                            } else {
                                button.textContent = 'Follow';
                                button.classList.remove('btn-primary');
                                button.classList.add('btn-outline-primary');
                            }
                            const followerCountDisplay = document.getElementById('followerCountDisplay');
                            if (followerCountDisplay) {
                                followerCountDisplay.textContent = responseData.followerCount;
                            }
                            console.log('[FollowBtn] UI updated based on response.'); // Log 10
                        } else {
                            console.error('[FollowBtn] Follow/Unfollow action failed on server or bad response:', responseData ? responseData.message : 'No responseData', 'Full response:', responseData); // Log 11
                            alert('An error occurred: ' + (responseData && responseData.message ? responseData.message : 'Please try again.'));
                            button.textContent = originalButtonText; 
                        }
                    } catch (error) {
                        console.error('[FollowBtn] Error during follow/unfollow action (catch block):', error); // Log 12
                        alert('A network error occurred. Please check the console and try again.');
                        button.textContent = originalButtonText; 
                    } finally {
                        console.log('[FollowBtn] Entering finally block. Re-enabling button.'); // Log 13
                        button.disabled = false;
                        console.log('[FollowBtn] Button disabled state after finally:', button.disabled); // Log 14
                    }
                }
            });
        } else {
            // This console warning is helpful for debugging if '.profile-actions' isn't found
            console.warn("'.profile-actions' container not found. Follow button event listener not attached via delegation.");
        }
    </script>

    <script>
    // Send message function (restored to working version)
    async function startMessage(userId) {
        try {
            console.log('Starting message with user ID:', userId);

            // Show loading indicator
            const originalText = event.target.textContent;
            event.target.textContent = 'Loading...';
            event.target.disabled = true;

            // Use a simplified approach - try to create/get thread directly
            const response = await fetch('api/start_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Start conversation response:', data);

            if (data.success && data.thread_id) {
                // Successfully got/created thread, redirect to it
                console.log('Redirecting to thread:', data.thread_id);
                window.location.href = `messages.php?thread=${data.thread_id}`;
            } else {
                throw new Error(data.error || 'Failed to start conversation');
            }

        } catch (error) {
            console.error('Error starting message:', error);
            alert('Error starting message: ' + error.message);

            // Restore button
            event.target.textContent = originalText;
            event.target.disabled = false;
        }
    }
    
    // Function to load testimonials for the profile
    async function loadTestimonials() {
        const profileUserId = <?= $profileId ?>;
        const currentUserId = <?= isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0 ?>;
        const testimonialsContainer = document.getElementById('testimonials-container');
        const testimonialsActions = document.querySelector('.testimonials-actions');

        try {
            console.log('Loading testimonials for user:', profileUserId);

            const response = await fetch(`api/get_testimonials.php?type=approved&user_id=${profileUserId}&limit=5`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.testimonials && data.testimonials.length > 0) {
                console.log(`Loaded ${data.testimonials.length} testimonials for user ${profileUserId}`);

                let testimonialsHTML = '<div class="row">';
                data.testimonials.forEach(testimonial => {
                    testimonialsHTML += renderTestimonial(testimonial);
                });
                testimonialsHTML += '</div>';

                testimonialsContainer.innerHTML = testimonialsHTML;
                // Always show actions for profile owner, and for others if they can write testimonials
                testimonialsActions.style.display = 'block';

            } else {
                // Get the user's first name for personalized message
                const userName = '<?= explode(' ', $u['full_name'])[0] ?>';
                
                testimonialsContainer.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-star fa-3x mb-3 text-muted"></i>
                        <p class="text-muted mb-0">${profileUserId == currentUserId ? 'You have no testimonials yet.' : `${userName} has no existing testimonials yet, be the first!`}</p>
                        ${profileUserId == currentUserId ? '<small class="text-muted">Testimonials from friends will appear here.</small>' : '<small class="text-muted">Write a testimonial to share your experience.</small>'}
                    </div>
                `;
                
                // Show actions even if no testimonials yet
                testimonialsActions.style.display = 'block';
            }

        } catch (error) {
            console.error('Error loading testimonials:', error);
            testimonialsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading testimonials: ${error.message}
                </div>
            `;
        }
    }

    // Function to render a single testimonial
    function renderTestimonial(testimonial) {
        const statusBadge = testimonial.status === 'pending' ?
            '<span class="badge bg-warning text-dark ms-2">Pending</span>' : '';
        
        // Get profile picture with fallback to gender-specific default
        // The API now provides a fully processed path for writer_profile_pic,
        // including defaults if necessary. So, we can use it directly.
        const profilePic = testimonial.writer_profile_pic; 
        
        // Generate star rating based on rating value
        // Use the same robust renderStarRating logic from testimonials.php if available,
        // or replicate its core logic here for consistency.
        // For now, keep existing logic but ensure testimonial.rating is correctly handled.
        let ratingValue = parseInt(testimonial.rating);
        if (isNaN(ratingValue) || ratingValue < 0 || ratingValue > 5) { // Allow 0 for "not rated" if desired, or <1 for default
            ratingValue = 0; // Default to 0 stars if invalid or not explicitly set (e.g. null from DB)
        }
        // If a rating of 0 should show 0 stars, the loop below is fine.
        // If it should default to 5 for display on profile when not set, then:
        // if (ratingValue === 0 && testimonial.rating === null) ratingValue = 5; // Or whatever default display rule.
        // The task log implies fixing "not displaying correctly", not changing default display rules.
        // So, using the actual rating (or 0 if invalid/null) is best.
        
        // Generate star rating based on rating value (default to 0 if not set/invalid)
        const rating = ratingValue;
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                starsHtml += '<i class="fas fa-star" style="color: #2c3e50;"></i>';
            } else {
                starsHtml += '<i class="far fa-star" style="color: #2c3e50;"></i>';
            }
        }
        
        return `
            <div class="col-md-6 mb-3">
                <div class="card h-100" style="border-color: #e9ecef;">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="${profilePic}"
                                 alt="Profile" class="rounded-circle me-3"
                                 style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <h6 class="mb-0">
                                    <a href="view_profile.php?id=${testimonial.writer_user_id}" class="text-decoration-none" style="color: #2c3e50;">
                                        ${testimonial.writer_name}
                                    </a>
                                    ${statusBadge}
                                </h6>
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i>
                                    ${new Date(testimonial.created_at).toLocaleDateString()}
                                </small>
                            </div>
                        </div>
                        <p class="card-text">${testimonial.content}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                ${starsHtml}
                            </div>
                            ${testimonial.status === 'pending' && testimonial.recipient_user_id == <?= $current['id'] ?> ? 
                                `<div class="btn-group btn-group-sm">
                                    <button class="btn btn-success btn-sm" onclick="approveTestimonial(${testimonial.testimonial_id})">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="rejectTestimonial(${testimonial.testimonial_id})">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>` : ''
                            }
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Function to open write testimonial modal
    function openWriteTestimonialModal(recipientUserId) {
        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="writeTestimonialModal" tabindex="-1" aria-labelledby="writeTestimonialModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background-color: #2c3e50; color: white;">
                            <h5 class="modal-title" id="writeTestimonialModalLabel">
                                <i class="fas fa-star me-2"></i>Write a Testimonial
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
                        </div>
                        <div class="modal-body">
                            <form id="testimonialForm">
                                <input type="hidden" id="recipientUserId" value="${recipientUserId}">
                                <div class="mb-3">
                                    <label for="testimonialContent" class="form-label">Your Testimonial</label>
                                    <textarea class="form-control" id="testimonialContent" rows="4"
                                              placeholder="Share your experience with this person..." required></textarea>
                                    <div class="form-text">Write a thoughtful testimonial about your experience with this person.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Rating</label>
                                    <div class="rating-container">
                                        <div class="star-rating">
                                            <input type="radio" id="star5" name="rating" value="5" /><label for="star5" title="Excellent">5 stars</label>
                                            <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="Very Good">4 stars</label>
                                            <input type="radio" id="star3" name="rating" value="3" checked /><label for="star3" title="Good">3 stars</label>
                                            <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="Fair">2 stars</label>
                                            <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="Poor">1 star</label>
                                        </div>
                                        <div class="rating-text mt-2">
                                            <span class="small text-muted">1 - Poor, 5 - Excellent</span>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn" style="background-color: #2c3e50; color: white;" onclick="submitTestimonial()">
                                <i class="fas fa-paper-plane me-1"></i>Submit Testimonial
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existingModal = document.getElementById('writeTestimonialModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('writeTestimonialModal'));
        modal.show();
    }

    // Function to submit testimonial
    async function submitTestimonial() {
        const recipientUserId = document.getElementById('recipientUserId').value;
        const content = document.getElementById('testimonialContent').value.trim();
        const ratingInputs = document.getElementsByName('rating');
        let rating = 3; // Default rating
        
        // Get selected rating
        for (const input of ratingInputs) {
            if (input.checked) {
                rating = parseInt(input.value);
                break;
            }
        }

        if (!content) {
            alert('Please write your testimonial before submitting.');
            return;
        }

        try {
            const response = await fetch('api/submit_testimonial.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    recipient_user_id: recipientUserId,
                    content: content,
                    rating: rating
                })
            });

            const data = await response.json();

            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('writeTestimonialModal'));
                modal.hide();

                // Show success message
                alert('Testimonial submitted successfully! It will be visible once approved.');

                // Reload testimonials
                loadTestimonials();
            } else {
                alert('Error submitting testimonial: ' + data.error);
            }
        } catch (error) {
            console.error('Error submitting testimonial:', error);
            alert('An error occurred while submitting your testimonial.');
        }
    }

    // Function to approve testimonial
    async function approveTestimonial(testimonialId) {
        try {
            const response = await fetch('api/manage_testimonial.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    testimonial_id: testimonialId,
                    action: 'approve'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                alert('Testimonial approved successfully!');
                
                // Reload testimonials
                loadTestimonials();
            } else {
                alert('Error approving testimonial: ' + data.error);
            }
        } catch (error) {
            console.error('Error approving testimonial:', error);
            alert('An error occurred while approving the testimonial.');
        }
    }
    
    // Function to reject testimonial
    async function rejectTestimonial(testimonialId) {
        if (!confirm('Are you sure you want to reject this testimonial? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch('api/manage_testimonial.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    testimonial_id: testimonialId,
                    action: 'reject'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show success message
                alert('Testimonial rejected.');
                
                // Reload testimonials
                loadTestimonials();
            } else {
                alert('Error rejecting testimonial: ' + data.error);
            }
        } catch (error) {
            console.error('Error rejecting testimonial:', error);
            alert('An error occurred while rejecting the testimonial.');
        }
    }
</script>

</body>



</html>
