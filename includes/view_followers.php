<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'bootstrap.php'; // Should include db.php and start sessions
require_once 'includes/FollowManager.php';

if (!isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}
$currentUserId = $_SESSION['user']['id'];

// Get target user ID from GET parameter
$targetUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($targetUserId <= 0) {
    // Instead of die, render a proper error page or redirect with an error message
    // For now, simple die:
    die('Invalid user ID specified.');
}

// Fetch target user's details
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmt->execute();
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        die('User not found.');
    }
} catch (PDOException $e) {
    error_log("Error fetching target user: " . $e->getMessage());
    die('An error occurred while fetching user details.');
}

$pageTitle = "People Following " . htmlspecialchars($targetUser['full_name']);

// Instantiate FollowManager
$followManager = new FollowManager($pdo);

// Pagination settings
$limit = 20; // Number of followers per page
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}
$offset = ($currentPage - 1) * $limit;

// Get follower list
$followers = $followManager->getFollowerList((string)$targetUserId, 'user', $limit, $offset);

// Get total number of followers for pagination
$totalFollowers = $followManager->getFollowersCount((string)$targetUserId, 'user');
$totalPages = ceil($totalFollowers / $limit);

// Define default profile picture paths
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

// Determine which main navigation to use: topnav.php or assets/navigation.php
// Assuming assets/navigation.php for a 3-column layout as in friends.php or user_connections.php
$useThreeColumnLayout = true; 
// If you have a global variable or a way to determine layout, use that.
// For example, if $currentUser is set by bootstrap.php and used by assets/navigation.php:
if (!isset($currentUser) && isset($_SESSION['user'])) {
    $currentUser = $_SESSION['user']; // Make sure $currentUser is available for navigation
}
// If assets/navigation.php needs specific variables like $currentPageName, set them.
$currentPageName = "view_followers";


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Link to your project's main CSS file -->
    <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet"> 
    <link href="assets/css/dashboard_style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        /* Basic styling for follower list - can be moved to a CSS file */
        .follower-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
        }
        .follower-item img {
            width: 60px; /* Slightly larger for better visibility */
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
            border: 2px solid #eee;
        }
        .follower-item a {
            font-weight: bold;
            text-decoration: none;
            color: #333;
        }
        .follower-item a:hover {
            text-decoration: underline;
            color: #007bff;
        }
        .pagination-container {
            margin-top: 20px;
        }
        .content-area {
            background-color: #f8f9fa; /* Light background for the content area */
            padding: 20px;
            border-radius: 8px;
        }
        .profile-link-button {
            margin-bottom:20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php if ($useThreeColumnLayout): ?>
                <div class="col-md-3">
                    <?php include 'assets/navigation.php'; ?>
                </div>
                <div class="col-md-6">
            <?php else: ?>
                <?php include 'topnav.php'; // Fallback or alternative navigation ?>
                <div class="col-md-12"> 
            <?php endif; ?>
                <!-- Main Content Area -->
                <main class="content-area">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h4">Followers of <?php echo htmlspecialchars($targetUser['full_name']); ?></h1>
                        <a href="view_profile.php?id=<?php echo $targetUserId; ?>" class="btn btn-outline-primary profile-link-button">
                            <i class="fas fa-arrow-left"></i> Back to <?php echo htmlspecialchars($targetUser['full_name']); ?>'s Profile
                        </a>
                    </div>

                    <?php if (empty($followers)): ?>
                        <div class="alert alert-info text-center">
                            <?php echo htmlspecialchars($targetUser['full_name']); ?> doesn't have any followers yet.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($followers as $follower): ?>
                                <?php
                                $profilePic = $defaultMalePic; // Default to male
                                if (!empty($follower['profile_pic'])) {
                                    $profilePic = 'uploads/profile_pics/' . htmlspecialchars($follower['profile_pic']);
                                } elseif (isset($follower['gender'])) {
                                    if ($follower['gender'] === 'Female') {
                                        $profilePic = $defaultFemalePic;
                                    }
                                }
                                ?>
                                <div class="follower-item">
                                    <img src="<?php echo $profilePic; ?>" alt="Profile Picture of <?php echo htmlspecialchars($follower['full_name']); ?>">
                                    <div>
                                        <a href="view_profile.php?id=<?php echo htmlspecialchars($follower['id']); ?>">
                                            <?php echo htmlspecialchars($follower['full_name']); ?>
                                        </a>
                                        <br>
                                        <a href="view_profile.php?id=<?php echo htmlspecialchars($follower['id']); ?>" class="btn btn-sm btn-outline-secondary mt-1">
                                            View Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination Links -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="pagination-container d-flex justify-content-center">
                                <ul class="pagination">
                                    <?php if ($currentPage > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="view_followers.php?user_id=<?php echo $targetUserId; ?>&page=<?php echo $currentPage - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                            <a class="page-link" href="view_followers.php?user_id=<?php echo $targetUserId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($currentPage < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="view_followers.php?user_id=<?php echo $targetUserId; ?>&page=<?php echo $currentPage + 1; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </main>
            </div> <!-- end main content col -->

            <?php if ($useThreeColumnLayout): ?>
                <div class="col-md-3">
                    <?php include 'assets/add_ons_middle_element_html.php'; ?>
                    <?php include 'assets/add_ons_bottom_element_html.php'; ?>
                </div>
            <?php endif; ?>

        </div> <!-- end row -->
    </div> <!-- end container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/footer.php'; // Common footer ?>
</body>
</html>
