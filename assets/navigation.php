<?php
// navigation.php – reusable left-sidebar navigation
if (session_status() === PHP_SESSION_NONE) session_start();
$currentUser = $_SESSION['user'] ?? null;
?>
<!-- ────────── HAMBURGER (mobile) ────────── -->
<button class="hamburger" id="hamburgerBtn">☰</button>

<!-- ────────── LEFT SIDEBAR ────────── -->
<aside class="left-sidebar" id="leftSidebar">
  <?php if ($currentUser): ?>
    <div class="user-greeting">
      <h3>Welcome, <?= htmlspecialchars($currentUser['name']) ?>!</h3>
    </div>
  <?php endif; ?>

  <nav class="navbar-vertical">
    <ul>
      <li><a href="view_profile.php?id=<?= $user['id'] ?>">View Profile</a></li>
      <li><a href="edit_profile.php">Edit Profile</a></li>
      <li><a href="messages.php">Messages</a></li>
      <li><a href="testimonials.php">Testimonials</a></li>
      <li><a href="friends.php">Friend Requests</a></li>
      <li><a href="dashboard.php">Newsfeed</a></li>
      <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
        <li><a href="admin_users.php">Manage Users</a></li>
      <?php endif; ?>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</aside>

<!-- ────────── JS for toggle + outside-click ────────── -->
<script>
  const hamburger   = document.getElementById('hamburgerBtn');
  const sidebar     = document.getElementById('leftSidebar');

  // Toggle sidebar
  hamburger.addEventListener('click', e => {
    e.stopPropagation();
    sidebar.classList.toggle('show');
  });

  // Click outside to close
  document.addEventListener('click', e => {
    if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
      sidebar.classList.remove('show');
    }
  });
</script>
