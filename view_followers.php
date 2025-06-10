<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'bootstrap.php'; // Should handle db.php
require_once 'includes/FollowManager.php';
// require_once 'includes/NotificationHelper.php'; // Only if navigation.php strictly needs it passed or globally

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
// For navigation.php if it uses $current_user or $user
$current_user = $_SESSION['user'];
$user = $_SESSION['user'];


$targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$targetUserId) {
    die("Invalid or missing user ID.");
}

$stmtUser = $pdo->prepare("SELECT CONCAT_WS(' ', first_name, last_name) AS full_name, id FROM users WHERE id = ?");
$stmtUser->execute([$targetUserId]);
$targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    die("User not found.");
}

$followManager = new FollowManager($pdo);

$limit = 20;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($currentPage - 1) * $limit;

$followers = $followManager->getFollowerList((string)$targetUserId, 'user', $limit, $offset);
$totalFollowers = $followManager->getFollowersCount((string)$targetUserId, 'user');
$totalPages = ceil($totalFollowers / $limit);

$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

$pageTitle = "People Following " . htmlspecialchars($targetUser['full_name']);
// $currentPageNav might be needed by navigation.php to highlight the active link
// For example, if navigation.php uses a variable like $activePage:
$activePage = 'profile'; // Assuming viewing followers is related to profiles
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/dashboard_style.css?v=<?php echo time(); ?>">
    <style>
        /* Styles specific to this page or minor overrides if necessary */
        .user-list-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            background-color: #fff; /* White background for items */
        }
        .user-list-item img {
            width: 50px; /* Consistent small size */
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        .user-list-item .user-info a {
            font-weight: bold;
            text-decoration: none;
            color: #333; /* Match main content text color */
        }
        .user-list-item .user-info a:hover {
            text-decoration: underline;
        }
        .pagination .page-link {
            color: #333; /* Darker color for pagination links */
        }
        .pagination .page-item.active .page-link {
            background-color: #333; /* Dark background for active page */
            border-color: #333;
            color: #fff;
        }
        /* Main content column within the content-area */
        .main-content-column {
             padding: 20px;
             background-color: #fff; /* White background for content box */
             border-radius: 8px;
             box-shadow: 0 0 10px rgba(0,0,0,0.1); /* Subtle shadow for depth */
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .content-header h3 {
            margin: 0;
            font-size: 1.75rem;
        }
        /* Hamburger for mobile, if used */
        .hamburger { display: none; /* Shown by dashboard_style.css media query */ }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <aside class="left-sidebar">
            <?php include 'assets/navigation.php'; ?>
        </aside>

        <main class="main-content">
            <?php include 'topnav.php'; // Top navigation bar ?>

            <div class="content-area py-4"> <!-- Added padding to content-area -->
                <div class="container-fluid">
                    <div class="main-content-column">
                        <div class="content-header">
                            <h3><?= $pageTitle ?></h3>
                            <a href="view_profile.php?id=<?= htmlspecialchars($targetUser['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($targetUser['full_name']) ?>'s Profile
                            </a>
                        </div>

                        <?php if (empty($followers)): ?>
                            <p class="text-center mt-4"><?= htmlspecialchars($targetUser['full_name']) ?> doesn't have any followers yet.</p>
                        <?php else: ?>
                            <div class="user-list">
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
                                        <img src="<?= $profilePic ?>" alt="<?= htmlspecialchars($follower['full_name']) ?>'s Profile Picture">
                                        <div class="user-info">
                                            <a href="view_profile.php?id=<?= htmlspecialchars($follower['id']) ?>"><?= htmlspecialchars($follower['full_name']) ?></a>
                                            <br>
                                            <a href="view_profile.php?id=<?= htmlspecialchars($follower['id']) ?>" class="btn btn-sm btn-outline-dark mt-1">View Profile</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4 d-flex justify-content-center">
                                    <ul class="pagination">
                                        <?php if ($currentPage > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?user_id=<?= $targetUserId ?>&page=<?= $currentPage - 1 ?>">Previous</a></li>
                                        <?php endif; ?>
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>"><a class="page-link" href="?user_id=<?= $targetUserId ?>&page=<?= $i ?>"><?= $i ?></a></li>
                                        <?php endfor; ?>
                                        <?php if ($currentPage < $totalPages): ?>
                                            <li class="page-item"><a class="page-link" href="?user_id=<?= $targetUserId ?>&page=<?= $currentPage + 1 ?>">Next</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <?php
        // Assuming assets/add_ons.php itself will render a div with class="right-sidebar"
        // or is styled as the right sidebar by dashboard_style.css directly.
        // If it needs an explicit wrapper:
        // echo '<div class="right-sidebar">';
        include 'assets/add_ons.php';
        // echo '</div>';
        ?>
    </div> <!-- end dashboard-grid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for hamburger toggle, if used and not in a global JS file
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            const mainContent = document.querySelector('.main-content');
            // This is a basic toggle, you might need to adjust classes based on your CSS
            if (sidebar && mainContent) {
                sidebar.classList.toggle('active'); // Assuming 'active' class controls visibility/position
                // Potentially adjust main content margin or something similar if sidebar overlaps
            }
        }
    </script>
</body>
</html>
