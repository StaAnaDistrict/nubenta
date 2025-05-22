<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$my_id = $user['id'];

// Define default profile pictures
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

try {
    // Fetch incoming friend requests with more user details
    $requests_stmt = $pdo->prepare("
        SELECT fr.*, 
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as sender_name,
               u.profile_pic,
               u.gender,
               u.id as user_id
        FROM friend_requests fr 
        JOIN users u ON fr.sender_id = u.id 
        WHERE fr.receiver_id = ? AND fr.status = 'pending'
        ORDER BY fr.created_at DESC
    ");
    $requests_stmt->execute([$my_id]);
    $incoming_requests = $requests_stmt->fetchAll();

    // Fetch accepted friends with more details
    $friends_stmt = $pdo->prepare("
        SELECT u.*, 
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as full_name
        FROM users u
        JOIN friend_requests fr ON 
            ((fr.sender_id = u.id AND fr.receiver_id = ?) OR 
             (fr.receiver_id = u.id AND fr.sender_id = ?))
        WHERE fr.status = 'accepted'
        ORDER BY u.first_name, u.last_name
    ");
    $friends_stmt->execute([$my_id, $my_id]);
    $friends = $friends_stmt->fetchAll();

    // Fetch suggested friends
    $suggested_stmt = $pdo->prepare("
        SELECT u.id as user_id,
               u.profile_pic,
               u.gender,
               u.location,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as full_name
        FROM users u
        WHERE u.id != ? 
        AND u.id NOT IN (
            SELECT CASE 
                WHEN sender_id = ? THEN receiver_id 
                ELSE sender_id 
            END
            FROM friend_requests 
            WHERE (sender_id = ? OR receiver_id = ?)
        )
        ORDER BY u.first_name, u.last_name
        LIMIT 10
    ");
    $suggested_stmt->execute([$my_id, $my_id, $my_id, $my_id]);
    $suggested_friends = $suggested_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Nubenta</title>
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
            $currentUser = $user;
include 'assets/navigation.php';
?>
        </aside>

        <script>
            // Function to track user activity
            async function trackActivity() {
                try {
                    const response = await fetch('api/track_activity.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    console.log('Dashboard: Activity tracked successfully:', data);
                    
                    // Update unread count if available
                    if (data.success && data.unread_count !== undefined) {
                        if (window.updateUnreadCount) {
                            window.updateUnreadCount(data.unread_count);
                        }
                    }
                    
                    return data;
                } catch (error) {
                    console.error('Dashboard: Error tracking activity:', error);
                    throw error;
                }
            }

            // Make trackActivity available globally
            window.trackActivity = trackActivity;

            // Track activity on page load
            trackActivity().catch(error => {
                console.error('Initial activity tracking error:', error);
            });

            // Track activity periodically
            setInterval(trackActivity, 5000);
        </script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function toggleSidebar() {
                const sidebar = document.querySelector('.left-sidebar');
                sidebar.classList.toggle('show');
            }

            // Click outside to close
            document.addEventListener('click', function(e) {
                const sidebar = document.querySelector('.left-sidebar');
                const hamburger = document.getElementById('hamburgerBtn');
                if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            });

            // Initialize navigation after DOM is loaded
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Dashboard: DOMContentLoaded fired');
                // Track activity and check notifications
                trackActivity().catch(error => {
                    console.error('Initial trackActivity call error:', error);
                });
                if (window.checkUnreadDeliveredMessages) {
                    window.checkUnreadDeliveredMessages();
                }
            });
        </script>

  <!-- Main Content -->
  <main class="main-content">
    <form action="create_post.php" method="POST" enctype="multipart/form-data" class="post-box">
      <textarea name="content" placeholder="What's on your mind?" required></textarea>
      <div class="post-actions">
  <input type="file" name="media">
  <select name="visibility">
    <option value="public">Public</option>
    <option value="friends">Friends Only</option>
  </select>
  <button type="submit">Post</button>
</div>
    </form>
            <div class="welcome-section">
                <h3>Latest Newsfeed</h3>
            </div>
    <section class="newsfeed">
      <?php
      $posts = []; // ← Replace with real DB content.
      foreach ($posts as $post):
      ?>
        <article class="post">
          <p><strong><?php echo htmlspecialchars($post['author']); ?></strong></p>
          <p><?php echo htmlspecialchars($post['content']); ?></p>
          <?php if (!empty($post['media'])): ?>
            <img src="uploads/<?php echo $post['media']; ?>" alt="Post media" style="max-width:100%;">
          <?php endif; ?>
          <small>Posted on <?php echo $post['created_at']; ?></small>
        </article>
      <?php endforeach; ?>
    </section>
  </main>

  <!-- Right Sidebar -->
  <aside class="right-sidebar">
    <div class="sidebar-section">
      <h4>📢 Ads</h4>
      <p>(Coming Soon)</p>
    </div>
    <div class="sidebar-section">
      <h4>🕑 Activity Feed</h4>
      <p>(Coming Soon)</p>
    </div>
    <div class="sidebar-section">
      <h4>🟢 Online Friends</h4>
      <p>(Coming Soon)</p>
    </div>
  </aside>
</div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show');
        }

        // Click outside to close
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>
