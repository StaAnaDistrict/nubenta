<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}
$user = $_SESSION['user'];

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard – Nubenta</title>
  <link rel="stylesheet" href="assets/css/dashboard_style.css">
</head>
<body>
<div class="hamburger">☰</div>

<div class="dashboard-grid">
<?php                         // ---------- ONLY ONE INCLUDE ----------
$currentUser = $user;         // pass user to navigation.php
include 'assets/navigation.php';
?>
  

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



</body>
</html>
