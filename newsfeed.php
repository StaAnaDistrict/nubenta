<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];

// Fetch posts: Show public posts and own posts
$stmt = $pdo->prepare("
  SELECT posts.*, users.name 
  FROM posts 
  JOIN users ON posts.user_id = users.id 
  WHERE visibility = 'public' OR posts.user_id = ? 
  ORDER BY posts.created_at DESC
");
$stmt->execute([$user['id']]);
$posts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Newsfeed</title>
  <style>
    .post {
      background: #fff;
      padding: 15px;
      margin: 15px auto;
      width: 70%;
      border-radius: 8px;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
      text-align: left;
    }
    .media {
      margin-top: 10px;
    }
    .media img, .media video {
      max-width: 100%;
      height: auto;
    }
    .author {
      font-weight: bold;
      color: #333;
    }
    .timestamp {
      color: gray;
      font-size: 0.9em;
    }
  </style>
</head>
<body>

  <h2>Newsfeed</h2>
  <p><a href="create_post.php">Create a Post</a> | <a href="dashboard.php">Back to Dashboard</a></p>

  <?php if (count($posts) > 0): ?>
    <?php foreach ($posts as $post): ?>
      <div class="post">
        <div class="author"><?= htmlspecialchars($post['name']) ?></div>
        <div class="timestamp"><?= htmlspecialchars($post['created_at']) ?></div>
        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

        <?php if ($post['media']): ?>
          <div class="media">
            <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $post['media'])): ?>
              <img src="<?= htmlspecialchars($post['media']) ?>" alt="media">
            <?php elseif (preg_match('/\.mp4$/i', $post['media'])): ?>
              <video controls>
                <source src="<?= htmlspecialchars($post['media']) ?>" type="video/mp4">
                Your browser does not support the video tag.
              </video>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p>No posts to show yet.</p>
  <?php endif; ?>

</body>
</html>
