<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// bootstrap.php should handle session_start() if it has the conditional check
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
require_once 'bootstrap.php';
require_once 'includes/FollowManager.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Ensure $user and $current_user are available for included files like navigation.php
if (isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $current_user = $_SESSION['user'];
    $my_id = $user['id'];
}


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
    error_log("Error fetching target user in view_following: " . $e->getMessage());
    die('An error occurred while fetching user details.');
}

$pageTitle = htmlspecialchars($targetUser['full_name']) . " is Following";
$followManager = new FollowManager($pdo);

// Pagination settings
$limit = 18; // Adjusted for potentially 3 cards per row
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($currentPage - 1) * $limit;

$followingList = $followManager->getFollowingList((int)$targetUserId, 'user', $limit, $offset);
$totalFollowing = $followManager->getFollowingCount((int)$targetUserId, 'user');
$totalPages = ceil($totalFollowing / $limit);

$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

// $currentPageName = "view_following"; // For navigation active state, if needed
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
        .user-card-item { /* Reflects card nature */
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            height: 100%;
            margin-bottom: 15px;
        }
        .user-card-item img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .user-card-item .user-info h5 {
            margin: 0 0 10px 0;
            font-size: 1rem;
        }
        .user-card-item .user-info .btn {
            padding: 0.25rem 0.5rem;
            font-size: .875rem;
        }
        .user-name-link {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
        }
        .user-name-link:hover {
            color: #333;
            text-decoration: underline;
        }
        .page-header-flex {
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
            font-size: 1.75rem;
        }
        .pagination-container {
            margin-top: 20px;
        }
         .dashboard-grid > .main-content .main-content-column { /* Adjusted selector for clarity */
             padding: 20px;
             background-color: #fff;
             border-radius: 8px;
             box-shadow: 0 1px 2px rgba(0,0,0,0.1);
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
            <?php include 'topnav.php'; ?>
            <div class="container-fluid mt-3"> <!-- Added container-fluid & mt-3 for padding -->
                <div class="main-content-column"> <!-- Added this wrapper -->
                    <div class="page-header-flex">
                        <h2 class="h4"><?= htmlspecialchars($pageTitle) ?></h2>
                        <a href="view_profile.php?id=<?= htmlspecialchars($targetUser['id']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($targetUser['full_name']) ?>'s Profile
                        </a>
                    </div>

                    <?php if (empty($followingList)): ?>
                        <div class="alert alert-info text-center">
                            <?= htmlspecialchars($targetUser['full_name']) ?> is not following anyone yet.
                        </div>
                    <?php else: ?>
                        <div class="row"> <!-- Bootstrap Row for card layout -->
                            <?php foreach ($followingList as $followedUser): ?>
                                <div class="col-sm-12 col-md-6 col-lg-4"> <!-- Bootstrap Columns -->
                                    <?php
                                    $profilePic = $defaultMalePic;
                                    if (!empty($followedUser['profile_pic'])) {
                                        $profilePic = 'uploads/profile_pics/' . htmlspecialchars($followedUser['profile_pic']);
                                    } elseif (isset($followedUser['gender']) && $followedUser['gender'] === 'Female') {
                                        $profilePic = $defaultFemalePic;
                                    }
                                    ?>
                                    <div class="user-card-item">
                                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture of <?= htmlspecialchars($followedUser['full_name']) ?>">
                                        <div class="user-info flex-grow-1">
                                            <h5>
                                                <a href="view_profile.php?id=<?= htmlspecialchars($followedUser['id']) ?>" class="user-name-link">
                                                    <?= htmlspecialchars($followedUser['full_name']) ?>
                                                </a>
                                            </h5>
                                            <a href="view_profile.php?id=<?= htmlspecialchars($followedUser['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                View Profile
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div> <!-- End Bootstrap Row -->

                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="pagination-container d-flex justify-content-center">
                                <ul class="pagination">
                                    <?php if ($currentPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="view_following.php?user_id=<?= $targetUserId ?>&page=<?= $currentPage - 1 ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                                            <a class="page-link" href="view_following.php?user_id=<?= $targetUserId ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($currentPage < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="view_following.php?user_id=<?= $targetUserId ?>&page=<?= $currentPage + 1 ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <?php
        include 'assets/add_ons.php';
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show');
        }
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (sidebar.classList.contains('show') && !sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>