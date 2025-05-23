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
        <li><a href="dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Home
        </a></li>
        <li><a href="messages.php" class="<?php echo $currentPage === 'messages' ? 'active' : ''; ?>" id="messagesLink">
            <i class="fas fa-envelope"></i> Messages
            <span class="notification-badge" id="messagesNotification" style="display: none;"></span>
        </a></li>
        <li>
            <a href="friends.php">
                <i class="fas fa-user-friends"></i> Connections
                <?php if ($pending_requests > 0): ?>
                    <span class="notification-badge"><?= $pending_requests ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li><a href="testimonials.php"><i class="fas fa-star"></i> Testimonials</a></li>
        <li><a href="view_profile.php?id=<?= $user['id'] ?>"><i class="fas fa-user"></i> View Profile</a></li>
        <li><a href="edit_profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
        
      <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
            <li><a href="admin_dashboard.php"><i class="fas fa-users-cog"></i> Admin Panel</a></li>
      <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

<script>
// Function to check for unread delivered messages
async function checkUnreadDeliveredMessages() {
    try {
        const response = await fetch('api/check_unread_delivered.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const messagesNotification = document.getElementById('messagesNotification');
        
        if (data.success && data.has_unread_delivered) {
            messagesNotification.style.display = 'inline-block';
            messagesNotification.textContent = data.count > 0 ? data.count : '';
        } else {
            messagesNotification.style.display = 'none';
        }
    } catch (error) {
        console.error('Error checking unread delivered messages:', error);
    }
}

// Function to update unread count
function updateUnreadCount(count) {
    const messagesNotification = document.getElementById('messagesNotification');
    if (count > 0) {
        messagesNotification.style.display = 'inline-block';
        messagesNotification.textContent = count;
    } else {
        messagesNotification.style.display = 'none';
    }
}

// Check for unread delivered messages when the page loads
checkUnreadDeliveredMessages();

// Check periodically (every 5 seconds)
setInterval(checkUnreadDeliveredMessages, 5000);

// Make functions available globally
window.updateUnreadCount = updateUnreadCount;
window.checkUnreadDeliveredMessages = checkUnreadDeliveredMessages;
</script>

<style>
.navbar-vertical a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
    transition: background-color 0.3s;
}

.navbar-vertical a:hover {
    background-color: #f0f0f0;
}

.navbar-vertical a.active {
    background-color: #e0e0e0;
    font-weight: bold;
}

.navbar-vertical i {
    width: 20px;
    text-align: center;
}

.notification-badge {
    background-color: #ff4444;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
}

#messagesLink {
    position: relative;
}
</style>
