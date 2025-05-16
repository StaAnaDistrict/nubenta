<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $content = trim($_POST['content'] ?? '');
  $visibility = $_POST['visibility'] ?? 'public';
  $mediaPath = null;

  // Validate content
  if (empty($content)) {
    $errors[] = "Post content cannot be empty.";
  }

  // Handle media upload if exists
  if (!empty($_FILES['media']['name'])) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $fileName = basename($_FILES["media"]["name"]);
    $targetFile = $targetDir . time() . "_" . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4'];

    if (in_array($fileType, $allowedTypes)) {
      if (move_uploaded_file($_FILES["media"]["tmp_name"], $targetFile)) {
        $mediaPath = $targetFile;
      } else {
        $errors[] = "Failed to upload media.";
      }
    } else {
      $errors[] = "Invalid media type. Allowed: jpg, png, gif, mp4.";
    }
  }

  // Insert post into database
  if (empty($errors)) {
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, media, visibility) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $content, $mediaPath, $visibility]);
    header("Location: dashboard.php"); // Redirect back to dashboard or newsfeed
    exit();
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create Post</title>
</head>
<body>
  <h2>Create a Post</h2>

  <?php if ($errors): ?>
    <ul style="color:red;">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <textarea name="content" rows="4" cols="50" placeholder="What's on your mind?" required></textarea><br><br>

    <label>Attach Media (optional):</label>
    <input type="file" name="media" accept="image/*,video/mp4"><br><br>

    <label>Visibility:</label>
    <select name="visibility">
      <option value="public">Public</option>
      <option value="friends">Friends Only</option>
    </select><br><br>

    <button type="submit">Post</button>
  </form>

  <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>
