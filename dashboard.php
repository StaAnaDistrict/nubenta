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
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
  <p>This is your user dashboard.</p>

  <nav class="navbar">
    <ul>
      <li><a href="edit_profile.php">Edit Profile</a></li>
      <li><a href="post.php">Create Post</a></li>
      <li><a href="newsfeed.php">Newsfeed</a></li>
      <?php if ($user['role'] === 'admin'): ?>
        <li><a href="admin_users.php">Manage Users</a></li>
      <?php endif; ?>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
<main>

    <!-- 📝 Post Box -->
    <form action="create_post.php" method="POST" enctype="multipart/form-data" class="post-box">
      <textarea name="content" placeholder="What's on your mind?" required></textarea>
      <input type="file" name="media">
      <select name="visibility">
        <option value="public">Public</option>
        <option value="friends">Friends Only</option>
      </select>
      <button type="submit">Post</button>
    </form>

    <!-- 📰 Newsfeed -->
    <section class="newsfeed">
      <?php
      // Example structure. Replace with real DB content.
      $posts = []; // ← Load this from the database
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
</body>
</html>