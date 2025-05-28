<?php
/**
 * user_albums.php - Public view of a user's albums
 * Shows all public albums for a specific user
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$currentUser = $_SESSION['user'];

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Get user details
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, profile_pic
    FROM users
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: dashboard.php?error=User not found");
    exit();
}

$userName = $user['first_name'] . ' ' . $user['last_name'];

// Check if users are friends (for privacy filtering)
$areFriends = false;
if ($userId !== $currentUser['id']) {
    $friendStmt = $pdo->prepare("
        SELECT COUNT(*) as is_friend
        FROM friend_requests
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        AND status = 'accepted'
    ");
    $friendStmt->execute([$currentUser['id'], $userId, $userId, $currentUser['id']]);
    $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
    $areFriends = ($friendship['is_friend'] > 0);
}

// Get albums based on privacy and friendship
if ($userId === $currentUser['id'] || $currentUser['role'] === 'admin') {
    // Show all albums for owner or admin
    $stmt = $pdo->prepare("
        SELECT a.*,
               COUNT(am.media_id) as media_count,
               (SELECT media_url FROM user_media um
                JOIN album_media am2 ON um.id = am2.media_id
                WHERE am2.album_id = a.id AND um.media_type LIKE 'image%'
                ORDER BY am2.created_at DESC LIMIT 1) as cover_image
        FROM user_media_albums a
        LEFT JOIN album_media am ON a.id = am.album_id
        WHERE a.user_id = ?
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$userId]);
} else {
    // Show only public albums for non-friends, or public+friends for friends
    $privacyCondition = $areFriends ? "(a.privacy = 'public' OR a.privacy = 'friends')" : "a.privacy = 'public'";

    $stmt = $pdo->prepare("
        SELECT a.*,
               COUNT(am.media_id) as media_count,
               (SELECT media_url FROM user_media um
                JOIN album_media am2 ON um.id = am2.media_id
                WHERE am2.album_id = a.id AND um.media_type LIKE 'image%'
                ORDER BY am2.created_at DESC LIMIT 1) as cover_image
        FROM user_media_albums a
        LEFT JOIN album_media am ON a.id = am.album_id
        WHERE a.user_id = ? AND {$privacyCondition}
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$userId]);
}

$albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($userName) ?>'s Albums - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php
            $currentPage = 'user_albums';
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($user['profile_pic'])): ?>
                            <?php
                            $profilePicPath = (strpos($user['profile_pic'], 'uploads/') === 0)
                                ? $user['profile_pic']
                                : 'uploads/profile_pics/' . $user['profile_pic'];
                            ?>
                            <img src="<?= htmlspecialchars($profilePicPath) ?>" class="rounded-circle me-3" width="50" height="50" alt="Profile picture">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h4 class="mb-0"><?= htmlspecialchars($userName) ?>'s Albums</h4>
                            <small class="text-muted"><?= count($albums) ?> album<?= count($albums) != 1 ? 's' : '' ?></small>
                        </div>
                    </div>
                    <div>
                        <a href="view_profile.php?id=<?= $userId ?>" class="btn btn-outline-dark">
                            <i class="fas fa-user me-1"></i> View Profile
                        </a>
                        <?php if ($userId === $currentUser['id']): ?>
                            <a href="manage_albums.php" class="btn btn-dark ms-2">
                                <i class="fas fa-cog me-1"></i> Manage Albums
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($albums)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-photo-video fa-3x mb-3 text-muted"></i>
                    <h5>No Albums Found</h5>
                    <p class="text-muted">
                        <?php if ($userId === $currentUser['id']): ?>
                            You haven't created any albums yet.
                        <?php else: ?>
                            This user hasn't shared any albums publicly.
                        <?php endif; ?>
                    </p>
                    <?php if ($userId === $currentUser['id']): ?>
                        <a href="manage_albums.php" class="btn btn-dark">
                            <i class="fas fa-plus me-1"></i> Create Your First Album
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($albums as $album): ?>
                        <div class="col">
                            <div class="card h-100 album-card">
                                <div class="position-relative">
                                    <?php if ($album['cover_image']): ?>
                                        <img src="<?= htmlspecialchars($album['cover_image']) ?>" class="card-img-top" alt="Album cover" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                            <i class="fas fa-photo-video fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Privacy indicator -->
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <span class="badge bg-dark">
                                            <i class="fas fa-<?= $album['privacy'] === 'public' ? 'globe' : ($album['privacy'] === 'friends' ? 'user-friends' : 'lock') ?>"></i>
                                            <?= ucfirst($album['privacy']) ?>
                                        </span>
                                    </div>

                                    <!-- Media count -->
                                    <div class="position-absolute bottom-0 start-0 p-2">
                                        <span class="badge bg-dark">
                                            <i class="fas fa-images me-1"></i>
                                            <?= $album['media_count'] ?> item<?= $album['media_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($album['album_name']) ?></h5>
                                    <?php if (!empty($album['description'])): ?>
                                        <p class="card-text text-muted"><?= htmlspecialchars(substr($album['description'], 0, 100)) ?><?= strlen($album['description']) > 100 ? '...' : '' ?></p>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        Created <?= date('M j, Y', strtotime($album['created_at'])) ?>
                                    </small>
                                </div>

                                <div class="card-footer bg-transparent">
                                    <a href="view_album.php?id=<?= $album['id'] ?>" class="btn btn-dark w-100">
                                        <i class="fas fa-eye me-1"></i> View Album
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
        <?php include 'assets/add_ons.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.left-sidebar').classList.toggle('show');
        }
    </script>

    <style>
        .album-card {
            transition: transform 0.2s ease-in-out;
        }

        .album-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</body>
</html>
