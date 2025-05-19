<?php
// navigation.php – reusable left-sidebar navigation
if (session_status() === PHP_SESSION_NONE) session_start();
$currentUser = $_SESSION['user'] ?? null;

// Get pending friend requests count
$pending_requests = 0;
if ($currentUser) {
    try {
        require_once __DIR__ . '/../db.php';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM friend_requests 
            WHERE receiver_id = ? AND status = 'pending'
        ");
        $stmt->execute([$currentUser['id']]);
        $pending_requests = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting pending requests: " . $e->getMessage());
    }
}
?>

<?php if ($currentUser): ?>
    <div class="user-greeting">
        <h2>Welcome, <?= htmlspecialchars($currentUser['first_name']) ?>!</h2>
    </div>
<?php endif; ?>

<nav class="navbar-vertical">
    <ul>
        <li><a href="view_profile.php?id=<?= $user['id'] ?>">View Profile</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="messages.php">Messages</a></li>
        <li><a href="testimonials.php">Testimonials</a></li>
        <li>
            <a href="friends.php">
                Friend Requests
                <?php if ($pending_requests > 0): ?>
                    <span class="notification-badge"><?= $pending_requests ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li><a href="dashboard.php">Newsfeed</a></li>
        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
            <li><a href="admin_users.php">Manage Users</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</nav>

<style>
.notification-badge {
    background-color: #ff4444;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 5px;
}
</style>
