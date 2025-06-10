<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    // session_start(); // bootstrap.php should handle this
}
require_once 'bootstrap.php'; 
require_once 'includes/FollowManager.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
    $user = $_SESSION['user']; // For navigation if it uses $user
    $my_id = $user['id'];     // For navigation if it uses $my_id
}

$targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$targetUserId) {
    die('Invalid or missing user ID.');
}

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

$limit = 18; // Adjusted for potentially 3 cards per row (e.g., 6 rows)
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$offset = ($currentPage - 1) * $limit;

$followers = $followManager->getFollowerList((string)$targetUserId, 'user', $limit, $offset);
$totalFollowers = $followManager->getFollowersCount((string)$targetUserId, 'user');
$totalPages = ceil($totalFollowers / $limit);

$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
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
        .user-card-item { /* Renamed from user-list-item to reflect card nature */
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: white;
            display: flex;
            flex-direction: column; /* Stack image, info, button vertically */
            align-items: center; /* Center items horizontally */
            text-align: center;
            height: 100%; /* For consistent card height if using Bootstrap row's equal height cols */
            margin-bottom: 15px; /* Add margin to the bottom of each card */
        }
        .user-card-item img {
            width: 80px; /* Slightly larger for card view */
            height: 80px;
            border-radius: 50%; /* Circular images for cards often look good */
            object-fit: cover;
            margin-bottom: 10px; /* Space below image */
        }
        .user-card-item .user-info h5 {
            margin: 0 0 10px 0;
            font-size: 1rem; /* Bootstrap h5 size */
        }
        .user-card-item .user-info .btn {
            padding: 0.25rem 0.5rem; /* Bootstrap btn-sm padding */
            font-size: .875rem; /* Bootstrap btn-sm font size */
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
        .page-header-flex h2 { /* Changed from h3 to h2 for main page title */
            margin: 0;
            color: #1a1a1a;
            font-weight: bold;
            font-size: 1.75rem;
        }
        .pagination-container {
            margin-top: 20px;
        }
         .dashboard-grid > .main-content .content-area .main-content-column {
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
            <div class="container-fluid mt-3">
                <div class="main-content-column"> <!-- Added this wrapper from previous version -->
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
                        <div class="row"> <!-- Bootstrap Row -->
                            <?php foreach ($followers as $follower): ?>
                                <div class="col-sm-12 col-md-6 col-lg-4"> <!-- Bootstrap Columns for card layout -->
                                    <?php
                                    $profilePic = $defaultMalePic;
                                    if (!empty($follower['profile_pic'])) {
                                        $profilePic = 'uploads/profile_pics/' . htmlspecialchars($follower['profile_pic']);
                                    } elseif (isset($follower['gender']) && $follower['gender'] === 'Female') {
                                        $profilePic = $defaultFemalePic;
                                    }
                                    ?>
                                    <div class="user-card-item">
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
                                </div>
                            <?php endforeach; ?>
                        </div> <!-- End Bootstrap Row -->

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