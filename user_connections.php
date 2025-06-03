<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$currentUser = $_SESSION['user']; // For navigation.php

// Get and validate target user ID
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    header("Location: dashboard.php"); // Or some error page
    exit();
}

// Fetch target user's details
$userStmt = $pdo->prepare("SELECT id, first_name, last_name, profile_pic FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$targetUser = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    // User not found, redirect or show error
    // For simplicity, redirecting to dashboard
    header("Location: dashboard.php");
    exit();
}
$targetUserName = htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']);
$targetUserProfilePic = !empty($targetUser['profile_pic']) ? 'uploads/profile_pics/' . htmlspecialchars($targetUser['profile_pic']) : 'assets/images/MaleDefaultProfilePicture.png'; // Fallback, gender can be added if fetched

// Fetch all connections for the target user
$connectionsStmt = $pdo->prepare("
    SELECT
        u.id,
        CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name,
        u.profile_pic,
        u.gender,
        fr.created_at as friendship_date
    FROM friend_requests fr
    JOIN users u ON (
        (fr.sender_id = :userId AND fr.receiver_id = u.id) OR
        (fr.receiver_id = :userId AND fr.sender_id = u.id)
    )
    WHERE fr.status = 'accepted' AND (fr.sender_id = :userId OR fr.receiver_id = :userId)
    ORDER BY u.first_name, u.last_name
");
$connectionsStmt->execute(['userId' => $userId]);
$connections = $connectionsStmt->fetchAll(PDO::FETCH_ASSOC);
$totalConnectionsCount = count($connections);

// Define default profile pictures (used in the loop for friends)
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $targetUserName ?>'s Connections - Nubenta</title>
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
        .profile-header-mini {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .profile-header-mini img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        .connection-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .connection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .connection-card img {
            width: 80px; /* Slightly larger for emphasis */
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .connection-card .card-body {
            padding: 0; /* Remove default padding if custom layout used */
            text-align: center;
        }
        .connection-card .friend-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .connection-card .friendship-date {
            font-size: 0.85em;
            color: #666;
        }
        .back-link {
            margin-bottom: 15px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php include 'assets/navigation.php'; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="profile-header-mini">
                <img src="<?= $targetUserProfilePic ?>" alt="<?= $targetUserName ?>">
                <div>
                    <h2><?= $targetUserName ?>'s Connections</h2>
                    <p class="text-muted mb-0">Total Connections: <?= $totalConnectionsCount ?></p>
                </div>
            </div>

            <a href="view_profile.php?id=<?= htmlspecialchars($userId) ?>" class="btn btn-outline-secondary btn-sm back-link">
                <i class="fas fa-arrow-left me-1"></i> Back to Profile
            </a>

            <?php if ($totalConnectionsCount > 0): ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3">
                    <?php foreach ($connections as $connection): ?>
                        <div class="col">
                            <a href="view_profile.php?id=<?= htmlspecialchars($connection['id']) ?>" class="text-decoration-none">
                                <div class="connection-card h-100">
                                    <?php
                                    $friendPic = !empty($connection['profile_pic'])
                                        ? 'uploads/profile_pics/' . htmlspecialchars($connection['profile_pic'])
                                        : ($connection['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
                                    ?>
                                    <img src="<?= $friendPic ?>" alt="<?= htmlspecialchars($connection['full_name']) ?>">
                                    <div class="card-body">
                                        <div class="friend-name text-truncate"><?= htmlspecialchars($connection['full_name']) ?></div>
                                        <div class="friendship-date">
                                            Friends since <?= date('M Y', strtotime($connection['friendship_date'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p class="text-muted"><?= $targetUserName ?> has no connections yet.</p>
                </div>
            <?php endif; ?>
        </main>

        <!-- Right Sidebar -->
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

        // Click outside to close sidebar
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
