<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $content = trim($_POST['content'] ?? '');
  $visibility = $_POST['visibility'] ?? 'public';
  $errors = [];

  // Validate content
  if (empty($content)) {
    $errors[] = "Post content cannot be empty";
  }

  // Handle media files
  $mediaPaths = [];

  if (!empty($_FILES['media']['name'][0])) {
    // Multiple files were uploaded
    $fileCount = count($_FILES['media']['name']);

    for ($i = 0; $i < $fileCount; $i++) {
      if (empty($_FILES['media']['name'][$i])) continue; // Skip empty entries

      $targetDir = "uploads/";
      if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

      $fileName = basename($_FILES["media"]["name"][$i]);
      $targetFile = $targetDir . time() . "_" . $fileName;
      $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
      $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4'];

      if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES["media"]["tmp_name"][$i], $targetFile)) {
          $mediaPaths[] = $targetFile;
        } else {
          $errors[] = "Failed to upload media file: " . $fileName;
        }
      } else {
        $errors[] = "Invalid media type for file: " . $fileName . ". Allowed: jpg, jpeg, png, gif, mp4.";
      }
    }
  }

  // Insert post into database
  if (empty($errors)) {
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, media, visibility, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user['id'], $content, json_encode($mediaPaths), $visibility]);
    $postId = $pdo->lastInsertId();

    // ENHANCED: Track media in the user_media system for view_album.php integration
    if (!empty($mediaPaths)) {
      try {
        require_once 'includes/MediaUploader.php';
        $mediaUploader = new MediaUploader($pdo);

        // Make sure mediaPaths is an array
        if (!is_array($mediaPaths)) {
          error_log("Converting mediaPaths to array: " . json_encode($mediaPaths));
          $mediaPaths = [$mediaPaths];
        }

        // Log for debugging
        error_log("Tracking post media for user {$user['id']}, post {$postId}: " . json_encode($mediaPaths));

        // Track media in user_media system
        $trackResult = $mediaUploader->trackPostMedia($user['id'], $mediaPaths, $postId);

        if ($trackResult) {
          error_log("Successfully tracked media in user_media system");
        } else {
          error_log("Failed to track media in user_media system");
        }

      } catch (Exception $e) {
        error_log("Error tracking post media: " . $e->getMessage());
        // Don't fail the post creation if media tracking fails
      }
    }

    header("Location: dashboard.php"); // Redirect back to dashboard or newsfeed
    exit();
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Create Post</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .media-preview-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
    }
    .media-preview-item {
      position: relative;
      width: 100px;
      height: 100px;
    }
    .media-preview-item img, .media-preview-item video {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 4px;
    }
    .remove-media-btn {
      position: absolute;
      top: 0;
      right: 0;
      background: rgba(255, 0, 0, 0.7);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      line-height: 1;
      cursor: pointer;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <div class="card shadow">
          <div class="card-header bg-primary text-white">
            <h2 class="h4 mb-0">Create a Post</h2>
          </div>
          <div class="card-body">
            <?php if ($errors): ?>
              <div class="alert alert-danger">
                <ul class="mb-0">
                  <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
              <div class="mb-3">
                <textarea name="content" class="form-control" rows="4" placeholder="What's on your mind?" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Attach Media (optional):</label>
                <input type="file" name="media[]" id="media-input" class="form-control" accept="image/*,video/mp4" multiple>
                <div id="media-preview-container" class="media-preview-container"></div>
              </div>

              <div class="mb-3">
                <label class="form-label">Visibility:</label>
                <select name="visibility" class="form-select">
                  <option value="public">Public</option>
                  <option value="friends">Friends Only</option>
                </select>
              </div>

              <div class="d-flex justify-content-between">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <button type="submit" class="btn btn-primary">Post</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Preview selected media files
    document.getElementById('media-input').addEventListener('change', function(e) {
      const previewContainer = document.getElementById('media-preview-container');
      previewContainer.innerHTML = '';

      const files = Array.from(e.target.files);
      files.forEach(file => {
        const preview = document.createElement('div');
        preview.className = 'media-preview-item';

        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');

          const reader = new FileReader();
          reader.onload = function(e) {
            img.src = e.target.result;
          };
          reader.readAsDataURL(file);

          preview.appendChild(img);
        } else if (file.type.startsWith('video/')) {
          const video = document.createElement('video');
          video.controls = true;

          const reader = new FileReader();
          reader.onload = function(e) {
            video.src = e.target.result;
          };
          reader.readAsDataURL(file);

          preview.appendChild(video);
        }

        // Add remove button (note: this is just visual, doesn't actually remove from input)
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-media-btn';
        removeBtn.innerHTML = '×';
        removeBtn.addEventListener('click', function() {
          preview.remove();
        });

        preview.appendChild(removeBtn);
        previewContainer.appendChild(preview);
      });
    });
  </script>
</body>
</html>
