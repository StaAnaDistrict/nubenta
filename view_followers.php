<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'bootstrap.php'; // bootstrap.php now handles session_start()
require_once 'includes/FollowManager.php'; // Keep other necessary requires
require_once 'includes/FollowManager.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Ensure $user and $current_user are available for included files like navigation.php
$user = $_SESSION['user'];
$current_user = $_SESSION['user']; // Can be the same if navigation expects $current_user
$my_id = $user['id']; // Used in friends.php, define for consistency if navigation expects it

$targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$targetUserId) {
    die('Invalid or missing user ID.');
}

// Fetch target user's details
try {
    $stmtTargetUser = $pdo->prepare("SELECT id, CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE id = :user_id");
    $stmtTargetUser->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmtTargetUser->execute();
    $targetUser = $stmtTargetUser->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        die('User not found.');
    }
} catch (PDOException $e) {
    error_log("Error fetching target user in view_followers: " . $e->getMessage());
    die('An error occurred while fetching user details.');
}

$pageTitle = "People Following " . htmlspecialchars($targetUser['full_name']);

$followManager = new FollowManager($pdo);

// Pagination settings
$limit = 20;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($currentPage - 1) * $limit;

$followers = $followManager->getFollowerList((string)$targetUserId, 'user', $limit, $offset);
$totalFollowers = $followManager->getFollowersCount((string)$targetUserId, 'user');
$totalPages = ceil($totalFollowers / $limit);

$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

// $currentPageName = "view_followers"; // For navigation active state, if needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard_style.css?v=<?php echo time(); ?>">
    <style>
        /* Using styles similar to friends.php for consistency, plus specific item styles */
        .user-list-item { /* Adapted from .friend-card for this context */
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            display: flex; /* For aligning image and info */
            align-items: center; /* Vertically align items */
        }
        .user-list-item img {
            width: 60px;
            height: 60px;
            border-radius: 8px; /* Matching friends.php card image */
            object-fit: cover;
            margin-right: 15px; /* Space between image and text */
        }
        .user-list-item .user-info h5 { /* For name */
            margin: 0 0 5px 0; /* Adjust spacing */
            font-size: 16px;
            line-height: 1.4;
        }
        .user-list-item .user-info .btn { /* For View Profile button */
            padding: 4px 12px;
            font-size: 13px;
        }
        .user-name-link { /* Specific class for user name link */
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
        }
        .user-name-link:hover {
            color: #333;
            text-decoration: underline;
        }
        .page-header-flex { /* For header with back button */
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .page-header-flex h2 {
            margin: 0;
            color: #1a1a1a;
            font-weight: bold;
        }
        .pagination-container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <aside class="left-sidebar">
            <?php include 'assets/navigation.php'; ?>
        </aside>

        <main class="main-content">
            <?php include 'topnav.php'; // Including topnav for consistency ?>
            <div class="container-fluid mt-3"> <!-- Add some margin/padding for content start -->
                <div class="page-header-flex">
                    <h2 class="h4"><?= htmlspecialchars($pageTitle) ?></h2>
                    <a href="view_profile.php?id=<?= htmlspecialchars($targetUser['id']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($targetUser['full_name']) ?>'s Profile
                    </a>
                </div>

                <?php if (empty($followers)): ?>
                    <div class="alert alert-info text-center">
                        <?= htmlspecialchars($targetUser['full_name']) ?> doesn't have any followers yet.
                    </div>
                <?php else: ?>
                    <div class="user-list-container"> <!-- Changed from list-group to avoid conflicts if any -->
                        <?php foreach ($followers as $follower): ?>
                            <?php
                            $profilePic = $defaultMalePic;
                            if (!empty($follower['profile_pic'])) {
                                $profilePic = 'uploads/profile_pics/' . htmlspecialchars($follower['profile_pic']);
                            } elseif (isset($follower['gender']) && $follower['gender'] === 'Female') {
                                $profilePic = $defaultFemalePic;
                            }
                            ?>
                            <div class="user-list-item">
                                <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture of <?= htmlspecialchars($follower['full_name']) ?>">
                                <div class="user-info flex-grow-1">
                                    <h5>
                                        <a href="view_profile.php?id=<?= htmlspecialchars($follower['id']) ?>" class="user-name-link">
                                            <?= htmlspecialchars($follower['full_name']) ?>
                                        </a>
                                    </h5>
                                    <a href="view_profile.php?id=<?= htmlspecialchars($follower['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        View Profile
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="pagination-container d-flex justify-content-center">
                            <ul class="pagination">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="view_followers.php?user_id=<?= $targetUserId ?>&page=<?= $currentPage - 1 ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                                        <a class="page-link" href="view_followers.php?user_id=<?= $targetUserId ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($currentPage < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="view_followers.php?user_id=<?= $targetUserId ?>&page=<?= $currentPage + 1 ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div> <!-- end container-fluid -->
        </main>

        <?php
        // This include for right sidebar is from friends.php.
        // Ensure assets/add_ons.php creates a .right-sidebar classed div or is styled as the third grid item.
        include 'assets/add_ons.php';
        ?>
    </div> <!-- end dashboard-grid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show'); // 'show' class should be defined in your CSS for mobile view
        }

        // Optional: Close sidebar if clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            // Check if sidebar is shown (usually via a class like 'show')
            // and if the click is outside both the sidebar and the hamburger button
            if (sidebar.classList.contains('show') && !sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>