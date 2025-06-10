<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'bootstrap.php'; // Should handle db.php
require_once 'includes/FollowManager.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
// Ensure $current_user is available for assets/navigation.php if it uses it.
if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
}

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
// $currentPageNav = 'profile'; // Example for navigation active state
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
        /* Styles specific to this page or minor overrides if necessary */
        .user-list-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            background-color: #fff;
        }
        .user-list-item img {
            width: 50px; 
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }
        .user-list-item .user-info a {
            font-weight: bold;
            text-decoration: none;
            color: #333;
        }
        .user-list-item .user-info a:hover {
            text-decoration: underline;
        }
        .pagination .page-link {
            color: #333; 
        }
        .pagination .page-item.active .page-link {
            background-color: #333; 
            border-color: #333;
            color: #fff;
        }
        .dashboard-grid > .main-content .content-area .main-content-column {
             padding: 20px;
             background-color: #fff; 
             border-radius: 8px;
             box-shadow: 0 1px 2px rgba(0,0,0,0.1);
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
    </style>
</head>
<body>
    <div class="dashboard-grid">  <!-- Applied .dashboard-grid class -->
        <?php
        // assets/navigation.php is expected to render content suitable for the first grid column.
        // If it needs a specific class like .left-sidebar for styling from dashboard_style.css,
        // and doesn't output it itself, you might wrap it:
        // echo '<div class="left-sidebar">';
        include 'assets/navigation.php';
        // echo '</div>';
        ?>

        <div class="main-content"> <!-- This is the second grid column -->
            <?php include 'topnav.php'; ?>
            <div class="content-area py-4"> 
                <div class="container-fluid">
                    <div class="main-content-column"> 
                        <div class="content-header">
                            <h3><?= htmlspecialchars($pageTitle) ?></h3>
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
                                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($follower['full_name']) ?>'s Profile Picture">
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
        </div>

        <div class="right-sidebar"> <!-- Applied .right-sidebar class -->
             <?php include_once __DIR__ . "/api/add_ons_middle_element_html.php"; ?>
             <?php include_once __DIR__ . "/api/add_ons_bottom_element_html.php"; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>