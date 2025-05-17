<?php
// topnav.php – include in any page
if (session_status() === PHP_SESSION_NONE) session_start();
$me = $_SESSION['user'] ?? null;
?>
<link rel="stylesheet" href="assets/topnav.css">
<nav class="topnav">
  <button class="hamburger" id="navToggle">☰</button>
  <a class="logo" href="<?= $me ? 'dashboard.php':'index.html' ?>">Nubenta</a>

  <div class="topnav-links" id="navLinks">
    <?php if ($me): ?>
      <a href="dashboard.php">Home</a>
      <a href="view_profile.php?id=<?= $me['id'] ?>">Profile</a>
      <a href="messages.php">Messages</a>
      <a href="connections.php">Connections</a>
      <a href="search.php">Search</a>
      <a href="help.php">Help</a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    <?php endif; ?>
  </div>
</nav>

<script>
  const navToggle = document.getElementById('navToggle');
  const navLinks  = document.getElementById('navLinks');
  navToggle.addEventListener('click', e=>{
    e.stopPropagation();
    navLinks.classList.toggle('show');
  });
  document.addEventListener('click', e=>{
    if(!navLinks.contains(e.target) && !navToggle.contains(e.target)){
      navLinks.classList.remove('show');
    }
  });
</script>
